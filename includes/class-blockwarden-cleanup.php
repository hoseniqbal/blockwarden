<?php
/**
 * Fake account cleanup.
 *
 * @package BlockWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Finds customer accounts that never ordered and never came back, and removes them in small batches.
 */
class BlockWarden_Cleanup {

	const STATE = 'blockwarden_cleanup';
	const HOOK  = 'blockwarden_cleanup';
	const BATCH = 50;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'run_batch' ) );
	}

	/**
	 * Is this user an unused customer account?
	 *
	 * @param WP_User $user   User.
	 * @param int     $cutoff Unix time.
	 * @return bool
	 */
	public static function is_unused( $user, $cutoff ) {
		if ( ! $user instanceof WP_User || array( 'customer' ) !== array_values( (array) $user->roles ) ) {
			return false;
		}
		if ( strtotime( $user->user_registered . ' UTC' ) > $cutoff ) {
			return false;
		}
		$active = (int) get_user_meta( $user->ID, 'wc_last_active', true );
		if ( $active > $cutoff ) {
			return false;
		}
		return 0 === (int) wc_get_customer_order_count( $user->ID );
	}

	/**
	 * Find unused accounts.
	 *
	 * @param int $limit Max accounts to return.
	 * @param int $scan  Max customers to look at.
	 * @return array{ids:int[],scanned:int,more:bool}
	 */
	public static function find( $limit, $scan = 2000 ) {
		$days   = (int) BlockWarden_Settings::get( 'cleanup_days' );
		$cutoff = time() - $days * DAY_IN_SECONDS;
		$ids    = array();
		$seen   = 0;
		$page   = 1;
		do {
			$users = get_users(
				array(
					'role'       => 'customer',
					'number'     => 200,
					'paged'      => $page,
					'orderby'    => 'ID',
					'order'      => 'ASC',
					'date_query' => array( array( 'before' => gmdate( 'Y-m-d H:i:s', $cutoff ) ) ),
				)
			);
			foreach ( $users as $user ) {
				++$seen;
				if ( self::is_unused( $user, $cutoff ) ) {
					$ids[] = (int) $user->ID;
					if ( count( $ids ) >= $limit ) {
						return array(
							'ids'     => $ids,
							'scanned' => $seen,
							'more'    => true,
						);
					}
				}
			}
			++$page;
		} while ( count( $users ) === 200 && $seen < $scan );
		return array(
			'ids'     => $ids,
			'scanned' => $seen,
			'more'    => $seen >= $scan,
		);
	}

	/**
	 * Start a cleanup.
	 *
	 * @return bool
	 */
	public static function start() {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return false;
		}
		update_option(
			self::STATE,
			array(
				'running' => true,
				'started' => time(),
				'deleted' => 0,
			),
			false
		);
		as_enqueue_async_action( self::HOOK, array(), 'blockwarden' );
		return true;
	}

	/**
	 * Stop a running cleanup.
	 *
	 * @return void
	 */
	public static function stop() {
		$state            = self::state();
		$state['running'] = false;
		update_option( self::STATE, $state, false );
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK );
		}
	}

	/**
	 * Current state.
	 *
	 * @return array
	 */
	public static function state() {
		$state = get_option( self::STATE, array() );
		return wp_parse_args(
			is_array( $state ) ? $state : array(),
			array(
				'running'  => false,
				'started'  => 0,
				'finished' => 0,
				'deleted'  => 0,
			)
		);
	}

	/**
	 * Delete one batch, then queue the next.
	 *
	 * @return void
	 */
	public static function run_batch() {
		$state = self::state();
		if ( ! $state['running'] ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/user.php';
		$found = self::find( self::BATCH, 1000 );
		foreach ( $found['ids'] as $id ) {
			if ( wp_delete_user( $id ) ) {
				++$state['deleted'];
			}
		}
		if ( count( $found['ids'] ) >= self::BATCH && $state['deleted'] < 20000 ) {
			update_option( self::STATE, $state, false );
			as_enqueue_async_action( self::HOOK, array(), 'blockwarden' );
			return;
		}
		$state['running']  = false;
		$state['finished'] = time();
		update_option( self::STATE, $state, false );
	}
}
