<?php
/**
 * Request helpers.
 *
 * @package BlockWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Visitor address and user agent, plus keyed hashes so nothing personal is stored.
 */
class BlockWarden_Request {

	/**
	 * Visitor IP. Developers behind a proxy can change it with the blockwarden_client_ip filter.
	 *
	 * @return string
	 */
	public static function ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ip = (string) apply_filters( 'blockwarden_client_ip', $ip );
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}

	/**
	 * User agent.
	 *
	 * @return string
	 */
	public static function user_agent() {
		return isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 400 ) : '';
	}

	/**
	 * Keyed hash.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public static function hash( $value ) {
		return hash_hmac( 'sha256', strtolower( trim( (string) $value ) ), wp_salt( 'auth' ) );
	}

	/**
	 * Hash of the current IP.
	 *
	 * @return string
	 */
	public static function ip_hash() {
		return self::hash( self::ip() );
	}
}
