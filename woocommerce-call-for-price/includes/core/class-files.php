<?php
/**
 * Call for Price for WooCommerce – File Loader
 *
 * Loads all plugin PHP files in dependency order using explicit require_once
 * calls (no autoloader). Admin-only files are loaded conditionally.
 *
 * @package WooCommerce-Call-For-Price-Lite
 */

namespace TycheSoftwares\CallForPrice\Lite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Files
 *
 * Loads every plugin file in the correct dependency order.
 * Only makes classes available — instantiation is handled by Plugin::init().
 */
class Files {

	/**
	 * Load all plugin files.
	 * Call once from Plugin::load_files() after constants are defined.
	 *
	 * @return void
	 */
	public static function load(): void {
		// Core (no WC dependency).
		self::require( 'includes/core/class-compatibility.php' );

		// Admin / Settings layer.
		self::require( 'includes/admin/class-settings.php' );
		self::require( 'includes/admin/class-migration.php' );
		self::require( 'includes/admin/class-product-meta-migration.php' );

		// REST API layer.
		self::require( 'includes/api/class-api-base.php' );
		self::require( 'includes/api/class-api-settings.php' );
		self::require( 'includes/api/class-api-dashboard.php' );
		self::require( 'includes/api/class-api-migration.php' );
		self::require( 'includes/api/class-api-options.php' );
		self::require( 'includes/api/class-api-product.php' );
		self::require( 'includes/api/class-api.php' );

		// Hooks layer.
		self::require( 'includes/core/class-hooks.php' );

		// Admin-only files.
		if ( is_admin() ) {
			self::load_admin();
		}
	}

	/**
	 * Load admin-only files (settings UI, metabox, Tyche helper classes).
	 *
	 * @return void
	 */
	private static function load_admin(): void {
		self::require( 'includes/admin/class-admin-page.php' );
		self::require( 'includes/admin/class-product-metabox.php' );
		self::require( 'includes/admin/class-admin.php' );

		// Tyche helper libraries (tracking, deactivation survey).
		// Loaded conditionally — they may not exist in all environments.
		$tyche_files = array(
			'includes/class-tracking.php',
			'includes/class-deactivation.php',
		);

		foreach ( $tyche_files as $file ) {
			self::require_if_exists( $file );
		}
	}

	/**
	 * require_once a file relative to the plugin root.
	 *
	 * @param string $relative_path Path relative to CFP_LITE_PLUGIN_PATH.
	 * @return void
	 */
	private static function require( string $relative_path ): void {
		require_once CFP_LITE_PLUGIN_PATH . '/' . $relative_path; // phpcs:ignore WPThemeReview.CoreFunctionality.FileInclude
	}

	/**
	 * require_once a file only if it exists on disk.
	 *
	 * @param string $relative_path Path relative to CFP_LITE_PLUGIN_PATH.
	 * @return void
	 */
	private static function require_if_exists( string $relative_path ): void {
		$full_path = CFP_LITE_PLUGIN_PATH . '/' . $relative_path;
		if ( file_exists( $full_path ) ) {
			require_once $full_path; // phpcs:ignore WPThemeReview.CoreFunctionality.FileInclude
		}
	}
}
