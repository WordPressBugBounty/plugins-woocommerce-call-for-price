<?php
/**
 * Compatibility
 *
 * Provides the two runtime helper functions that the Hooks and Frontend classes
 * use to read settings. Each helper first reads from the new consolidated key;
 * if that key is absent (migration not yet run) it falls back to the legacy
 * get_option() / get_post_meta() calls so the plugin always works correctly
 * regardless of migration state.
 *
 * @package WooCommerce-Call-For-Price-Lite
 */

namespace TycheSoftwares\CallForPrice\Lite;

defined( 'ABSPATH' ) || exit;

/**
 * Class Compatibility
 *
 * Handles reading settings with fallback to legacy options.
 */
class Compatibility {

	/**
	 * Cached copy of cfp_pro_settings to avoid repeated get_option() calls.
	 *
	 * @var array|null
	 */
	private static ?array $settings_cache = null;

	/**
	 * Read a setting value with a new-then-legacy fallback.
	 *
	 * Example:
	 *   Compatibility::get_setting( 'general', 'enabled', true )
	 *   Compatibility::get_setting( 'simple', 'call_type', 'text' )
	 *   Compatibility::get_setting( 'variable', [ 'views', 'single', 'text' ], '<strong>CFP</strong>' )
	 *
	 * @param string       $section Top-level section key.
	 * @param string|array $key     Field key or dot-path array for nested values.
	 * @param mixed        $default Fallback default.
	 * @return mixed
	 */
	public static function get_setting( string $section, $key, $default = null ) {
		// Load and cache the consolidated option once per request.
		if ( null === self::$settings_cache ) {
			self::$settings_cache = get_option( 'cfp_pro_settings', array() );
		}

		// Try the new consolidated key first.
		if ( ! empty( self::$settings_cache[ $section ] ) ) {
			$value = self::$settings_cache[ $section ];

			$path = is_array( $key ) ? $key : array( $key );
			foreach ( $path as $part ) {
				if ( is_array( $value ) && array_key_exists( $part, $value ) ) {
					$value = $value[ $part ];
				} else {
					$value = null;
					break;
				}
			}

			if ( null !== $value ) {
				return $value;
			}
		}

		// Legacy fallback.
		return self::legacy_get_setting( $section, $key, $default );
	}

	/**
	 * Read per-product settings with new-then-legacy fallback.
	 *
	 * Merges the canonical product defaults with whatever is found in the new
	 * consolidated meta key (or, if absent, the legacy individual meta rows).
	 *
	 * @param int $product_id WP post ID.
	 * @return array<string, string> Merged product settings.
	 */
	public static function get_product_meta( int $product_id ): array {
		$defaults = Settings::product_meta_defaults();

		// Try new consolidated key first.
		$new = get_post_meta( $product_id, '_cfp_pro_product_settings', true );
		if ( is_array( $new ) && ! empty( $new ) ) {
			return array_merge( $defaults, $new );
		}

		// Fall back to legacy individual meta keys.
		return array_merge(
			$defaults,
			array(
				'enabled'           => (string) get_post_meta( $product_id, '_alg_wc_call_for_price_enabled', true ),
				'call_type'         => (string) get_post_meta( $product_id, '_alg_wc_call_for_price_call_type', true ) ?: 'text',
				'custom_value'      => (string) get_post_meta( $product_id, '_alg_wc_call_for_price_custom_value', true ),
				'whatsapp_template' => (string) get_post_meta( $product_id, '_alg_wc_call_for_price_whatsapp_template', true ),
				'email_subject'     => (string) get_post_meta( $product_id, '_alg_wc_call_for_price_email_subject', true ),
				'email_content'     => (string) get_post_meta( $product_id, '_alg_wc_call_for_price_email_content', true ),
				'text_all_views'    => (string) get_post_meta( $product_id, '_alg_wc_call_for_price_text_all_views', true ),
				'request_forms'     => (string) get_post_meta( $product_id, '_alg_wc_call_for_price_req_forms_enabled', true ) ?: 'none',
				'shortcode'         => (string) get_post_meta( $product_id, '_alg_wc_call_for_price_forms_shortcode', true ),
			)
		);
	}

	/**
	 * Bust the in-memory settings cache (call after saving settings via REST).
	 *
	 * @return void
	 */
	public static function bust_cache(): void {
		self::$settings_cache = null;
	}

	// -------------------------------------------------------------------------
	// Legacy fallback helpers
	// -------------------------------------------------------------------------

	/**
	 * Read a value from the legacy individual wp_options rows.
	 *
	 * @param string       $section Section key.
	 * @param string|array $key     Field key or path array.
	 * @param mixed        $default Fallback default.
	 * @return mixed
	 */
	private static function legacy_get_setting( string $section, $key, $default ) {
		if ( 'general' === $section && is_string( $key ) ) {
			return self::legacy_general( $key, $default );
		}

		$type_sections = array( 'simple', 'variable', 'grouped', 'external' );
		if ( in_array( $section, $type_sections, true ) ) {
			return self::legacy_type( $section, $key, $default );
		}

		return $default;
	}

	/**
	 * Legacy general setting lookup.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback default.
	 * @return mixed
	 */
	private static function legacy_general( string $key, $default ) {
		$map = array(
			'enabled'                    => array( 'alg_wc_call_for_price_enabled', 'yes' ),
			'per_product_enabled'        => array( 'alg_wc_call_for_price_per_product_enabled', 'no' ),
			'hide_sale_tag'              => array( 'alg_wc_call_for_price_hide_sale_sign', 'yes' ),
			'hide_main_variable_price'   => array( 'alg_wc_call_for_price_hide_main_variable_price', 'no' ),
			'force_variation_price'      => array( 'alg_wc_call_for_price_force_variation_price', 'no' ),
			'hide_variations_atc_button' => array( 'alg_wc_call_for_price_hide_variations_add_to_cart_button', 'yes' ),
			'show_stock_for_empty_price' => array( 'alg_call_for_price_enable_stock_for_empty_price', 'no' ),
			'change_button_text'         => array( 'alg_call_for_price_change_button_text', 'no' ),
			'button_text'                => array( 'alg_call_for_price_button_text', 'Call for Price' ),
			'button_url'                 => array( 'alg_call_for_price_button_url', '' ),
			'hide_button'                => array( 'alg_call_for_price_hide_button', 'no' ),
		);

		if ( isset( $map[ $key ] ) ) {
			return get_option( $map[ $key ][0], $map[ $key ][1] );
		}

		if ( 'logged_in_only' === $key ) {
			return (array) get_option( 'alg_call_for_price_make_empty_price_per_user_roles', array() );
		}

		return $default;
	}

	/**
	 * Legacy product-type setting lookup.
	 *
	 * @param string       $type    Product type (simple|variable|grouped|external).
	 * @param string|array $key     Field key or path array.
	 * @param mixed        $default Fallback default.
	 * @return mixed
	 */
	private static function legacy_type( string $type, $key, $default ) {
		if ( is_string( $key ) ) {
			$map = array(
				'enabled'           => array( "alg_wc_call_for_price_{$type}_enabled", 'yes' ),
				'call_type'         => array( "alg_wc_call_for_price_call_type_{$type}", 'text' ),
				'call_type_value'   => array( "alg_wc_call_for_price_call_type_value_{$type}", '' ),
				'whatsapp_template' => array( "alg_wc_call_for_price_template_{$type}", '' ),
				'email_subject'     => array( "alg_wc_call_for_price_email_subject_{$type}", '' ),
				'email_content'     => array( "alg_wc_call_for_price_email_content_{$type}", '' ),
			);

			if ( isset( $map[ $key ] ) ) {
				return get_option( $map[ $key ][0], $map[ $key ][1] );
			}
		}

		// Nested: [ 'views', 'single', 'enabled' ] or [ 'views', 'single', 'text' ]
		if ( is_array( $key ) && 'views' === ( $key[0] ?? '' ) ) {
			$view  = $key[1] ?? '';
			$field = $key[2] ?? '';

			if ( 'enabled' === $field ) {
				return get_option( "alg_wc_call_for_price_{$type}_{$view}_enabled", 'yes' );
			}
			if ( 'text' === $field ) {
				return get_option( "alg_wc_call_for_price_text_{$type}_{$view}", '<strong>Call for Price</strong>' );
			}
		}

		return $default;
	}
}
