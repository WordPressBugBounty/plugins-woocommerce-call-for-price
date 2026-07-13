<?php
/**
 * Settings
 *
 * Canonical schema and default values for the cfp_pro_settings option.
 * This is the single source of truth used by:
 *   • Migration::seed_defaults()       — fresh installs
 *   • Api_Settings::get_defaults()     — REST GET /settings default merge
 *   • Api_Settings::reset_section()    — POST /settings/reset
 *
 * @package WooCommerce-Call-For-Price-Lite
 */

namespace TycheSoftwares\CallForPrice\Lite;

defined( 'ABSPATH' ) || exit;

/**
 * Class Settings
 *
 * Holds default configuration values for the plugin.
 */
class Settings {

	/**
	 * Default WhatsApp message template.
	 *
	 * @var string
	 */
	const DEFAULT_WHATSAPP_TEMPLATE = "Hi, I'm interested in this product: {product_name} from {store_name}. Could you please share the price and more details?";

	/**
	 * Default email subject line.
	 *
	 * @var string
	 */
	const DEFAULT_EMAIL_SUBJECT = 'Price inquiry for {product_name} on {store_name}';

	/**
	 * Default email body.
	 *
	 * @var string
	 */
	const DEFAULT_EMAIL_CONTENT = "Hello,\n\nI'm interested in the following product listed on {store_name}:\n Product: {product_name}\n Link: {product_url}\n\nCould you please provide the price and any additional details?\nThank you!";

	/**
	 * Default CFP label HTML (used as the text for every product type / view).
	 *
	 * @var string
	 */
	const DEFAULT_LABEL = '<strong>Call for Price</strong>';

	/**
	 * Return the full default settings array.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'general'  => self::general_defaults(),
			'simple'   => self::type_defaults(),
			'variable' => self::type_defaults( true ),
			'grouped'  => self::type_defaults(),
			'external' => self::type_defaults(),
		);
	}

	/**
	 * Return defaults for a single product type.
	 *
	 * @param bool $is_variable Whether to include the variation view and variation flag.
	 * @return array<string, mixed>
	 */
	public static function type_defaults( bool $is_variable = false ): array {
		$view_defaults = array(
			'single'  => array(
				'enabled' => true,
				'text'    => self::DEFAULT_LABEL,
			),
			'related' => array(
				'enabled' => true,
				'text'    => self::DEFAULT_LABEL,
			),
			'home'    => array(
				'enabled' => true,
				'text'    => self::DEFAULT_LABEL,
			),
			'page'    => array(
				'enabled' => true,
				'text'    => self::DEFAULT_LABEL,
			),
			'archive' => array(
				'enabled' => true,
				'text'    => self::DEFAULT_LABEL,
			),
		);

		if ( $is_variable ) {
			$view_defaults['variation'] = array(
				'enabled' => true,
				'text'    => self::DEFAULT_LABEL,
			);
		}

		$base = array(
			'enabled'           => true,
			'call_type'         => 'text',
			'call_type_value'   => '',
			'whatsapp_template' => self::DEFAULT_WHATSAPP_TEMPLATE,
			'email_subject'     => self::DEFAULT_EMAIL_SUBJECT,
			'email_content'     => self::DEFAULT_EMAIL_CONTENT,
			'views'             => $view_defaults,
		);

		return $base;
	}

	/**
	 * Return defaults for the General section.
	 *
	 * @return array<string, mixed>
	 */
	public static function general_defaults(): array {
		return array(
			'enabled'                    => true,
			'per_product_enabled'        => false,
			'hide_sale_tag'              => true,
			'hide_main_variable_price'   => 'no',
			'force_variation_price'      => false,
			'hide_variations_atc_button' => true,
			'show_stock_for_empty_price' => false,
			'change_button_text'         => false,
			'button_text'                => 'Call for Price',
			'button_url'                 => '',
			'hide_button'                => false,
			'logged_in_only'             => array(),
			'force'                      => array(
				'all_products'             => false,
				'out_of_stock'             => false,
				'for_zero_price'           => false,
				'for_zero_price_variation' => false,
				'for_all_products_text'    => false,
				'by_taxonomy'              => array(
					'enabled'     => false,
					'product_cat' => array(),
					'product_tag' => array(),
				),
				'by_price'                 => array(
					'enabled' => false,
					'min'     => 0,
					'max'     => 0,
				),
			),
			'exclude'                    => array(
				'products'   => array(),
				'categories' => array(),
			),
		);
	}

	/**
	 * Return defaults for the per-product meta (_cfp_pro_product_settings).
	 *
	 * @return array<string, string>
	 */
	public static function product_meta_defaults(): array {
		return array(
			'enabled'           => 'no',
			'call_type'         => 'text',
			'custom_value'      => '',
			'whatsapp_template' => '',
			'email_subject'     => '',
			'email_content'     => '',
			'text_all_views'    => '',
			'request_forms'     => 'none',
			'shortcode'         => '',
		);
	}
}
