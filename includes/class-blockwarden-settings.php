<?php
/**
 * Settings.
 *
 * @package BlockWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All settings in one option.
 */
class BlockWarden_Settings {

	const OPTION = 'blockwarden_settings';

	/**
	 * Cache.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'pass_register'   => 'yes',
			'pass_login'      => 'yes',
			'pass_lost'       => 'yes',
			'lock_enabled'    => 'yes',
			'lock_attempts'   => 5,
			'lock_minutes'    => 15,
			'reg_limit'       => 'yes',
			'reg_per_hour'    => 3,
			'lost_per_hour'   => 5,
			'disposable'      => 'yes',
			'blocked_domains' => '',
			'allowed_domains' => '',
			'cleanup_days'    => 30,
			'retention_days'  => 60,
			'delete_data'     => 'no',
		);
	}

	/**
	 * All values.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		}
		return self::$cache;
	}

	/**
	 * One value.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Yes/no check.
	 *
	 * @param string $key Key.
	 * @return bool
	 */
	public static function on( $key ) {
		return 'yes' === self::get( $key );
	}

	/**
	 * A domain list setting as an array.
	 *
	 * @param string $key Key.
	 * @return string[]
	 */
	public static function domains( $key ) {
		return array_values( array_filter( preg_split( '/[\s,]+/', strtolower( (string) self::get( $key ) ) ) ) );
	}

	/**
	 * Clean a list of domains.
	 *
	 * @param string $raw Raw text.
	 * @return string
	 */
	private static function clean_domains( $raw ) {
		$out = array();
		foreach ( preg_split( '/[\s,]+/', strtolower( sanitize_textarea_field( $raw ) ) ) as $domain ) {
			$domain = ltrim( trim( $domain ), '@' );
			if ( preg_match( '/^[a-z0-9.-]+\.[a-z]{2,}$/', $domain ) ) {
				$out[] = $domain;
			}
		}
		return implode( "\n", array_slice( array_unique( $out ), 0, 2000 ) );
	}

	/**
	 * Sanitize.
	 *
	 * @param mixed $in Raw.
	 * @return array
	 */
	public static function sanitize( $in ) {
		$d   = self::defaults();
		$in  = is_array( $in ) ? $in : array();
		$out = array();
		foreach ( array( 'pass_register', 'pass_login', 'pass_lost', 'lock_enabled', 'reg_limit', 'disposable', 'delete_data' ) as $key ) {
			$out[ $key ] = ( isset( $in[ $key ] ) && 'yes' === $in[ $key ] ) ? 'yes' : 'no';
		}
		$ints = array(
			'lock_attempts'  => array( 3, 50 ),
			'lock_minutes'   => array( 1, 1440 ),
			'reg_per_hour'   => array( 1, 100 ),
			'lost_per_hour'  => array( 1, 100 ),
			'cleanup_days'   => array( 7, 3650 ),
			'retention_days' => array( 7, 730 ),
		);
		foreach ( $ints as $key => $range ) {
			$val         = isset( $in[ $key ] ) ? absint( $in[ $key ] ) : $d[ $key ];
			$out[ $key ] = min( $range[1], max( $range[0], $val ) );
		}
		foreach ( array( 'blocked_domains', 'allowed_domains' ) as $key ) {
			$out[ $key ] = isset( $in[ $key ] ) ? self::clean_domains( wp_unslash( $in[ $key ] ) ) : '';
		}
		self::$cache = null;
		return $out;
	}
}
