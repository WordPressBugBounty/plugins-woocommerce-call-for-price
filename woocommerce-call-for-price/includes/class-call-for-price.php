<?php
/**
 * Call for Price for WooCommerce – Main Plugin Class
 *
 * Singleton that owns all bootstrap logic:
 *  - Plugin constants
 *  - HPOS (High-Performance Order Storage) compatibility declaration
 *  - Activation / deactivation hooks
 *  - File loading (via Files::load())
 *  - Component instantiation (Migration, Api, Admin, Hooks)
 *  - Text-domain, action-links, wpcodefactory helper cleanup
 *  - Backward-compatible alg_call_for_price filter shim
 *
 * @package WooCommerce-Call-For-Price-Lite
 */

namespace TycheSoftwares\CallForPrice\Lite;

use Automattic\WooCommerce\Utilities\FeaturesUtil;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 *
 * Main plugin class handling bootstrap, constants, hooks, and backward compatibility.
 */
final class Plugin {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public string $version = '4.4.0';

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	protected static ?Plugin $instance = null;

	/**
	 * Returns (and creates, if needed) the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — define constants then wire all hooks.
	 */
	public function __construct() {
		$this->define_constants();
		$this->init_hooks();
	}

	/**
	 * Prevent cloning of the singleton.
	 *
	 * @return void
	 */
	public function __clone() {
		_doing_it_wrong(
			__FUNCTION__,
			esc_html__( 'Cloning is not allowed.', 'woocommerce-call-for-price' ),
			esc_attr( CFP_LITE_VERSION )
		);
	}

	/**
	 * Prevent unserialization of the singleton.
	 *
	 * @return void
	 */
	public function __wakeup() {
		_doing_it_wrong(
			__FUNCTION__,
			esc_html__( 'Unserializing is not allowed.', 'woocommerce-call-for-price' ),
			esc_attr( CFP_LITE_VERSION )
		);
	}

	/**
	 * Define all CFP_LITE_* constants from the bootstrap file.
	 *
	 * @return void
	 */
	private function define_constants(): void {
		$constants = array(
			'CFP_LITE_VERSION'          => $this->version,
			'CFP_LITE_PLUGIN_FILE'      => CFP_LITE_BOOTSTRAP_FILE,
			'CFP_LITE_PLUGIN_PATH'      => untrailingslashit( plugin_dir_path( CFP_LITE_BOOTSTRAP_FILE ) ),
			'CFP_LITE_PLUGIN_URL'       => untrailingslashit( plugin_dir_url( CFP_LITE_BOOTSTRAP_FILE ) ),
			'CFP_LITE_PLUGIN_BASENAME'  => plugin_basename( CFP_LITE_BOOTSTRAP_FILE ),
			'CFP_LITE_STORE_URL'        => 'https://www.tychesoftwares.com/',
			'CFP_LITE_ITEM_NAME'        => 'Call for Price for WooCommerce',
			'CFP_LITE_DOWNLOAD_ID'      => 339922,
		);

		foreach ( $constants as $name => $value ) {
			if ( ! defined( $name ) ) {
				define( $name, $value );
			}
		}
	}

	/**
	 * Register all hooks that must fire before the init action, then
	 * defer component initialisation to the init action so WooCommerce
	 * classes are guaranteed to be available.
	 *
	 * @return void
	 */
	private function init_hooks(): void {

		// Deactivate Lite.

		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ), 999 );
		$this->register_tracking_settings();

		register_activation_hook( CFP_LITE_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( CFP_LITE_PLUGIN_FILE, array( $this, 'deactivate' ) );

		add_filter( 'alg_call_for_price', array( $this, 'alg_call_for_price_compat' ), PHP_INT_MAX, 5 );

		if ( is_admin() ) {
			add_filter( 'plugin_action_links_' . CFP_LITE_PLUGIN_BASENAME, array( $this, 'action_links' ) );
			add_action( 'alg_get_plugins_list', array( $this, 'remove_from_wpcodefactory_list' ), PHP_INT_MAX );
		}

		$this->load_files();
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Load all plugin PHP files via the Files class.
	 *
	 * @return void
	 */
	private function load_files(): void {
		require_once CFP_LITE_PLUGIN_PATH . '/includes/core/class-files.php';
		Files::load();
	}

	/**
	 * Main plugin initialisation — runs on the WP init action.
	 * WooCommerce classes are available at this point.
	 *
	 * @return void
	 */
	public function init(): void {
		load_plugin_textdomain(
			'woocommerce-call-for-price',
			false,
			dirname( CFP_LITE_PLUGIN_BASENAME ) . '/languages'
		);

		Migration::run();
		Product_Meta_Migration::init();

		// Auto-start per-product meta migration when it has not yet completed.
		// Skip when already 'running' — ActionScheduler owns the queue at that point
		// and calling start() risks hitting the brief window where no actions appear
		// pending (they're executing), which would reset the done/failed counters.
		$migration_status = get_option( Product_Meta_Migration::STATUS_OPTION, 'none' );
		if ( 'running' !== $migration_status
			&& get_option( Product_Meta_Migration::VERSION_OPTION ) !== Product_Meta_Migration::MIGRATION_VERSION
		) {
			Product_Meta_Migration::start();
		}

		new Api();
		new Hooks();

		if ( is_admin() ) {
			new Admin();
		}
	}

	/**
	 * Register tracking-related options so they can be accessed and updated via REST API.
	 * This is required for the "Reset Usage Tracking" feature in the admin UI.
	 *
	 * @return void
	 */
	private function register_tracking_settings(): void {
		register_setting(
			'options',
			'cfp_allow_tracking',
			array(
				'type'         => 'string',
				'default'      => '',
				'show_in_rest' => true,
			)
		);
		register_setting(
			'options',
			'ts_tracker_last_send',
			array(
				'type'         => 'string',
				'default'      => '',
				'show_in_rest' => true,
			)
		);
	}

	/**
	 * Declare HPOS and orders-cache compatibility so WooCommerce does not
	 * show the "incompatible plugins" admin notice.
	 *
	 * @return void
	 */
	public function declare_hpos_compatibility(): void {
		if ( class_exists( FeaturesUtil::class ) ) {
			FeaturesUtil::declare_compatibility( 'custom_order_tables', CFP_LITE_PLUGIN_FILE, true );
			FeaturesUtil::declare_compatibility( 'orders_cache', CFP_LITE_PLUGIN_FILE, true );
		}
	}

	/**
	 * Run on plugin activation: seed default settings.
	 *
	 * @return void
	 */
	public function activate(): void {
		Migration::run();
	}

	/**
	 * Run on plugin deactivation: clean up scheduled actions.
	 *
	 * @return void
	 */
	public function deactivate(): void {
		if ( function_exists( 'as_next_scheduled_action' ) && false !== as_next_scheduled_action( 'ts_send_data_tracking_usage' ) ) {
			as_unschedule_action( 'ts_send_data_tracking_usage' );
		}
		do_action( 'cfp_deactivate' );
	}

	/**
	 * Add "Settings" link on the Plugins list page.
	 *
	 * @param array $links Existing plugin action links.
	 * @return array Modified links.
	 */
	public function action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=wc-settings&tab=call-for-price-for-woocommerce' ) ),
			esc_html__( 'Settings', 'woocommerce-call-for-price' )
		);
		return array_merge( array( $settings_link ), $links );
	}

	/**
	 * Remove this plugin from the WPCodeFactory managed-plugins list.
	 *
	 * @return void
	 */
	public function remove_from_wpcodefactory_list(): void {
		$list = get_option( 'alg_wpcodefactory_helper_plugins', array() );
		if ( ! empty( $list ) && is_array( $list ) ) {
			update_option(
				'alg_wpcodefactory_helper_plugins',
				array_diff( $list, array( 'woocommerce-call-for-price' ) )
			);
		}
	}

	/**
	 * Implements the old alg_call_for_price filter so third-party code that
	 * calls apply_filters( 'alg_call_for_price', $value, $type, ... ) continues
	 * to receive the correct value from the new settings store.
	 *
	 * Supported $type values (from the live 4.1.x plugin):
	 *   'settings'    — always returns ''
	 *   'value'       — CFP label for a product type + view
	 *   'per_product' — 'yes'|'no' whether per-product override is enabled
	 *   'button_text' — archive button label text
	 *   'button_url'  — archive button URL
	 *   'out_of_stock'— 'yes'|'no' whether out-of-stock forcing is active
	 *
	 * @param mixed  $value        Current filter value.
	 * @param string $type         Request type.
	 * @param string $product_type Product type slug or 'per_product'.
	 * @param string $view         View context (single|archive|home|…).
	 * @param array  $args         Extra args, e.g. [ 'product_id' => 123 ].
	 * @return mixed
	 */
	public function alg_call_for_price_compat( $value, string $type = '', string $product_type = '', string $view = '', array $args = array() ) {
		switch ( $type ) {
			case 'settings':
				return '';

			case 'value':
				if ( 'per_product' === $product_type ) {
					$product_id = (int) ( $args['product_id'] ?? 0 );
					if ( $product_id ) {
						$meta = Compatibility::get_product_meta( $product_id );
						return $meta['text_all_views'] ?? $value;
					}
					return $value;
				}
				return Compatibility::get_setting( $product_type, array( 'views', $view, 'text' ), $value );

			case 'per_product':
				$enabled = Compatibility::get_setting( 'general', 'per_product_enabled', false );
				return ( $enabled && 'no' !== $enabled ) ? 'yes' : 'no';

			case 'button_text':
				return Compatibility::get_setting( 'general', 'button_text', $value );

			case 'button_url':
				return Compatibility::get_setting( 'general', 'button_url', $value );

			case 'out_of_stock':
				$forced = Compatibility::get_setting( 'general', array( 'force', 'out_of_stock' ), false );
				return ( $forced && 'no' !== $forced ) ? 'yes' : 'no';

			default:
				return $value;
		}
	}

	/**
	 * Get plugin URL.
	 *
	 * @return string
	 */
	public function plugin_url(): string {
		return CFP_LITE_PLUGIN_URL;
	}

	/**
	 * Get plugin path.
	 *
	 * @return string
	 */
	public function plugin_path(): string {
		return CFP_LITE_PLUGIN_PATH;
	}
}

// Backward-compat class alias – code that referenced the old Alg_Woocommerce_Call_For_Price class still works.
if ( ! class_exists( 'Alg_Woocommerce_Call_For_Price' ) ) {
	class_alias( Plugin::class, 'Alg_Woocommerce_Call_For_Price' );
}
