<?php
/**
 * Admin screens.
 *
 * @package BlockWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Overview, Settings and Cleanup.
 */
class BlockWarden_Admin {

	const PAGE = 'blockwarden';
	const CAP  = 'manage_woocommerce';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 63 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_blockwarden_cleanup', array( __CLASS__, 'handle_cleanup' ) );
		add_filter( 'option_page_capability_blockwarden', array( __CLASS__, 'capability' ) );
		add_filter( 'submenu_file', array( __CLASS__, 'submenu_file' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( BLOCKWARDEN_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Capability for saving settings.
	 *
	 * @return string
	 */
	public static function capability() {
		return self::CAP;
	}

	/**
	 * Tabs.
	 *
	 * @return array
	 */
	private static function tabs() {
		$tabs = array(
			'overview' => __( 'Overview', 'blockwarden' ),
			'settings' => __( 'Settings', 'blockwarden' ),
			'cleanup'  => __( 'Account cleanup', 'blockwarden' ),
		);
		if ( ! class_exists( 'BWP_Plugin' ) ) {
			$tabs['pro'] = __( 'Pro', 'blockwarden' );
		}
		return $tabs;
	}

	/**
	 * Page URL.
	 *
	 * @param string $tab  Tab.
	 * @param array  $args Extra args.
	 * @return string
	 */
	public static function url( $tab = 'overview', $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE, 'tab' => $tab ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Plugins screen link.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Dashboard', 'blockwarden' ) . '</a>' );
		return $links;
	}

	/**
	 * Menu.
	 *
	 * @return void
	 */
	public static function menu() {
		$icon = 'data:image/svg+xml;base64,' . base64_encode( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- menu icon.
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" d="M10 1.5 2.8 4.2v5.2c0 4.4 3 7.7 7.2 8.9 4.2-1.2 7.2-4.5 7.2-8.9V4.2L10 1.5Zm0 4a2.3 2.3 0 0 1 2.3 2.3v1.3h.6c.4 0 .7.3.7.7v3.6c0 .4-.3.7-.7.7H7.1a.7.7 0 0 1-.7-.7V9.8c0-.4.3-.7.7-.7h.6V7.8A2.3 2.3 0 0 1 10 5.5Zm0 1.3c-.6 0-1 .4-1 1v1.3h2V7.8c0-.6-.4-1-1-1Z"/></svg>'
		);
		add_menu_page( __( 'BlockWarden', 'blockwarden' ), __( 'BlockWarden', 'blockwarden' ), self::CAP, self::PAGE, array( __CLASS__, 'render' ), $icon, 57 );
		add_submenu_page( self::PAGE, __( 'Overview', 'blockwarden' ), __( 'Overview', 'blockwarden' ), self::CAP, self::PAGE, array( __CLASS__, 'render' ) );
		$tabs = self::tabs();
		foreach ( array_intersect( array_keys( self::tabs() ), array( 'settings', 'cleanup', 'pro' ) ) as $tab ) {
			add_submenu_page( self::PAGE, $tabs[ $tab ], $tabs[ $tab ], self::CAP, 'admin.php?page=' . self::PAGE . '&tab=' . $tab );
		}
	}

	/**
	 * Highlight the open tab in the menu.
	 *
	 * @param string|null $file Submenu file.
	 * @return string|null
	 */
	public static function submenu_file( $file ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- menu highlight.
		if ( self::PAGE !== $page ) {
			return $file;
		}
		$tab = self::current_tab();
		return 'overview' === $tab ? self::PAGE : 'admin.php?page=' . self::PAGE . '&tab=' . $tab;
	}

	/**
	 * Styles.
	 *
	 * @param string $hook Hook.
	 * @return void
	 */
	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::PAGE ) ) {
			return;
		}
		$file = BLOCKWARDEN_PATH . 'assets/css/admin.css';
		wp_enqueue_style( 'blockwarden-admin', BLOCKWARDEN_URL . 'assets/css/admin.css', array(), BLOCKWARDEN_VERSION . '.' . (int) filemtime( $file ) );
	}

	/**
	 * Settings registration.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			'blockwarden',
			BlockWarden_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'BlockWarden_Settings', 'sanitize' ),
				'default'           => BlockWarden_Settings::defaults(),
			)
		);
	}

	/**
	 * Current tab.
	 *
	 * @return string
	 */
	private static function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tab switch.
		return isset( self::tabs()[ $tab ] ) ? $tab : 'overview';
	}

	/**
	 * Reason labels.
	 *
	 * @param string $reason Reason.
	 * @return string
	 */
	public static function reason_label( $reason ) {
		$labels = array(
			'pass_missing'   => __( 'No pass: sent without visiting the page', 'blockwarden' ),
			'pass_malformed' => __( 'Fake pass', 'blockwarden' ),
			'pass_forged'    => __( 'Fake pass', 'blockwarden' ),
			'pass_expired'   => __( 'Old pass', 'blockwarden' ),
			'pass_too_fast'  => __( 'Sent faster than a person can type', 'blockwarden' ),
			'pass_agent'     => __( 'Pass taken from another browser', 'blockwarden' ),
			'disposable'     => __( 'Temporary email address', 'blockwarden' ),
			'reg_limit'      => __( 'Too many sign-ups from one address', 'blockwarden' ),
			'locked'         => __( 'Locked out after failed logins', 'blockwarden' ),
			'lost_limit'     => __( 'Too many password reset requests', 'blockwarden' ),
		);
		return isset( $labels[ $reason ] ) ? $labels[ $reason ] : $reason;
	}

	/**
	 * Form labels.
	 *
	 * @param string $form Form.
	 * @return string
	 */
	private static function form_label( $form ) {
		$forms = array(
			'register' => __( 'Registration', 'blockwarden' ),
			'login'    => __( 'Login', 'blockwarden' ),
			'lost'     => __( 'Lost password', 'blockwarden' ),
		);
		return isset( $forms[ $form ] ) ? $forms[ $form ] : $form;
	}

	/**
	 * Logo.
	 *
	 * @return void
	 */
	private static function logo() {
		?>
		<svg width="34" height="34" viewBox="0 0 32 32" aria-hidden="true" focusable="false"><path d="M16 2.5 4.5 6.8v8.4c0 7.1 4.9 12.4 11.5 14.3 6.6-1.9 11.5-7.2 11.5-14.3V6.8L16 2.5Z" fill="#12704c"/><rect x="11" y="14" width="10" height="8" rx="1.5" fill="#fff"/><path d="M13 14v-2.2a3 3 0 0 1 6 0V14" fill="none" stroke="#fff" stroke-width="2"/><circle cx="23.5" cy="21.5" r="4" fill="#3b6fd8" stroke="#fff" stroke-width="1.5"/></svg>
		<?php
	}

	/**
	 * Render.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$tab = self::current_tab();
		?>
		<div class="wrap blockwarden">
			<div class="ow-hero">
				<div class="blockwarden-header">
					<span class="ow-logo"><?php self::logo(); ?></span>
					<div class="ow-title">
						<h1><?php esc_html_e( 'BlockWarden', 'blockwarden' ); ?></h1>
						<p class="ow-tagline"><?php esc_html_e( 'Keeps bots out of My Account without a single CAPTCHA.', 'blockwarden' ); ?></p>
					</div>
					<span class="blockwarden-version"><?php echo esc_html( 'v' . BLOCKWARDEN_VERSION ); ?></span>
				</div>
				<nav class="blockwarden-tabs" aria-label="<?php esc_attr_e( 'BlockWarden sections', 'blockwarden' ); ?>">
					<?php foreach ( self::tabs() as $key => $label ) : ?>
						<a href="<?php echo esc_url( self::url( $key ) ); ?>"<?php echo 'pro' === $key ? ' class="ow-tab-pro"' : ''; ?><?php echo $key === $tab ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
					<?php endforeach; ?>
				</nav>
			</div>
			<hr class="wp-header-end">
			<?php
			if ( 'settings' === $tab ) {
				self::render_settings();
			} elseif ( 'cleanup' === $tab ) {
				self::render_cleanup();
			} elseif ( 'pro' === $tab ) {
				self::render_pro();
			} else {
				self::render_overview();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Overview.
	 *
	 * @return void
	 */
	private static function render_overview() {
		$forms = array(
			'register' => 'pass_register',
			'login'    => 'pass_login',
			'lost'     => 'pass_lost',
		);
		$on    = 0;
		$armed = 0;
		foreach ( $forms as $form => $key ) {
			if ( BlockWarden_Settings::on( $key ) ) {
				++$on;
				$armed += BlockWarden_Pass::armed( $form ) ? 1 : 0;
			}
		}
		$state  = 0 === $on ? 'is-off' : ( $armed === $on ? 'is-on' : 'is-learning' );
		$recent = BlockWarden_Store::recent_blocks( 20 );
		$reg    = get_option( 'users_can_register' ) || 'yes' === get_option( 'woocommerce_enable_myaccount_registration' );
		?>
		<section class="blockwarden-banner <?php echo esc_attr( $state ); ?>">
			<div>
				<?php if ( 'is-on' === $state ) : ?>
					<h2><?php esc_html_e( 'My Account is protected', 'blockwarden' ); ?></h2>
					<p><?php esc_html_e( 'Bots that post straight to your forms are turned away. Real customers never see a puzzle.', 'blockwarden' ); ?></p>
				<?php elseif ( 'is-learning' === $state ) : ?>
					<h2><?php esc_html_e( 'Getting ready', 'blockwarden' ); ?></h2>
					<p><?php esc_html_e( 'Each form starts blocking after the first real visitor uses it, so nobody is locked out by a caching or theme problem. Login limits and the temporary email block already work.', 'blockwarden' ); ?></p>
				<?php else : ?>
					<h2><?php esc_html_e( 'The invisible pass is off', 'blockwarden' ); ?></h2>
					<p><?php esc_html_e( 'Turn it on for at least one form in Settings.', 'blockwarden' ); ?></p>
				<?php endif; ?>
			</div>
		</section>

		<section class="blockwarden-panel">
			<dl class="blockwarden-figures">
				<div><dt><?php esc_html_e( 'Bots stopped, 7 days', 'blockwarden' ); ?></dt><dd><?php echo esc_html( number_format_i18n( BlockWarden_Store::count_since( 'blocked', WEEK_IN_SECONDS ) ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Failed logins, 24 hours', 'blockwarden' ); ?></dt><dd><?php echo esc_html( number_format_i18n( BlockWarden_Store::count_since( 'login_failed', DAY_IN_SECONDS ) ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Addresses locked now', 'blockwarden' ); ?></dt><dd><?php echo esc_html( number_format_i18n( BlockWarden_Store::locked_count() ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Temporary emails stopped, 7 days', 'blockwarden' ); ?></dt><dd><?php echo esc_html( number_format_i18n( BlockWarden_Store::count_since( 'blocked', WEEK_IN_SECONDS, 'disposable' ) ) ); ?></dd></div>
			</dl>
		</section>

		<section class="blockwarden-panel">
			<div class="blockwarden-panel-head">
				<h2><?php esc_html_e( 'Forms', 'blockwarden' ); ?></h2>
				<p><?php esc_html_e( 'WooCommerce My Account', 'blockwarden' ); ?></p>
			</div>
			<ul class="bw-checks">
				<?php
				foreach ( $forms as $form => $key ) :
					if ( ! BlockWarden_Settings::on( $key ) ) {
						$dot  = 'is-wait';
						$tag  = array( '', __( 'Off', 'blockwarden' ) );
						$text = __( 'The invisible pass is turned off for this form.', 'blockwarden' );
					} elseif ( BlockWarden_Pass::armed( $form ) ) {
						$dot  = 'is-on';
						$tag  = array( 'is-ok', __( 'Protected', 'blockwarden' ) );
						$text = __( 'Requests without a valid pass are refused.', 'blockwarden' );
					} else {
						$dot  = '';
						$tag  = array( 'is-would', __( 'Waiting for first visitor', 'blockwarden' ) );
						$text = __( 'Starts blocking as soon as one real visitor has used this form.', 'blockwarden' );
					}
					if ( 'register' === $form && ! $reg ) {
						$text .= ' ' . __( 'Account creation on My Account is turned off in WooCommerce, so this form is not shown.', 'blockwarden' );
					}
					?>
					<li>
						<span class="blockwarden-dot <?php echo esc_attr( $dot ); ?>"></span>
						<div><strong><?php echo esc_html( self::form_label( $form ) ); ?></strong><p><?php echo esc_html( $text ); ?></p></div>
						<span class="blockwarden-tag <?php echo esc_attr( $tag[0] ); ?>"><?php echo esc_html( $tag[1] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>

		<section class="blockwarden-panel">
			<div class="blockwarden-panel-head">
				<h2><?php esc_html_e( 'Recently stopped', 'blockwarden' ); ?></h2>
				<p><?php esc_html_e( 'Addresses are stored only as a scrambled fingerprint.', 'blockwarden' ); ?></p>
			</div>
			<?php if ( empty( $recent ) ) : ?>
				<div class="blockwarden-empty">
					<strong><?php esc_html_e( 'Nothing stopped yet', 'blockwarden' ); ?></strong>
					<?php esc_html_e( 'Blocked sign-ups, logins and password resets show up here with the reason.', 'blockwarden' ); ?>
				</div>
			<?php else : ?>
				<table class="blockwarden-table">
					<thead><tr>
						<th scope="col"><?php esc_html_e( 'When', 'blockwarden' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Form', 'blockwarden' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Why', 'blockwarden' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $recent as $row ) : ?>
						<tr>
							<td class="is-time">
								<?php
								/* translators: %s: time difference */
								echo esc_html( sprintf( __( '%s ago', 'blockwarden' ), human_time_diff( strtotime( $row['created_at'] . ' UTC' ) ) ) );
								?>
							</td>
							<td><?php echo esc_html( self::form_label( $row['form'] ) ); ?></td>
							<td><span class="blockwarden-tag is-blocked"><?php echo esc_html( self::reason_label( $row['reason'] ) ); ?></span></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Switch.
	 *
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @return void
	 */
	private static function field_switch( $key, $label ) {
		?>
		<label class="blockwarden-switch">
			<input type="checkbox" name="<?php echo esc_attr( BlockWarden_Settings::OPTION . '[' . $key . ']' ); ?>" value="yes" <?php checked( BlockWarden_Settings::get( $key ), 'yes' ); ?>>
			<span><?php echo esc_html( $label ); ?></span>
		</label>
		<?php
	}

	/**
	 * Number input.
	 *
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @return string
	 */
	private static function num( $key, $label ) {
		return sprintf(
			'<input type="number" min="0" step="1" name="%1$s" value="%2$s" aria-label="%3$s">',
			esc_attr( BlockWarden_Settings::OPTION . '[' . $key . ']' ),
			esc_attr( BlockWarden_Settings::get( $key ) ),
			esc_attr( $label )
		);
	}

	/**
	 * Sentence with inputs.
	 *
	 * @param string $html Escaped HTML.
	 * @return void
	 */
	private static function sentence( $html ) {
		echo '<p>' . wp_kses(
			$html,
			array(
				'input' => array(
					'type'       => true,
					'min'        => true,
					'step'       => true,
					'name'       => true,
					'value'      => true,
					'aria-label' => true,
				),
			)
		) . '</p>';
	}

	/**
	 * Settings.
	 *
	 * @return void
	 */
	private static function render_settings() {
		settings_errors();
		$opt = BlockWarden_Settings::OPTION;
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'blockwarden' ); ?>
			<section class="blockwarden-panel">
				<div class="blockwarden-panel-head"><h2><?php esc_html_e( 'Invisible pass', 'blockwarden' ); ?></h2></div>
				<div class="blockwarden-row">
					<div class="blockwarden-row-label"><?php esc_html_e( 'Forms', 'blockwarden' ); ?><p><?php esc_html_e( 'A real visit to the page gives the browser a signed pass. Bots that post directly do not have one.', 'blockwarden' ); ?></p></div>
					<div class="blockwarden-row-control">
						<?php self::field_switch( 'pass_register', __( 'Registration form', 'blockwarden' ) ); ?>
						<?php self::field_switch( 'pass_login', __( 'Login form', 'blockwarden' ) ); ?>
						<?php self::field_switch( 'pass_lost', __( 'Lost password form', 'blockwarden' ) ); ?>
					</div>
				</div>
			</section>

			<section class="blockwarden-panel">
				<div class="blockwarden-panel-head"><h2><?php esc_html_e( 'Limits', 'blockwarden' ); ?></h2></div>
				<div class="blockwarden-row">
					<div class="blockwarden-row-label"><?php esc_html_e( 'Failed logins', 'blockwarden' ); ?><p><?php esc_html_e( 'Applies to every login: My Account, wp-login.php and XML-RPC.', 'blockwarden' ); ?></p></div>
					<div class="blockwarden-row-control">
						<?php self::field_switch( 'lock_enabled', __( 'Lock out password guessers', 'blockwarden' ) ); ?>
						<?php
						self::sentence(
							sprintf(
								/* translators: 1: attempts, 2: minutes */
								esc_html__( 'After %1$s failed logins from one address, refuse logins from it for %2$s minutes.', 'blockwarden' ),
								self::num( 'lock_attempts', __( 'Attempts', 'blockwarden' ) ),
								self::num( 'lock_minutes', __( 'Minutes', 'blockwarden' ) )
							)
						);
						?>
					</div>
				</div>
				<div class="blockwarden-row">
					<div class="blockwarden-row-label"><?php esc_html_e( 'New accounts', 'blockwarden' ); ?><p><?php esc_html_e( 'Counts accounts made on My Account and at checkout.', 'blockwarden' ); ?></p></div>
					<div class="blockwarden-row-control">
						<?php self::field_switch( 'reg_limit', __( 'Limit sign-ups per address', 'blockwarden' ) ); ?>
						<?php
						self::sentence(
							sprintf(
								/* translators: %s: number */
								esc_html__( 'At most %s new accounts per hour from one address.', 'blockwarden' ),
								self::num( 'reg_per_hour', __( 'Accounts', 'blockwarden' ) )
							)
						);
						?>
					</div>
				</div>
				<div class="blockwarden-row">
					<div class="blockwarden-row-label"><?php esc_html_e( 'Password resets', 'blockwarden' ); ?><p><?php esc_html_e( 'Stops reset email floods that annoy customers and hurt your email reputation.', 'blockwarden' ); ?></p></div>
					<div class="blockwarden-row-control">
						<?php
						self::sentence(
							sprintf(
								/* translators: %s: number */
								esc_html__( 'At most %s reset requests per hour from one address.', 'blockwarden' ),
								self::num( 'lost_per_hour', __( 'Requests', 'blockwarden' ) )
							)
						);
						?>
					</div>
				</div>
			</section>

			<section class="blockwarden-panel">
				<div class="blockwarden-panel-head"><h2><?php esc_html_e( 'Temporary email addresses', 'blockwarden' ); ?></h2></div>
				<div class="blockwarden-row">
					<div class="blockwarden-row-label"><?php esc_html_e( 'Block them', 'blockwarden' ); ?><p><?php esc_html_e( 'A built-in list of throwaway inbox services.', 'blockwarden' ); ?></p></div>
					<div class="blockwarden-row-control">
						<?php self::field_switch( 'disposable', __( 'Refuse sign-ups with a temporary email address', 'blockwarden' ) ); ?>
						<p class="blockwarden-help">
							<?php
							/* translators: %s: number of domains */
							echo esc_html( sprintf( __( '%s domains are on the built-in list.', 'blockwarden' ), number_format_i18n( count( BlockWarden_Disposable::bundled() ) ) ) );
							?>
						</p>
					</div>
				</div>
				<div class="blockwarden-row">
					<div class="blockwarden-row-label"><label for="bw-blocked"><?php esc_html_e( 'Also block', 'blockwarden' ); ?></label><p><?php esc_html_e( 'One domain per line.', 'blockwarden' ); ?></p></div>
					<div class="blockwarden-row-control"><textarea id="bw-blocked" class="bw-list" name="<?php echo esc_attr( $opt ); ?>[blocked_domains]"><?php echo esc_textarea( BlockWarden_Settings::get( 'blocked_domains' ) ); ?></textarea></div>
				</div>
				<div class="blockwarden-row">
					<div class="blockwarden-row-label"><label for="bw-allowed"><?php esc_html_e( 'Always allow', 'blockwarden' ); ?></label><p><?php esc_html_e( 'Domains that should never be blocked.', 'blockwarden' ); ?></p></div>
					<div class="blockwarden-row-control"><textarea id="bw-allowed" class="bw-list" name="<?php echo esc_attr( $opt ); ?>[allowed_domains]"><?php echo esc_textarea( BlockWarden_Settings::get( 'allowed_domains' ) ); ?></textarea></div>
				</div>
			</section>

			<section class="blockwarden-panel">
				<div class="blockwarden-panel-head"><h2><?php esc_html_e( 'Data', 'blockwarden' ); ?></h2></div>
				<div class="blockwarden-row">
					<div class="blockwarden-row-label"><?php esc_html_e( 'Activity log', 'blockwarden' ); ?></div>
					<div class="blockwarden-row-control">
						<?php
						self::sentence(
							sprintf(
								/* translators: %s: days */
								esc_html__( 'Keep the log for %s days.', 'blockwarden' ),
								self::num( 'retention_days', __( 'Days', 'blockwarden' ) )
							)
						);
						?>
					</div>
				</div>
				<div class="blockwarden-row">
					<div class="blockwarden-row-label"><?php esc_html_e( 'When you delete the plugin', 'blockwarden' ); ?></div>
					<div class="blockwarden-row-control"><?php self::field_switch( 'delete_data', __( 'Also delete its settings and log', 'blockwarden' ) ); ?></div>
				</div>
			</section>

			<div class="blockwarden-actions">
				<?php submit_button( __( 'Save settings', 'blockwarden' ), 'blockwarden-btn', 'submit', false ); ?>
			</div>
		</form>
		<?php
	}

	/**
	 * Cleanup.
	 *
	 * @return void
	 */
	private static function render_cleanup() {
		$state = BlockWarden_Cleanup::state();
		$msg   = isset( $_GET['bw_msg'] ) ? sanitize_key( wp_unslash( $_GET['bw_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$msgs  = array(
			'started' => array( 'success', __( 'Cleanup started. It runs in the background in small batches.', 'blockwarden' ) ),
			'stopped' => array( 'success', __( 'Cleanup stopped.', 'blockwarden' ) ),
			'confirm' => array( 'error', __( 'Tick the box to confirm first.', 'blockwarden' ) ),
			'saved'   => array( 'success', __( 'Rule saved.', 'blockwarden' ) ),
		);
		if ( isset( $msgs[ $msg ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $msgs[ $msg ][0] ), esc_html( $msgs[ $msg ][1] ) );
		}
		$found = BlockWarden_Cleanup::find( 10000, 2000 );
		$count = count( $found['ids'] );
		$days  = (int) BlockWarden_Settings::get( 'cleanup_days' );
		?>
		<section class="blockwarden-panel">
			<div class="blockwarden-panel-head">
				<h2><?php esc_html_e( 'Fake customer accounts', 'blockwarden' ); ?></h2>
				<p><?php esc_html_e( 'Only accounts with the Customer role and nothing else.', 'blockwarden' ); ?></p>
			</div>
			<form class="blockwarden-row" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<div class="blockwarden-row-label"><?php esc_html_e( 'Which accounts', 'blockwarden' ); ?><p><?php esc_html_e( 'No orders, and not active on the site since they signed up.', 'blockwarden' ); ?></p></div>
				<div class="blockwarden-row-control">
					<input type="hidden" name="action" value="blockwarden_cleanup">
					<input type="hidden" name="do" value="rule">
					<?php wp_nonce_field( 'blockwarden_cleanup' ); ?>
					<?php
					self::sentence(
						sprintf(
							/* translators: %s: days */
							esc_html__( 'Signed up more than %s days ago.', 'blockwarden' ),
							sprintf( '<input type="number" min="7" step="1" name="days" value="%1$s" aria-label="%2$s">', esc_attr( $days ), esc_attr__( 'Days', 'blockwarden' ) )
						)
					);
					?>
					<?php submit_button( __( 'Update the count', 'blockwarden' ), 'blockwarden-btn-secondary', 'submit', false ); ?>
				</div>
			</form>
			<div class="blockwarden-row">
				<div class="blockwarden-row-label"><?php esc_html_e( 'Found', 'blockwarden' ); ?></div>
				<div class="blockwarden-row-control">
					<p>
						<strong>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: number of accounts */
								_n( '%s account matches.', '%s accounts match.', $count, 'blockwarden' ),
								number_format_i18n( $count ) . ( $found['more'] ? '+' : '' )
							)
						);
						?>
						</strong>
					</p>
					<?php if ( $state['running'] ) : ?>
						<p>
							<?php
							/* translators: %s: number deleted */
							echo esc_html( sprintf( __( 'Cleanup is running. %s accounts removed so far.', 'blockwarden' ), number_format_i18n( (int) $state['deleted'] ) ) );
							?>
						</p>
					<?php elseif ( $state['finished'] ) : ?>
						<p class="blockwarden-help">
							<?php
							/* translators: 1: number deleted, 2: time difference */
							echo esc_html( sprintf( __( 'Last cleanup removed %1$s accounts, %2$s ago.', 'blockwarden' ), number_format_i18n( (int) $state['deleted'] ), human_time_diff( (int) $state['finished'] ) ) );
							?>
						</p>
					<?php endif; ?>
				</div>
			</div>
			<?php if ( $count ) : ?>
				<ul class="bw-sample">
					<?php
					foreach ( array_slice( $found['ids'], 0, 8 ) as $id ) :
						$user = get_userdata( $id );
						if ( ! $user ) {
							continue;
						}
						?>
						<li>
							<?php
							/* translators: 1: username, 2: date */
							echo esc_html( sprintf( __( '%1$s, signed up %2$s', 'blockwarden' ), $user->user_login, date_i18n( get_option( 'date_format' ), strtotime( $user->user_registered . ' UTC' ) ) ) );
							?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>

		<form class="blockwarden-actions bw-inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="blockwarden_cleanup">
			<?php wp_nonce_field( 'blockwarden_cleanup' ); ?>
			<?php if ( $state['running'] ) : ?>
				<input type="hidden" name="do" value="stop">
				<?php submit_button( __( 'Stop cleanup', 'blockwarden' ), 'blockwarden-btn-secondary', 'submit', false ); ?>
			<?php elseif ( $count ) : ?>
				<input type="hidden" name="do" value="start">
				<label class="bw-confirm"><input type="checkbox" name="confirm" value="yes"> <?php esc_html_e( 'I understand these accounts will be deleted for good.', 'blockwarden' ); ?></label>
				<?php submit_button( __( 'Delete these accounts', 'blockwarden' ), 'blockwarden-btn', 'submit', false ); ?>
			<?php else : ?>
				<p class="bw-note"><?php esc_html_e( 'Nothing to clean up.', 'blockwarden' ); ?></p>
			<?php endif; ?>
		</form>
		<?php
	}

	/**
	 * Cleanup actions.
	 *
	 * @return void
	 */
	public static function handle_cleanup() {
		if ( ! current_user_can( self::CAP ) || ! current_user_can( 'delete_users' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'blockwarden' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'blockwarden_cleanup' );
		$do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		if ( 'rule' === $do ) {
			$all                 = BlockWarden_Settings::all();
			$all['cleanup_days'] = isset( $_POST['days'] ) ? absint( $_POST['days'] ) : 30;
			update_option( BlockWarden_Settings::OPTION, BlockWarden_Settings::sanitize( $all ) );
			$msg = 'saved';
		} elseif ( 'stop' === $do ) {
			BlockWarden_Cleanup::stop();
			$msg = 'stopped';
		} elseif ( isset( $_POST['confirm'] ) && 'yes' === $_POST['confirm'] ) {
			BlockWarden_Cleanup::start();
			$msg = 'started';
		} else {
			$msg = 'confirm';
		}
		wp_safe_redirect( self::url( 'cleanup', array( 'bw_msg' => $msg ) ) );
		exit;
	}
	/**
	 * Pro page: what the add-on adds. Shows "Coming soon" until a store link is set.
	 *
	 * @return void
	 */
	private static function render_pro() {
		$points = array(
			__( 'Emailed login codes for administrators and shop managers', 'blockwarden' ),
			__( 'Trusted browsers are not asked again for the days you choose', 'blockwarden' ),
			__( 'Allow or block new accounts by country', 'blockwarden' ),
			__( 'Attack alerts on Slack, Discord, Telegram or any webhook', 'blockwarden' ),
			__( 'Weekly security report by email', 'blockwarden' ),
		);
		/**
		 * Where to buy BlockWarden Pro. Empty shows "Coming soon".
		 *
		 * @param string $url URL.
		 */
		$buy = (string) apply_filters( 'blockwarden_pro_url', '' );
		?>
		<section class="ow-sale">
			<div class="ow-sale-main">
				<h2><?php esc_html_e( 'BlockWarden Pro', 'blockwarden' ); ?></h2>
				<p><?php esc_html_e( 'A second lock for your staff accounts and a say in who can sign up.', 'blockwarden' ); ?></p>
				<ul class="ow-points">
					<?php foreach ( $points as $point ) : ?>
						<li><?php echo esc_html( $point ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<div class="ow-sale-aside">
				<span class="ow-pro-badge"><?php esc_html_e( 'Pro', 'blockwarden' ); ?></span>
				<h3 style="margin-top:14px"><?php esc_html_e( 'A separate add-on', 'blockwarden' ); ?></h3>
				<p><?php esc_html_e( 'Everything you use today stays free, with no limits. Pro adds the features on the left.', 'blockwarden' ); ?></p>
				<?php if ( '' === $buy ) : ?>
					<p><strong><?php esc_html_e( 'Coming soon.', 'blockwarden' ); ?></strong></p>
				<?php else : ?>
					<a class="button ow-btn-gold" href="<?php echo esc_url( $buy ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'See plans', 'blockwarden' ); ?></a>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}
}
