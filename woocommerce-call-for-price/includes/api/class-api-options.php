<?php
/**
 * API Options
 *
 * GET /cfp-pro/v1/options
 * Returns helper lists consumed by the React UI:
 *   - Available call types
 *   - Installed form plugins (CF7, Gravity Forms)
 *
 * @package WooCommerce-Call-For-Price-Lite
 */

namespace TycheSoftwares\CallForPrice\Lite;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Class Api_Options
 *
 * Handles endpoint that returns static options lists for the React UI.
 */
class Api_Options extends Api_Base {

	/**
	 * Register REST routes for options endpoint.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/options',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_options' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * GET /options – returns all helper data.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function get_options( WP_REST_Request $request ): WP_REST_Response {
		return $this->success(
			array(
				'call_types'         => array(
					array( 'value' => 'text',        'label' => __( 'Text', 'woocommerce-call-for-price' ) ),
					array( 'value' => 'phone',       'label' => __( 'Phone call', 'woocommerce-call-for-price' ) ),
					array( 'value' => 'whatsapp',    'label' => __( 'WhatsApp', 'woocommerce-call-for-price' ) ),
					array( 'value' => 'email',       'label' => __( 'Email', 'woocommerce-call-for-price' ) ),
					array( 'value' => 'custom_link', 'label' => __( 'Custom link', 'woocommerce-call-for-price' ) ),
				),
				'request_forms'      => $this->get_available_forms(),
				'product_categories' => $this->get_term_options( 'product_cat' ),
				'product_tags'       => $this->get_term_options( 'product_tag' ),
				'products'           => $this->get_product_options(),
				'user_roles'         => $this->get_user_role_options(),
			)
		);
	}

	/**
	 * Return { value, label } pairs for a taxonomy (categories or tags).
	 *
	 * @param string $taxonomy 'product_cat' or 'product_tag'.
	 * @return array<int, array{value:int, label:string}>
	 */
	private function get_term_options( string $taxonomy ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 500,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		return array_map(
			static function ( \WP_Term $term ): array {
				return array(
					'value' => (int) $term->term_id,
					'label' => $term->name,
				);
			},
			$terms
		);
	}

	/**
	 * Return { value, label } pairs for all published products (up to 500).
	 *
	 * @return array<int, array{value:int, label:string}>
	 */
	private function get_product_options(): array {
		$products = wc_get_products(
			array(
				'status'  => 'publish',
				'limit'   => 500,
				'return'  => 'objects',
				'orderby' => 'title',
				'order'   => 'ASC',
			)
		);

		if ( empty( $products ) ) {
			return array();
		}

		return array_map(
			static function ( \WC_Product $p ): array {
				return array(
					'value' => $p->get_id(),
					'label' => $p->get_name(),
				);
			},
			$products
		);
	}

	/**
	 * Return { value, label } pairs for every registered WordPress user role.
	 * Value is the role slug (string), label is the translated display name.
	 *
	 * @return array<int, array{value:string, label:string}>
	 */
	private function get_user_role_options(): array {
		$roles  = wp_roles()->get_names();
		$result = array(
			array(
				'value' => 'guest',
				'label' => __( 'Guest', 'woocommerce-call-for-price' )
			)
		);
		foreach ( $roles as $slug => $name ) {
			$result[] = array(
				'value' => $slug,
				'label' => translate_user_role( $name ),
			);
		}
		return $result;
	}

	/**
	 * Get available form plugin options for the Request Forms dropdown.
	 *
	 * @return array<int, array{value:string, label:string}>
	 */
	private function get_available_forms(): array {
		$forms = array(
			array( 'value' => 'none', 'label' => __( 'None', 'woocommerce-call-for-price' ) ),
		);

		if ( class_exists( 'WPCF7' ) ) {
			$forms[] = array( 'value' => 'contact-form7', 'label' => 'Contact Form 7' );
		}

		if ( class_exists( 'GFForms' ) ) {
			$forms[] = array( 'value' => 'gravity-form', 'label' => 'Gravity Forms' );
		}

		$forms[] = array(
			'value' => 'other-form',
			'label' => __( 'Other (Shortcode)', 'woocommerce-call-for-price' ),
		);

		return $forms;
	}
}
