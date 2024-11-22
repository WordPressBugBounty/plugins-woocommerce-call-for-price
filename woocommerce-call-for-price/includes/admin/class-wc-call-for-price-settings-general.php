<?php
/**
 * WooCommerce Call for Price - General Section Settings
 *
 * @package CallForPrice
 * @version 3.2.3
 * @since   2.0.0
 * @author  Tyche Softwares
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Alg_WC_Call_For_Price_Settings_General' ) ) :
	/**
	 * Main Alg_WC_Call_For_Price_Settings_General Class
	 *
	 * @class   Alg_WC_Call_For_Price_Settings_General
	 * @version 1.2.0
	 * @since   1.0.0
	 */
	class Alg_WC_Call_For_Price_Settings_General {
		/**
		 * Id.
		 *
		 * @var $id
		 * @since 3.0.0
		 */
		public $id = '';

		/**
		 * Desc.
		 *
		 * @var $desc
		 * @since 3.0.0
		 */
		public $desc = '';
		/**
		 * Constructor.
		 *
		 * @version 3.0.0
		 */
		public function __construct() {

			$this->id = '';
			add_action( 'init', array( &$this, 'add_cfp_desc_general' ) );

			add_filter( 'woocommerce_get_sections_alg_call_for_price', array( $this, 'settings_section' ) );
			add_filter( 'woocommerce_get_settings_alg_call_for_price_' . $this->id, array( $this, 'get_settings' ), PHP_INT_MAX );
		}

		/**
		 * Add desc to setting page.
		 */
		public function add_cfp_desc_general() {
			$this->desc = __( 'General', 'woocommerce-call-for-price' );
		}

		/**
		 * Wettings_section.
		 *
		 * @param array $sections Array of section infomration.
		 */
		public function settings_section( $sections ) {
			$sections[ $this->id ] = $this->desc;
			return $sections;
		}

		/**
		 * Get_terms.
		 *
		 * @param array $args Array of arguments.
		 * @version 3.2.0
		 * @since   3.2.0
		 */
		public function get_terms( $args ) {
			if ( ! is_array( $args ) ) {
				$_taxonomy = $args;
				$args      = array(
					'taxonomy'   => $_taxonomy,
					'orderby'    => 'name',
					'hide_empty' => false,
				);
			}
			global $wp_version;
			if ( version_compare( $wp_version, '4.5.0', '>=' ) ) {
				$_terms = get_terms( $args );
			} else {
				$_taxonomy = $args['taxonomy'];
				unset( $args['taxonomy'] );
				$_terms = get_terms( $_taxonomy, $args );
			}
			$_terms_options = array();
			if ( ! empty( $_terms ) && ! is_wp_error( $_terms ) ) {
				foreach ( $_terms as $_term ) {
					$_terms_options[ $_term->term_id ] = $_term->name;
				}
			}
			return $_terms_options;
		}

		/**
		 * Get_settings.
		 *
		 * @version 3.2.3
		 */
		public function get_settings() {

			$plugin_settings = array(
				array(
					'title' => __( 'Call for Price Options', 'woocommerce-call-for-price' ),
					'type'  => 'title',
					'id'    => 'alg_wc_call_for_price_options',
				),
				array(
					'title'    => __( 'WooCommerce Call for Price', 'woocommerce-call-for-price' ),
					'desc'     => '<strong>' . __( 'Enable plugin', 'woocommerce-call-for-price' ) . '</strong>',
					'desc_tip' => __( 'Create any custom price label for all WooCommerce products with empty price.', 'woocommerce-call-for-price' ) .
						'<p><a class="button" style="font-style: italic;" href="https://www.tychesoftwares.com/docs/docs/call-for-price-for-woocommerce/" target="_blank">' .
							__( 'Documentation', 'woocommerce-call-for-price' ) . '</a></p>',
					'id'       => 'alg_wc_call_for_price_enabled',
					'default'  => 'yes',
					'type'     => 'checkbox',
				),
				array(
					'type' => 'sectionend',
					'id'   => 'alg_wc_call_for_price_options',
				),
			);

			$general_settings = array(
				array(
					'title' => __( 'General Options', 'woocommerce-call-for-price' ),
					'type'  => 'title',
					'id'    => 'alg_wc_call_for_price_general_options',
				),
				array(
					'title'             => __( 'Per product', 'woocommerce-call-for-price' ),
					'desc'              => __( 'Enable', 'woocommerce-call-for-price' ),
					'desc_tip'          => __( 'This will add new meta box to each product\'s admin edit page.', 'woocommerce-call-for-price' ) . ' ' .
					apply_filters(
						'alg_call_for_price',
						'<br>' . sprintf( /* translators: %s: Link to pro version */
							__( 'You will need %s plugin to enable "Per Product" option.', 'woocommerce-call-for-price' ),
							'<a target="_blank" href="https://www.tychesoftwares.com/store/premium-plugins/woocommerce-call-for-price-plugin/?utm_source=cfpupgradetopro&utm_medium=link&utm_campaign=CallForPriceLite">' .
							__( 'Call for Price for WooCommerce Pro', 'woocommerce-call-for-price' ) . '</a>'
						),
						'settings'
					),
					'id'                => 'alg_wc_call_for_price_per_product_enabled',
					'default'           => 'no',
					'type'              => 'checkbox',
					'custom_attributes' => apply_filters( 'alg_call_for_price', array( 'disabled' => 'disabled' ), 'settings' ),
				),
				array(
					'title'   => __( 'Enable Call for Price for zero(0) price products.', 'woocommerce-call-for-price' ),
					'desc'    => __( 'Check the box to display call for price text for products whose prices set to 0 as well.', 'woocommerce-call-for-price' ),
					'id'      => 'alg_call_for_price_enable_cfp_for_zero_price',
					'default' => 'no',
					'type'    => 'checkbox',
				),
				array(
					'title'   => __( 'Show stock status for empty priced products.', 'woocommerce-call-for-price' ),
					'desc'    => __( 'Check the box to display stock status/quantity for empty priced products.', 'woocommerce-call-for-price' ),
					'id'      => 'alg_call_for_price_enable_stock_for_empty_price',
					'default' => 'no',
					'type'    => 'checkbox',
				),
				array(
					'type' => 'sectionend',
					'id'   => 'alg_wc_call_for_price_general_options',
				),
			);

			$button_settings = array(
				array(
					'title' => __( 'Button Options', 'woocommerce-call-for-price' ),
					'type'  => 'title',
					'id'    => 'alg_wc_call_for_price_button_options',
				),
				array(
					'title'    => __( 'Button text', 'woocommerce-call-for-price' ),
					'desc_tip' => __( 'Changes "Read more" button text for "Call for Price" products on archives.', 'woocommerce-call-for-price' ),
					'desc'     => __( 'Enable', 'woocommerce-call-for-price' ),
					'id'       => 'alg_call_for_price_change_button_text',
					'default'  => 'no',
					'type'     => 'checkbox',
				),
				array(
					'desc'    => __( 'Button text', 'woocommerce-call-for-price' ),
					'id'      => 'alg_call_for_price_button_text',
					'default' => __( 'Call for Price', 'woocommerce-call-for-price' ),
					'type'    => 'text',
				),
				array(
					'title'    => __( 'Hide button', 'woocommerce-call-for-price' ),
					'desc_tip' => __( 'Hides "Read more" button for "Call for Price" products on archives.', 'woocommerce-call-for-price' ),
					'desc'     => __( 'Hide', 'woocommerce-call-for-price' ),
					'id'       => 'alg_call_for_price_hide_button',
					'default'  => 'no',
					'type'     => 'checkbox',
				),
				array(
					'title'    => __( 'Variations "add to cart" button', 'woocommerce-call-for-price' ),
					'desc_tip' => __( 'Hides disabled "add to cart" button for variations with empty prices.', 'woocommerce-call-for-price' ),
					'desc'     => __( 'Hide', 'woocommerce-call-for-price' ),
					'id'       => 'alg_wc_call_for_price_hide_variations_add_to_cart_button',
					'default'  => 'yes',
					'type'     => 'checkbox',
				),
				array(
					'type' => 'sectionend',
					'id'   => 'alg_wc_call_for_price_button_options',
				),
			);

			$price_step     = 1 / pow( 10, get_option( 'woocommerce_price_num_decimals', 2 ) );
			$force_settings = array(
				array(
					'title' => __( 'Force Products "Call for Price"', 'woocommerce-call-for-price' ),
					'desc'  => __( 'By default only products with empty price display "Call for Price" labels, however you can additionally force products with not empty price to display "Call for Price" label also.', 'woocommerce-call-for-price' ),
					'id'    => 'alg_call_for_price_make_options',
					'type'  => 'title',
				),
				array(
					'title'    => __( 'All products', 'woocommerce-call-for-price' ),
					'desc_tip' => __( 'Makes all your shop\'s products "Call for Price".', 'woocommerce-call-for-price' ),
					'desc'     => __( 'Enable', 'woocommerce-call-for-price' ),
					'id'       => 'alg_call_for_price_make_all_empty',
					'default'  => 'no',
					'type'     => 'checkbox',
				),
				array(
					'title'             => __( '"Out of stock" products', 'woocommerce-call-for-price' ),
					'desc'              => __( 'Enable', 'woocommerce-call-for-price' ),
					'desc_tip'          => __( 'Makes "Call for Price" for all products that can not be purchased (not "in stock" or "on backorder" stock statuses).', 'woocommerce-call-for-price' ) .
					apply_filters(
						'alg_call_for_price',
						'<br>' . sprintf( /* translators: %s: Link to pro version */
							__( 'You will need %s plugin to enable this option.', 'woocommerce-call-for-price' ),
							'<a target="_blank" href="https://www.tychesoftwares.com/store/premium-plugins/woocommerce-call-for-price-plugin/?utm_source=cfpupgradetopro&utm_medium=link&utm_campaign=CallForPriceLite">' .
							__( 'Call for Price for WooCommerce Pro', 'woocommerce-call-for-price' ) . '</a>'
						),
						'settings'
					),
					'id'                => 'alg_call_for_price_make_out_of_stock_empty_price',
					'default'           => 'no',
					'type'              => 'checkbox',
					'custom_attributes' => apply_filters( 'alg_call_for_price', array( 'disabled' => 'disabled' ), 'settings' ),
				),
				array(
					'title'    => __( 'Per product taxonomy', 'woocommerce-call-for-price' ),
					'desc'     => __( 'Enable', 'woocommerce-call-for-price' ),
					'desc_tip' => __( 'Makes "Call for Price" for all products from selected product categories and/or product tags.', 'woocommerce-call-for-price' ),
					'id'       => 'alg_call_for_price_make_empty_price_per_taxonomy',
					'default'  => 'no',
					'type'     => 'checkbox',
				),
				array(
					'desc'    => __( 'Product categories', 'woocommerce-call-for-price' ),
					'id'      => 'alg_call_for_price_make_empty_price_product_cat',
					'default' => '',
					'type'    => 'multiselect',
					'class'   => 'chosen_select',
					'options' => $this->get_terms( 'product_cat' ),
				),
				array(
					'desc'    => __( 'Product tags', 'woocommerce-call-for-price' ),
					'id'      => 'alg_call_for_price_make_empty_price_product_tag',
					'default' => '',
					'type'    => 'multiselect',
					'class'   => 'chosen_select',
					'options' => $this->get_terms( 'product_tag' ),
				),
				array(
					'title'    => __( 'By product price', 'woocommerce-call-for-price' ),
					'desc'     => __( 'Enable', 'woocommerce-call-for-price' ),
					'desc_tip' => __( 'Makes "Call for Price" for all products in selected price range.', 'woocommerce-call-for-price' ),
					'id'       => 'alg_call_for_price_make_empty_price_by_product_price',
					'default'  => 'no',
					'type'     => 'checkbox',
				),
				array(
					'desc'              => __( 'Min price', 'woocommerce-call-for-price' ),
					'desc_tip'          => __( 'Ignored, if set set to zero.', 'woocommerce-call-for-price' ),
					'id'                => 'alg_call_for_price_make_empty_price_min_price',
					'default'           => 0,
					'type'              => 'number',
					'custom_attributes' => array(
						'min'  => 0,
						'step' => $price_step,
					),
				),
				array(
					'desc'              => __( 'Max price', 'woocommerce-call-for-price' ),
					'desc_tip'          => __( 'Ignored, if set set to zero.', 'woocommerce-call-for-price' ),
					'id'                => 'alg_call_for_price_make_empty_price_max_price',
					'default'           => 0,
					'type'              => 'number',
					'custom_attributes' => array(
						'min'  => 0,
						'step' => $price_step,
					),
				),
				array(
					'type' => 'sectionend',
					'id'   => 'alg_call_for_price_make_options',
				),
			);

			$advanced_settings = array(
				array(
					'title' => __( 'Advanced Options', 'woocommerce-call-for-price' ),
					'type'  => 'title',
					'id'    => 'alg_wc_call_for_price_advanced_options',
				),
				array(
					'title'    => __( 'Sale tag', 'woocommerce-call-for-price' ),
					'desc_tip' => __( 'Hides sale tag for products with empty prices.', 'woocommerce-call-for-price' ),
					'desc'     => __( 'Hide', 'woocommerce-call-for-price' ),
					'id'       => 'alg_wc_call_for_price_hide_sale_sign',
					'default'  => 'yes',
					'type'     => 'checkbox',
				),
				array(
					'title'    => __( 'Main variable price', 'woocommerce-call-for-price' ),
					'desc_tip' => __( 'Hides main variable price on single product page for all products.', 'woocommerce-call-for-price' ),
					'id'       => 'alg_wc_call_for_price_hide_main_variable_price',
					'default'  => 'no',
					'type'     => 'select',
					'options'  => array(
						'no'           => __( 'Do not hide', 'woocommerce-call-for-price' ),
						'yes'          => __( 'Hide', 'woocommerce-call-for-price' ),
						'yes_with_css' => __( 'Hide with CSS', 'woocommerce-call-for-price' ),
					),
				),
				array(
					'title'    => __( 'Force variation price', 'woocommerce-call-for-price' ),
					'desc_tip' => __( 'Forces variations prices on single product page for all products.', 'woocommerce-call-for-price' ),
					'desc'     => __( 'Enable', 'woocommerce-call-for-price' ),
					'id'       => 'alg_wc_call_for_price_force_variation_price',
					'default'  => 'no',
					'type'     => 'checkbox',
				),
				array(
					'type' => 'sectionend',
					'id'   => 'alg_wc_call_for_price_advanced_options',
				),
			);

			return array_merge( $plugin_settings, $general_settings, $button_settings, $force_settings, $advanced_settings );
		}

	}

endif;

return new Alg_WC_Call_For_Price_Settings_General();
