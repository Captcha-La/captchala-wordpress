<?php
/**
 * Protect the WooCommerce lost-password form.
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

final class LostPassword extends AbstractIntegration {

	public static function option_key(): string {
		return 'wc_lost_password';
	}

	protected function action(): string {
		return Action::LOST_PASSWORD;
	}

	public function init(): void {
		add_action( 'woocommerce_lostpassword_form', [ $this, 'render_field' ] );
		add_action( 'lostpassword_post',             [ $this, 'validate_lost_password' ], 9, 1 );
	}

	public function render_field(): void {
		$this->print_widget();
	}

	/**
	 * @param \WP_Error $errors
	 */
	public function validate_lost_password( $errors ): void {
		// Only handle when the post originated from the Woo nonce — the
		// generic WP integration handles native wp-login.php submissions.
		// Presence-only sniff; the value isn't read here, Woo verifies the
		// nonce itself before lostpassword_post fires.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- presence-only check; value is not used; Woo verifies the nonce upstream.
		if ( ! isset( $_POST['woocommerce-lost-password-nonce'] ) ) {
			return;
		}
		if ( ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		$result = $this->validate();
		if ( ! $result->isValid() && $errors instanceof \WP_Error ) {
			$errors->add( 'captchala_failed', $this->plugin->error_message( $result->getError() ) );
		}
	}
}
