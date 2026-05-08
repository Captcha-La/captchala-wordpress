<?php
/**
 * Protect the lost-password form.
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

final class LostPassword extends AbstractIntegration {

	public static function option_key(): string {
		return 'wp_lost_password';
	}

	protected function action(): string {
		return Action::LOST_PASSWORD;
	}

	public function init(): void {
		add_action( 'lostpassword_form', [ $this, 'render_field' ] );
		add_action( 'lostpassword_post', [ $this, 'validate_lost_password' ], 10, 1 );
	}

	public function render_field(): void {
		$this->print_widget();
	}

	/**
	 * @param \WP_Error $errors
	 */
	public function validate_lost_password( $errors ): void {
		if ( ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		if ( $this->plugin->should_skip_for_user() ) {
			return;
		}
		$result = $this->validate();
		if ( ! $result->isValid() && $errors instanceof \WP_Error ) {
			// Halt the lost-password flow before WP looks up the user/email,
			// so the response is identical for "user not found" and
			// "user found, mail sent" — kills email-enumeration through
			// the "we just sent you a reset link" message.
			$errors->add( 'captchala_failed', $this->plugin->error_message( $result->getError() ) );
		}
	}
}
