<?php // phpcs:ignore
/**
 * WooCommerce Call for Price Compatibility
 *
 * @package CallForPrice
 * @version 3.1.0
 * @since   3.1.0
 * @author  Tyche Softwares
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Alg_WC_Call_For_Price_Compatibility' ) ) :
	/**
	 * Main Alg_WC_Call_For_Price_Compatibility Class
	 *
	 * @class   Alg_WC_Call_For_Price_Compatibility
	 * @version 1.2.0
	 * @since   1.0.0
	 */
	class Alg_WC_Call_For_Price_Compatibility {

		/**
		 * Constructor.
		 *
		 * @version 3.1.0
		 * @since   3.1.0
		 */
		public function __construct() {
			// Solaris theme fix.
			if ( function_exists( 'themerex_woocommerce_after_price' ) && function_exists( 'themerex_woocommerce_before_price' ) ) {
				add_action( 'woocommerce_before_single_product', array( $this, 'solaris_theme_fix_single' ) );
				add_action( 'woocommerce_after_single_product', array( $this, 'solaris_theme_fix_single_end' ) );
				add_action( 'woocommerce_before_shop_loop_item', array( $this, 'solaris_theme_fix_loop' ) );
				add_action( 'woocommerce_after_shop_loop_item', array( $this, 'solaris_theme_fix_loop_end' ) );
			}
		}

		/**
		 * Is_empty_price_product.
		 *
		 * @version 3.1.0
		 * @since   3.1.0
		 */
		public function is_empty_price_product() {
			$_product = wc_get_product( get_the_ID() );
			return ( '' === $_product->get_price() );
		}

		/**
		 * Solaris_theme_fix_single_end.
		 *
		 * @version 3.1.0
		 * @since   2.0.1
		 */
		public function solaris_theme_fix_single_end() {
			if ( $this->is_empty_price_product() ) {
				add_action( 'woocommerce_single_product_summary', 'themerex_woocommerce_after_price', 11 );
				add_action( 'woocommerce_single_product_summary', 'themerex_woocommerce_before_price', 9 );
			}
		}

		/**
		 * Solaris_theme_fix_single.
		 *
		 * @version 3.1.0
		 * @since   2.0.1
		 */
		public function solaris_theme_fix_single() {
			if ( $this->is_empty_price_product() ) {
				remove_action( 'woocommerce_single_product_summary', 'themerex_woocommerce_after_price', 11 );
				remove_action( 'woocommerce_single_product_summary', 'themerex_woocommerce_before_price', 9 );
			}
		}

		/**
		 * Solaris_theme_fix_loop_end.
		 *
		 * @version 3.1.0
		 * @since   2.0.1
		 */
		public function solaris_theme_fix_loop_end() {
			if ( $this->is_empty_price_product() ) {
				add_action( 'woocommerce_after_shop_loop_item_title', 'themerex_woocommerce_after_price', 11 );
				add_action( 'woocommerce_after_shop_loop_item_title', 'themerex_woocommerce_before_price', 9 );
			}
		}

		/**
		 * Solaris_theme_fix_loop.
		 *
		 * @version 3.1.0
		 * @since   2.0.1
		 */
		public function solaris_theme_fix_loop() {
			if ( $this->is_empty_price_product() ) {
				remove_action( 'woocommerce_after_shop_loop_item_title', 'themerex_woocommerce_after_price', 11 );
				remove_action( 'woocommerce_after_shop_loop_item_title', 'themerex_woocommerce_before_price', 9 );
			}
		}
	}

endif;

return new Alg_WC_Call_For_Price_Compatibility();
