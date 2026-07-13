<?php
/**
 * API Settings
 *
 * Handles the GET /settings, POST /settings, and POST /settings/reset endpoints.
 * All plugin-level settings live in a single wp_options row (cfp_pro_settings).
 *
 * @package WooCommerce-Call-For-Price-Lite
 */

namespace TycheSoftwares\CallForPrice\Lite;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Class Api_Settings
 */
class Api_Settings extends Api_Base {

	/**
	 * wp_options key for the consolidated settings.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'cfp_pro_settings';

	/**
	 * REST route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'settings';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET|POST /cfp-pro/v1/settings.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		// POST /cfp-pro/v1/settings/reset.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/reset',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reset_section' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'section' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
						'enum'              => array( 'general', 'simple', 'variable', 'grouped', 'external' ),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/tracking/reset',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'reset_tracking' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * POST /tracking/reset – returns true.
	 *
	 * @return WP_REST_Response
	 */
	public function reset_tracking() {
		delete_option( 'cfp_allow_tracking' );
		delete_option( 'ts_tracker_last_send' );
		return $this->success( array( 'reset' => true ) );
	}

	/**
	 * GET /settings – returns the full merged settings.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function get_settings( WP_REST_Request $request ): WP_REST_Response {
		return $this->success( $this->get_merged() );
	}

	/**
	 * POST /settings – merges the posted payload onto saved settings and persists.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function update_settings( WP_REST_Request $request ): WP_REST_Response {
		$params  = $request->get_json_params();
		$current = $this->get_merged();
		$merged  = $this->deep_merge( $current, $this->sanitize_settings( (array) $params ) );

		update_option( self::OPTION_KEY, $merged, false );

		return $this->success( $merged );
	}

	/**
	 * POST /settings/reset – resets a named section to its defaults.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function reset_section( WP_REST_Request $request ): WP_REST_Response {
		$section  = $request->get_param( 'section' );
		$defaults = $this->get_defaults();

		if ( ! isset( $defaults[ $section ] ) ) {
			return $this->error( 'cfp_invalid_section', 'Invalid section.', 400 );
		}

		$current             = $this->get_merged();
		$current[ $section ] = $defaults[ $section ];

		update_option( self::OPTION_KEY, $current, false );

		return $this->success( $current );
	}

	/**
	 * Return the canonical default settings array.
	 * Delegates to the Settings class so defaults are defined in one place.
	 *
	 * @return array<string, mixed>
	 */
	public function get_defaults(): array {
		return Settings::defaults();
	}

	/**
	 * Load saved settings and deep-merge them onto the canonical defaults.
	 *
	 * @return array<string, mixed>
	 */
	private function get_merged(): array {
		$saved    = get_option( self::OPTION_KEY, array() );
		$defaults = $this->get_defaults();

		return $this->deep_merge( $defaults, is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Sanitize an inbound settings payload.
	 *
	 * @param array $data Raw decoded JSON payload.
	 * @return array Sanitized settings.
	 */
	private function sanitize_settings( array $data ): array {
		$allowed_sections = array( 'general', 'simple', 'variable', 'grouped', 'external' );
		$clean            = array();

		foreach ( $allowed_sections as $section ) {
			if ( ! isset( $data[ $section ] ) ) {
				continue;
			}
			$clean[ $section ] = $this->sanitize_section( $data[ $section ], $section );
		}

		return $clean;
	}

	/**
	 * Sanitize a single section payload.
	 *
	 * @param array  $data    Raw section data.
	 * @param string $section Section key.
	 * @return array
	 */
	private function sanitize_section( array $data, string $section ): array {
		if ( 'general' === $section ) {
			return $this->sanitize_general( $data );
		}

		return $this->sanitize_type_section( $data );
	}

	/**
	 * Sanitize the general section.
	 *
	 * @param array $d Raw general section data.
	 * @return array
	 */
	private function sanitize_general( array $d ): array {
		$clean = array();

		if ( isset( $d['enabled'] ) ) {
			$clean['enabled'] = (bool) $d['enabled'];
		}
		if ( isset( $d['per_product_enabled'] ) ) {
			$clean['per_product_enabled'] = (bool) $d['per_product_enabled'];
		}
		if ( isset( $d['hide_sale_tag'] ) ) {
			$clean['hide_sale_tag'] = (bool) $d['hide_sale_tag'];
		}
		if ( isset( $d['force_variation_price'] ) ) {
			$clean['force_variation_price'] = (bool) $d['force_variation_price'];
		}
		if ( isset( $d['hide_variations_atc_button'] ) ) {
			$clean['hide_variations_atc_button'] = (bool) $d['hide_variations_atc_button'];
		}
		if ( isset( $d['show_stock_for_empty_price'] ) ) {
			$clean['show_stock_for_empty_price'] = (bool) $d['show_stock_for_empty_price'];
		}
		if ( isset( $d['change_button_text'] ) ) {
			$clean['change_button_text'] = (bool) $d['change_button_text'];
		}
		if ( isset( $d['hide_button'] ) ) {
			$clean['hide_button'] = (bool) $d['hide_button'];
		}
		if ( isset( $d['hide_main_variable_price'] ) ) {
			$allowed = array( 'no', 'yes', 'yes_with_css' );
			$clean['hide_main_variable_price'] = in_array( $d['hide_main_variable_price'], $allowed, true )
				? $d['hide_main_variable_price']
				: 'no';
		}
		if ( isset( $d['button_text'] ) ) {
			$clean['button_text'] = sanitize_text_field( $d['button_text'] );
		}
		if ( isset( $d['button_url'] ) ) {
			$clean['button_url'] = esc_url_raw( $d['button_url'] );
		}
		if ( isset( $d['logged_in_only'] ) ) {
			$clean['logged_in_only'] = array_map( 'sanitize_key', (array) $d['logged_in_only'] );
		}

		// Force sub-section.
		if ( isset( $d['force'] ) && is_array( $d['force'] ) ) {
			$f = $d['force'];
			$clean['force'] = array();

			$force_booleans = array(
				'all_products',
				'out_of_stock',
				'for_zero_price',
				'for_zero_price_variation',
				'for_all_products_text',
			);
			foreach ( $force_booleans as $key ) {
				if ( isset( $f[ $key ] ) ) {
					$clean['force'][ $key ] = (bool) $f[ $key ];
				}
			}

			if ( isset( $f['by_taxonomy'] ) && is_array( $f['by_taxonomy'] ) ) {
				$t = $f['by_taxonomy'];
				$clean['force']['by_taxonomy'] = array(
					'enabled'     => isset( $t['enabled'] ) ? (bool) $t['enabled'] : false,
					'product_cat' => isset( $t['product_cat'] ) ? array_map( 'absint', (array) $t['product_cat'] ) : array(),
					'product_tag' => isset( $t['product_tag'] ) ? array_map( 'absint', (array) $t['product_tag'] ) : array(),
				);
			}

			if ( isset( $f['by_price'] ) && is_array( $f['by_price'] ) ) {
				$p = $f['by_price'];
				$clean['force']['by_price'] = array(
					'enabled' => isset( $p['enabled'] ) ? (bool) $p['enabled'] : false,
					'min'     => isset( $p['min'] ) ? (float) $p['min'] : 0.0,
					'max'     => isset( $p['max'] ) ? (float) $p['max'] : 0.0,
				);
			}
		}

		// Exclude sub-section.
		if ( isset( $d['exclude'] ) && is_array( $d['exclude'] ) ) {
			$e = $d['exclude'];
			$clean['exclude'] = array(
				'products'   => isset( $e['products'] ) ? array_map( 'absint', (array) $e['products'] ) : array(),
				'categories' => isset( $e['categories'] ) ? array_map( 'absint', (array) $e['categories'] ) : array(),
			);
		}

		return $clean;
	}

	/**
	 * Sanitize a product-type section (simple | variable | grouped | external).
	 *
	 * @param array $d Raw section data.
	 * @return array
	 */
	private function sanitize_type_section( array $d ): array {
		$clean              = array();
		$allowed_call_types = array( 'text', 'phone', 'whatsapp', 'email', 'custom_link' );

		if ( isset( $d['enabled'] ) ) {
			$clean['enabled'] = (bool) $d['enabled'];
		}
		if ( isset( $d['call_type'] ) ) {
			$clean['call_type'] = in_array( $d['call_type'], $allowed_call_types, true ) ? $d['call_type'] : 'text';
		}
		if ( isset( $d['call_type_value'] ) ) {
			$clean['call_type_value'] = sanitize_text_field( $d['call_type_value'] );
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

		// Views.
		if ( isset( $d['views'] ) && is_array( $d['views'] ) ) {
			$allowed_views = array( 'single', 'related', 'home', 'page', 'archive', 'variation' );
			$clean['views'] = array();

			foreach ( $allowed_views as $view ) {
				if ( ! isset( $d['views'][ $view ] ) ) {
					continue;
				}
				$v = $d['views'][ $view ];
				$clean['views'][ $view ] = array(
					'enabled' => isset( $v['enabled'] ) ? (bool) $v['enabled'] : true,
					'text'    => isset( $v['text'] ) ? wp_kses_post( $v['text'] ) : '',
				);
			}
		}

		return $clean;
	}
}