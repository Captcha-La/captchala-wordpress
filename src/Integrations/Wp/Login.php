<?php
/**
 * Protect wp-login.php login form.
 *
 * @package Captchala\Wp
 */

declare( strict_types=1 );

namespace Captchala\Wp\Integrations\Wp;

use Captchala\Cms\Action;
use Captchala\Wp\Integrations\AbstractIntegration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Login extends AbstractIntegration {

	public static function option_key(): string {
		return 'wp_login';
	}

	protected function action(): string {
		return Action::LOGIN;
	}

	public function init(): void {
		add_action( 'login_form',            [ $this, 'render_field' ] );
		add_action( 'login_enqueue_scripts', [ $this, 'login_styles' ] );
		// Hook at very low priority so we run AFTER WP's
		// wp_authenticate_username_password (priority 20). We can't run before
		// it because it ignores WP_Error returns from earlier filters and
		// re-runs the username lookup, leaking "user not registered". Running
		// last lets us OVERRIDE whatever upstream said with a single uniform
		// captcha error — kills user-enumeration through error-message
		// disambiguation.
		add_filter( 'authenticate', [ $this, 'authenticate' ], 999, 3 );
	}

	public function render_field(): void {
		$this->print_widget();
	}

	public function login_styles(): void {
		// Make the captcha block sit nicely inside the wp-login.php form.
		?>
		<style id="captchala-login-style">
		.login form [data-captchala] { margin: 12px 0; }
		</style>
		<?php
	}

	/**
	 * @param \WP_User|\WP_Error|null $user      Filter input — null at the start of the chain.
	 * @param string                  $username  Submitted username.
	 * @param string                  $password  Submitted password.
	 * @return \WP_User|\WP_Error|null
	 */
	public function authenticate( $user, $username = '', $password = '' ) {
		// Skip XMLRPC / REST so site owners can still automate.
		if ( ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $user;
		}
		// Skip on non-POSTs (the authenticate filter also fires on cookie auth).
		$method = isset( $_SERVER['REQUEST_METHOD'] )
			? strtoupper( sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_METHOD'] ) ) )
			: '';
		if ( $method !== 'POST' ) {
			return $user;
		}
		// Skip when no credentials were submitted (e.g. cookie-based re-auth).
		if ( $username === '' && $password === '' ) {
			return $user;
		}
		// Skip if Woo is handling its own login form (Woo integration covers it).
		// We sniff for the field-name presence only — the value is never read,
		// so the host form's own nonce check still gates the actual submission.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- presence-only check; value is not used.
		if ( isset( $_POST['woocommerce-login-nonce'] ) ) {
			return $user;
		}
		// Trusted-role bypass.
		if ( $this->plugin->should_skip_for_user() ) {
			return $user;
		}
		$result = $this->validate();
		if ( ! $result->isValid() ) {
			// Short-circuit the entire authenticate chain — WP will not look up
			// the username, so the response is identical for "user doesn't
			// exist" and "wrong password", killing user enumeration.
			return new \WP_Error(
				'captchala_failed',
				$this->plugin->error_message( $result->getError() )
			);
		}
		return $user;
	}
}
