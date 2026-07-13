<?php
/**
 * Call for Price for WooCommerce – Usage Tracking Bootstrap
 *
 * Loads the generic Tyche tracking library and the plugin‑specific
 * tracking data class.
 *
 * @package WooCommerce-Call-For-Price-Lite
 */

namespace TycheSoftwares\CallForPrice\Lite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tracking
 */
class Tracking {

	/**
	 * Constructor.
	 */
	public function __construct() {
		require_once __DIR__ . '/tyche/components/plugin-tracking/class-plugin-tracking.php';

		new \Tyche_Plugin_Tracking(
			array(
				'plugin_name'       => 'Call for Price for WooCommerce',
				'plugin_locale'     => 'woocommerce-call-for-price',
				'plugin_short_name' => 'cfp',
				'version'           => CFP_LITE_VERSION,
				'blog_link'         => 'https://www.tychesoftwares.com/docs/woocommerce-call-for-price/call-for-price-usage-tracking/',
			)
		);

		if ( is_admin() ) {
			require_once __DIR__ . '/tyche/components/plugin-tracking/class-cf-data-tracking.php';
		}
	}
}

new Tracking();
