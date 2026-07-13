<?php
/**
 * Product Metabox
 *
 * Outputs the #cfp-pro-product-root div on the product edit screen and
 * enqueues the compiled product JS bundle. The React component mounts into
 * this div and communicates with GET|POST /cfp-pro/v1/products/{id}.
 *
 * @package WooCommerce-Call-For-Price-Lite
 */

namespace TycheSoftwares\CallForPrice\Lite;

defined( 'ABSPATH' ) || exit;

/**
 * Class Product_Metabox
 */
class Product_Metabox {

	/**
	 * Constructor.
	 *
	 * Registers metabox and enqueue hooks.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register_metabox' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register the metabox only when per-product settings are enabled.
	 *
	 * @return void
	 */
	public function register_metabox(): void {
		$settings        = get_option( 'cfp_pro_settings', array() );
		$per_product_on  = $settings['general']['per_product_enabled'] ?? false;

		// Handle both boolean (new format) and 'yes'/'no' string (legacy).
		if ( ! $per_product_on || 'no' === $per_product_on ) {
			return;
		}

		add_meta_box(
			'cfp-pro-product-metabox',
			__( 'Call for Price', 'woocommerce-call-for-price' ),
			array( $this, 'render_metabox' ),
			'product',
			'normal',
			'high'
		);
	}

	/**
	 * Render the React mount point.
	 *
	 * The product ID is passed via a data attribute so the React app can
	 * initialise its REST fetch without scanning the DOM.
	 *
	 * @param \WP_Post $post Current product post.
	 * @return void
	 */
	public function render_metabox( \WP_Post $post ): void {
		printf(
			'<div id="cfp-pro-product-root" data-product-id="%d"></div>',
			esc_attr( $post->ID )
		);
	}

	/**
	 * Enqueue the compiled product metabox bundle.
	 * Only loads on the product edit screen.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		global $post_type;

		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		if ( 'product' !== $post_type ) {
			return;
		}

		$asset_file = CFP_LITE_PLUGIN_PATH . '/build/product.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file; // phpcs:ignore WPThemeReview.CoreFunctionality.FileInclude

		wp_enqueue_script(
			'cfp-pro-product',
			CFP_LITE_PLUGIN_URL . '/build/product.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations(
			'cfp-pro-product',
			'woocommerce-call-for-price',
			CFP_LITE_PLUGIN_PATH . '/languages'
		);

		wp_enqueue_style(
			'cfp-pro-product',
			CFP_LITE_PLUGIN_URL . '/build/product.css',
			array( 'wp-components' ),
			$asset['version']
		);

		wp_localize_script(
			'cfp-pro-product',
			'cfpProData',
			array(
				'adminUrl'  => esc_url_raw( admin_url() ),
				'restUrl'   => esc_url_raw( rest_url( 'cfp-pro/v1' ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'version'   => CFP_LITE_VERSION,
				'pluginUrl' => CFP_LITE_PLUGIN_URL,
			)
		);
	}
}
