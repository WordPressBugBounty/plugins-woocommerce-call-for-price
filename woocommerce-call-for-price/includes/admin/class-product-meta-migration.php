<?php
/**
 * Class Product_Meta_Migration
 *
 * Migrates per-product Call for Price data from individual postmeta rows
 * (old format) into a single consolidated postmeta key _cfp_pro_product_settings
 * (new format).
 *
 * Uses ActionScheduler (bundled with WooCommerce) to process products in
 * batches of 20 via scheduled async actions, avoiding PHP timeouts and memory
 * exhaustion on large catalogues. Falls back to inline processing when
 * ActionScheduler is unavailable (rare, early-boot edge-case).
 *
 * Safety guarantees:
 *   • Old meta rows are NEVER deleted — they remain as a fallback.
 *   • Each product receives a _cfp_pro_product_settings_migrated flag so the
 *     migration is idempotent and safe to resume after interruption.
 *   • Errors are logged to the WooCommerce logger (channel: cfp-migration).
 *   • Stores with zero legacy per-product data are silently marked complete.
 *
 * @package WooCommerce-Call-For-Price-Lite
 */

namespace TycheSoftwares\CallForPrice\Lite;

use Throwable;
use WC_Log_Levels;

defined( 'ABSPATH' ) || exit;

/**
 * Class Product_Meta_Migration
 */
class Product_Meta_Migration {

	/**
	 * New consolidated postmeta key written to every migrated product.
	 *
	 * @var string
	 */
	const NEW_META_KEY = '_cfp_pro_product_settings';

	/**
	 * Per-product flag set after a successful migration. Used to skip re-processing.
	 *
	 * @var string
	 */
	const MIGRATED_FLAG = '_cfp_pro_product_settings_migrated';

	/**
	 * Migration version stamped in the per-product flag.
	 *
	 * @var string
	 */
	const MIGRATION_VERSION = '1.0.0';

	/**
	 * wp_options key that stores the completed migration version (global).
	 *
	 * @var string
	 */
	const VERSION_OPTION = 'cfp_pro_product_meta_migration_version';

	/**
	 * wp_options key holding the live progress object { total, started }.
	 *
	 * @var string
	 */
	const PROGRESS_OPTION = 'cfp_pro_product_meta_migration_progress';

	/**
	 * Separate atomic counter for successfully migrated products.
	 * Stored as a plain integer so it can be incremented via raw SQL UPDATE
	 * (option_value = option_value + 1), which is race-condition-safe when
	 * ActionScheduler runs many single-product actions in parallel.
	 *
	 * @var string
	 */
	const DONE_COUNTER_OPTION = 'cfp_pro_product_meta_migration_done';

	/**
	 * Separate atomic counter for products that failed migration.
	 *
	 * @var string
	 */
	const FAILED_COUNTER_OPTION = 'cfp_pro_product_meta_migration_failed';

	/**
	 * wp_options key holding the overall status string.
	 *
	 * @var string
	 */
	const STATUS_OPTION = 'cfp_pro_product_meta_migration_status';

	/**
	 * ActionScheduler hook: processes one batch of BATCH_SIZE products. Receives { offset }.
	 *
	 * @var string
	 */
	const AS_HOOK_BATCH = 'cfp_pro_migrate_product_batch';

	/**
	 * ActionScheduler hook: migrates a single product. Receives { product_id }.
	 *
	 * @var string
	 */
	const AS_HOOK_SINGLE = 'cfp_pro_migrate_single_product';

	/**
	 * Number of products processed per batch action.
	 *
	 * @var int
	 */
	const BATCH_SIZE = 20;

	/**
	 * WooCommerce logger source channel.
	 *
	 * @var string
	 */
	const LOG_CHANNEL = 'cfp-migration';

	/**
	 * wp_options key set when the admin dismisses the migration notice.
	 *
	 * @var string
	 */
	const DISMISSED_OPTION = 'cfp_pro_product_migration_dismissed';

	/**
	 * Register ActionScheduler hooks.
	 * REST routes are registered by Api_Migration (class-api-migration.php).
	 * Call once from the main plugin bootstrap (e.g. on plugins_loaded).
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( self::AS_HOOK_BATCH, array( __CLASS__, 'process_batch' ) );
		add_action( self::AS_HOOK_SINGLE, array( __CLASS__, 'migrate_single_product' ) );
	}

	/**
	 * Kick off the per-product migration if not already running or complete.
	 * Safe to call multiple times — idempotent.
	 *
	 * @return bool True when migration was freshly started; false when already done/running.
	 */
	public static function start(): bool {
		$status = get_option( self::STATUS_OPTION, 'none' );

		if ( 'complete' === $status && get_option( self::VERSION_OPTION ) === self::MIGRATION_VERSION ) {
			return false;
		}

		if ( 'running' === $status ) {
			if ( self::has_pending_actions() ) {
				return false;
			}

			// No pending AS actions while status is 'running'. This happens during the
			// brief window when an action is executing (in-progress) rather than pending,
			// OR when migration genuinely stalled. Either way, do NOT reset the progress
			// counters — just recount remaining products and reschedule if needed.
			$remaining = self::count_products_needing_migration();
			if ( 0 === $remaining ) {
				self::finish();
			} else {
				self::log( 'No pending actions found while running — rescheduling batch.' );
				self::schedule_next_batch( 0 );
			}

			return false;
		}

		$total = self::count_products_needing_migration();

		if ( 0 === $total ) {
			update_option( self::VERSION_OPTION, self::MIGRATION_VERSION );
			update_option( self::STATUS_OPTION, 'complete' );
			update_option(
				self::PROGRESS_OPTION,
				array(
					'total'  => 0,
					'done'   => 0,
					'failed' => 0,
				)
			);
			return false;
		}

		update_option( self::STATUS_OPTION, 'running' );
		update_option(
			self::PROGRESS_OPTION,
			array(
				'total'   => $total,
				'started' => time(),
			)
		);
		update_option( self::DONE_COUNTER_OPTION,   0, false );
		update_option( self::FAILED_COUNTER_OPTION, 0, false );

		self::log( "Migration started. Products to migrate: {$total}." );
		self::schedule_next_batch( 0 );

		return true;
	}

	/**
	 * Return the current migration status for the REST status endpoint.
	 *
	 * @return array{
	 *     status: string,
	 *     total: int,
	 *     done: int,
	 *     failed: int,
	 *     pending: int,
	 *     percent: int,
	 *     dismissed: bool
	 * }
	 */
	public static function get_status(): array {
		$status   = get_option( self::STATUS_OPTION, 'none' );
		$progress = get_option( self::PROGRESS_OPTION, array() );

		$total   = (int) ( $progress['total'] ?? 0 );
		$done    = (int) get_option( self::DONE_COUNTER_OPTION,   $progress['done']   ?? 0 );
		$failed  = (int) get_option( self::FAILED_COUNTER_OPTION, $progress['failed'] ?? 0 );
		$pending = max( 0, $total - $done - $failed );
		$percent = $total > 0 ? min( 100, (int) round( ( $done / $total ) * 100 ) ) : 0;

		return array(
			'status'    => $status,
			'total'     => $total,
			'done'      => $done,
			'failed'    => $failed,
			'pending'   => $pending,
			'percent'   => $percent,
			'dismissed' => (bool) get_option( self::DISMISSED_OPTION, false ),
		);
	}

	/**
	 * Process one batch: fetch BATCH_SIZE un-migrated products, schedule an
	 * individual AS action for each, then schedule the next batch if more remain.
	 *
	 * @param int $offset Batch offset (informational, not used for pagination).
	 * @return void
	 */
	public static function process_batch( int $offset = 0 ): void {
		global $wpdb;

		if ( 'running' !== get_option( self::STATUS_OPTION ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm
					ON  pm.post_id  = p.ID
					AND pm.meta_key LIKE %s
				WHERE p.post_type   IN ('product', 'product_variation')
				  AND p.post_status != 'trash'
				  AND p.ID NOT IN (
					  SELECT post_id
					  FROM {$wpdb->postmeta}
					  WHERE meta_key = %s
				  )
				ORDER BY p.ID ASC
				LIMIT %d",
				'\\_alg\\_wc\\_call\\_for\\_price\\_%',
				self::MIGRATED_FLAG,
				self::BATCH_SIZE
			)
		);

		if ( empty( $ids ) ) {
			self::finish();
			return;
		}

		foreach ( $ids as $product_id ) {
			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action(
					time(),
					self::AS_HOOK_SINGLE,
					array( 'product_id' => (int) $product_id ),
					'cfp-migration'
				);
			} else {
				self::migrate_single_product( (int) $product_id );
			}
		}

		self::schedule_next_batch( $offset + self::BATCH_SIZE, 5 );
	}

	/**
	 * Migrate a single product's postmeta from old format to the new structure.
	 * This is the ActionScheduler callback for AS_HOOK_SINGLE.
	 *
	 * @param int $product_id WP post ID of the product or product_variation.
	 * @return void
	 */
	public static function migrate_single_product( int $product_id ): void {
		if ( ! $product_id ) {
			return;
		}

		if ( get_post_meta( $product_id, self::MIGRATED_FLAG, true ) ) {
			self::increment_progress( 'done' );
			return;
		}

		try {
			$product_settings = self::read_old_meta( $product_id );
			update_post_meta( $product_id, self::NEW_META_KEY, $product_settings );
			update_post_meta( $product_id, self::MIGRATED_FLAG, self::MIGRATION_VERSION );

			self::increment_progress( 'done' );
			self::log( "Product {$product_id} migrated successfully." );
		} catch ( Throwable $e ) {
			self::increment_progress( 'failed' );
			self::log(
				"Product {$product_id} migration failed: " . $e->getMessage(),
				WC_Log_Levels::ERROR
			);
		}
	}

	/**
	 * Read all _alg_wc_call_for_price_* postmeta for a product in a single
	 * query and return the consolidated array in the new format.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, string> Consolidated product settings array.
	 */
	private static function read_old_meta( int $product_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, meta_value
				FROM {$wpdb->postmeta}
				WHERE post_id  = %d
				  AND meta_key LIKE %s",
				$product_id,
				'\\_alg\\_wc\\_call\\_for\\_price\\_%'
			),
			ARRAY_A
		);

		$meta = array();
		foreach ( (array) $rows as $row ) {
			$meta[ $row['meta_key'] ] = $row['meta_value'];
		}

		return array(
			'enabled'           => (string) ( $meta['_alg_wc_call_for_price_enabled']           ?? 'no'   ),
			'call_type'         => (string) ( $meta['_alg_wc_call_for_price_call_type']          ?? 'text' ),
			'custom_value'      => (string) ( $meta['_alg_wc_call_for_price_custom_value']       ?? ''     ),
			'whatsapp_template' => (string) ( $meta['_alg_wc_call_for_price_whatsapp_template']  ?? ''     ),
			'email_subject'     => (string) ( $meta['_alg_wc_call_for_price_email_subject']      ?? ''     ),
			'email_content'     => (string) ( $meta['_alg_wc_call_for_price_email_content']      ?? ''     ),
			'text_all_views'    => (string) ( $meta['_alg_wc_call_for_price_text_all_views']     ?? ''     ),
			'request_forms'     => (string) ( $meta['_alg_wc_call_for_price_req_forms_enabled']  ?? 'none' ),
			'shortcode'         => (string) ( $meta['_alg_wc_call_for_price_forms_shortcode']    ?? ''     ),
		);
	}

	/**
	 * Atomically increment a progress counter ('done' or 'failed').
	 *
	 * Uses a raw SQL UPDATE (option_value = option_value + 1) so that concurrent
	 * ActionScheduler workers — which can run up to 25 in parallel — never
	 * overwrite each other's increments. A plain get_option/update_option
	 * read-modify-write is NOT safe under concurrent execution.
	 *
	 * @param string $key Counter name: 'done' or 'failed'.
	 * @return void
	 */
	private static function increment_progress( string $key ): void {
		global $wpdb;

		$option_name = 'done' === $key ? self::DONE_COUNTER_OPTION : self::FAILED_COUNTER_OPTION;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = option_value + 1 WHERE option_name = %s",
				$option_name
			)
		);

		if ( ! $rows ) {
			add_option( $option_name, 1, '', 'no' );
		}

		// Bust object-cache so the next get_option returns the fresh DB value.
		wp_cache_delete( $option_name, 'options' );
		wp_cache_delete( 'alloptions', 'options' );

		$progress = get_option( self::PROGRESS_OPTION, array() );
		$total    = (int) ( $progress['total'] ?? 0 );
		$done     = (int) get_option( self::DONE_COUNTER_OPTION,   0 );
		$failed   = (int) get_option( self::FAILED_COUNTER_OPTION, 0 );

		if ( $total > 0 && ( $done + $failed ) >= $total ) {
			self::finish();
		}
	}

	/**
	 * Mark the migration as complete (or partial on failures) and log the summary.
	 *
	 * @return void
	 */
	private static function finish(): void {
		$current = get_option( self::STATUS_OPTION, 'running' );

		if ( in_array( $current, array( 'complete', 'partial' ), true ) ) {
			return;
		}

		$progress = get_option( self::PROGRESS_OPTION, array() );
		$done     = (int) get_option( self::DONE_COUNTER_OPTION,   0 );
		$failed   = (int) get_option( self::FAILED_COUNTER_OPTION, 0 );
		$total    = (int) ( $progress['total']   ?? 0 );
		$elapsed  = time() - (int) ( $progress['started'] ?? time() );

		if ( $failed > 0 ) {
			update_option( self::STATUS_OPTION, 'partial' );
			self::log(
				"Migration completed with {$failed} error(s). "
				. 'Failed products retain their original meta and will still render correctly '
				. 'via the backward-compat fallback layer.',
				WC_Log_Levels::WARNING
			);
		} else {
			update_option( self::STATUS_OPTION, 'complete' );
			update_option( self::VERSION_OPTION, self::MIGRATION_VERSION );
			self::log( 'Migration completed successfully. All products migrated.' );
		}

		self::log( "Migration finished in {$elapsed}s — done: {$done}, failed: {$failed}, total: {$total}." );
	}

	/**
	 * Schedule the next batch action (or process it inline if AS is unavailable).
	 *
	 * @param int $offset Batch offset to pass to the batch action.
	 * @param int $delay  Delay in seconds before the action runs.
	 * @return void
	 */
	private static function schedule_next_batch( int $offset, int $delay = 0 ): void {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			self::process_batch( $offset );
			return;
		}

		if ( as_next_scheduled_action( self::AS_HOOK_BATCH, array( 'offset' => $offset ), 'cfp-migration' ) ) {
			return;
		}

		as_schedule_single_action(
			time() + $delay,
			self::AS_HOOK_BATCH,
			array( 'offset' => $offset ),
			'cfp-migration'
		);
	}

	/**
	 * Check whether any migration actions are still pending in ActionScheduler.
	 *
	 * @return bool
	 */
	private static function has_pending_actions(): bool {
		if ( ! function_exists( 'as_next_scheduled_action' ) ) {
			return false;
		}

		return (bool) as_next_scheduled_action( self::AS_HOOK_BATCH, array(), 'cfp-migration' )
			|| (bool) as_next_scheduled_action( self::AS_HOOK_SINGLE, array(), 'cfp-migration' );
	}

	/**
	 * Count products that have legacy CFP meta but no migration flag yet.
	 *
	 * @return int
	 */
	public static function count_products_needing_migration(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT( DISTINCT p.ID )
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm
					ON  pm.post_id  = p.ID
					AND pm.meta_key LIKE %s
				WHERE p.post_type   IN ('product', 'product_variation')
				  AND p.post_status != 'trash'
				  AND p.ID NOT IN (
					  SELECT post_id
					  FROM {$wpdb->postmeta}
					  WHERE meta_key = %s
				  )",
				'\\_alg\\_wc\\_call\\_for\\_price\\_%',
				self::MIGRATED_FLAG
			)
		);
	}

	/**
	 * Write a message to the WooCommerce logger under the cfp-migration channel.
	 *
	 * @param string $message Log message.
	 * @param string $level   WC_Log_Levels constant (default: 'info').
	 * @return void
	 */
	private static function log( string $message, string $level = 'info' ): void {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		wc_get_logger()->log(
			$level,
			'[CFP Product Migration] ' . $message,
			array( 'source' => self::LOG_CHANNEL )
		);
	}

	/**
	 * Return true when the React Dashboard migration notice should be visible.
	 * Called from the admin notice hook or the Dashboard REST endpoint.
	 *
	 * @return bool
	 */
	public static function should_show_notice(): bool {
		if ( get_option( self::DISMISSED_OPTION ) ) {
			return false;
		}

		$status = get_option( self::STATUS_OPTION, 'none' );
		return in_array( $status, array( 'pending', 'running', 'complete', 'partial' ), true );
	}
}
