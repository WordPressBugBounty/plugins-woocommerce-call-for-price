<?php // phpcs:ignore
/**
 * Plugin Name: Call for Price for WooCommerce
 * Plugin URI: https://www.tychesoftwares.com/store/premium-plugins/woocommerce-call-for-price-plugin/
 * Description: Plugin extends WooCommerce by outputting "Call for Price" when price field for product is left empty.
 * Version: 3.10.0
 * Author: Tyche Softwares
 * Author URI: https://www.tychesoftwares.com/
 * Text Domain: woocommerce-call-for-price
 * Domain Path: /langs
 * Copyright: � 2021 Tyche Softwares
 * Requires PHP: 7.4
 * WC tested up to: 9.8.1
 * Tested up to: 6.8.0
 * WC requires at least: 5.0.0
 * Requires Plugins: woocommerce
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package CallForPrice
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Automattic\WooCommerce\Utilities\OrderUtil;

// Check if WooCommerce is active.
$plugin_name = 'woocommerce/woocommerce.php';
if (
	! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ), true ) &&
	! ( is_multisite() && array_key_exists( $plugin_name, get_site_option( 'active_sitewide_plugins', array() ) ) )
) {
	return;
}

if ( 'woocommerce-call-for-price.php' === basename( __FILE__ ) ) {
	// Check if Pro is active, if so then return.
	$plugin_name = 'woocommerce-call-for-price-pro/woocommerce-call-for-price-pro.php';
	if (
		in_array( 'woocommerce-call-for-price-pro/woocommerce-call-for-price-pro.php', apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ), true ) ||
		( is_multisite() && array_key_exists( $plugin_name, get_site_option( 'active_sitewide_plugins', array() ) ) )
	) {
		return;
	}
}

if ( ! class_exists( 'Alg_Woocommerce_Call_For_Price' ) ) :

	/**
	 * Main Alg_Woocommerce_Call_For_Price Class
	 *
	 * @class   Alg_Woocommerce_Call_For_Price
	 * @version 3.2.2
	 */
	final class Alg_Woocommerce_Call_For_Price {

		/**
		 * Plugin version.
		 *
		 * @var   string
		 * @since 3.0.0
		 */
		public $version = '3.10.0';

		/**
		 * Setting.
		 *
		 * @var $setting
		 * @since 3.0.0
		 */
		public $settings = '';

		/**
		 * Instance Variable
		 *
		 * @var Alg_Woocommerce_Call_For_Price The single instance of the class
		 */
		protected static $instance = null;

		/**
		 * Main Alg_Woocommerce_Call_For_Price Instance
		 *
		 * Ensures only one instance of Alg_Woocommerce_Call_For_Price is loaded or can be loaded.
		 *
		 * @static
		 * @return Alg_Woocommerce_Call_For_Price - Main instance
		 */
		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Alg_Woocommerce_Call_For_Price Constructor.
		 *
		 * @access  public
		 * @version 3.0.0
		 */
		public function __construct() {

			// Set up localisation.
			add_action( 'init', array( $this, 'cfp_load_text_domain' ) );

			// Include required files.
			$this->includes();

			// Settings.
			if ( is_admin() ) {
				add_filter( 'woocommerce_get_settings_pages', array( $this, 'add_woocommerce_settings_tab' ) );
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'action_links' ) );
				add_action( 'before_woocommerce_init', array( &$this, 'cfp_lite_custom_order_tables_compatibility' ), 999 );
			}
		}

		/**
		 * Added plugin text domain.
		 */
		public function cfp_load_text_domain() {
			load_plugin_textdomain( 'woocommerce-call-for-price', false, dirname( plugin_basename( __FILE__ ) ) . '/langs/' );
		}

		/**
		 * Show action links on the plugin screen
		 *
		 * @param mixed $links Link.
		 * @return  array
		 * @version 3.1.1
		 */
		public function action_links( $links ) {
			$custom_links   = array();
			$custom_links[] = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=alg_call_for_price' ) . '">' . __( 'Settings', 'woocommerce' ) . '</a>';
			if ( 'woocommerce-call-for-price.php' === basename( __FILE__ ) ) {
				$custom_links[] = '<a target="_blank" href="https://www.tychesoftwares.com/store/premium-plugins/woocommerce-call-for-price-plugin/?utm_source=cfpupgradetopro&utm_medium=unlockall&utm_campaign=CallForPriceLite">' . __( 'Unlock All', 'woocommerce-call-for-price' ) . '</a>';
			}
			return array_merge( $custom_links, $links );
		}

		/**
		 * Include required core files used in admin and on the frontend.
		 *
		 * @version 3.2.2
		 */
		private function includes() {
			$this->settings                  = array();
			$this->settings['general']       = require_once 'includes/admin/class-wc-call-for-price-settings-general.php';
			$this->settings['product-types'] = require_once 'includes/admin/class-wc-call-for-price-settings-product-types.php';
			if ( is_admin() && get_option( 'alg_wc_call_for_price_version', '' ) !== $this->version ) {
				foreach ( $this->settings as $section ) {
					foreach ( $section->get_settings() as $value ) {
						if ( isset( $value['default'] ) && isset( $value['id'] ) ) {
							$autoload = isset( $value['autoload'] ) ? (bool) $value['autoload'] : true;
							add_option( $value['id'], $value['default'], '', ( $autoload ? 'yes' : 'no' ) );
						}
					}
				}
				$this->handle_deprecated_options();
				update_option( 'alg_wc_call_for_price_version', $this->version );
			}
			require_once 'includes/class-wc-call-for-price.php';
			$cfp_plugin_url = plugins_url() . '/woocommerce-call-for-price';

			// plugin deactivation.
			if ( is_admin() ) {
				if ( strpos( $_SERVER['REQUEST_URI'], 'plugins.php' ) !== false || strpos( $_SERVER['REQUEST_URI'], 'action=deactivate' ) !== false || ( strpos( $_SERVER['REQUEST_URI'], 'admin-ajax.php' ) !== false && isset( $_POST['action'] ) && $_POST['action'] === 'tyche_plugin_deactivation_submit_action' ) ) { //phpcs:ignore
					require_once 'includes/component/plugin-deactivation/class-tyche-plugin-deactivation.php';
					new Tyche_Plugin_Deactivation(
						array(
							'plugin_name'       => 'Call for Price for WooCommerce',
							'plugin_base'       => 'woocommerce-call-for-price/woocommerce-call-for-price.php',
							'script_file'       => $cfp_plugin_url . '/includes/js/plugin-deactivation.js',
							'plugin_short_name' => 'cfp_lite',
							'version'           => $this->version,
							'plugin_locale'     => 'woocommerce-call-for-price',
						)
					);
				}
				require_once 'includes/class-cfp-lite-data-tracking.php';
				require_once 'includes/component/plugin-tracking/class-tyche-plugin-tracking.php';
				new Tyche_Plugin_Tracking(
					array(
						'plugin_name'       => 'Call for Price for WooCommerce',
						'plugin_locale'     => 'woocommerce-call-for-price',
						'plugin_short_name' => 'cfp_lite',
						'version'           => $this->version,
						'blog_link'         => 'https://www.tychesoftwares.com/docs/woocommerce-call-for-price/call-for-price-usage-tracking/',
					)
				);
			}
		}

		/**
		 * Handle_deprecated_options.
		 *
		 * @version 3.0.2
		 * @since   3.0.0
		 */
		public function handle_deprecated_options() {
			$deprecated_settings = array(
				// v3.0.0.
				'woocommerce_call_for_price_enabled'      => 'alg_wc_call_for_price_enabled',
				'woocommerce_call_for_price_text'         => 'alg_wc_call_for_price_text_simple_single',
				'woocommerce_call_for_price_text_on_archive' => 'alg_wc_call_for_price_text_simple_archive',
				'woocommerce_call_for_price_text_on_home' => 'alg_wc_call_for_price_text_simple_home',
				'woocommerce_call_for_price_text_on_related' => 'alg_wc_call_for_price_text_simple_related',
				'woocommerce_call_for_price_hide_sale_sign' => 'alg_wc_call_for_price_hide_sale_sign',
			);
			foreach ( $deprecated_settings as $old => $new ) {
				$old_value = get_option( $old );
				if ( false !== $old_value ) {
					update_option( $new, $old_value );
					delete_option( $old );
				}
			}
		}

		/**
		 * Add Woocommerce settings tab to WooCommerce settings.
		 *
		 * @param array $settings array of plugin links.
		 * @version 3.0.0
		 */
		public function add_woocommerce_settings_tab( $settings ) {
			$settings[] = include 'includes/admin/class-wc-settings-call-for-price.php';
			return $settings;
		}

		/**
		 * Get the plugin url.
		 *
		 * @return string
		 */
		public function plugin_url() {
			return untrailingslashit( plugin_dir_url( __FILE__ ) );
		}

		/**
		 * Get the plugin path.
		 *
		 * @return string
		 */
		public function plugin_path() {
			return untrailingslashit( plugin_dir_path( __FILE__ ) );
		}
		/**
		 * Sets the compatibility with Woocommerce HPOS.
		 *
		 * @since 3.6.0
		 */
		public function cfp_lite_custom_order_tables_compatibility() {

			if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', 'woocommerce-call-for-price/woocommerce-call-for-price.php', true );
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'orders_cache', 'woocommerce-call-for-price/woocommerce-call-for-price.php', true );
			}
		}
	}

endif;

if ( ! function_exists( 'alg_woocommerce_call_for_price' ) ) {
	/**
	 * Returns the main instance of Alg_Woocommerce_Call_For_Price to prevent the need to use globals.
	 *
	 * @return  Alg_Woocommerce_Call_For_Price
	 * @version 3.0.0
	 */
	function alg_woocommerce_call_for_price() { // phpcs:ignore
		return Alg_Woocommerce_Call_For_Price::instance();
	}
}

alg_woocommerce_call_for_price();
