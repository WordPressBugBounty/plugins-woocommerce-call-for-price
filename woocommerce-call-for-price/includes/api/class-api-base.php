<?php
/**
 * API Base
 *
 * Abstract base class for all cfp-pro/v1 REST controllers.
 * Provides the shared permission callback, and success / error response helpers.
 *
 * @package WooCommerce-Call-For-Price-Lite
 */

namespace TycheSoftwares\CallForPrice\Lite;

use WP_REST_Controller;
use WP_REST_Response;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Class Api_Base
 *
 * Abstract base class for REST API endpoints.
 */
abstract class Api_Base extends WP_REST_Controller {

	/**
	 * REST namespace shared by every CFP Pro endpoint.
	 *
	 * @var string
	 */
	protected $namespace = 'cfp-pro/v1';

	/**
	 * Permission callback — requires manage_woocommerce capability.
	 * WordPress REST authentication automatically handles nonce verification
	 * when the request carries a valid X-WP-Nonce header.
	 *
	 * @return bool|WP_Error True if permitted, WP_Error otherwise.
	 */
	public function check_permission() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new WP_Error(
				'cfp_forbidden',
				__( 'You do not have permission to manage Call for Price settings.', 'woocommerce-call-for-price' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Return a standardised success response.
	 *
	 * @param mixed $data   Response payload.
	 * @param int   $status HTTP status code (default 200).
	 * @return WP_REST_Response
	 */
	protected function success( $data = null, int $status = 200 ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			$status
		);
	}

	/**
	 * Return a standardised error response.
	 *
	 * @param string $code    Machine-readable error code.
	 * @param string $message Human-readable error message.
	 * @param int    $status  HTTP status code (default 400).
	 * @return WP_REST_Response
	 */
	protected function error( string $code, string $message, int $status = 400 ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'success' => false,
				'code'    => $code,
				'message' => $message,
			),
			$status
		);
	}

	/**
	 * Deep-merge $override onto $base, recursing into nested associative arrays.
	 * Values in $override win; keys absent from $override keep the $base value.
	 * List arrays (e.g. arrays of selected IDs/slugs) are replaced wholesale —
	 * including with an empty array — rather than recursed into, since an empty
	 * override list still represents an explicit "clear the selection" value.
	 *
	 * @param array $base     Base array.
	 * @param array $override Override array (higher priority).
	 * @return array Merged array.
	 */
	protected function deep_merge( array $base, array $override ): array {
		$result = $base;

		foreach ( $override as $key => $value ) {
			if ( is_array( $value ) && ! $this->is_list( $value ) && isset( $result[ $key ] ) && is_array( $result[ $key ] ) && ! $this->is_list( $result[ $key ] ) ) {
				$result[ $key ] = $this->deep_merge( $result[ $key ], $value );
			} else {
				$result[ $key ] = $value;
			}
		}

		return $result;
	}

	/**
	 * Determine whether an array is a list (sequential integer keys from 0),
	 * as opposed to an associative/object-like array.
	 *
	 * @param array $value Array to check.
	 * @return bool
	 */
	protected function is_list( array $value ): bool {
		if ( array() === $value ) {
			return true;
		}

		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}
}
