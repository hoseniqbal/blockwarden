<?php
/**
 * The invisible pass: a signed token that only a real page visit can get.
 *
 * @package BlockWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Issues, delivers and verifies passes.
 */
class BlockWarden_Pass {

	const SECRET = 'blockwarden_secret';
	const ARMED  = 'blockwarden_armed';
	const FIELD  = 'blockwarden_pass';
	const MIN    = 1;
	const MAX    = 43200;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'route' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'script' ) );
	}

	/**
	 * Signing key.
	 *
	 * @return string
	 */
	private static function key() {
		$secret = get_option( self::SECRET );
		if ( ! is_string( $secret ) || strlen( $secret ) < 32 ) {
			$secret = wp_generate_password( 64, true, true );
			update_option( self::SECRET, $secret, false );
		}
		return wp_salt( 'nonce' ) . $secret;
	}

	/**
	 * Short user agent fingerprint.
	 *
	 * @return string
	 */
	private static function ua() {
		return substr( hash( 'sha256', BlockWarden_Request::user_agent() ), 0, 10 );
	}

	/**
	 * New pass.
	 *
	 * @param int|null $now Unix time.
	 * @return string
	 */
	public static function issue( $now = null ) {
		$data = (string) ( null === $now ? time() : (int) $now ) . '.' . bin2hex( random_bytes( 8 ) ) . '.' . self::ua();
		return $data . '.' . hash_hmac( 'sha256', $data, self::key() );
	}

	/**
	 * Check a pass.
	 *
	 * @param string $pass Pass.
	 * @return string valid|missing|malformed|forged|expired|too_fast|agent
	 */
	public static function verify( $pass ) {
		if ( ! is_string( $pass ) || '' === $pass ) {
			return 'missing';
		}
		$parts = explode( '.', $pass );
		if ( 4 !== count( $parts ) || ! ctype_digit( $parts[0] ) ) {
			return 'malformed';
		}
		if ( ! hash_equals( hash_hmac( 'sha256', $parts[0] . '.' . $parts[1] . '.' . $parts[2], self::key() ), $parts[3] ) ) {
			return 'forged';
		}
		$age = time() - (int) $parts[0];
		if ( $age > self::MAX ) {
			return 'expired';
		}
		if ( $age < self::MIN ) {
			return 'too_fast';
		}
		if ( ! hash_equals( self::ua(), $parts[2] ) ) {
			return 'agent';
		}
		return 'valid';
	}

	/**
	 * Pass sent with this form post.
	 *
	 * @return string
	 */
	public static function posted() {
		// The WooCommerce form nonce is checked by WooCommerce; this field only carries our signed pass.
		return isset( $_POST[ self::FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::FIELD ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Decide for one form. Returns '' to allow, or the reason to block.
	 *
	 * A form is only enforced after one real visitor has sent a valid pass on it,
	 * so a caching or theme problem can never lock out real customers.
	 *
	 * @param string $form register|login|lost.
	 * @return string
	 */
	public static function check( $form ) {
		$result = self::verify( self::posted() );
		$armed  = get_option( self::ARMED, array() );
		$armed  = is_array( $armed ) ? $armed : array();
		if ( 'valid' === $result ) {
			if ( empty( $armed[ $form ] ) ) {
				$armed[ $form ] = time();
				update_option( self::ARMED, $armed, false );
			}
			return '';
		}
		if ( empty( $armed[ $form ] ) && in_array( $result, array( 'missing', 'expired' ), true ) ) {
			return '';
		}
		return 'pass_' . $result;
	}

	/**
	 * Is a form enforced yet?
	 *
	 * @param string $form Form.
	 * @return bool
	 */
	public static function armed( $form ) {
		$armed = get_option( self::ARMED, array() );
		return is_array( $armed ) && ! empty( $armed[ $form ] );
	}

	/**
	 * REST route that hands out passes.
	 *
	 * @return void
	 */
	public static function route() {
		register_rest_route(
			'blockwarden/v1',
			'/pass',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => array( __CLASS__, 'rest_pass' ),
			)
		);
	}

	/**
	 * REST callback.
	 *
	 * @return WP_REST_Response
	 */
	public static function rest_pass() {
		$response = new WP_REST_Response( array( 'pass' => self::issue() ) );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		return $response;
	}

	/**
	 * Load the small script on My Account pages for visitors.
	 *
	 * @return void
	 */
	public static function script() {
		if ( is_user_logged_in() || ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return;
		}
		if ( ! BlockWarden_Settings::on( 'pass_register' ) && ! BlockWarden_Settings::on( 'pass_login' ) && ! BlockWarden_Settings::on( 'pass_lost' ) ) {
			return;
		}
		$file = BLOCKWARDEN_PATH . 'assets/js/blockwarden-pass.js';
		wp_enqueue_script( 'blockwarden-pass', BLOCKWARDEN_URL . 'assets/js/blockwarden-pass.js', array(), BLOCKWARDEN_VERSION . '.' . (int) filemtime( $file ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_localize_script(
			'blockwarden-pass',
			'blockwardenData',
			array(
				'passUrl' => esc_url_raw( rest_url( 'blockwarden/v1/pass' ) ),
				'field'   => self::FIELD,
			)
		);
	}
}
