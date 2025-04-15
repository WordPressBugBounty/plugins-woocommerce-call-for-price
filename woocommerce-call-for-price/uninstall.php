<?php
/**
 * Currency Per Product Pro Uninstall
 *
 * Deletes all the settings for the plugin from the database when plugin is uninstalled.
 *
 * @package CallForPrice
 * @author  Tyche Softwares
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( file_exists( WP_PLUGIN_DIR . 'woocommerce-call-for-price-pro/woocommerce-call-for-price-pro.php' ) ) {
	return;
}

global $wpdb;
$results = $wpdb->get_results( // phpcs:ignore
	$wpdb->prepare(
		'SELECT option_name FROM `' . $wpdb->prefix . 'options` WHERE option_name LIKE %s OR option_name LIKE %s',
		'alg_wc_call_for_price%',
		'alg_call_for_price%'
	)
); // WPCS: db call ok, WPCS: cache ok.
foreach ( $results as $key => $value ) {
	delete_option( $value->option_name );
}
