<?php
/**
 * Bootstrap.
 *
 * @package BlockWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires everything together.
 */
class BlockWarden_Plugin {

	const CRON = 'blockwarden_daily';

	/**
	 * Runs on plugins_loaded.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		if ( get_option( 'blockwarden_db_version' ) !== BLOCKWARDEN_DB_VERSION ) {
			BlockWarden_Store::install();
		}
		add_action( self::CRON, array( 'BlockWarden_Store', 'purge' ) );
		BlockWarden_Pass::init();
		BlockWarden_Guard::init();
		BlockWarden_Cleanup::init();
		if ( is_admin() ) {
			BlockWarden_Admin::init();
		}
	}

	/**
	 * Activation.
	 *
	 * @return void
	 */
	public static function activate() {
		BlockWarden_Store::install();
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}
	}

	/**
	 * Deactivation.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON );
	}
}
