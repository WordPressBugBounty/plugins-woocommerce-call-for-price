<?php
/**
 * Custom Order Numbers for WooCommerce - Data Tracking Class
 *
 * @version 1.0.0
 * @since   1.3.0
 * @package Custom Order Numbers/Data Tracking
 * @author  Tyche Softwares
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Cfp_Lite_Data_Tracking' ) ) :

	/**
	 * Custom Order Number Data Tracking Core.
	 */
	class Cfp_Lite_Data_Tracking {

		/**
		 * Construct.
		 *
		 * @since 1.3.0
		 */
		public function __construct() {

			// Include JS script for the notice.
			add_filter( 'cfp_lite_ts_tracker_data', array( __CLASS__, 'cfp_lite_ts_add_plugin_tracking_data' ), 10, 1 );
			add_action( 'admin_footer', array( __CLASS__, 'ts_admin_notices_scripts' ) );
			// Send Tracker Data.
			add_action( 'cfp_lite_init_tracker_completed', array( __CLASS__, 'init_tracker_completed' ), 10, 2 );
			add_filter( 'cfp_lite_ts_tracker_display_notice', array( __CLASS__, 'cfp_lite_ts_tracker_display_notice' ), 10, 1 );

		}

		/**
		 * Send the plugin data when the user has opted in
		 *
		 * @hook ts_tracker_data
		 * @param array $data All data to send to server.
		 *
		 * @return array $plugin_data All data to send to server.
		 */
		public static function cfp_lite_ts_add_plugin_tracking_data( $data ) {
			$plugin_short_name = 'cfp_lite';
			if ( ! isset( $_GET[ $plugin_short_name . '_tracker_nonce' ] ) ) {
				return $data;
			}

			$tracker_option = isset( $_GET[ $plugin_short_name . '_tracker_optin' ] ) ? $plugin_short_name . '_tracker_optin' : ( isset( $_GET[ $plugin_short_name . '_tracker_optout' ] ) ? $plugin_short_name . '_tracker_optout' : '' ); // phpcs:ignore
			if ( '' === $tracker_option || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET[ $plugin_short_name . '_tracker_nonce' ] ) ), $tracker_option ) ) {
				return $data;
			}

			$data = self::cfp_lite_plugin_tracking_data( $data );
			return $data;
		}

		/**
		 * Admin enqueue scripts for data tracking.
		 *
		 * @since 1.3.0
		 */
		public static function ts_admin_notices_scripts() {
			$nonce            = wp_create_nonce( 'tracking_notice' );
			$plugin_url       = plugins_url() . '/woocommerce-call-for-price';
			$numbers_instance = alg_woocommerce_call_for_price();
			wp_enqueue_script(
				'cfp_ts_dismiss_notice',
				$plugin_url . '/includes/js/tyche-dismiss-tracking-notice.js',
				'',
				$numbers_instance->version,
				false
			);

			wp_localize_script(
				'cfp_ts_dismiss_notice',
				'cfp_ts_dismiss_notice',
				array(
					'ts_prefix_of_plugin' => 'cfp_lite',
					'ts_admin_url'        => admin_url( 'admin-ajax.php' ),
					'tracking_notice'     => $nonce,
				)
			);
		}

		/**
		 * Add tracker completed.
		 */
		public static function init_tracker_completed() {
			header( 'Location: ' . admin_url( 'admin.php?page=wc-settings&tab=alg_call_for_price' ) );
			exit;
		}

		/**
		 * Display admin notice on specific page.
		 *
		 * @param array $is_flag Is Flag defailt value true.
		 */
		public static function cfp_lite_ts_tracker_display_notice( $is_flag ) {
			global $current_section;
			if ( isset( $_GET['page'] ) && 'wc-settings' === $_GET['page'] ) { // phpcs:ignore
				$is_flag = false;
				if ( isset( $_GET['tab'] ) && 'alg_call_for_price' === $_GET['tab'] && empty( $current_section ) ) { // phpcs:ignore
					$is_flag = true;
				}
			}
			return $is_flag;
		}

		/**
		 * Returns plugin data for tracking.
		 *
		 * @param array $data - Generic data related to WP, WC, Theme, Server and so on.
		 * @return array $data - Plugin data included in the original data received.
		 * @since 1.3.0
		 */
		public static function cfp_lite_plugin_tracking_data( $data ) {

			$plugin_data = array(
				'ts_meta_data_table_name'   => 'ts_tracking_cfp_lite_meta_data',
				'ts_plugin_name'            => 'Call for Price for WooCommerce',
				'general_settings'          => self::cfp_get_general_settings(),
				'simple_product_settings'   => self::cfp_get_simple_product_settings(),
				'variable_product_settings' => self::cfp_get_variable_product_settings(),
				'grouped_product_settings'  => self::cfp_get_grouped_product_settings(),
				'external_product_settings' => self::cfp_get_external_product_settings(),
				'products_settings_count'   => self::cfp_get_product_settings_count(),
			);

			$data['plugin_data'] = $plugin_data;

			return $data;
		}

		/**
		 * Send the general settings for tracking.
		 *
		 * @since 3.2.9
		 */
		public static function cfp_get_general_settings() {
			$hide_main_variable_price_data = get_option( 'alg_wc_call_for_price_hide_main_variable_price' );
			$hide_main_variable_price      = '';
			if ( 'yes_with_css' === $hide_main_variable_price_data ) {
				$hide_main_variable_price = 'Hide with CSS';
			} elseif ( 'yes' === $hide_main_variable_price_data ) {
				$hide_main_variable_price = 'Hide';
			} else {
				$hide_main_variable_price = 'Do not hide';
			}
			$general_settings                    = array(
				'alg_wc_call_for_price_enabled'         => get_option( 'alg_wc_call_for_price_enabled' ),
				'alg_call_for_price_enable_cfp_for_zero_price' => get_option( 'alg_call_for_price_enable_cfp_for_zero_price' ),
				'alg_call_for_price_enable_stock_for_empty_price' => get_option( 'alg_call_for_price_enable_stock_for_empty_price' ),
				'alg_call_for_price_change_button_text' => get_option( 'alg_call_for_price_change_button_text' ),
				'alg_call_for_price_button_text'        => get_option( 'alg_call_for_price_button_text' ),
				'alg_call_for_price_hide_button'        => get_option( 'alg_call_for_price_hide_button' ),
				'alg_wc_call_for_price_hide_variations_add_to_cart_button' => get_option( 'alg_wc_call_for_price_hide_variations_add_to_cart_button' ),
				'alg_call_for_price_make_all_empty'     => get_option( 'alg_call_for_price_make_all_empty' ),
				'alg_call_for_price_make_empty_price_per_taxonomy' => get_option( 'alg_call_for_price_make_empty_price_per_taxonomy' ),
				'alg_call_for_price_make_empty_price_product_cat' => get_option( 'alg_call_for_price_make_empty_price_product_cat' ),
				'alg_call_for_price_make_empty_price_product_tag' => get_option( 'alg_call_for_price_make_empty_price_product_tag' ),
				'alg_call_for_price_make_empty_price_by_product_price' => get_option( 'alg_call_for_price_make_empty_price_by_product_price' ),
				'alg_call_for_price_make_empty_price_min_price' => get_option( 'alg_call_for_price_make_empty_price_min_price' ),
				'alg_call_for_price_make_empty_price_max_price' => get_option( 'alg_call_for_price_make_empty_price_max_price' ),
				'alg_wc_call_for_price_hide_sale_sign'  => get_option( 'alg_wc_call_for_price_hide_sale_sign' ),
				'alg_wc_call_for_price_hide_main_variable_price' => $hide_main_variable_price,
				'alg_wc_call_for_price_force_variation_price' => get_option( 'alg_wc_call_for_price_force_variation_price' ),
				'alg_call_for_price_enable_cfp_text_for_all_products' => get_option( 'alg_call_for_price_enable_cfp_text_for_all_products' ),
				'alg_call_for_price_button_url'         => get_option( 'alg_call_for_price_button_url' ),
				'alg_call_for_price_make_empty_price_per_user_roles' => get_option( 'alg_call_for_price_make_empty_price_per_user_roles' ),
			);
			$general_setting['general_settings'] = $general_settings;
			return $general_setting;
		}


		/**
		 * Returns the simple product settings.
		 *
		 * @since 3.2.9
		 */
		public static function cfp_get_simple_product_settings() {
			$simple_products_settings                            = array(
				'alg_wc_call_for_price_simple_enabled'     => get_option( 'alg_wc_call_for_price_simple_enabled' ),
				'alg_wc_call_for_price_simple_single_enabled' => get_option( 'alg_wc_call_for_price_simple_single_enabled' ),
				'alg_wc_call_for_price_text_simple_single' => get_option( 'alg_wc_call_for_price_text_simple_single' ),
				'alg_wc_call_for_price_simple_related_enabled' => get_option( 'alg_wc_call_for_price_simple_related_enabled' ),
				'alg_wc_call_for_price_simple_home_enabled' => get_option( 'alg_wc_call_for_price_simple_home_enabled' ),
				'alg_wc_call_for_price_simple_page_enabled' => get_option( 'alg_wc_call_for_price_simple_page_enabled' ),
				'alg_wc_call_for_price_simple_archive_enabled' => get_option( 'alg_wc_call_for_price_simple_archive_enabled' ),
			);
			$simple_products_setting['simple_products_settings'] = $simple_products_settings;
			return $simple_products_setting;
		}

		/**
		 * Returns the variable product settings.
		 *
		 * @since 3.2.9
		 */
		public static function cfp_get_variable_product_settings() {
			$variable_products_settings                              = array(
				'alg_wc_call_for_price_variable_enabled' => get_option( 'alg_wc_call_for_price_variable_enabled' ),
				'alg_wc_call_for_price_variable_single_enabled' => get_option( 'alg_wc_call_for_price_variable_single_enabled' ),
				'alg_wc_call_for_price_text_variable_single' => get_option( 'alg_wc_call_for_price_text_variable_single' ),
				'alg_wc_call_for_price_variable_related_enabled' => get_option( 'alg_wc_call_for_price_variable_related_enabled' ),
				'alg_wc_call_for_price_variable_home_enabled' => get_option( 'alg_wc_call_for_price_variable_home_enabled' ),
				'alg_wc_call_for_price_variable_page_enabled' => get_option( 'alg_wc_call_for_price_variable_page_enabled' ),
				'alg_wc_call_for_price_variable_archive_enabled' => get_option( 'alg_wc_call_for_price_variable_archive_enabled' ),
				'alg_wc_call_for_price_text_variable_archive' => get_option( 'alg_wc_call_for_price_text_variable_archive' ),
				'alg_wc_call_for_price_variable_variation_enabled' => get_option( 'alg_wc_call_for_price_variable_variation_enabled' ),
			);
			$variable_products_setting['variable_products_settings'] = $variable_products_settings;
			return $variable_products_setting;
		}

		/**
		 * Returns the grouped product settings.
		 *
		 * @since 3.2.9
		 */
		public static function cfp_get_grouped_product_settings() {
			$grouped_products_settings                             = array(
				'alg_wc_call_for_price_grouped_enabled' => get_option( 'alg_wc_call_for_price_grouped_enabled' ),
				'alg_wc_call_for_price_grouped_single_enabled' => get_option( 'alg_wc_call_for_price_grouped_single_enabled' ),
				'alg_wc_call_for_price_text_grouped_single' => get_option( 'alg_wc_call_for_price_text_grouped_single' ),
				'alg_wc_call_for_price_grouped_related_enabled' => get_option( 'alg_wc_call_for_price_grouped_related_enabled' ),
				'alg_wc_call_for_price_grouped_home_enabled' => get_option( 'alg_wc_call_for_price_grouped_home_enabled' ),
				'alg_wc_call_for_price_grouped_page_enabled' => get_option( 'alg_wc_call_for_price_grouped_page_enabled' ),
				'alg_wc_call_for_price_grouped_archive_enabled' => get_option( 'alg_wc_call_for_price_grouped_archive_enabled' ),
			);
			$grouped_products_setting['grouped_products_settings'] = $grouped_products_settings;
			return $grouped_products_setting;
		}

		/**
		 * Returns the external product settings.
		 *
		 * @since 3.2.9
		 */
		public static function cfp_get_external_product_settings() {
			$external_products_settings                              = array(
				'alg_wc_call_for_price_external_enabled' => get_option( 'alg_wc_call_for_price_external_enabled' ),
				'alg_wc_call_for_price_external_single_enabled' => get_option( 'alg_wc_call_for_price_external_single_enabled' ),
				'alg_wc_call_for_price_text_external_single' => get_option( 'alg_wc_call_for_price_text_external_single' ),
				'alg_wc_call_for_price_external_related_enabled' => get_option( 'alg_wc_call_for_price_external_related_enabled' ),
				'alg_wc_call_for_price_external_home_enabled' => get_option( 'alg_wc_call_for_price_external_home_enabled' ),
				'alg_wc_call_for_price_external_page_enabled' => get_option( 'alg_wc_call_for_price_external_page_enabled' ),
				'alg_wc_call_for_price_external_archive_enabled' => get_option( 'alg_wc_call_for_price_external_archive_enabled' ),
			);
			$external_products_setting['external_products_settings'] = $external_products_settings;
			return $external_products_setting;
		}

		/**
		 * Sends an array where the status is the key and the count of orders is the array value
		 *
		 * @since 3.2.9
		 */
		public static function cfp_get_product_settings_count() {
			global $wpdb;
			$cfp_enabled = 'yes';
			$count = $wpdb->get_var( $wpdb->prepare( "SELECT count(post_id) FROM `" . $wpdb->prefix . "postmeta` WHERE meta_key = '%s' AND meta_value = '%s'", '_alg_wc_call_for_price_enabled', 'yes' ) ); // phpcs:ignore

			return $count;
		}

	}

endif;

$cfp_data_tracking = new Cfp_Lite_Data_Tracking();
