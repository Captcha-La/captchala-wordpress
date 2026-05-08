<?php
/**
 * Main plugin singleton — boots settings, integrations, and the
 * server_token request lifecycle.
 *
 * @package Captchala\Wp
 */

declare( strict_types=1 );

namespace Captchala\Wp;

use Captchala\Client;
use Captchala\Cms\Action;
use Captchala\Cms\Errors;
use Captchala\Cms\Widget;
use Captchala\IssueResult;
use Captchala\ValidateResult;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// =============================================================================
// v1.1 third-party integrations to revisit (intentionally deferred from v1.0):
//   - Avada / Fusion Builder forms
//   - Beaver Builder
//   - Brizy
//   - Kadence Blocks
//   - WPDiscuz
//   - Ninja Forms (NF)
//   - Quform
//   - Spectra (Ultimate Addons)
//   - Otter Blocks
//   - Mailchimp For WP (MC4WP)
//   - Tutor LMS / LearnDash / LearnPress
//   - SureForms
//   - Jetpack
//   - Paid Memberships Pro
//   - Asgaros Forum
//   - Theme My Login / WPDiscuz / WP Job Openings
// =============================================================================

/**
 * Plugin orchestrator.
 *
 * Wires the WP settings page, every core / Woo / third-party integration,
 * and exposes a per-request cache for `sct_` server tokens so a single
 * page render only costs one issue call regardless of how many widgets
 * the page emits.
 */
final class Plugin {

	public const OPTION_KEY            = 'captchala_settings';
	public const NONCE_ACTION          = 'captchala_admin';
	public const AJAX_TEST_CONNECTION  = 'captchala_test_connection';
	public const AJAX_REFRESH_TOKEN    = 'captchala_refresh_token';
	public const HIDDEN_INPUT_NAME     = 'captchala_token';
	public const TEXT_DOMAIN           = 'captchala';

	/**
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * @var array<string,mixed>
	 */
	private array $settings;

	/**
	 * @var Client|null
	 */
	private ?Client $client = null;

	/**
	 * Per-request cache: action => sct_ token. Single page render only ever
	 * issues one server_token per action, no matter how many integrations
	 * render a widget for that action.
	 *
	 * @var array<string,string>
	 */
	private array $token_cache = [];

	/**
	 * Boot from `plugins_loaded`. Idempotent.
	 */
	public static function boot(): void {
		if ( self::$instance instanceof self ) {
			return;
		}
		self::$instance = new self();
		self::$instance->init();
	}

	public static function instance(): self {
		if ( ! self::$instance instanceof self ) {
			self::$instance = new self();
			self::$instance->init();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->settings = $this->load_settings();
	}

	// -------------------------------------------------------------------------
	// Lifecycle
	// -------------------------------------------------------------------------

	private function init(): void {
		// i18n: WordPress 4.6+ auto-loads translations from the plugin slug
		// directory on wordpress.org — calling load_plugin_textdomain() is
		// discouraged by the plugin checker and unnecessary.

		// Settings + admin
		( new Settings\SettingsPage( $this ) )->init();

		// AJAX endpoints (nonce-checked, manage_options-gated)
		add_action( 'wp_ajax_' . self::AJAX_TEST_CONNECTION, [ $this, 'ajax_test_connection' ] );

		// Refresh token endpoint — public (logged-in or not). The widget
		// calls this when the SDK reports the sct_ / challenge expired
		// after the page sat idle, so the user can keep submitting without
		// a full page reload.
		add_action( 'wp_ajax_'        . self::AJAX_REFRESH_TOKEN, [ $this, 'ajax_refresh_token' ] );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_REFRESH_TOKEN, [ $this, 'ajax_refresh_token' ] );

		// Plugin row "Settings" link
		add_filter( 'plugin_action_links_' . plugin_basename( CAPTCHALA_WP_FILE ), [ $this, 'add_action_links' ] );

		// Abilities API (WP 6.5+)
		if ( class_exists( Abilities\AbilitiesProvider::class ) ) {
			( new Abilities\AbilitiesProvider( $this ) )->init();
		}

		// Skip everything if keys aren't configured.
		if ( ! $this->is_configured() ) {
			return;
		}

		// Wire up core, Woo, and third-party form integrations.
		$this->register_integrations();
	}

	public function add_action_links( array $actions ): array {
		$url = admin_url( 'options-general.php?page=captchala' );
		array_unshift(
			$actions,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( $url ),
				esc_html__( 'Settings', 'captchala' )
			)
		);
		return $actions;
	}

	// -------------------------------------------------------------------------
	// Integrations
	// -------------------------------------------------------------------------

	private function register_integrations(): void {
		// NOTE: do NOT short-circuit on should_skip_for_user() here. Hooks must
		// be registered for *every* request so that anonymous form submits
		// run through validation. Each integration calls should_skip_for_user()
		// per-render and per-validate so admins still bypass the challenge on
		// the forms they're submitting themselves.

		// --- WordPress core ---
		if ( $this->is_enabled( 'wp_login' ) )         { ( new Integrations\Wp\Login( $this ) )->init(); }
		if ( $this->is_enabled( 'wp_register' ) )      { ( new Integrations\Wp\Register( $this ) )->init(); }
		if ( $this->is_enabled( 'wp_lost_password' ) ) { ( new Integrations\Wp\LostPassword( $this ) )->init(); }
		if ( $this->is_enabled( 'wp_comment' ) )       { ( new Integrations\Wp\Comment( $this ) )->init(); }

		// --- WooCommerce (built-in sub-module) ---
		if ( $this->is_woocommerce_active() ) {
			if ( $this->is_enabled( 'wc_login' ) )          { ( new Integrations\Wc\Login( $this ) )->init(); }
			if ( $this->is_enabled( 'wc_register' ) )       { ( new Integrations\Wc\Register( $this ) )->init(); }
			if ( $this->is_enabled( 'wc_lost_password' ) )  { ( new Integrations\Wc\LostPassword( $this ) )->init(); }
			if ( $this->is_enabled( 'wc_account_create' ) ) { ( new Integrations\Wc\AccountCreate( $this ) )->init(); }
			if ( $this->is_enabled( 'wc_checkout' ) )       { ( new Integrations\Wc\Checkout( $this ) )->init(); }
			if ( $this->is_enabled( 'wc_pay_for_order' ) )  { ( new Integrations\Wc\PayForOrder( $this ) )->init(); }
		}

		// --- Third-party form plugins (init only if host is loaded) ---
		$third_party = [
			Integrations\Cf7::class,
			Integrations\Wpforms::class,
			Integrations\Gravity::class,
			Integrations\Forminator::class,
			Integrations\Formidable::class,
			Integrations\Fluent::class,
			Integrations\Elementor::class,
			Integrations\Divi::class,
			Integrations\BuddyPress::class,
			Integrations\Bbpress::class,
			Integrations\UltimateMember::class,
			Integrations\MemberPress::class,
			Integrations\Edd::class,
			Integrations\MailPoet::class,
		];
		foreach ( $third_party as $cls ) {
			/** @var class-string<Integrations\IntegrationInterface> $cls */
			if ( class_exists( $cls ) && $cls::is_available() && $this->is_enabled( $cls::option_key() ) ) {
				( new $cls( $this ) )->init();
			}
		}
	}

	// -------------------------------------------------------------------------
	// Settings
	// -------------------------------------------------------------------------

	/**
	 * @return array<string,mixed>
	 */
	private function load_settings(): array {
		$saved = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $saved ) ) {
			$saved = [];
		}
		return array_merge( $this->default_settings(), $saved );
	}

	/**
	 * @return array<string,mixed>
	 */
	public function default_settings(): array {
		return [
			'app_key'            => '',
			'app_secret'         => '',
			'product'            => 'bind',      // popup|float|embed|bind
			'lang'               => 'auto',
			'theme'              => 'default',   // default|dark|stereoscopic|custom
			'theme_color'        => '',          // hex like '#3B82F6' (only when theme=custom)
			'theme_gradient'     => '',          // CSS gradient string (custom only)
			'theme_hover'        => '',          // CSS gradient string for hover (custom only)
			'theme_brightness'   => 'system',    // system|light|dark (custom only)
			'theme_radius'       => '',          // CSS length, e.g. '8px' (custom only)
			'skip_for_admins'    => 1,

			// WordPress core toggles
			'wp_login'           => 1,
			'wp_register'        => 1,
			'wp_lost_password'   => 1,
			'wp_comment'         => 1,

			// WooCommerce toggles
			'wc_login'           => 1,
			'wc_register'        => 1,
			'wc_lost_password'   => 1,
			'wc_account_create'  => 1,
			'wc_checkout'        => 1,
			'wc_pay_for_order'   => 1,

			// Third-party toggles (default on; integration only attaches if host is active)
			'cf7'                => 1,
			'wpforms'            => 1,
			'gravity'            => 1,
			'forminator'         => 1,
			'formidable'         => 1,
			'fluent'             => 1,
			'elementor'          => 1,
			'divi'               => 1,
			'buddypress'         => 1,
			'bbpress'            => 1,
			'ultimatemember'     => 1,
			'memberpress'        => 1,
			'edd'                => 1,
			'mailpoet'           => 1,
		];
	}

	public function get_setting( string $key, mixed $default = null ): mixed {
		return $this->settings[ $key ] ?? $default;
	}

	public function is_enabled( string $key ): bool {
		return ! empty( $this->settings[ $key ] );
	}

	public function is_configured(): bool {
		return ! empty( $this->settings['app_key'] ) && ! empty( $this->settings['app_secret'] );
	}

	public function should_skip_for_user(): bool {
		if ( empty( $this->settings['skip_for_admins'] ) ) {
			return false;
		}
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$skip = current_user_can( 'manage_options' ) || current_user_can( 'edit_others_posts' );
		/**
		 * Filter whether to skip CAPTCHA for the current user.
		 *
		 * @param bool $skip Whether to skip.
		 */
		return (bool) apply_filters( 'captchala_skip_for_user', $skip );
	}

	// -------------------------------------------------------------------------
	// SDK access
	// -------------------------------------------------------------------------

	public function client(): Client {
		if ( ! $this->client instanceof Client ) {
			$this->client = new Client(
				(string) $this->get_setting( 'app_key', '' ),
				(string) $this->get_setting( 'app_secret', '' )
			);
		}
		return $this->client;
	}

	/**
	 * Return a per-request cached server_token for the given action, issuing
	 * one lazily the first time it's requested. On failure, returns an empty
	 * string — the widget will still render its DOM but the loader will get
	 * an empty server_token, which the dashboard treats as a hard fail.
	 */
	public function server_token( string $action ): string {
		if ( isset( $this->token_cache[ $action ] ) ) {
			return $this->token_cache[ $action ];
		}
		if ( ! Action::isValid( $action ) ) {
			return '';
		}
		$ip     = $this->remote_ip();
		$result = $this->client()->issueServerToken( $action, $ip, 300, 3, null );
		if ( ! $result instanceof IssueResult || ! $result->isOk() ) {
			return $this->token_cache[ $action ] = '';
		}
		return $this->token_cache[ $action ] = (string) $result->getToken();
	}

	/**
	 * Render the canonical CaptchaLa widget HTML for an action.
	 *
	 * @param string               $action  An `Action::*` string.
	 * @param array<string,mixed>  $extra   Extra opts forwarded to Widget::renderHtml.
	 */
	public function render_widget( string $action, array $extra = [] ): string {
		if ( ! Action::isValid( $action ) || ! $this->is_configured() ) {
			return '';
		}
		// Honour the saved Widget mode setting; fall through to 'bind' on
		// any unexpected value so plugin updates that drop modes don't break
		// existing installs.
		$saved_product = (string) $this->get_setting( 'product', 'bind' );
		$product       = in_array( $saved_product, [ 'bind', 'embed', 'float', 'popup' ], true )
			? $saved_product
			: 'bind';
		$opts = array_merge(
			[
				'product'          => $product,
				'lang'             => (string) $this->get_setting( 'lang', 'auto' ),
				'theme'            => (string) $this->get_setting( 'theme', 'default' ),
				'theme_color'      => (string) $this->get_setting( 'theme_color', '' ),
				'theme_gradient'   => (string) $this->get_setting( 'theme_gradient', '' ),
				'theme_hover'      => (string) $this->get_setting( 'theme_hover', '' ),
				'theme_brightness' => (string) $this->get_setting( 'theme_brightness', 'system' ),
				'theme_radius'     => (string) $this->get_setting( 'theme_radius', '' ),
				'hidden_input'     => true,
				// Boot-script can re-fetch a fresh sct_ from this endpoint
				// after the SDK reports an expired-token error, so the user
				// keeps interacting without a full page reload.
				'refresh_url'      => admin_url( 'admin-ajax.php' ),
				'refresh_action'   => self::AJAX_REFRESH_TOKEN,
				'refresh_scene'    => $action,
			],
			$extra
		);
		return Widget::renderHtml(
			(string) $this->get_setting( 'app_key', '' ),
			$this->server_token( $action ),
			$action,
			$opts
		);
	}

	/**
	 * Validate the posted token. Returns ValidateResult; the integration
	 * is responsible for converting failure into the host CMS's native
	 * error stack (WP_Error / wc_add_notice / etc.).
	 */
	public function validate_request( string $action ): ValidateResult {
		// We're hooking into host forms (wp-login.php, Woo my-account, comment
		// form, CF7, etc.) that already nonce-verify their own submissions.
		// The captchala_token field is just an extra anti-bot ticket we read
		// alongside the host form's payload — its own nonce check guards the
		// request integrity. The pt_ token itself is unguessable + single-use
		// + IP-bound + action-bound, so a spoofed POST without the nonce can't
		// pass validation either.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- host form's own nonce is verified upstream; pt_ token is single-use + IP-bound.
		$token = isset( $_POST[ self::HIDDEN_INPUT_NAME ] )
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- see above.
			? sanitize_text_field( wp_unslash( (string) $_POST[ self::HIDDEN_INPUT_NAME ] ) )
			: '';
		return $this->client()->validate( $token, false, $this->remote_ip() );
	}

	public function error_message( ?string $code ): string {
		// The SDK gives us an English source string; we expose the
		// already-translated wp.org-friendly variants for the messages we
		// actually emit. Unknown codes fall back to the SDK's generic line.
		$source = Errors::standardize( $code );
		$known  = [
			'CAPTCHA verification did not pass. Please try again.'
				=> __( 'CAPTCHA verification did not pass. Please try again.', 'captchala' ),
			'CAPTCHA token already used. Please refresh and try again.'
				=> __( 'CAPTCHA token already used. Please refresh and try again.', 'captchala' ),
			'CAPTCHA token expired. Please refresh and try again.'
				=> __( 'CAPTCHA token expired. Please refresh and try again.', 'captchala' ),
			'CAPTCHA was solved for a different action. Please refresh.'
				=> __( 'CAPTCHA was solved for a different action. Please refresh.', 'captchala' ),
			'CAPTCHA was solved from a different network. Please refresh.'
				=> __( 'CAPTCHA was solved from a different network. Please refresh.', 'captchala' ),
			'CAPTCHA was not solved by the expected user.'
				=> __( 'CAPTCHA was not solved by the expected user.', 'captchala' ),
			'CAPTCHA service is temporarily unavailable. Please try again shortly.'
				=> __( 'CAPTCHA service is temporarily unavailable. Please try again shortly.', 'captchala' ),
			'Too many CAPTCHA attempts. Please wait a moment.'
				=> __( 'Too many CAPTCHA attempts. Please wait a moment.', 'captchala' ),
			'CAPTCHA configuration error — please contact the site owner.'
				=> __( 'CAPTCHA configuration error — please contact the site owner.', 'captchala' ),
			'CAPTCHA is currently disabled for this site.'
				=> __( 'CAPTCHA is currently disabled for this site.', 'captchala' ),
		];
		return $known[ $source ] ?? $source;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	public function remote_ip(): ?string {
		// Honour reverse proxies that WP recognises; fall back to REMOTE_ADDR.
		foreach ( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' ] as $h ) {
			if ( empty( $_SERVER[ $h ] ) ) {
				continue;
			}
			$raw = sanitize_text_field( wp_unslash( (string) $_SERVER[ $h ] ) );
			$ip  = trim( explode( ',', $raw )[0] );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}
		return null;
	}

	public function is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	// -------------------------------------------------------------------------
	// AJAX handlers
	// -------------------------------------------------------------------------

	public function ajax_test_connection(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Forbidden.', 'captchala' ) ], 403 );
		}

		$app_key    = isset( $_POST['app_key'] )    ? sanitize_text_field( wp_unslash( (string) $_POST['app_key'] ) )    : '';
		$app_secret = isset( $_POST['app_secret'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['app_secret'] ) ) : '';

		if ( $app_key === '' || $app_secret === '' ) {
			wp_send_json_error(
				[ 'message' => __( 'Please enter App Key and App Secret first.', 'captchala' ) ],
				400
			);
		}

		$client = new Client( $app_key, $app_secret );
		$result = $client->issueServerToken( Action::LOGIN, '127.0.0.1', 60, 1, null );

		if ( $result->isOk() ) {
			wp_send_json_success(
				[
					'message' => __( 'Connection OK. CaptchaLa accepted your credentials.', 'captchala' ),
					'token'   => substr( (string) $result->getToken(), 0, 12 ) . '…',
				]
			);
		}

		wp_send_json_error(
			[
				'message' => $this->error_message( $result->getError() ),
				'code'    => $result->getError(),
			],
			502
		);
	}

	/**
	 * Mint a fresh sct_ for the requested action so the front-end widget
	 * can re-mount after the previous server_token / challenge expired.
	 * Public endpoint (anonymous and logged-in both call it from the
	 * widget bootstrap).
	 */
	public function ajax_refresh_token(): void {
		// Public endpoint by design — fired from the widget bootstrap on
		// pages where the user may not yet be authenticated (wp-login.php,
		// register, comment form, etc.). A nonce wouldn't help: the page
		// rendering the widget already sent a fresh sct_ via the same
		// channel; this endpoint just re-mints one of equivalent capability,
		// scoped to a known action whitelist (Action::isValid below) and
		// rate-limited by the dashboard backend.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- public refresh endpoint; whitelisted action + dashboard rate-limit guard the call.
		$action = isset( $_POST['action_name'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- see above.
			? sanitize_text_field( wp_unslash( (string) $_POST['action_name'] ) )
			: '';
		if ( ! Action::isValid( $action ) ) {
			wp_send_json_error( [ 'error' => 'invalid_action' ], 400 );
		}

		// Drop any cached server_token for this action so server_token()
		// issues a fresh one.
		unset( $this->token_cache[ $action ] );
		$server_token = $this->server_token( $action );
		if ( $server_token === '' ) {
			wp_send_json_error( [ 'error' => 'request_failed' ], 502 );
		}

		wp_send_json_success( [
			'server_token' => $server_token,
			'action'       => $action,
		] );
	}

}
