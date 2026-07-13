<?php
/**
 * Class Migration
 *
 * Migrates all settings from the old WooCommerce Settings API format
 * (individual wp_options rows) into the new unified cfp_pro_settings structure.
 *
 * Safety guarantees:
 *   • Idempotent — safe to call on every admin_init; exits immediately if
 *     the migration version flag is already stamped.
 *   • Non-destructive — legacy option keys are never deleted here. A separate
 *     cleanup utility (future scope) handles that after the store admin confirms
 *     the migration is stable.
 *   • Fresh-install aware — probes for legacy data before running; seeds
 *     defaults instead of attempting to migrate when none exist.
 *
 * @package WooCommerce-Call-For-Price-Lite
 */

namespace TycheSoftwares\CallForPrice\Lite;

defined( 'ABSPATH' ) || exit;

/**
 * Class Migration
 *
 * Handles one‑time migration from legacy options to the consolidated settings array.
 */
class Migration {

	/**
	 * New unified settings option key.
	 *
	 * @var string
	 */
	const NEW_OPTION_KEY = 'cfp_pro_settings';

	/**
	 * wp_options key used to store the completed migration version.
	 * Prevents re-running after the first successful migration.
	 *
	 * @var string
	 */
	const MIGRATION_FLAG = 'cfp_pro_migration_version';

	/**
	 * Current migration version. Bump this string to force a re-migration
	 * on the next admin_init (e.g. when the schema changes in a new release).
	 *
	 * @var string
	 */
	const MIGRATION_VERSION = '1.0.0';

	/**
	 * Run migration.
	 *
	 * Called from the plugin activation hook and on admin_init so that stores
	 * upgrading via FTP (without re-activating) are also migrated transparently.
	 * Safe to call multiple times — exits immediately if already complete.
	 *
	 * @return void
	 */
	public static function run(): void {
		// Already migrated — nothing to do.
		if ( get_option( self::MIGRATION_FLAG ) === self::MIGRATION_VERSION ) {
			return;
		}

		// New settings already present (e.g. partial run, then re-activation).
		// Don't overwrite — just stamp the flag and exit.
		if ( false !== get_option( self::NEW_OPTION_KEY ) ) {
			update_option( self::MIGRATION_FLAG, self::MIGRATION_VERSION );
			return;
		}

		$has_old_data = false !== get_option( 'alg_wc_call_for_price_enabled' );

		if ( ! $has_old_data ) {
			self::seed_defaults();
		} else {
			self::migrate();
		}

		update_option( self::MIGRATION_FLAG, self::MIGRATION_VERSION );
	}

	/**
	 * Read all legacy wp_options rows and write them into cfp_pro_settings.
	 *
	 * @return void
	 */
	private static function migrate(): void {
		$new_settings = array(
			'general'  => self::migrate_general(),
			'simple'   => self::migrate_product_type( 'simple' ),
			'variable' => self::migrate_product_type( 'variable' ),
			'grouped'  => self::migrate_product_type( 'grouped' ),
			'external' => self::migrate_product_type( 'external' ),
			'license'  => self::migrate_license(),
		);

		update_option( self::NEW_OPTION_KEY, $new_settings, false );

		/**
		 * Action triggered after migration is complete.
		 *
		 * @param array $new_settings The fully migrated settings array.
		 */
		do_action( 'cfp_pro_migration_complete', $new_settings );
	}

	/**
	 * Migrate General settings section.
	 *
	 * @return array<string, mixed>
	 */
	private static function migrate_general(): array {
		return array(
			'enabled'                    => self::get_yes_no_option( 'alg_wc_call_for_price_enabled', true ),
			'per_product_enabled'        => self::get_yes_no_option( 'alg_wc_call_for_price_per_product_enabled', false ),
			'hide_sale_tag'              => self::get_yes_no_option( 'alg_wc_call_for_price_hide_sale_sign', true ),
			'hide_main_variable_price'   => (string) get_option( 'alg_wc_call_for_price_hide_main_variable_price', 'no' ),
			'force_variation_price'      => self::get_yes_no_option( 'alg_wc_call_for_price_force_variation_price', false ),
			'hide_variations_atc_button' => self::get_yes_no_option( 'alg_wc_call_for_price_hide_variations_add_to_cart_button', true ),
			'show_stock_for_empty_price' => self::get_yes_no_option( 'alg_call_for_price_enable_stock_for_empty_price', false ),
			'change_button_text'         => self::get_yes_no_option( 'alg_call_for_price_change_button_text', false ),
			'button_text'                => (string) get_option( 'alg_call_for_price_button_text', 'Call for Price' ),
			'button_url'                 => (string) get_option( 'alg_call_for_price_button_url', '' ),
			'hide_button'                => self::get_yes_no_option( 'alg_call_for_price_hide_button', false ),
			'logged_in_only'             => (array) get_option( 'alg_call_for_price_make_empty_price_per_user_roles', array() ),
			'force'                      => array(
				'all_products'             => self::get_yes_no_option( 'alg_call_for_price_make_all_empty', false ),
				'out_of_stock'             => self::get_yes_no_option( 'alg_call_for_price_make_out_of_stock_empty_price', false ),
				'for_zero_price'           => self::get_yes_no_option( 'alg_call_for_price_enable_cfp_for_zero_price', false ),
				'for_zero_price_variation' => self::get_yes_no_option( 'alg_call_for_price_enable_cfp_for_zero_price_variation', false ),
				'for_all_products_text'    => self::get_yes_no_option( 'alg_call_for_price_enable_cfp_text_for_all_products', false ),
				'by_taxonomy'              => array(
					'enabled'     => self::get_yes_no_option( 'alg_call_for_price_make_empty_price_per_taxonomy', false ),
					'product_cat' => (array) get_option( 'alg_call_for_price_make_empty_price_product_cat', array() ),
					'product_tag' => (array) get_option( 'alg_call_for_price_make_empty_price_product_tag', array() ),
				),
				'by_price'                 => array(
					'enabled' => self::get_yes_no_option( 'alg_call_for_price_make_empty_price_by_product_price', false ),
					'min'     => (float) get_option( 'alg_call_for_price_make_empty_price_min_price', 0 ),
					'max'     => (float) get_option( 'alg_call_for_price_make_empty_price_max_price', 0 ),
				),
			),
			'exclude'                    => array(
				'products'   => (array) get_option( 'alg_call_for_price_exclude_product', array() ),
				'categories' => (array) get_option( 'alg_call_for_price_exclude_product_cat', array() ),
			),
		);
	}

	/**
	 * Migrate the license key/status into the unified settings store.
	 *
	 * @return array{ key: string, status: string }
	 */
	private static function migrate_license(): array {

		return array(
			'key'    => '',
			'status' => '',
		);
	}

	/**
	 * Migrate settings for a single product type.
	 *
	 * Handles simple, variable, grouped, and external uniformly.
	 * Variable gets the extra `variation` view and the `custom_variation_text` flag.
	 *
	 * @param string $type Product type slug (simple|variable|grouped|external).
	 * @return array<string, mixed>
	 */
	private static function migrate_product_type( string $type ): array {
		$default_label = '<strong>Call for Price</strong>';

		$views = array( 'single', 'related', 'home', 'page', 'archive' );
		if ( 'variable' === $type ) {
			$views[] = 'variation';
		}

		$migrated_views = array();
		foreach ( $views as $view ) {
			$migrated_views[ $view ] = array(
				'enabled' => self::get_yes_no_option( "alg_wc_call_for_price_{$type}_{$view}_enabled", true ),
				'text'    => (string) get_option( "alg_wc_call_for_price_text_{$type}_{$view}", $default_label ),
			);
		}

		$data = array(
			'enabled'           => self::get_yes_no_option( "alg_wc_call_for_price_{$type}_enabled", true ),
			'call_type'         => (string) get_option( "alg_wc_call_for_price_call_type_{$type}", 'text' ),
			'call_type_value'   => (string) get_option( "alg_wc_call_for_price_call_type_value_{$type}", '' ),
			'whatsapp_template' => (string) get_option(
				"alg_wc_call_for_price_template_{$type}",
				__( 'Hi, I\'m interested in this product: {product_name} from {store_name}. Could you please share the price and more details?', 'woocommerce-call-for-price' )
			),
			'email_subject'     => (string) get_option(
				"alg_wc_call_for_price_email_subject_{$type}",
				__( 'Price inquiry for {product_name} on {store_name}', 'woocommerce-call-for-price' )
			),
			'email_content'     => (string) get_option(
				"alg_wc_call_for_price_email_content_{$type}",
				__( "Hello,\n\nI'm interested in the following product listed on {store_name}:\n Product: {product_name}\n Link: {product_url}\n\nCould you please provide the price and any additional details?\nThank you!", 'woocommerce-call-for-price' )
			),
			'views'             => $migrated_views,
		);

		return $data;
	}

	/**
	 * Seed default settings for a fresh install.
	 *
	 * Loads the REST API settings controller to obtain the canonical defaults
	 * array — keeping defaults defined in exactly one place.
	 *
	 * @return void
	 */
	private static function seed_defaults(): void {
		$settings_file = CFP_LITE_PLUGIN_PATH . '/includes/api/class-api-settings.php';

		if ( ! file_exists( $settings_file ) ) {
			return;
		}

		if ( ! class_exists( 'TycheSoftwares\\CallForPrice\\Pro\\Api_Settings' ) ) {
			require_once $settings_file; // phpcs:ignore WPThemeReview.CoreFunctionality.FileInclude
		}

		$controller = new Api_Settings();

		add_option( self::NEW_OPTION_KEY, $controller->get_defaults(), '', false );
	}

	/**
	 * Read a single value from the consolidated settings with a fallback.
	 *
	 * @param string $section Top-level section key (general|simple|variable|grouped|external).
	 * @param string $key     Field key within the section.
	 * @param mixed  $default Fallback value returned when the key is absent.
	 * @return mixed
	 */
	public static function get( string $section, string $key, $default = null ) {
		static $settings_cache = null;

		if ( null === $settings_cache ) {
			$settings_cache = get_option( self::NEW_OPTION_KEY, array() );
		}

		if ( ! empty( $settings_cache ) && isset( $settings_cache[ $section ][ $key ] ) ) {
			return $settings_cache[ $section ][ $key ];
		}

		return $default;
	}

	/**
	 * Read a yes/no string option and return it as a boolean.
	 *
	 * @param string $option_key wp_options key.
	 * @param bool   $default    Default value (converted to 'yes'/'no' for the get_option call).
	 * @return bool
	 */
	private static function get_yes_no_option( string $option_key, bool $default = false ): bool {
		$value = get_option( $option_key, $default ? 'yes' : 'no' );
		return 'yes' === $value;
	}
}
