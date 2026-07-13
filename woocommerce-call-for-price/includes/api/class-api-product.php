<?php
/**
 * API Product
 *
 * GET|POST /cfp-pro/v1/products/{id}
 *
 * Reads and writes the consolidated per-product meta key _cfp_pro_product_settings.
 * Falls back to legacy meta keys via the Compatibility layer when the new key
 * is absent (i.e. the store has not yet run the per-product migration).
 *
 * @package WooCommerce-Call-For-Price-Lite
 */

namespace TycheSoftwares\CallForPrice\Lite;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Class Api_Product
 *
 * Handles per-product settings REST endpoints.
 */
class Api_Product extends Api_Base {

	/**
	 * Consolidated postmeta key for product settings.
	 *
	 * @var string
	 */
	const META_KEY = '_cfp_pro_product_settings';

	/**
	 * Register REST routes for products endpoint.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/products/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_product_settings' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_product_settings' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);
	}

	/**
	 * GET /products/{id} – returns product settings.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function get_product_settings( WP_REST_Request $request ): WP_REST_Response {
		$product_id = absint( $request->get_param( 'id' ) );

		if ( ! $this->product_exists( $product_id ) ) {
			return $this->error( 'cfp_product_not_found', 'Product not found.', 404 );
		}

		return $this->success( Compatibility::get_product_meta( $product_id ) );
	}

	/**
	 * POST /products/{id} – updates product settings.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function update_product_settings( WP_REST_Request $request ): WP_REST_Response {
		$product_id = absint( $request->get_param( 'id' ) );

		if ( ! $this->product_exists( $product_id ) ) {
			return $this->error( 'cfp_product_not_found', 'Product not found.', 404 );
		}

		$params  = (array) $request->get_json_params();
		$current = Compatibility::get_product_meta( $product_id );
		$merged  = array_merge( $current, $this->sanitize_product_settings( $params ) );

		update_post_meta( $product_id, self::META_KEY, $merged );

		return $this->success( $merged );
	}

	/**
	 * Sanitize incoming product settings payload.
	 *
	 * @param array $d Raw settings data.
	 * @return array Sanitized settings.
	 */
	private function sanitize_product_settings( array $d ): array {
		$clean              = array();
		$allowed_call_types = array( 'text', 'phone', 'whatsapp', 'email', 'custom_link' );
		$allowed_forms      = array( 'none', 'contact-form7', 'gravity-form', 'other-form' );

		if ( isset( $d['enabled'] ) ) {
			$raw              = $d['enabled'];
			$clean['enabled'] = ( true === $raw || 'yes' === $raw ) ? 'yes' : 'no';
		}

		if ( isset( $d['call_type'] ) ) {
			$clean['call_type'] = in_array( $d['call_type'], $allowed_call_types, true ) ? $d['call_type'] : 'text';
		}

		if ( isset( $d['custom_value'] ) ) {
			$clean['custom_value'] = sanitize_text_field( $d['custom_value'] );
		}
		if ( isset( $d['whatsapp_template'] ) ) {
			$clean['whatsapp_template'] = sanitize_textarea_field( $d['whatsapp_template'] );
		}
		if ( isset( $d['email_subject'] ) ) {
			$clean['email_subject'] = sanitize_text_field( $d['email_subject'] );
		}
		if ( isset( $d['email_content'] ) ) {
			$clean['email_content'] = sanitize_textarea_field( $d['email_content'] );
		}
		if ( isset( $d['text_all_views'] ) ) {
			$clean['text_all_views'] = wp_kses_post( $d['text_all_views'] );
		}
		if ( isset( $d['shortcode'] ) ) {
			$clean['shortcode'] = sanitize_textarea_field( $d['shortcode'] );
		}
		if ( isset( $d['request_forms'] ) ) {
			$clean['request_forms'] = in_array( $d['request_forms'], $allowed_forms, true ) ? $d['request_forms'] : 'none';
		}

		return $clean;
	}

	/**
	 * Check if a product exists by ID.
	 *
	 * @param int $id Product ID.
	 * @return bool
	 */
	private function product_exists( int $id ): bool {
		return 'product' === get_post_type( $id );
	}
}