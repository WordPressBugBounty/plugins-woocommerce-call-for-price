<?php
/**
 * Admin Page – Tab registration for WooCommerce settings.
 *
 * @package WooCommerce-Call-For-Price-Lite
 */

namespace TycheSoftwares\CallForPrice\Lite;

defined( 'ABSPATH' ) || exit;

/**
 * Class Admin_Page
 *
 * Handles registration of the Call for Price settings tab in WooCommerce
 * and enqueues the React build assets.
 */
class Admin_Page {

	const TAB_ID = 'call-for-price-for-woocommerce';

	/**
	 * Constructor.
	 *
	 * Hooks into WooCommerce settings tab filters and actions.
	 */
	public function __construct() {
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 50 );
		add_action( 'woocommerce_settings_' . self::TAB_ID, array( $this, 'output' ) );
		add_action( 'woocommerce_settings_save_' . self::TAB_ID, '__return_false' );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add the Call for Price tab to WooCommerce settings.
	 *
	 * @param array $tabs Existing WooCommerce settings tabs.
	 * @return array Modified tabs with the new tab added.
	 */
	public function add_settings_tab( array $tabs ): array {
		$tabs[ self::TAB_ID ] = __( 'Call for Price', 'woocommerce-call-for-price' );
		return $tabs;
	}

	/**
	 * Output the container div for the React application.
	 *
	 * @return void
	 */
	public function output(): void {
		echo '<div id="cfp-pro-settings-root"></div>';
	}

	/**
	 * Enqueue JavaScript and CSS assets for the settings page.
	 *
	 * Only loads on the WooCommerce settings page with the correct tab.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}

		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $current_tab !== self::TAB_ID ) {
			return;
		}

		$asset_file = CFP_LITE_PLUGIN_PATH . '/build/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'cfp-pro-settings',
			CFP_LITE_PLUGIN_URL . '/build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations(
			'cfp-pro-settings',
			'woocommerce-call-for-price',
			CFP_LITE_PLUGIN_PATH . '/languages'
		);

		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style(
			'cfp-pro-settings',
			CFP_LITE_PLUGIN_URL . '/build/index.css',
			array( 'wp-components' ),
			$asset['version']
		);

		wp_localize_script(
			'cfp-pro-settings',
			'cfpProData',
			array(
				'adminUrl'    => esc_url_raw( admin_url() ),
				'restUrl'     => esc_url_raw( rest_url( 'cfp-pro/v1' ) ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'version'     => CFP_LITE_VERSION,
				'pluginUrl'   => CFP_LITE_PLUGIN_URL,
				'settingsUrl' => esc_url_raw( admin_url( 'admin.php?page=wc-settings&tab=' . self::TAB_ID ) ),
			)
		);
	}
}