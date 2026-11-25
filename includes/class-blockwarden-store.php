<?php
/**
 * Data: event log.
 *
 * @package BlockWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One table. IPs and usernames are stored only as keyed hashes.
 */
class BlockWarden_Store {

	/**
	 * Table.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'blockwarden_events';
	}

	/**
	 * Create or update the table.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$table   = self::table();
		dbDelta(
			"CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			event varchar(20) NOT NULL,
			reason varchar(32) NOT NULL DEFAULT '',
			form varchar(20) NOT NULL DEFAULT '',
			ip_hash char(64) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY event_created (event, created_at),
			KEY ip_event (ip_hash, event, created_at)
		) {$charset};"
		);
		update_option( 'blockwarden_db_version', BLOCKWARDEN_DB_VERSION, false );
	}

	/**
	 * Add an event for the current visitor.
	 *
	 * @param string $event  blocked|login_failed|registered|lost.
	 * @param string $reason Reason.
	 * @param string $form   register|login|lost.
	 * @return void
	 */
	public static function add( $event, $reason = '', $form = '' ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- own table, write.
		$wpdb->insert(
			self::table(),
			array(
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
				'event'      => substr( sanitize_key( $event ), 0, 20 ),
				'reason'     => substr( sanitize_key( $reason ), 0, 32 ),
				'form'       => substr( sanitize_key( $form ), 0, 20 ),
				'ip_hash'    => BlockWarden_Request::ip_hash(),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
		if ( 'blocked' === $event ) {
			/**
			 * A request was stopped.
			 *
			 * @param string $reason Reason.
			 * @param string $form   register, login or lost.
			 */
			do_action( 'blockwarden_blocked', $reason, $form );
		}
	}

	/**
	 * Events of a type from the current visitor in the last N seconds.
	 *
	 * @param string $event   Event.
	 * @param int    $seconds Window.
	 * @return int
	 */
	public static function count_ip( $event, $seconds ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table, live data.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE ip_hash = %s AND event = %s AND created_at >= %s', self::table(), BlockWarden_Request::ip_hash(), $event, gmdate( 'Y-m-d H:i:s', time() - (int) $seconds ) ) );
	}

	/**
	 * Events of a type in the last N seconds, optionally one reason only.
	 *
	 * @param string $event   Event.
	 * @param int    $seconds Window.
	 * @param string $reason  Reason or ''.
	 * @return int
	 */
	public static function count_since( $event, $seconds, $reason = '' ) {
		global $wpdb;
		$since = gmdate( 'Y-m-d H:i:s', time() - (int) $seconds );
		if ( '' !== $reason ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table, live data.
			return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE event = %s AND reason = %s AND created_at >= %s', self::table(), $event, $reason, $since ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table, live data.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE event = %s AND created_at >= %s', self::table(), $event, $since ) );
	}

	/**
	 * Addresses locked out right now.
	 *
	 * @return int
	 */
	public static function locked_count() {
		global $wpdb;
		$minutes  = (int) BlockWarden_Settings::get( 'lock_minutes' );
		$attempts = (int) BlockWarden_Settings::get( 'lock_attempts' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table, live data.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ( SELECT ip_hash FROM %i WHERE event = %s AND created_at >= %s GROUP BY ip_hash HAVING COUNT(*) >= %d ) t', self::table(), 'login_failed', gmdate( 'Y-m-d H:i:s', time() - $minutes * MINUTE_IN_SECONDS ), $attempts ) );
	}

	/**
	 * Recent blocks.
	 *
	 * @param int $limit Rows.
	 * @return array
	 */
	public static function recent_blocks( $limit = 20 ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table, live data.
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT created_at, reason, form FROM %i WHERE event = %s ORDER BY id DESC LIMIT %d', self::table(), 'blocked', $limit ), ARRAY_A );
	}

	/**
	 * Delete old rows.
	 *
	 * @return void
	 */
	public static function purge() {
		global $wpdb;
		$days = (int) BlockWarden_Settings::get( 'retention_days' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table, cleanup.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE created_at < %s LIMIT 10000', self::table(), gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) );
	}
}
