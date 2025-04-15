<?php // phpcs:ignore
/**
 * WooCommerce Call for Price
 *
 * @package CallForPrice
 * @version 3.2.3
 * @since   2.0.0
 * @author  Tyche Softwares
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Alg_WC_Call_For_Price' ) ) :

	/**
	 * Main Alg_WC_Call_For_Price Class
	 *
	 * @class   Alg_WC_Call_For_Price
	 * @version 1.2.0
	 * @since   1.0.0
	 */
	class Alg_WC_Call_For_Price {
		/**
		 * Woocommerce version.
		 *
		 * @var $is_wc_below_3_0_0
		 * @since 3.0.0
		 */
		public $is_wc_below_3_0_0 = '';

		/**
		 * Constructor.
		 *
		 * @version 3.2.3
		 */
		public function __construct() {
			if ( 'yes' === get_option( 'alg_wc_call_for_price_enabled', 'yes' ) ) {
				// Class properties.
				$this->is_wc_below_3_0_0 = version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' );
				// Empty price hooks.
				add_action( 'init', array( $this, 'add_hooks' ), PHP_INT_MAX );
				// Sale flash.
				add_filter( 'woocommerce_sale_flash', array( $this, 'hide_sales_flash' ), PHP_INT_MAX, 3 );
				// Variable products.
				if ( 'yes' === get_option( 'alg_wc_call_for_price_variable_enabled', 'yes' ) ) {
					if ( 'yes' === get_option( 'alg_wc_call_for_price_variable_variation_enabled', 'yes' ) ) {
						add_filter( 'woocommerce_variation_is_visible', array( $this, 'make_variation_visible_with_empty_price' ), PHP_INT_MAX, 4 );
						add_action( 'admin_head', array( $this, 'hide_variation_price_required_placeholder' ), PHP_INT_MAX );
					}
					if ( 'yes' === get_option( 'alg_wc_call_for_price_hide_variations_add_to_cart_button', 'yes' ) ) {
						add_action( 'wp_head', array( $this, 'hide_disabled_variation_add_to_cart_button' ) );
					}
				}
				// Per product meta box.
				if ( 'yes' === apply_filters( 'alg_call_for_price', 'no', 'per_product' ) ) {
					require_once 'admin/class-wc-call-for-price-settings-per-product.php';
				}
				// Force "Call for Price" for all products.
				if ( 'yes' === get_option( 'alg_call_for_price_make_all_empty', 'no' ) ) {
					$this->hook_price_filters( 'make_empty_price' );
				}
				// Out of stock products.
				if ( 'yes' === apply_filters( 'alg_call_for_price', 'no', 'out_of_stock' ) ) {
					$this->hook_price_filters( 'make_empty_price_out_of_stock' );
				}
				// "Call for Price" per product taxonomy.
				if ( 'yes' === get_option( 'alg_call_for_price_make_empty_price_per_taxonomy', 'no' ) ) {
					$this->hook_price_filters( 'make_empty_price_per_taxonomy' );
				}
				// Call for price text for the products with zero price.
				if ( 'yes' === get_option( 'alg_call_for_price_enable_cfp_for_zero_price', 'no' ) ) {
					add_filter( 'woocommerce_get_price_html', array( $this, 'alg_wc_cfp_handle_cfp_text' ), 10, 2 );
					// Hide ATC button on product page when price is set 0.
					add_filter( 'woocommerce_is_purchasable', array( $this, 'alg_call_for_price_to_remove_atc_button' ), 10, 2 );
				}
				// "Call for Price" by product price.
				if ( 'yes' === get_option( 'alg_call_for_price_make_empty_price_by_product_price', 'no' ) ) {
					$this->hook_price_filters( 'make_empty_price_by_product_price' );
				}
				// Variation hash (for forcing "Call for Price").
				add_filter( 'woocommerce_get_variation_prices_hash', array( $this, 'get_variation_prices_hash' ), PHP_INT_MAX, 3 );
				// Button label (archives).
				if ( 'yes' === get_option( 'alg_call_for_price_change_button_text', 'no' ) ) {
					add_filter( 'woocommerce_product_add_to_cart_text', array( $this, 'change_button_text' ), PHP_INT_MAX, 2 );
				}
				// Hide button.
				if ( 'yes' === get_option( 'alg_call_for_price_hide_button', 'no' ) ) {
					add_filter( 'woocommerce_loop_add_to_cart_link', array( $this, 'remove_button_on_archives' ), PHP_INT_MAX, 2 );
				}
				// Hide main variable price.
				$do_hide = get_option( 'alg_wc_call_for_price_hide_main_variable_price', 'no' );

				if ( 'yes' === $do_hide ) {
					add_filter( 'woocommerce_variable_price_html', array( $this, 'hide_main_variable_price_on_single_product_page' ), PHP_INT_MAX );
				} elseif ( 'yes_with_css' === $do_hide ) {
					add_filter( 'wp_head', array( $this, 'hide_main_variable_price_on_single_product_page_with_css' ) );
				}
				// Force variation price.
				if ( 'yes' === get_option( 'alg_wc_call_for_price_force_variation_price', 'no' ) ) {
					add_filter( 'woocommerce_show_variation_price', '__return_true', PHP_INT_MAX );
				}
			}
			add_action( 'admin_enqueue_scripts', array( $this, 'alg_call_for_price_setting_script' ) );
		}

		/**
		 * Hide_main_variable_price_on_single_product_page.
		 *
		 * @param string $price_html HTML of price.
		 * @version 3.2.3
		 * @since   3.2.3
		 */
		public function hide_main_variable_price_on_single_product_page( $price_html ) {
			return ( is_product() ? '' : $price_html );
		}

		/**
		 * Enqueue JS script for deactivate plugin.
		 *
		 * @version 3.6.0
		 * @since   3.6.0
		 */
		public static function alg_call_for_price_setting_script() {
			$plugin_url = plugins_url() . '/woocommerce-call-for-price';
			wp_register_script(
				'tyche',
				$plugin_url . '/includes/js/tyche.js',
				array( 'jquery' ),
				'3.6.0',
				true
			);
			wp_enqueue_script( 'tyche' );
		}

		/**
		 * Get the value of a call for price for zero price products setting.
		 *
		 * @return string The setting value of Call for Price for zero products.
		 * @since 3.3.0
		 */
		public function alg_wc_cfp_setting_for_zero_priced_product() {
			return get_option( 'alg_call_for_price_enable_cfp_for_zero_price', 'no' );
		}

		/**
		 * To show the CFP text for zero price products.
		 *
		 * @param string $price_html Price Html.
		 * @param object $_product Product.
		 * @version 3.3.0
		 * @since 3.3.0
		 */
		public function alg_wc_cfp_handle_cfp_text( $price_html, $_product ) {
			// Get product type, product id and current filter.
			$current_filter = current_filter();
			if ( $this->is_wc_below_3_0_0 ) {
				$_product_id  = $_product->id;
				$product_type = 'simple'; // default.
				switch ( $current_filter ) {
					case 'woocommerce_variable_empty_price_html':
					case 'woocommerce_variation_empty_price_html':
						$product_type = 'variable';
						break;
					case 'woocommerce_grouped_empty_price_html':
						$product_type = 'grouped';
						break;
					default: // 'woocommerce_empty_price_html'
						$product_type = ( $_product->is_type( 'external' ) ) ? 'external' : 'simple';
				}
			} else {
				$_product_id = ( $_product->is_type( 'variation' ) ) ? $_product->get_parent_id() : $_product->get_id();
				if ( $_product->is_type( 'variation' ) ) {
					$current_filter = 'woocommerce_variation_empty_price_html';
					$product_type   = 'variable';
				} else {
					$product_type = $_product->get_type();
				}
			}

			// Check if enabled for current product type.
			if ( 'per_product' !== $product_type && 'yes' !== get_option( 'alg_wc_call_for_price_' . $product_type . '_enabled', 'yes' ) ) {
				return $price;
			}

			// Get view.
			if ( 'per_product' === $product_type ) {
				$view = 'all_views';
			} else {
				$view = 'single'; // default.
				if ( 'woocommerce_variation_empty_price_html' === $current_filter ) {
					$view = 'variation';
				} elseif ( is_single( $_product_id ) ) {
					$view = 'single';
				} elseif ( is_single() ) {
					$view = 'related';
				} elseif ( is_front_page() ) {
					$view = 'home';
				} elseif ( is_page() ) {
					$view = 'page';
				} elseif ( is_archive() ) {
					$view = 'archive';
				}

				// Check if enabled for current view.
				if ( 'yes' !== get_option( 'alg_wc_call_for_price_' . $product_type . '_' . $view . '_enabled', 'yes' ) ) {
					return $price_html;
				}
			}
			if ( 'single' === $view || 'variation' === $view ) {
				// Label for product page.
				$label = get_option(
					'alg_wc_call_for_price_text_' . $product_type . '_' . $view,
					'<strong>' . __( 'Call for Price', 'woocommerce-call-for-price' ) . '</strong>'
				);
			} else {
				// Apply the label.
				$label = apply_filters(
					'alg_call_for_price',
					'<strong>' . __( 'Call for Price', 'woocommerce-call-for-price' ) . '</strong>',
					'value',
					$product_type,
					$view,
					array( 'product_id' => $_product_id )
				);
			}
			if ( '0' === $_product->get_price() ) {
				$is_cfp_for_zero_price_enabled = $this->alg_wc_cfp_setting_for_zero_priced_product();
				if ( 'no' === $is_cfp_for_zero_price_enabled ) {
					return $price_html;
				} else {
					$status = apply_filters( 'alg_call_for_price_for_zero_price_products', false, $_product->get_id() );
					if ( true === $status ) {
						return $price_html;
					} else {
						return do_shortcode( $label );
					}
				}
			}
			return $price_html;
		}

		/**
		 * Function to hide the ATC button on products page when price is set to 0.
		 *
		 * @param bool   $is_purchasable Product is purchasable or not.
		 * @param object $product Product object.
		 */
		public function alg_call_for_price_to_remove_atc_button( $is_purchasable, $product ) {
			$product_price = $product->get_price();
			if ( '0' === $product_price ) {
				return false;
			}
			return $is_purchasable;
		}

		/**
		 * Returns the stock setting for empty price products.
		 *
		 * @return string The stock setting value for empty priced products.
		 * @since 3.3.0
		 */
		public function alg_wc_cfp_stock_setting_for_empty_price_product() {
			return get_option( 'alg_call_for_price_enable_stock_for_empty_price', 'no' );
		}

		/**
		 * Appends a stock quantity/availability with empty price products.
		 *
		 * @param string $short_desc The Product short description.
		 * @return string The Revised short description with stock quantity.
		 *
		 * @since 3.3.0
		 */
		public function alg_cfp_empty_price_products_stock_management( $short_desc ) {

			if ( is_product() ) {

				global $post;
				$product       = wc_get_product( $post->ID );
				$product_id    = $product->get_id();
				$product_price = $product->get_price();
				$product_type  = $product->get_type();

				switch ( $product->get_type() ) {
					case 'simple':
						if ( ( ( '' === $product_price || ( ( '0' === $product_price ) && ( 'yes' === get_option( 'alg_call_for_price_enable_cfp_for_zero_price', 'no' ) ) ) ) && ( 'yes' === $this->alg_wc_cfp_stock_setting_for_empty_price_product() ) ) ) {
							$short_desc .= wc_get_stock_html( $product );
						}
						break;
				}
			}
			return $short_desc;
		}

		/**
		 * Hide_main_variable_price_on_single_product_page_with_css.
		 *
		 * @version 3.2.3
		 * @since   3.2.3
		 */
		public function hide_main_variable_price_on_single_product_page_with_css() {
			echo '<style>.single-product div.product-type-variable p.price { display: none !important; }</style>';
		}

		/**
		 * Remove_button_on_archives.
		 *
		 * @param string $link link.
		 * @param object $_product Object of Product.
		 * @version 3.2.2
		 * @since   3.2.2
		 */
		public function remove_button_on_archives( $link, $_product ) {
			$product_price      = $_product->get_price();
			$cfp_for_zero_price = get_option( 'alg_call_for_price_enable_cfp_for_zero_price', 'no' );
			if ( '' === $product_price || ( '0' === $product_price && 'yes' === $cfp_for_zero_price ) ) {
				return '';
			} else {
				return $link;
			}
		}

		/**
		 * Hook_price_filters.
		 *
		 * @param string $function_name Name of the function.
		 * @version 3.2.0
		 * @since   3.2.0
		 */
		public function hook_price_filters( $function_name ) {
			add_filter( ( $this->is_wc_below_3_0_0 ? 'woocommerce_get_price' : 'woocommerce_product_get_price' ), array( $this, $function_name ), PHP_INT_MAX, 2 );
			add_filter( 'woocommerce_variation_prices_price', array( $this, $function_name ), PHP_INT_MAX, 2 );
			if ( ! $this->is_wc_below_3_0_0 ) {
				add_filter( 'woocommerce_product_variation_get_price', array( $this, $function_name ), PHP_INT_MAX, 2 );
			}
		}

		/**
		 * Change_button_text.
		 *
		 * @param string $text Text of the Add to Cart button.
		 * @param object $_product Object of product.
		 * @version 3.2.0
		 * @since   3.2.0
		 */
		public function change_button_text( $text, $_product ) {
			return ( '' === $_product->get_price() ? get_option( 'alg_call_for_price_button_text', __( 'Call for Price', 'woocommerce-call-for-price' ) ) : $text );
		}

		/**
		 * Make_empty_price_by_product_price.
		 *
		 * @param float  $price    Price of product.
		 * @param object $_product Object of product.
		 * @version 3.2.1
		 * @since   3.2.1
		 */
		public function make_empty_price_by_product_price( $price, $_product ) {
			$min_price                     = get_option( 'alg_call_for_price_make_empty_price_min_price', 0 );
			$max_price                     = get_option( 'alg_call_for_price_make_empty_price_max_price', 0 );
			$is_cfp_for_zero_price_enabled = $this->alg_wc_cfp_setting_for_zero_priced_product();

			if ( 0 === $min_price && 0 === $max_price ) {
				return $price;
			}
			if ( 0 === $max_price ) {
				$max_price = PHP_INT_MAX;
			}
			if ( $price >= $min_price && $price <= $max_price ) {
				return $this->fetch_product_price_if_zero_or_empty( $price, $_product );
			} else { // phpcs:ignore
				if ( '0' === $price ) {
					if ( 'no' === $is_cfp_for_zero_price_enabled ) {
						return $price;
					} else {
						$status = apply_filters( 'alg_call_for_price_for_zero_price_products', false, $_product->get_id() );
						if ( true === $status ) {
							return $price;
						} else {
							return '';
						}
					}
				} else {
					return $price;
				}
			}
		}

		/**
		 * Make_empty_price_per_taxonomy.
		 *
		 * @param float  $price    Price of the product.
		 * @param object $_product Object of product.
		 * @version 3.2.0
		 * @since   3.2.0
		 */
		public function make_empty_price_per_taxonomy( $price, $_product ) {
			foreach ( array( 'product_cat', 'product_tag' ) as $taxonomy ) {
				$term_ids = get_option( 'alg_call_for_price_make_empty_price_' . $taxonomy, '' );
				if ( ! empty( $term_ids ) ) {
					$product_id    = ( $this->is_wc_below_3_0_0 ? $_product->id : ( $_product->is_type( 'variation' ) ? $_product->get_parent_id() : $_product->get_id() ) );
					$product_terms = get_the_terms( $product_id, $taxonomy );
					if ( ! empty( $product_terms ) ) {
						foreach ( $product_terms as $product_term ) {
							if ( in_array( (string) $product_term->term_id, $term_ids, true ) ) {
								return '';
							}
						}
					}
				}
			}
			return $price;
		}

		/**
		 * Make_empty_price_out_of_stock.
		 *
		 * @param float  $price Price of product.
		 * @param object $_product Object of product.
		 * @version 3.2.0
		 * @since   3.2.0
		 */
		public function make_empty_price_out_of_stock( $price, $_product ) {
			if ( ! $_product->is_in_stock() ) {
				return $this->fetch_product_price_if_zero_or_empty( $price, $_product );
			} else {
				return $price;
			}
		}

		/**
		 * Get_variation_prices_hash.
		 *
		 * @param array  $price_hash Array of price hash.
		 * @param object $_product Object of product.
		 * @param bool   $display Display price or not.
		 * @version 3.2.1
		 * @since   3.1.0
		 */
		public function get_variation_prices_hash( $price_hash, $_product, $display ) { // phpcs:ignore
			$price_hash['alg_call_for_price'] = array(
				'force_all'               => get_option( 'alg_call_for_price_make_all_empty', 'no' ),
				'force_out_of_stock'      => apply_filters( 'alg_call_for_price', 'no', 'out_of_stock' ),
				'force_per_taxonomy'      => get_option( 'alg_call_for_price_make_empty_price_per_taxonomy', 'no' ),
				'force_per_taxonomy_cats' => get_option( 'alg_call_for_price_make_empty_price_product_cat', '' ),
				'force_per_taxonomy_tags' => get_option( 'alg_call_for_price_make_empty_price_product_tag', '' ),
				'force_by_price'          => get_option( 'alg_call_for_price_make_empty_price_by_product_price', 'no' ),
				'force_by_price_min'      => get_option( 'alg_call_for_price_make_empty_price_min_price', 0 ),
				'force_by_price_max'      => get_option( 'alg_call_for_price_make_empty_price_max_price', 0 ),
			);
			return $price_hash;
		}

		/**
		 * Hide_variation_price_required_placeholder.
		 *
		 * @version 3.1.0
		 * @since   3.1.0
		 */
		public function hide_variation_price_required_placeholder() {
			echo '<style>
			div.variable_pricing input.wc_input_price::-webkit-input-placeholder { /* WebKit browsers */
				color: transparent;
			}
			div.variable_pricing input.wc_input_price:-moz-placeholder { /* Mozilla Firefox 4 to 18 */
				color: transparent;
			}
			div.variable_pricing input.wc_input_price::-moz-placeholder { /* Mozilla Firefox 19+ */
				color: transparent;
			}
			div.variable_pricing input.wc_input_price:-ms-input-placeholder { /* Internet Explorer 10+ */
				color: transparent;
			}
		</style>';
		}

		/**
		 * Returns the product price after being set to zero or empty.
		 *
		 * @param string $price The product price.
		 * @param object $_product  The Product object.
		 *
		 * @since 3.3.0
		 * @return The price as per different conditons.
		 */
		public function fetch_product_price_if_zero_or_empty( $price, $_product ) {
			$is_cfp_for_zero_price_enabled = $this->alg_wc_cfp_setting_for_zero_priced_product();
			if ( 'no' === $is_cfp_for_zero_price_enabled ) {
				if ( '0' === $price ) {
					return $price;
				} else {
					return '';
				}
			} else { // phpcs:ignore
				if ( '0' === $price ) {
					$status = apply_filters( 'alg_call_for_price_for_zero_price_products', false, $_product->get_id() );
					if ( true === $status ) {
						return $price;
					} else {
						return '';
					}
				} else {
					return '';
				}
			}
		}

		/**
		 * Make_empty_price.
		 *
		 * @param float  $price Price.
		 * @param object $_product Object of product.
		 * @version 3.0.3
		 * @since   3.0.3
		 */
		public function make_empty_price( $price, $_product ) {
			return $this->fetch_product_price_if_zero_or_empty( $price, $_product );
		}

		/**
		 * Make_variation_visible_with_empty_price.
		 *
		 * @param boolean $visible Should be visible or not.
		 * @param object  $_variation_id Id of the variation.
		 * @param float   $_id ID.
		 * @param object  $_product Object of product.
		 * @return  bool
		 * @version 3.0.0
		 * @since   3.0.0
		 */
		public function make_variation_visible_with_empty_price( $visible, $_variation_id, $_id, $_product ) {
			if ( '' === $_product->get_price() ) {
				$visible = true;
				// Published == enabled checkbox.
				if ( get_post_status( $_variation_id ) !== 'publish' ) {
					$visible = false;
				}
			}
			return $visible;
		}

		/**
		 * Hide_disabled_variation_add_to_cart_button.
		 *
		 * @version 3.0.0
		 * @since   3.0.0
		 */
		public function hide_disabled_variation_add_to_cart_button() {
			echo '<style>div.woocommerce-variation-add-to-cart-disabled { display: none ! important; }</style>';
		}

		/**
		 * Add_hooks.
		 *
		 * @version 3.1.0
		 */
		public function add_hooks() {

			add_filter( 'woocommerce_empty_price_html', array( $this, 'on_empty_price' ), PHP_INT_MAX, 2 );
			add_filter( 'woocommerce_variable_empty_price_html', array( $this, 'on_empty_price' ), PHP_INT_MAX, 2 );
			add_filter( 'woocommerce_grouped_empty_price_html', array( $this, 'on_empty_price' ), PHP_INT_MAX, 2 );
			add_filter( 'woocommerce_variation_empty_price_html', array( $this, 'on_empty_price' ), PHP_INT_MAX, 2 ); // Only in < WC3.

			add_filter( 'woocommerce_short_description', array( $this, 'alg_cfp_empty_price_products_stock_management' ) );

			require_once 'class-wc-call-for-price-compatibility.php';
		}

		/**
		 * Hide "sales" icon for empty price products.
		 *
		 * @param string $onsale_html On sale HTML.
		 * @param object $post Post Object.
		 * @param object $_product Product Object.
		 * @version 3.0.0
		 */
		public function hide_sales_flash( $onsale_html, $post, $_product ) {
			if ( 'yes' === get_option( 'alg_wc_call_for_price_hide_sale_sign', 'yes' ) && '' === $_product->get_price() ) {
				return '';
			}
			return $onsale_html; // No changes.
		}

		/**
		 * Is_enabled_per_product.
		 *
		 * @param int $_product_id If of Product.
		 * @version 3.1.1
		 * @since   3.1.1
		 */
		public function is_enabled_per_product( $_product_id ) {
			return ( apply_filters( 'alg_call_for_price', 'no', 'per_product' ) && 'yes' === get_post_meta( $_product_id, '_alg_wc_call_for_price_enabled', true ) );
		}

		/**
		 * On empty price filter - return the label.
		 *
		 * @param float  $price Price of product.
		 * @param object $_product Object of product.
		 * @version 3.2.0
		 */
		public function on_empty_price( $price, $_product ) {
			// Get product type, product id and current filter.
			$current_filter = current_filter();
			if ( $this->is_wc_below_3_0_0 ) {
				$_product_id  = $_product->id;
				$product_type = 'simple'; // default.
				switch ( $current_filter ) {
					case 'woocommerce_variable_empty_price_html':
					case 'woocommerce_variation_empty_price_html':
						$product_type = 'variable';
						break;
					case 'woocommerce_grouped_empty_price_html':
						$product_type = 'grouped';
						break;
					default: // 'woocommerce_empty_price_html'
						$product_type = ( $_product->is_type( 'external' ) ) ? 'external' : 'simple';
				}
			} else {
				$_product_id = ( $_product->is_type( 'variation' ) ) ? $_product->get_parent_id() : $_product->get_id();
				if ( $_product->is_type( 'variation' ) ) {
					$current_filter = 'woocommerce_variation_empty_price_html';
					$product_type   = 'variable';
				} else {
					$product_type = $_product->get_type();
				}
			}

			// Check if enabled for current product type.
			if ( 'per_product' !== $product_type && 'yes' !== get_option( 'alg_wc_call_for_price_' . $product_type . '_enabled', 'yes' ) ) {
				return $price;
			}

			// Get view.
			if ( 'per_product' === $product_type ) {
				$view = 'all_views';
			} else {
				$view = 'single'; // default.
				if ( 'woocommerce_variation_empty_price_html' === $current_filter ) {
					$view = 'variation';
				} elseif ( is_single( $_product_id ) ) {
					$view = 'single';
				} elseif ( is_single() ) {
					$view = 'related';
				} elseif ( is_front_page() ) {
					$view = 'home';
				} elseif ( is_page() ) {
					$view = 'page';
				} elseif ( is_archive() ) {
					$view = 'archive';
				}

				// Check if enabled for current view.
				if ( 'yes' !== get_option( 'alg_wc_call_for_price_' . $product_type . '_' . $view . '_enabled', 'yes' ) ) {
					return $price;
				}
			}
			if ( 'single' === $view || 'variation' === $view ) {
				// Label for product page.
				$label = get_option(
					'alg_wc_call_for_price_text_' . $product_type . '_' . $view,
					'<strong>' . __( 'Call for Price', 'woocommerce-call-for-price' ) . '</strong>'
				);
			} else {
				// Apply the label.
				$label = apply_filters(
					'alg_call_for_price',
					'<strong>' . __( 'Call for Price', 'woocommerce-call-for-price' ) . '</strong>',
					'value',
					$product_type,
					$view,
					array( 'product_id' => $_product_id )
				);
			}
			return do_shortcode( $label );
		}
	}

endif;

return new Alg_WC_Call_For_Price();
