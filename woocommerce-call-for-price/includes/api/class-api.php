<?php
/**
 * API bootstrap
 *
 * Instantiates every REST controller under the cfp-pro/v1 namespace.
 *
 * @package WooCommerce-Call-For-Price-Lite
 */

namespace TycheSoftwares\CallForPrice\Lite;

defined( 'ABSPATH' ) || exit;

/**
 * Class Api
 *
 * Bootstraps all REST API controllers.
 */
class Api {

	/**
	 * Constructor.
	 *
	 * Hooks into rest_api_init.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Instantiate each controller; each calls register_routes() on its own.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		( new Api_Settings() )->register_routes();
		( new Api_Options() )->register_routes();
		( new Api_Product() )->register_routes();
		( new Api_Dashboard() )->register_routes();
		( new Api_Migration() )->register_routes();
	}
}
