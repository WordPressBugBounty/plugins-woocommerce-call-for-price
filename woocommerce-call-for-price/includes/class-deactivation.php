<?php
/**
 * Call for Price for WooCommerce – Deactivation Survey
 *
 * @package WooCommerce-Call-For-Price-Lite
 */

namespace TycheSoftwares\CallForPrice\Lite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Deactivation
 *
 * Loads the generic Tyche plugin‑deactivation survey library and
 * configures it for this plugin.
 */
class Deactivation {

	/**
	 * Constructor.
	 */
	public function __construct() {
		require_once __DIR__ . '/tyche/components/plugin-deactivation/class-plugin-deactivation.php';

		new \Tyche_Plugin_Deactivation(
			array(
				'plugin_name'       => 'Call for Price for WooCommerce',
				'plugin_base'       => 'woocommerce-call-for-price/woocommerce-call-for-price.php',
				'script_file'       => CFP_LITE_PLUGIN_URL . '/includes/tyche/assets/js/plugin-deactivation.js',
				'plugin_short_name' => 'cfp_lite',
				'version'           => CFP_LITE_VERSION,
				'plugin_locale'     => 'woocommerce-call-for-price',
			)
		);
	}
}

new Deactivation();
