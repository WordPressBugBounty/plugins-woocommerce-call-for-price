<?php
/**
 * Admin bootstrap.
 *
 * Instantiates every admin-layer component and wires the admin_init migration hook.
 *
 * @package WooCommerce-Call-For-Price-Lite
 */

namespace TycheSoftwares\CallForPrice\Lite;

defined( 'ABSPATH' ) || exit;

/**
 * Class Admin
 *
 * Bootstraps the admin-side components:
 * - Registers the React settings page.
 * - Initializes the product metabox.
 * - Runs the global migration on admin_init.
 */
class Admin {

	/**
	 * Constructor.
	 *
	 * Hooks into admin_init to run migration and instantiates admin components.
	 */
	public function __construct() {
		new Admin_Page();
		new Product_Metabox();

		add_action( 'admin_init', array( Migration::class, 'run' ) );
	}
}
