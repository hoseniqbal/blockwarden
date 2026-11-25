<?php
/**
 * Guards on registration, login and lost password.
 *
 * @package BlockWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Decides and records.
 */
class BlockWarden_Guard {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'woocommerce_process_registration_errors', array( __CLASS__, 'register_form' ), 10, 4 );
		add_filter( 'woocommerce_registration_errors', array( __CLASS__, 'any_registration' ), 10, 3 );
		add_action( 'woocommerce_created_customer', array( __CLASS__, 'registered' ) );
		add_filter( 'woocommerce_process_login_errors', array( __CLASS__, 'login_form' ), 10, 3 );
		add_filter( 'authenticate', array( __CLASS__, 'lockout' ), 30, 1 );
		add_action( 'wp_login_failed', array( __CLASS__, 'login_failed' ), 10, 2 );
		add_action( 'lostpassword_post', array( __CLASS__, 'lost_password' ), 10, 1 );
	}

	/**
	 * Messages people see.
	 *
	 * @param string $reason Reason.
	 * @return string
	 */
	public static function message( $reason ) {
		if ( 0 === strpos( $reason, 'pass_' ) ) {
			return __( 'Please wait a moment and try again. If this keeps happening, reload the page.', 'blockwarden' );
		}
		$messages = array(
			'disposable' => __( 'Please use a permanent email address. Temporary inboxes cannot receive your order updates.', 'blockwarden' ),
			'reg_limit'  => __( 'Too many accounts were created from your connection. Please try again later.', 'blockwarden' ),
			/* translators: %s: minutes */
			'locked'     => sprintf( __( 'Too many failed login attempts. Please try again in %s minutes or reset your password.', 'blockwarden' ), number_format_i18n( (int) BlockWarden_Settings::get( 'lock_minutes' ) ) ),
			'lost_limit' => __( 'Too many password reset requests. Please try again later.', 'blockwarden' ),
		);
		return isset( $messages[ $reason ] ) ? $messages[ $reason ] : __( 'Your request could not be completed. Please try again.', 'blockwarden' );
	}

	/**
	 * My Account register form: check the pass.
	 *
	 * @param WP_Error $errors   Errors.
	 * @param string   $username Username.
	 * @param string   $password Password.
	 * @param string   $email    Email.
	 * @return WP_Error
	 */
	public static function register_form( $errors, $username, $password, $email ) {
		unset( $username, $password, $email );
		if ( BlockWarden_Settings::on( 'pass_register' ) && $errors instanceof WP_Error ) {
			$reason = BlockWarden_Pass::check( 'register' );
			if ( '' !== $reason ) {
				BlockWarden_Store::add( 'blocked', $reason, 'register' );
				$errors->add( 'blockwarden_' . $reason, self::message( $reason ) );
			}
		}
		return $errors;
	}

	/**
	 * Every customer registration (My Account, checkout, block checkout): disposable domains and the per-address limit.
	 *
	 * @param WP_Error $errors   Errors.
	 * @param string   $username Username.
	 * @param string   $email    Email.
	 * @return WP_Error
	 */
	public static function any_registration( $errors, $username, $email ) {
		unset( $username );
		if ( ! $errors instanceof WP_Error || $errors->has_errors() || current_user_can( 'create_users' ) ) {
			return $errors;
		}
		if ( BlockWarden_Settings::on( 'disposable' ) && BlockWarden_Disposable::is_disposable( $email ) ) {
			BlockWarden_Store::add( 'blocked', 'disposable', 'register' );
			$errors->add( 'blockwarden_disposable', self::message( 'disposable' ) );
			return $errors;
		}
		if ( BlockWarden_Settings::on( 'reg_limit' ) && BlockWarden_Store::count_ip( 'registered', HOUR_IN_SECONDS ) >= (int) BlockWarden_Settings::get( 'reg_per_hour' ) ) {
			BlockWarden_Store::add( 'blocked', 'reg_limit', 'register' );
			$errors->add( 'blockwarden_reg_limit', self::message( 'reg_limit' ) );
		}
		return $errors;
	}

	/**
	 * Count a new customer.
	 *
	 * @return void
	 */
	public static function registered() {
		BlockWarden_Store::add( 'registered', '', 'register' );
	}

	/**
	 * My Account login form: check the pass.
	 *
	 * @param WP_Error $errors   Errors.
	 * @param string   $username Username.
	 * @param string   $password Password.
	 * @return WP_Error
	 */
	public static function login_form( $errors, $username, $password ) {
		unset( $username, $password );
		if ( BlockWarden_Settings::on( 'pass_login' ) && $errors instanceof WP_Error ) {
			$reason = BlockWarden_Pass::check( 'login' );
			if ( '' !== $reason ) {
				BlockWarden_Store::add( 'blocked', $reason, 'login' );
				$errors->add( 'blockwarden_' . $reason, self::message( $reason ) );
			}
		}
		return $errors;
	}

	/**
	 * Is the current address locked out?
	 *
	 * @return bool
	 */
	public static function is_locked() {
		return BlockWarden_Settings::on( 'lock_enabled' )
			&& BlockWarden_Store::count_ip( 'login_failed', (int) BlockWarden_Settings::get( 'lock_minutes' ) * MINUTE_IN_SECONDS ) >= (int) BlockWarden_Settings::get( 'lock_attempts' );
	}

	/**
	 * Refuse logins from a locked address, everywhere (My Account, wp-login.php, XML-RPC).
	 *
	 * @param WP_User|WP_Error|null $user User.
	 * @return WP_User|WP_Error|null
	 */
	public static function lockout( $user ) {
		if ( ( isset( $_POST['log'] ) || isset( $_POST['username'] ) || $user instanceof WP_User ) && self::is_locked() ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- only checks that a login form was posted.
			BlockWarden_Store::add( 'blocked', 'locked', 'login' );
			return new WP_Error( 'blockwarden_locked', self::message( 'locked' ) );
		}
		return $user;
	}

	/**
	 * Count a failed login.
	 *
	 * @param string        $username Username.
	 * @param WP_Error|null $error    Error.
	 * @return void
	 */
	public static function login_failed( $username, $error = null ) {
		unset( $username );
		if ( $error instanceof WP_Error && 0 === strpos( (string) $error->get_error_code(), 'blockwarden_' ) ) {
			return;
		}
		BlockWarden_Store::add( 'login_failed', '', 'login' );
	}

	/**
	 * Lost password: limit per address and check the pass on the WooCommerce form.
	 *
	 * @param WP_Error $errors Errors.
	 * @return void
	 */
	public static function lost_password( $errors ) {
		if ( ! $errors instanceof WP_Error ) {
			return;
		}
		if ( BlockWarden_Store::count_ip( 'lost', HOUR_IN_SECONDS ) >= (int) BlockWarden_Settings::get( 'lost_per_hour' ) ) {
			BlockWarden_Store::add( 'blocked', 'lost_limit', 'lost' );
			$errors->add( 'blockwarden_lost_limit', self::message( 'lost_limit' ) );
			return;
		}
		// WooCommerce checks its own nonce for this form before calling retrieve_password().
		if ( isset( $_POST['wc_reset_password'] ) && BlockWarden_Settings::on( 'pass_lost' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$reason = BlockWarden_Pass::check( 'lost' );
			if ( '' !== $reason ) {
				BlockWarden_Store::add( 'blocked', $reason, 'lost' );
				$errors->add( 'blockwarden_' . $reason, self::message( $reason ) );
				return;
			}
		}
		BlockWarden_Store::add( 'lost', '', 'lost' );
	}
}
