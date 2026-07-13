<?php
/**
 * WooCommerce Call for Price Hooks Class
 *
 * This class is responsible for applying all the functionality for the call for price.
 *
 * @package WooCommerce-Call-For-Price-Lite
 * @since   4.2.0
 * @version 4.2.0
 */

namespace TycheSoftwares\CallForPrice\Lite;

defined( 'ABSPATH' ) || exit;

/**
 * Class Hooks.
 */
class Hooks {

	/**
	 * Whether WooCommerce version is below 3.0.0.
	 *
	 * @var bool
	 */
	public $is_wc_below_3_0_0 = '';

	/**
	 * Constructor.
	 *
	 * Sets up all hooks and filters based on the current plugin settings.
	 */
	public function __construct() {
		// Load pluggable functions for user role checks.
		require_once ABSPATH . WPINC . '/pluggable.php';

		$current_user = wp_get_current_user();
		$current_role = isset( $current_user->roles[0] ) && '' !== $current_user->roles[0]
			? $current_user->roles[0]
			: 'guest';

		$user_roles = Compatibility::get_setting( 'general', 'logged_in_only', array() );
		$match      = ! empty( $user_roles ) && in_array( $current_role, $user_roles, true );

		// Admin CSS for product listing page.
		add_action( 'admin_head', array( $this, 'css_on_all_products_page' ) );

		// Only proceed if plugin is enabled and user role matches (if restricted).
		$plugin_enabled = Compatibility::get_setting( 'general', 'enabled', true );
		if ( $plugin_enabled && ( empty( $user_roles ) || $match ) ) {

			$this->is_wc_below_3_0_0 = version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' );

			// Empty price hooks.
			add_action( 'init', array( $this, 'add_hooks' ), PHP_INT_MAX );

			// Sale flash.
			add_filter( 'woocommerce_sale_flash', array( $this, 'hide_sales_flash' ), PHP_INT_MAX, 3 );

			// Variable product specific hooks.
			if ( Compatibility::get_setting( 'variable', 'enabled', true ) ) {
				if ( Compatibility::get_setting( 'variable', array( 'views', 'variation', 'enabled' ), true ) ) {
					add_filter( 'woocommerce_variation_is_visible', array( $this, 'make_variation_visible_with_empty_price' ), PHP_INT_MAX, 4 );
					add_action( 'admin_head', array( $this, 'hide_variation_price_required_placeholder' ), PHP_INT_MAX );
				}
				if ( Compatibility::get_setting( 'general', 'hide_variations_atc_button', true ) ) {
					add_action( 'wp_head', array( $this, 'hide_disabled_variation_add_to_cart_button' ) );
				}
			}

			// Per product meta box is handled by the metabox class.

			// Force "Call for Price" for all products.
			if ( Compatibility::get_setting( 'general', array( 'force', 'all_products' ), false ) ) {
				$this->hook_price_filters( 'make_empty_price' );
			}

			// Out of stock products.
			if ( Compatibility::get_setting( 'general', array( 'force', 'out_of_stock' ), false ) ) {
				$this->hook_price_filters( 'make_empty_price_out_of_stock' );
			}

			// By product taxonomy.
			if ( Compatibility::get_setting( 'general', array( 'force', 'by_taxonomy', 'enabled' ), false ) ) {
				$this->hook_price_filters( 'make_empty_price_per_taxonomy' );
			}

			// By product price range.
			if ( Compatibility::get_setting( 'general', array( 'force', 'by_price', 'enabled' ), false ) ) {
				$this->hook_price_filters( 'make_empty_price_by_product_price' );
			}

			// Variation prices hash for cache busting.
			add_filter( 'woocommerce_get_variation_prices_hash', array( $this, 'get_variation_prices_hash' ), PHP_INT_MAX, 3 );

			// Button label on archives.
			if ( Compatibility::get_setting( 'general', 'change_button_text', false ) ) {
				add_filter( 'woocommerce_product_add_to_cart_text', array( $this, 'change_button_text' ), PHP_INT_MAX, 2 );
				if ( '' !== Compatibility::get_setting( 'general', 'button_url', '' ) ) {
					add_filter( 'woocommerce_product_add_to_cart_url', array( $this, 'redirect_button_url' ), PHP_INT_MAX, 2 );
				}
			}

			// Hide add-to-cart button on archives.
			if ( Compatibility::get_setting( 'general', 'hide_button', false ) ) {
				add_filter( 'woocommerce_loop_add_to_cart_link', array( $this, 'remove_button_on_archives' ), PHP_INT_MAX, 2 );
			}

			// Hide main variable price.
			$hide_type = Compatibility::get_setting( 'general', 'hide_main_variable_price', 'no' );
			if ( 'yes' === $hide_type ) {
				add_filter( 'woocommerce_variable_price_html', array( $this, 'hide_main_variable_price_on_single_product_page' ), PHP_INT_MAX );
			} elseif ( 'yes_with_css' === $hide_type ) {
				add_action( 'wp_head', array( $this, 'hide_main_variable_price_on_single_product_page_with_css' ) );
			}

			// Force variation price always shown.
			if ( Compatibility::get_setting( 'general', 'force_variation_price', false ) ) {
				add_filter( 'woocommerce_show_variation_price', '__return_true', PHP_INT_MAX );
			}

			// Zero-price products: show CFP text and remove ATC button.
			if ( Compatibility::get_setting( 'general', array( 'force', 'for_zero_price' ), false ) ) {
				add_filter( 'woocommerce_get_price_html', array( $this, 'alg_wc_cfp_handle_cfp_text' ), 10, 2 );
				add_filter( 'woocommerce_is_purchasable', array( $this, 'alg_call_for_price_to_remove_atc_button' ), 10, 2 );
			}

			// For all products text (show CFP label even with price).
			if ( Compatibility::get_setting( 'general', array( 'force', 'for_all_products_text' ), false ) ) {
				add_filter( 'woocommerce_get_price_html', array( $this, 'alg_wc_cfp_handle_cfp_text' ), 10, 2 );
				add_filter( 'woocommerce_is_purchasable', array( $this, 'alg_cfp_remove_atc_button_for_priced_products' ), 10, 2 );
			}

			// Out-of-stock products: show CFP text if enabled.
			if ( Compatibility::get_setting( 'general', array( 'force', 'out_of_stock' ), false ) ) {
				add_filter( 'woocommerce_get_price_html', array( $this, 'alg_wc_cfp_handle_cfp_text' ), 10, 2 );
			}
		}

		// Variation per-product overrides.
		if ( Compatibility::get_setting( 'general', array( 'force', 'for_zero_price_variation' ), false ) ) {
			add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'alg_call_for_price_add_variable_field' ), 10, 3 );
			add_action( 'woocommerce_save_product_variation', array( $this, 'variable_save_fields' ), 10, 2 );
			add_filter( 'woocommerce_available_variation', array( $this, 'alg_call_for_price_save_content_on_frontent' ), 10, 1 );
			add_action( 'woocommerce_variable_add_to_cart', array( $this, 'alg_call_for_price_display_frontent' ), 10 );
		}

		// Clear product count cache on product changes.
		add_action( 'save_post_product', array( $this, 'clear_cfp_product_count_cache' ) );
		add_action( 'wp_trash_post', array( $this, 'clear_cfp_product_count_cache' ) );
		add_action( 'delete_post', array( $this, 'clear_cfp_product_count_cache' ) );
		add_action( 'updated_post_meta', array( $this, 'clear_cfp_product_count_cache' ), 10, 4 );
		add_action( 'added_post_meta', array( $this, 'clear_cfp_product_count_cache' ), 10, 4 );

		// Admin script for deactivation survey.
		add_action( 'admin_enqueue_scripts', array( $this, 'alg_call_for_price_setting_script' ) );
	}

	/**
	 * Clear the cached product count when a product's CFP settings are updated.
	 *
	 * @return void
	 */
	public static function clear_cfp_product_count_cache() {
		delete_transient( 'cfp_pro_enabled_product_count' );
	}

	/**
	 * Enqueue CSS on the product listing page (admin).
	 *
	 * @return void
	 */
	public function css_on_all_products_page() {
		$screen = get_current_screen();
		if ( ! isset( $screen->id ) || 'edit-product' !== $screen->id ) {
			return;
		}
		?>
		<style>
			.column-price .alg-cfp-btn {
				white-space: normal !important;
				padding: 4px 8px;
				line-height: 2;
			}
		</style>
		<?php
	}

	/**
	 * Enqueue JavaScript for deactivation survey.
	 *
	 * @return void
	 */
	public static function alg_call_for_price_setting_script() {
		$plugin_url = CFP_LITE_PLUGIN_URL;
		wp_register_script(
			'tyche',
			$plugin_url . '/assets/js/tyche.js',
			array( 'jquery' ),
			CFP_LITE_VERSION,
			true
		);
		wp_enqueue_script( 'tyche' );
	}

	/**
	 * Retrieve setting for zero-priced products.
	 *
	 * @return bool
	 */
	public function alg_wc_cfp_setting_for_zero_priced_product() {
		return Compatibility::get_setting( 'general', array( 'force', 'for_zero_price' ), false );
	}

	/**
	 * Retrieve setting for showing stock status on empty price products.
	 *
	 * @return bool
	 */
	public function alg_wc_cfp_stock_setting_for_empty_price_product() {
		return Compatibility::get_setting( 'general', 'show_stock_for_empty_price', false );
	}

	/**
	 * Determine if per-product override is enabled for a given product.
	 *
	 * @param int $_product_id Product ID.
	 * @return bool
	 */
	public function is_enabled_per_product( $_product_id ) {
		$meta = Compatibility::get_product_meta( $_product_id );
		return ( true === Compatibility::get_setting( 'general', 'per_product_enabled', false )
			&& 'yes' === ( $meta['enabled'] ?? 'no' ) );
	}

	/**
	 * Fetch product price after applying zero/empty price rules.
	 *
	 * @param mixed  $price     Product price.
	 * @param object $_product  Product object.
	 * @return mixed
	 */
	public function fetch_product_price_if_zero_or_empty( $price, $_product ) {
		$is_zero_price_enabled = $this->alg_wc_cfp_setting_for_zero_priced_product();
		if ( false === $is_zero_price_enabled ) {
			if ( 0.0 === (float) $price ) {
				return $price;
			}
			return '';
		} else {
			if ( 0.0 === (float) $price ) {
				$status = apply_filters( 'alg_call_for_price_for_zero_price_products', false, $_product->get_id() );
				if ( true === $status ) {
					return $price;
				}
				return '';
			}
			return '';
		}
	}

	/**
	 * Hook price filters for a given method.
	 *
	 * @param string $function_name Method name to hook.
	 * @return void
	 */
	public function hook_price_filters( $function_name ) {
		$filter = $this->is_wc_below_3_0_0 ? 'woocommerce_get_price' : 'woocommerce_product_get_price';
		add_filter( $filter, array( $this, $function_name ), PHP_INT_MAX, 2 );
		add_filter( 'woocommerce_variation_prices_price', array( $this, $function_name ), PHP_INT_MAX, 2 );
		if ( ! $this->is_wc_below_3_0_0 ) {
			add_filter( 'woocommerce_product_variation_get_price', array( $this, $function_name ), PHP_INT_MAX, 2 );
		}
	}

	public function make_empty_price( $price, $_product ) {
		$exclude_products   = Compatibility::get_setting( 'general', array( 'exclude', 'products' ), array() );
		$exclude_categories = Compatibility::get_setting( 'general', array( 'exclude', 'categories' ), array() );

		if ( $_product instanceof \WC_Product && 'variation' === $_product->get_type() ) {
			$product_id = $_product->get_parent_id();
		} else {
			$product_id = $_product->get_id();
		}

		if ( in_array( $product_id, $exclude_products, true ) ) {
			return $price;
		}

		$categories = get_the_terms( $product_id, 'product_cat' );
		if ( $categories && ! is_wp_error( $categories ) ) {
			foreach ( $categories as $cat ) {
				if ( in_array( $cat->term_id, $exclude_categories, true ) ) {
					return $price;
				}
			}
		}

		return $this->fetch_product_price_if_zero_or_empty( $price, $_product );
	}

	/**
	 * Force empty price for out-of-stock products.
	 *
	 * @param mixed  $price     Product price.
	 * @param object $_product  Product object.
	 * @return mixed
	 */
	public function make_empty_price_out_of_stock( $price, $_product ) {
		if ( ! $_product->is_in_stock() ) {
			return $this->fetch_product_price_if_zero_or_empty( $price, $_product );
		}
		return $price;
	}

	/**
	 * Force empty price based on product taxonomy.
	 *
	 * @param mixed  $price     Product price.
	 * @param object $_product  Product object.
	 * @return mixed
	 */
	public function make_empty_price_per_taxonomy( $price, $_product ) {
		$exclude_products = Compatibility::get_setting( 'general', array( 'exclude', 'products' ), array() );
		if ( in_array( $_product->get_id(), $exclude_products, true ) ) {
			return $price;
		}

		$taxonomies = array( 'product_cat', 'product_tag' );
		foreach ( $taxonomies as $taxonomy ) {
			$term_ids = Compatibility::get_setting( 'general', array( 'force', 'by_taxonomy', $taxonomy ), array() );
			if ( empty( $term_ids ) ) {
				continue;
			}
			$product_id = ( $this->is_wc_below_3_0_0 )
				? $_product->id
				: ( $_product->is_type( 'variation' ) ? $_product->get_parent_id() : $_product->get_id() );
			$product_terms = get_the_terms( $product_id, $taxonomy );
			$exclude_cats  = Compatibility::get_setting( 'general', array( 'exclude', 'categories' ), array() );

			if ( ! empty( $product_terms ) ) {
				foreach ( $product_terms as $product_term ) {
					if ( in_array( $product_term->term_id, $exclude_cats ) ) {
						return $price;
					}
					if ( in_array( $product_term->term_id, $term_ids ) ) {
						return '';
					}
				}
			}
		}
		return $price;
	}

	/**
	 * Force empty price based on product price range.
	 *
	 * @param mixed  $price     Product price.
	 * @param object $_product  Product object.
	 * @return mixed
	 */
	public function make_empty_price_by_product_price( $price, $_product ) {
		$min_price = Compatibility::get_setting( 'general', array( 'force', 'by_price', 'min' ), 0 );
		$max_price = Compatibility::get_setting( 'general', array( 'force', 'by_price', 'max' ), 0 );

		if ( '0' === $min_price && '0' === $max_price ) {
			return $price;
		}
		if ( '0' === $max_price ) {
			$max_price = PHP_INT_MAX;
		}

		if ( $price >= $min_price && $price <= $max_price ) {
			return $this->fetch_product_price_if_zero_or_empty( $price, $_product );
		}

		if ( 0.0 === (float) $price ) {
			$is_zero_price_enabled = $this->alg_wc_cfp_setting_for_zero_priced_product();
			if ( ! $is_zero_price_enabled ) {
				return $price;
			}
			$status = apply_filters( 'alg_call_for_price_for_zero_price_products', false, $_product->get_id() );
			if ( true === $status ) {
				return $price;
			}
			return '';
		}
		return $price;
	}

	/**
	 * Make variations visible even if they have an empty price.
	 *
	 * @param bool   $visible        Current visibility.
	 * @param int    $_variation_id  Variation ID.
	 * @param int    $_id            Product ID.
	 * @param object $_product       Product object.
	 * @return bool
	 */
	public function make_variation_visible_with_empty_price( $visible, $_variation_id, $_id, $_product ) {
		if ( '' === $_product->get_price() ) {
			$visible = true;
			if ( 'publish' !== get_post_status( $_variation_id ) ) {
				$visible = false;
			}
		}
		return $visible;
	}

	/**
	 * Hide the "price required" placeholder in variation admin.
	 *
	 * @return void
	 */
	public function hide_variation_price_required_placeholder() {
		?>
		<style>
			div.variable_pricing input.wc_input_price::-webkit-input-placeholder { color: transparent; }
			div.variable_pricing input.wc_input_price:-moz-placeholder { color: transparent; }
			div.variable_pricing input.wc_input_price::-moz-placeholder { color: transparent; }
			div.variable_pricing input.wc_input_price:-ms-input-placeholder { color: transparent; }
		</style>
		<?php
	}

	/**
	 * Hide the "Add to Cart" button for disabled variations.
	 *
	 * @return void
	 */
	public function hide_disabled_variation_add_to_cart_button() {
		?>
		<style>div.woocommerce-variation-add-to-cart-disabled { display: none !important; }</style>
		<?php
	}

	/**
	 * Add custom fields to variable product variations in admin.
	 *
	 * @param int    $loop            Loop index.
	 * @param array  $variation_data  Variation data.
	 * @param object $variation       Variation post object.
	 * @return void
	 */
	public function alg_call_for_price_add_variable_field( $loop, $variation_data, $variation ) {
		$enabled = get_post_meta( $variation->ID, 'variation_check', true );
		$text    = get_post_meta( $variation->ID, 'variation_text', true );

		woocommerce_wp_checkbox(
			array(
				'id'    => 'cfp_enable_for_variation[' . $loop . ']',
				'label' => '<span style="margin-left:10px">' . esc_html__( 'Enable Call For Price', 'woocommerce-call-for-price' ) . '</span>',
				'value' => $enabled,
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'            => 'cfp_custom_text_for_variation[' . $loop . ']',
				'label'         => esc_html__( 'Call For Price Text', 'woocommerce-call-for-price' ),
				'wrapper_class' => 'form-row',
				'placeholder'   => '<strong> Call for Price</strong>',
				'description'   => esc_html__( 'This will replace the variation price when call for price is enabled for this variation.', 'woocommerce-call-for-price' ),
				'desc_tip'      => true,
				'value'         => $text,
			)
		);
	}

	/**
	 * Save variation custom fields.
	 *
	 * @param int $variation_id Variation ID.
	 * @param int $loop         Loop index.
	 * @return void
	 */
	public function variable_save_fields( $variation_id, $loop ) {
		$checkbox = ! empty( $_POST['cfp_enable_for_variation'][ $loop ] ) ? 'yes' : 'no'; // phpcs:ignore WordPress.Security.NonceVerification
		$text     = isset( $_POST['cfp_custom_text_for_variation'][ $loop ] ) ? sanitize_text_field( wp_unslash( $_POST['cfp_custom_text_for_variation'][ $loop ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		update_post_meta( $variation_id, 'variation_text', $text );
		update_post_meta( $variation_id, 'variation_check', $checkbox );
	}

	/**
	 * Add custom data to the variation object for frontend use.
	 *
	 * @param array $variation Variation data.
	 * @return array
	 */
	public function alg_call_for_price_save_content_on_frontent( $variation ) {
		$variation['cfp_text_content']     = get_post_meta( $variation['variation_id'], 'variation_text', true );
		$variation['cfp_enable_variation'] = get_post_meta( $variation['variation_id'], 'variation_check' );
		return $variation;
	}

	/**
	 * Output inline JavaScript for variation price replacement.
	 *
	 * @return void
	 */
	public function alg_call_for_price_display_frontent() {
		wp_enqueue_script( 'wc-add-to-cart-variation' );
		$default_text = esc_js( __( 'Call for Price', 'woocommerce-call-for-price' ) );
		$js           = "
			(function($) {
				$(document).on('found_variation','form.cart',function(event,variation){
					setTimeout(function() {
						var cfpText = variation.cfp_text_content || '$default_text';
						if ( 'yes' === variation.cfp_enable_variation[0] ) {
							$('.woocommerce-variation-price').html('<span class=\"cfp-text\">' + cfpText + '</span>');
							$('.single_add_to_cart_button').hide();
							$('.quantity').hide();
						} else {
							$('.woocommerce-variation-price').html(variation.price_html);
							$('.single_add_to_cart_button').show();
							$('.quantity').show();
						}
					}, 1000);
				});
			})(jQuery);
		";
		wp_add_inline_script( 'wc-add-to-cart-variation', $js );
	}

	/**
	 * Hide the sale flash for empty price products.
	 *
	 * @param string $onsale_html  Sale HTML.
	 * @param object $post         Post object.
	 * @param object $_product     Product object.
	 * @return string
	 */
	public function hide_sales_flash( $onsale_html, $post, $_product ) {
		if ( true === Compatibility::get_setting( 'general', 'hide_sale_tag', true ) && '' === $_product->get_price() ) {
			return '';
		}
		return $onsale_html;
	}

	/**
	 * Change the "Add to Cart" button text on archives.
	 *
	 * @param string $text      Default text.
	 * @param object $_product  Product object.
	 * @return string
	 */
	public function change_button_text( $text, $_product ) {
		$price       = $_product->get_price();
		$zero_enabled = Compatibility::get_setting( 'general', array( 'force', 'for_zero_price' ), false );
		$force_text  = Compatibility::get_setting( 'general', array( 'force', 'for_all_products_text' ), false );

		if ( '' === $price || ( 0.0 === (float) $price && $zero_enabled ) || $force_text ) {
			return apply_filters( 'alg_call_for_price', __( 'Call for Price', 'woocommerce-call-for-price' ), 'button_text' );
		}
		return $text;
	}

	/**
	 * Redirect the "Add to Cart" URL on archives.
	 *
	 * @param string $url       Default URL.
	 * @param object $_product  Product object.
	 * @return string
	 */
	public function redirect_button_url( $url, $_product ) {
		$price       = $_product->get_price();
		$zero_enabled = Compatibility::get_setting( 'general', array( 'force', 'for_zero_price' ), false );
		$force_text  = Compatibility::get_setting( 'general', array( 'force', 'for_all_products_text' ), false );

		if ( '' === $price || ( 0.0 === (float) $price && $zero_enabled ) || $force_text ) {
			return apply_filters( 'alg_call_for_price', __( 'Call for Price', 'woocommerce-call-for-price' ), 'button_url' );
		}
		return $url;
	}

	/**
	 * Remove the "Add to Cart" button on archives.
	 *
	 * @param string $link      Button HTML.
	 * @param object $_product  Product object.
	 * @return string
	 */
	public function remove_button_on_archives( $link, $_product ) {
		$price       = $_product->get_price();
		$zero_enabled = Compatibility::get_setting( 'general', array( 'force', 'for_zero_price' ), false );
		$force_text  = Compatibility::get_setting( 'general', array( 'force', 'for_all_products_text' ), false );

		if ( '' === $price || ( 0.0 === (float) $price && $zero_enabled ) || $force_text ) {
			return '';
		}
		return $link;
	}

	/**
	 * Hide the variable price HTML on single product page.
	 *
	 * @param string $price_html Price HTML.
	 * @return string
	 */
	public function hide_main_variable_price_on_single_product_page( $price_html ) {
		return is_product() ? '' : $price_html;
	}

	/**
	 * Hide the variable price using CSS on single product page.
	 *
	 * @return void
	 */
	public function hide_main_variable_price_on_single_product_page_with_css() {
		?>
		<style>.single-product div.product-type-variable p.price { display: none !important; }</style>
		<?php
	}

	/**
	 * Add our settings to the variation prices hash.
	 *
	 * @param array  $price_hash Current hash array.
	 * @param object $_product   Product object.
	 * @param bool   $display    Whether to display.
	 * @return array
	 */
	public function get_variation_prices_hash( $price_hash, $_product, $display ) {
		$exclude_categories = Compatibility::get_setting( 'general', array( 'exclude', 'categories' ), array() );
		$exclude_products   = Compatibility::get_setting( 'general', array( 'exclude', 'products' ), array() );

		$categories = get_the_terms( $_product->get_id(), 'product_cat' );
		if ( $categories && is_array( $categories ) ) {
			foreach ( $categories as $cat ) {
				if ( in_array( $cat->term_id, $exclude_categories, true ) ) {
					return $price_hash;
				}
			}
		}
		if ( in_array( $_product->get_id(), $exclude_products, true ) ) {
			return $price_hash;
		}

		$price_hash['alg_call_for_price'] = array(
			'force_all'               => Compatibility::get_setting( 'general', array( 'force', 'all_products' ), false ),
			'force_out_of_stock'      => Compatibility::get_setting( 'general', array( 'force', 'out_of_stock' ), false ),
			'force_per_taxonomy'      => Compatibility::get_setting( 'general', array( 'force', 'by_taxonomy', 'enabled' ), false ),
			'force_per_taxonomy_cats' => Compatibility::get_setting( 'general', array( 'force', 'by_taxonomy', 'product_cat' ), array() ),
			'force_per_taxonomy_tags' => Compatibility::get_setting( 'general', array( 'force', 'by_taxonomy', 'product_tag' ), array() ),
			'force_by_price'          => Compatibility::get_setting( 'general', array( 'force', 'by_price', 'enabled' ), false ),
			'force_by_price_min'      => Compatibility::get_setting( 'general', array( 'force', 'by_price', 'min' ), 0 ),
			'force_by_price_max'      => Compatibility::get_setting( 'general', array( 'force', 'by_price', 'max' ), 0 ),
		);
		return $price_hash;
	}

	/**
	 * Replace empty price HTML with the Call for Price label.
	 *
	 * @param mixed  $price_html Current price HTML.
	 * @param object $_product   Product object.
	 * @return string
	 */
	public function on_empty_price( $price_html, $_product ) {
		$current_filter = current_filter();

		if ( $this->is_wc_below_3_0_0 ) {
			$_product_id  = $_product->id;
			$product_type = 'simple';
			switch ( $current_filter ) {
				case 'woocommerce_variable_empty_price_html':
				case 'woocommerce_variation_empty_price_html':
					$product_type = 'variable';
					break;
				case 'woocommerce_grouped_empty_price_html':
					$product_type = 'grouped';
					break;
				default:
					$product_type = $_product->is_type( 'external' ) ? 'external' : 'simple';
			}
		} else {
			$_product_id = $_product->is_type( 'variation' )
				? $_product->get_parent_id()
				: $_product->get_id();
			if ( $_product->is_type( 'variation' ) ) {
				$current_filter = 'woocommerce_variation_empty_price_html';
				$product_type   = 'variable';
			} else {
				$product_type = $_product->get_type();
			}
		}

		if ( $this->is_enabled_per_product( $_product_id ) ) {
			$product_type = 'per_product';
		}

		if ( 'per_product' !== $product_type && true !== Compatibility::get_setting( $product_type, 'enabled', true ) ) {
			return $price_html;
		}

		$view = 'single';
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

		if ( 'per_product' !== $product_type && true !== Compatibility::get_setting( $product_type, array( 'views', $view, 'enabled' ), true ) ) {
			return $price_html;
		}

		$label = apply_filters(
			'alg_call_for_price',
			'<strong>' . esc_html__( 'Call for Price', 'woocommerce-call-for-price' ) . '</strong>',
			'value',
			$product_type,
			$view,
			array( 'product_id' => $_product_id )
		);

		// For per-product, we might have additional call type handling, but the filter already handles it.
		return wp_kses_post( do_shortcode( $label ) );
	}

	/**
	 * Show CFP text for products with zero price, all products (force), or out-of-stock.
	 *
	 * @param mixed  $price_html Current price HTML.
	 * @param object $_product   Product object.
	 * @return string
	 */
	public function alg_wc_cfp_handle_cfp_text( $price_html, $_product ) {
		$current_filter = current_filter();

		if ( $this->is_wc_below_3_0_0 ) {
			$_product_id  = $_product->id;
			$product_type = 'simple';
			switch ( $current_filter ) {
				case 'woocommerce_variable_empty_price_html':
				case 'woocommerce_variation_empty_price_html':
					$product_type = 'variable';
					break;
				case 'woocommerce_grouped_empty_price_html':
					$product_type = 'grouped';
					break;
				default:
					$product_type = $_product->is_type( 'external' ) ? 'external' : 'simple';
			}
		} else {
			$_product_id = $_product->is_type( 'variation' )
				? $_product->get_parent_id()
				: $_product->get_id();
			if ( $_product->is_type( 'variation' ) ) {
				$current_filter = 'woocommerce_variation_empty_price_html';
				$product_type   = 'variable';
			} else {
				$product_type = $_product->get_type();
			}
		}

		if ( $this->is_enabled_per_product( $_product_id ) ) {
			$product_type = 'per_product';
		}

		if ( 'per_product' !== $product_type && true !== Compatibility::get_setting( $product_type, 'enabled', true ) ) {
			return $price_html;
		}

		$view = 'single';
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

		if ( 'per_product' !== $product_type && true !== Compatibility::get_setting( $product_type, array( 'views', $view, 'enabled' ), true ) ) {
			return $price_html;
		}

		$label = apply_filters(
			'alg_call_for_price',
			'<strong>' . esc_html__( 'Call for Price', 'woocommerce-call-for-price' ) . '</strong>',
			'value',
			$product_type,
			$view,
			array( 'product_id' => $_product_id )
		);

		$is_zero_price    = ( 0.0 === (float) $_product->get_price() );
		$has_price        = ( '' !== $_product->get_price() && 0.0 !== (float) $_product->get_price() );
		$is_out_of_stock  = ! $_product->is_in_stock();

		$show_cfp = false;

		// Zero price products.
		if ( $is_zero_price && $this->alg_wc_cfp_setting_for_zero_priced_product() ) {
			$status = apply_filters( 'alg_call_for_price_for_zero_price_products', false, $_product_id );
			if ( true !== $status ) {
				$show_cfp = true;
			}
		}

		// All products (force text).
		if ( $has_price && Compatibility::get_setting( 'general', array( 'force', 'for_all_products_text' ), false ) ) {
			$show_cfp = true;
		}

		// Out-of-stock products (force text).
		if ( $is_out_of_stock && Compatibility::get_setting( 'general', array( 'force', 'out_of_stock' ), false ) ) {
			$show_cfp = true;
		}

		if ( $show_cfp ) {
			// Build the button if needed (call type handling).
			$button_html = $label; // fallback to plain text.
			// For per-product, we may have custom call type; but the filter already handles it.
			// We'll output the label as is.
			return do_shortcode( $label );
		}

		return $price_html;
	}

	/**
	 * Remove the Add to Cart button for zero-price products.
	 *
	 * @param bool   $is_purchasable Current purchasable status.
	 * @param object $product        Product object.
	 * @return bool
	 */
	public function alg_call_for_price_to_remove_atc_button( $is_purchasable, $product ) {
		if ( 0.0 === (float) $product->get_price() ) {
			return false;
		}
		return $is_purchasable;
	}

	/**
	 * Remove the Add to Cart button for all products (if force text enabled).
	 *
	 * @param bool   $is_purchasable Current purchasable status.
	 * @param object $_product       Product object.
	 * @return bool
	 */
	public function alg_cfp_remove_atc_button_for_priced_products( $is_purchasable, $_product ) {
		if ( 'product_pack' === $_product->get_type() ) {
			return $is_purchasable;
		}
		if ( Compatibility::get_setting( 'general', array( 'force', 'for_all_products_text' ), false )
			&& false === Compatibility::get_setting( 'general', 'per_product_enabled', false ) ) {
			return false;
		}
		$product_id = $_product->get_id();
		$meta       = Compatibility::get_product_meta( $product_id );

		if ( 'yes' === ( $meta['enabled'] ?? 'no' ) ) {
			$show_atc = apply_filters( 'alg_wc_cfp_show_atc_button', false );
			if ( $show_atc ) {
				$author     = get_user_by( 'id', $_product->post->post_author );
				$author_id  = $author->ID;
				$user_meta  = get_userdata( $author_id );
				$user_roles = $user_meta->roles;
				if ( in_array( 'seller', $user_roles, true ) ) {
					return $is_purchasable;
				}
			}
			return false;
		}
		return $is_purchasable;
	}

	/**
	 * Add stock status to short description for empty price products.
	 *
	 * @param string $short_desc Short description.
	 * @return string
	 */
	public function alg_cfp_empty_price_products_stock_management( $short_desc ) {
		if ( is_product() ) {
			global $post;
			$product = wc_get_product( $post->ID );
			if ( ! $product ) {
				return $short_desc;
			}
			$price = $product->get_price();
			if ( ( '' === $price || ( 0.0 === (float) $price && $this->alg_wc_cfp_setting_for_zero_priced_product() ) )
				&& $this->alg_wc_cfp_stock_setting_for_empty_price_product() ) {
				$short_desc .= wc_get_stock_html( $product );
			}
		}
		return $short_desc;
	}

	/**
	 * Add hooks for empty price and stock management.
	 *
	 * @return void
	 */
	public function add_hooks() {
		add_filter( 'woocommerce_empty_price_html', array( $this, 'on_empty_price' ), PHP_INT_MAX, 2 );
		add_filter( 'woocommerce_variable_empty_price_html', array( $this, 'on_empty_price' ), PHP_INT_MAX, 2 );
		add_filter( 'woocommerce_grouped_empty_price_html', array( $this, 'on_empty_price' ), PHP_INT_MAX, 2 );
		add_filter( 'woocommerce_variation_empty_price_html', array( $this, 'on_empty_price' ), PHP_INT_MAX, 2 );
		add_filter( 'woocommerce_short_description', array( $this, 'alg_cfp_empty_price_products_stock_management' ) );
	}
}