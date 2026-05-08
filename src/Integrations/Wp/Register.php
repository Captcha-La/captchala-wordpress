<?php
/**
 * Protect wp-login.php registration form.
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

final class Register extends AbstractIntegration {

	public static function option_key(): string {
		return 'wp_register';
	}

	protected function action(): string {
		return Action::REGISTER;
	}

	public function init(): void {
		add_action( 'register_form',       [ $this, 'render_field' ] );
		add_filter( 'registration_errors', [ $this, 'validate_registration' ], 10, 3 );
	}

	public function render_field(): void {
		$this->print_widget();
	}

	/**
	 * @param \WP_Error $errors
	 * @param string    $sanitized_user_login
	 * @param string    $user_email
	 * @return \WP_Error
	 */
	public function validate_registration( $errors, $sanitized_user_login, $user_email ) {
		if ( ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $errors;
		}
		if ( $this->plugin->should_skip_for_user() ) {
			return $errors;
		}
		$result = $this->validate();
		if ( ! $result->isValid() ) {
			// Replace the entire errors object — by the time this filter runs,
			// WP has already populated $errors with "username exists" or
			// "email taken" messages, leaking registration state. Returning
			// only our captcha error kills the enumeration channel.
			return new \WP_Error(
				'captchala_failed',
				$this->plugin->error_message( $result->getError() )
			);
		}
		return $errors;
	}
}
