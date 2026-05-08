<?php
/**
 * Protect the WooCommerce my-account login form.
 *
 * @package Captchala\Wp
 */

declare( strict_types=1 );

namespace Captchala\Wp\Integrations\Wc;

use Captchala\Cms\Action;
use Captchala\Wp\Integrations\AbstractIntegration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Login extends AbstractIntegration {

	public static function option_key(): string {
		return 'wc_login';
	}

	protected function action(): string {
		return Action::LOGIN;
	}

	public function init(): void {
		add_action( 'woocommerce_login_form', [ $this, 'render_field' ] );
		add_filter( 'authenticate',           [ $this, 'authenticate' ], 21, 1 );
	}

	public function render_field(): void {
		$this->print_widget();
	}

	/**
	 * @param \WP_User|\WP_Error|null|mixed $user
	 * @return mixed
	 */
	public function authenticate( $user ) {
		// Presence-only sniff to detect that this authenticate() call came
		// through Woo's my-account login form (vs e.g. wp-login.php). Woo
		// itself nonce-verifies the submission before our captcha hook runs.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- presence-only check; value is not used; Woo verifies the nonce upstream.
		if ( ! isset( $_POST['woocommerce-login-nonce'] ) ) {
			return $user;
		}
		if ( ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $user;
		}
		// Don't bother with already-failed authenticators.
		if ( is_wp_error( $user ) ) {
			return $user;
		}
		$result = $this->validate();
		if ( ! $result->isValid() ) {
			return new \WP_Error(
				'captchala_failed',
				$this->plugin->error_message( $result->getError() )
			);
		}
		return $user;
	}
}
