<?php
/**
 * Plugin Name:       BlockWarden: Stop Fake Accounts and Login Attacks for WooCommerce
 * Plugin URI:        https://github.com/hoseniqbal/blockwarden
 * Description:       Stops bot sign-ups, password guessing and lost password floods on WooCommerce My Account with an invisible pass. No CAPTCHA. Cleans up fake customer accounts.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Iqbal Hossen
 * Author URI:        https://profiles.wordpress.org/iqbal511138/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       blockwarden
 * WC requires at least: 7.6
 * WC tested up to:   11.3
 *
 * @package BlockWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BLOCKWARDEN_VERSION', '1.0.0' );
define( 'BLOCKWARDEN_DB_VERSION', '1' );
define( 'BLOCKWARDEN_FILE', __FILE__ );
define( 'BLOCKWARDEN_PATH', plugin_dir_path( __FILE__ ) );
define( 'BLOCKWARDEN_URL', plugin_dir_url( __FILE__ ) );

require_once BLOCKWARDEN_PATH . 'includes/class-blockwarden-settings.php';
require_once BLOCKWARDEN_PATH . 'includes/class-blockwarden-request.php';
require_once BLOCKWARDEN_PATH . 'includes/class-blockwarden-store.php';
require_once BLOCKWARDEN_PATH . 'includes/class-blockwarden-pass.php';
require_once BLOCKWARDEN_PATH . 'includes/class-blockwarden-disposable.php';
require_once BLOCKWARDEN_PATH . 'includes/class-blockwarden-guard.php';
require_once BLOCKWARDEN_PATH . 'includes/class-blockwarden-cleanup.php';
require_once BLOCKWARDEN_PATH . 'includes/class-blockwarden-admin.php';
require_once BLOCKWARDEN_PATH . 'includes/class-blockwarden-plugin.php';

register_activation_hook( __FILE__, array( 'BlockWarden_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'BlockWarden_Plugin', 'deactivate' ) );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

add_action( 'plugins_loaded', array( 'BlockWarden_Plugin', 'init' ) );
