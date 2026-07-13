<?php
/**
 * Plugin Name: Call for Price for WooCommerce
 * Plugin URI: https://www.tychesoftwares.com/store/premium-plugins/woocommerce-call-for-price-plugin/
 * Description: Plugin extends WooCommerce by outputting "Call for Price" when price field for product is left empty.
 * Version: 4.4.0
 * Author: Tyche Softwares
 * Author URI: https://www.tychesoftwares.com/
 * Text Domain: woocommerce-call-for-price
 * Domain Path: /languages
 * Copyright: � 2021 Tyche Softwares
 * WC tested up to: 10.9.4
 * Tested up to: 7.0
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 * Requires at least: 6.0
 * WC requires at least: 7.0
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package WooCommerce-Call-For-Price-Lite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Bootstrap constant (used inside the main class for hook registration).
define( 'CFP_LITE_BOOTSTRAP_FILE', __FILE__ );

// Guard: WooCommerce must be active.
$cfp_wc_plugin = 'woocommerce/woocommerce.php';
$active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) );
$active_sitewide_plugins = is_multisite() ? get_site_option( 'active_sitewide_plugins', array() ) : array();

if (
	! in_array( $cfp_wc_plugin, $active_plugins, true ) &&
	! array_key_exists( $cfp_wc_plugin, $active_sitewide_plugins )
) {
	return;
}

// Load the main plugin class.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-call-for-price.php';

// Global accessor function.
if ( ! function_exists( 'cfp_lite' ) ) {
	/**
	 * Returns the singleton instance of the plugin.
	 *
	 * @return \TycheSoftwares\CallForPrice\Lite\Plugin
	 */
	function cfp_lite() { // phpcs:ignore
		return \TycheSoftwares\CallForPrice\Lite\Plugin::instance();
	}
}

cfp_lite();
