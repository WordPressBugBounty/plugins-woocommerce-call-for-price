<?php
/**
 * API Dashboard
 *
 * GET /cfp-pro/v1/dashboard
 * Returns plugin summary stats for the React Dashboard screen.
 *
 * @package WooCommerce-Call-For-Price-Lite
 */

namespace TycheSoftwares\CallForPrice\Lite;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Class Api_Dashboard
 *
 * Handles the dashboard REST endpoint.
 */
class Api_Dashboard extends Api_Base {

	/**
	 * Register REST routes for the dashboard.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/dashboard',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_dashboard' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * GET /dashboard – returns plugin stats.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function get_dashboard( WP_REST_Request $request ): WP_REST_Response {
		$settings         = get_option( 'cfp_pro_settings', array() );
		$migration_status = Product_Meta_Migration::get_status();

		$active_types = array();
		$product_types = array( 'simple', 'variable', 'grouped', 'external' );
		foreach ( $product_types as $type ) {
			if ( ! empty( $settings[ $type ]['enabled'] ) ) {
				$active_types[] = $type;
			}
		}

		return $this->success(
			array(
				'plugin_enabled'       => ! empty( $settings['general']['enabled'] ),
				'active_product_types' => $active_types,
				'per_product_enabled'  => ! empty( $settings['general']['per_product_enabled'] ),
				'total_products'       => $this->count_products(),
				'migration'            => array(
					'status'      => $migration_status['status'],
					'total'       => $migration_status['total'],
					'done'        => $migration_status['done'],
					'percent'     => $migration_status['percent'],
					'show_notice' => Product_Meta_Migration::should_show_notice(),
				),
				'license_status'       => (string) ( $settings['license']['status'] ?? get_option( 'edd_license_key_call_for_price_status', 'inactive' ) ),
			)
		);
	}

	/**
	 * Count the number of published products.
	 *
	 * @return int
	 */
	private function count_products(): int {
		$cache_key = 'cfp_pro_enabled_product_count';
		$count = get_transient( $cache_key );

		if ( false === $count ) {
			$args = array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'meta_query'     => array(
					array(
						'key'     => '_cfp_pro_product_settings',
						'value'   => 's:7:"enabled";s:3:"yes"',
						'compare' => 'LIKE',
					),
				),
				'fields'         => 'ids',
				'posts_per_page' => -1,
			);

			$query = new \WP_Query( $args );
			$count = $query->post_count;

			set_transient( $cache_key, $count, DAY_IN_SECONDS );
		}

		return (int) $count;
	}
}
