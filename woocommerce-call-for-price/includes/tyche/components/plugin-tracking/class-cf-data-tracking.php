<?php
/**
 * Call for Price for WooCommerce – Plugin-Specific Tracking Data
 *
 * Collects and sends plugin settings data via the Tyche tracking system.
 * Reads the license key/status from the new unified cfp_pro_settings store
 * with a fallback to the legacy edd_license_key_call_for_price options.
 *
 * Changes from the legacy version (pre-4.0):
 *  - Removed the woocommerce_reset_settings_alg_call_for_price hook (legacy
 *    WooCommerce Settings API — replaced by the React settings UI).
 *  - ts_admin_notices_scripts now uses CFP_LITE_VERSION instead of calling
 *    the deprecated Alg_Woocommerce_Call_For_Price() singleton function.
 *  - cfp_get_license_data() reads from cfp_pro_settings with legacy fallback.
 *
 * @version 4.0.0
 * @since   3.2.9
 * @author  Tyche Softwares
 * @package WooCommerce-Call-For-Price-Lite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Tyche_CFP_Data_Tracking' ) ) :

	/**
	 * Call for Price – Plugin-Specific Tracking Data.
	 */
	class Tyche_CFP_Data_Tracking {

		/**
		 * Cached consolidated settings from cfp_pro_settings.
		 *
		 * @var array
		 */
		private static $consolidated_settings_cache = null;

		public function __construct() {
			add_filter( 'cfp_ts_tracker_data',           array( __CLASS__, 'cfp_pro_ts_add_plugin_tracking_data' ), 10, 1 );
			add_action( 'admin_footer',                   array( __CLASS__, 'ts_admin_notices_scripts' ) );
			add_action( 'cfp_init_tracker_completed',    array( __CLASS__, 'init_tracker_completed' ),              10, 2 );
			add_filter( 'cfp_ts_tracker_display_notice', array( __CLASS__, 'cfp_pro_ts_tracker_display_notice' ),   10, 1 );
		}

		// ── Tracker hooks ─────────────────────────────────────────────────────

		public static function cfp_pro_ts_add_plugin_tracking_data( $data ) {
			$plugin_short_name = 'cfp';
			if ( ! isset( $_GET[ $plugin_short_name . '_tracker_nonce' ] ) ) { // phpcs:ignore
				return $data;
			}

			$opt = isset( $_GET[ $plugin_short_name . '_tracker_optin' ] )  // phpcs:ignore
				? $plugin_short_name . '_tracker_optin'
				: ( isset( $_GET[ $plugin_short_name . '_tracker_optout' ] ) // phpcs:ignore
					? $plugin_short_name . '_tracker_optout'
					: '' );

			if ( '' === $opt ||
				! wp_verify_nonce(
					sanitize_text_field( wp_unslash( $_GET[ $plugin_short_name . '_tracker_nonce' ] ) ), // phpcs:ignore
					$opt
				)
			) {
				return $data;
			}

			$data = self::cfp_pro_plugin_tracking_data( $data );
			return $data;
		}

		/** Enqueue the dismiss-tracking-notice script. */
		public static function ts_admin_notices_scripts(): void {
			$plugin_url = plugins_url() . '/woocommerce-call-for-price';
			$nonce      = wp_create_nonce( 'tracking_notice' );
			wp_enqueue_script(
				'cfp_ts_dismiss_notice',
				$plugin_url . '/includes/tyche/assets/js/tyche-dismiss-tracking-notice.js',
				array(),
				CFP_LITE_VERSION,
				false
			);
			wp_localize_script(
				'cfp_ts_dismiss_notice',
				'cfp_ts_dismiss_notice',
				array(
					'ts_prefix_of_plugin' => 'cfp',
					'ts_admin_url'        => admin_url( 'admin-ajax.php' ),
					'tracking_notice'     => $nonce,
				)
			);
		}

		public static function init_tracker_completed(): void {
			header( 'Location: ' . admin_url( 'admin.php?page=wc-settings&tab=call-for-price-for-woocommerce' ) );
			exit;
		}

		public static function cfp_pro_ts_tracker_display_notice( $is_flag ) {
			global $current_section;
			if ( isset( $_GET['page'] ) && 'wc-settings' === $_GET['page'] ) { // phpcs:ignore
				$is_flag = false;
				if ( isset( $_GET['tab'] ) && 'call-for-price-for-woocommerce' === $_GET['tab'] && empty( $current_section ) ) { // phpcs:ignore
					$is_flag = true;
				}
			}
			return $is_flag;
		}

		// ── Tracking data collectors ──────────────────────────────────────────

		/**
		 * Get the consolidated settings from the new option, with caching.
		 *
		 * @return array
		 */
		private static function get_cfp_pro_settings(): array {
			if ( null === self::$consolidated_settings_cache ) {
				$settings = get_option( 'cfp_pro_settings', array() );
				if ( ! is_array( $settings ) ) {
					$settings = array();
				}
				self::$consolidated_settings_cache = $settings;
			}
			return self::$consolidated_settings_cache;
		}

		public static function cfp_pro_plugin_tracking_data( $data ) {
			$data['plugin_data'] = array(
				'ts_meta_data_table_name'   => 'ts_tracking_cfp_meta_data',
				'ts_plugin_name'            => 'Call for Price for WooCommerce',
				'general_settings'          => self::cfp_get_general_settings(),
				'simple_product_settings'   => self::cfp_get_simple_product_settings(),
				'variable_product_settings' => self::cfp_get_variable_product_settings(),
				'grouped_product_settings'  => self::cfp_get_grouped_product_settings(),
				'external_product_settings' => self::cfp_get_external_product_settings(),
				'license_data'              => self::cfp_get_license_data(),
				'products_settings_count'   => self::cfp_get_product_settings_count(),
			);
			return $data;
		}

		public static function cfp_get_general_settings(): array {
			$hide = get_option( 'alg_wc_call_for_price_hide_main_variable_price', 'no' );
			return array(
				'cfp_pro_settings'         => wp_json_encode( self::get_cfp_pro_settings() ),
				'enabled'                  => get_option( 'alg_wc_call_for_price_enabled' ),
				'per_product_enabled'      => get_option( 'alg_wc_call_for_price_per_product_enabled' ),
				'cfp_for_zero_price'       => get_option( 'alg_call_for_price_enable_cfp_for_zero_price' ),
				'stock_for_empty_price'    => get_option( 'alg_call_for_price_enable_stock_for_empty_price' ),
				'change_button_text'       => get_option( 'alg_call_for_price_change_button_text' ),
				'button_text'              => get_option( 'alg_call_for_price_button_text' ),
				'hide_button'              => get_option( 'alg_call_for_price_hide_button' ),
				'hide_variations_atc'      => get_option( 'alg_wc_call_for_price_hide_variations_add_to_cart_button' ),
				'force_all'                => get_option( 'alg_call_for_price_make_all_empty' ),
				'force_out_of_stock'       => get_option( 'alg_call_for_price_make_out_of_stock_empty_price' ),
				'force_per_taxonomy'       => get_option( 'alg_call_for_price_make_empty_price_per_taxonomy' ),
				'force_taxonomy_cats'      => get_option( 'alg_call_for_price_make_empty_price_product_cat' ),
				'force_taxonomy_tags'      => get_option( 'alg_call_for_price_make_empty_price_product_tag' ),
				'force_by_price'           => get_option( 'alg_call_for_price_make_empty_price_by_product_price' ),
				'force_min_price'          => get_option( 'alg_call_for_price_make_empty_price_min_price' ),
				'force_max_price'          => get_option( 'alg_call_for_price_make_empty_price_max_price' ),
				'hide_sale_sign'           => get_option( 'alg_wc_call_for_price_hide_sale_sign' ),
				'hide_main_variable_price' => $hide,
				'force_variation_price'    => get_option( 'alg_wc_call_for_price_force_variation_price' ),
				'cfp_text_for_all'         => get_option( 'alg_call_for_price_enable_cfp_text_for_all_products' ),
				'button_url'               => get_option( 'alg_call_for_price_button_url' ),
				'user_roles'               => get_option( 'alg_call_for_price_make_empty_price_per_user_roles' ),
			);
		}

		public static function cfp_get_simple_product_settings(): array {
			return array(
				'enabled'         => get_option( 'alg_wc_call_for_price_simple_enabled' ),
				'single_enabled'  => get_option( 'alg_wc_call_for_price_simple_single_enabled' ),
				'single_text'     => get_option( 'alg_wc_call_for_price_text_simple_single' ),
				'related_enabled' => get_option( 'alg_wc_call_for_price_simple_related_enabled' ),
				'related_text'    => get_option( 'alg_wc_call_for_price_text_simple_related' ),
				'home_enabled'    => get_option( 'alg_wc_call_for_price_simple_home_enabled' ),
				'home_text'       => get_option( 'alg_wc_call_for_price_text_simple_home' ),
				'page_enabled'    => get_option( 'alg_wc_call_for_price_simple_page_enabled' ),
				'page_text'       => get_option( 'alg_wc_call_for_price_text_simple_page' ),
				'archive_enabled' => get_option( 'alg_wc_call_for_price_simple_archive_enabled' ),
				'archive_text'    => get_option( 'alg_wc_call_for_price_text_simple_archive' ),
			);
		}

		public static function cfp_get_variable_product_settings(): array {
			return array(
				'enabled'           => get_option( 'alg_wc_call_for_price_variable_enabled' ),
				'single_enabled'    => get_option( 'alg_wc_call_for_price_variable_single_enabled' ),
				'single_text'       => get_option( 'alg_wc_call_for_price_text_variable_single' ),
				'related_enabled'   => get_option( 'alg_wc_call_for_price_variable_related_enabled' ),
				'related_text'      => get_option( 'alg_wc_call_for_price_text_variable_related' ),
				'home_enabled'      => get_option( 'alg_wc_call_for_price_variable_home_enabled' ),
				'home_text'         => get_option( 'alg_wc_call_for_price_text_variable_home' ),
				'page_enabled'      => get_option( 'alg_wc_call_for_price_variable_page_enabled' ),
				'page_text'         => get_option( 'alg_wc_call_for_price_text_variable_page' ),
				'archive_enabled'   => get_option( 'alg_wc_call_for_price_variable_archive_enabled' ),
				'archive_text'      => get_option( 'alg_wc_call_for_price_text_variable_archive' ),
				'variation_enabled' => get_option( 'alg_wc_call_for_price_variable_variation_enabled' ),
				'variation_text'    => get_option( 'alg_wc_call_for_price_text_variable_variation' ),
			);
		}

		public static function cfp_get_grouped_product_settings(): array {
			return array(
				'enabled'         => get_option( 'alg_wc_call_for_price_grouped_enabled' ),
				'single_enabled'  => get_option( 'alg_wc_call_for_price_grouped_single_enabled' ),
				'single_text'     => get_option( 'alg_wc_call_for_price_text_grouped_single' ),
				'related_enabled' => get_option( 'alg_wc_call_for_price_grouped_related_enabled' ),
				'related_text'    => get_option( 'alg_wc_call_for_price_text_grouped_related' ),
				'home_enabled'    => get_option( 'alg_wc_call_for_price_grouped_home_enabled' ),
				'home_text'       => get_option( 'alg_wc_call_for_price_text_grouped_home' ),
				'page_enabled'    => get_option( 'alg_wc_call_for_price_grouped_page_enabled' ),
				'page_text'       => get_option( 'alg_wc_call_for_price_text_grouped_page' ),
				'archive_enabled' => get_option( 'alg_wc_call_for_price_grouped_archive_enabled' ),
				'archive_text'    => get_option( 'alg_wc_call_for_price_text_grouped_archive' ),
			);
		}

		public static function cfp_get_external_product_settings(): array {
			return array(
				'enabled'         => get_option( 'alg_wc_call_for_price_external_enabled' ),
				'single_enabled'  => get_option( 'alg_wc_call_for_price_external_single_enabled' ),
				'single_text'     => get_option( 'alg_wc_call_for_price_text_external_single' ),
				'related_enabled' => get_option( 'alg_wc_call_for_price_external_related_enabled' ),
				'related_text'    => get_option( 'alg_wc_call_for_price_text_external_related' ),
				'home_enabled'    => get_option( 'alg_wc_call_for_price_external_home_enabled' ),
				'home_text'       => get_option( 'alg_wc_call_for_price_text_external_home' ),
				'page_enabled'    => get_option( 'alg_wc_call_for_price_external_page_enabled' ),
				'page_text'       => get_option( 'alg_wc_call_for_price_text_external_page' ),
				'archive_enabled' => get_option( 'alg_wc_call_for_price_external_archive_enabled' ),
				'archive_text'    => get_option( 'alg_wc_call_for_price_text_external_archive' ),
			);
		}

		/**
		 * Return license key and status.
		 * Reads from the unified cfp_pro_settings first; falls back to legacy options.
		 */
		public static function cfp_get_license_data(): array {
			$settings = get_option( 'cfp_pro_settings', array() );

			$key    = $settings['license']['key']    ?? get_option( 'edd_license_key_call_for_price', '' );
			$status = $settings['license']['status'] ?? get_option( 'edd_license_key_call_for_price_status', '' );

			return array(
				'license_key'    => $key,
				'license_status' => $status,
			);
		}

		/**
		 * Count products that have per-product CFP enabled.
		 * Checks both the new consolidated meta key and the legacy per-key.
		 */
		public static function cfp_get_product_settings_count(): ?string {
			global $wpdb;

			// Legacy meta key count.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$legacy = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s",
				'_alg_wc_call_for_price_enabled',
				'yes'
			) );

			// New consolidated meta key count (enabled = 'yes' inside serialised array).
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$new = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s",
				'_cfp_pro_product_settings',
				'%"enabled":"yes"%'
			) );

			return (string) max( $legacy, $new );
		}
	}

endif;

new Tyche_CFP_Data_Tracking();
