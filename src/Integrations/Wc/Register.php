<?php
/**
 * Protect the WooCommerce my-account registration form.
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

final class Register extends AbstractIntegration {

	public static function option_key(): string {
		return 'wc_register';
	}

	protected function action(): string {
		return Action::REGISTER;
	}

	public function init(): void {
		add_action( 'woocommerce_register_form',   [ $this, 'render_field' ] );
		add_action( 'woocommerce_register_post',   [ $this, 'validate_register' ], 10, 3 );
	}

	public function render_field(): void {
		$this->print_widget();
	}

	/**
	 * @param string    $username
	 * @param string    $email
	 * @param \WP_Error $errors
	 */
	public function validate_register( $username, $email, $errors ): void {
		if ( ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		// Don't double-validate when user is registering through checkout.
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return;
		}
		$result = $this->validate();
		if ( ! $result->isValid() && $errors instanceof \WP_Error ) {
			$errors->add( 'captchala_failed', $this->plugin->error_message( $result->getError() ) );
		}
	}
}
