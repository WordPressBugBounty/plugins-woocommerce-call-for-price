<?php
/**
 * API Migration
 *
 * GET  /cfp-pro/v1/migration/status  — current per-product migration progress
 * POST /cfp-pro/v1/migration/start   — kick off background migration
 * POST /cfp-pro/v1/migration/dismiss — hide the notice after completion
 *
 * Delegates all logic to Product_Meta_Migration so that the progress tracking,
 * ActionScheduler batching, and WC logger calls remain in one place.
 *
 * @package WooCommerce-Call-For-Price-Lite
 */

namespace TycheSoftwares\CallForPrice\Lite;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Class Api_Migration
 *
 * Handles REST endpoints for per-product meta migration.
 */
class Api_Migration extends Api_Base {

	/**
	 * Register REST routes for migration endpoints.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$permission = array( $this, 'check_permission' );

		register_rest_route(
			$this->namespace,
			'/migration/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			$this->namespace,
			'/migration/start',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'start' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			$this->namespace,
			'/migration/dismiss',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'dismiss' ),
				'permission_callback' => $permission,
			)
		);
	}

	/**
	 * GET /migration/status – returns current migration progress.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function get_status( WP_REST_Request $request ): WP_REST_Response {
		return $this->success( Product_Meta_Migration::get_status() );
	}

	/**
	 * POST /migration/start – kicks off background migration.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function start( WP_REST_Request $request ): WP_REST_Response {
		$newly_started = Product_Meta_Migration::start();

		return $this->success(
			array_merge(
				Product_Meta_Migration::get_status(),
				array( 'newly_started' => $newly_started )
			)
		);
	}

	/**
	 * POST /migration/dismiss – hides the migration notice.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function dismiss( WP_REST_Request $request ): WP_REST_Response {
		update_option( Product_Meta_Migration::DISMISSED_OPTION, '1' );
		return $this->success();
	}
}
