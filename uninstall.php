<?php
/**
 * Uninstall: remove data only when the owner asked for it.
 *
 * @package BlockWarden
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$blockwarden_settings = get_option( 'blockwarden_settings', array() );
if ( ! is_array( $blockwarden_settings ) || empty( $blockwarden_settings['delete_data'] ) || 'yes' !== $blockwarden_settings['delete_data'] ) {
	return;
}

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- uninstall.
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'blockwarden_events' ) );
foreach ( array( 'blockwarden_settings', 'blockwarden_db_version', 'blockwarden_secret', 'blockwarden_armed', 'blockwarden_cleanup' ) as $blockwarden_option ) {
	delete_option( $blockwarden_option );
}
wp_clear_scheduled_hook( 'blockwarden_daily' );
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'blockwarden_cleanup' );
}
