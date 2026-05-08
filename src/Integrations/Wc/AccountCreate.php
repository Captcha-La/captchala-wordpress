<?php
/**
 * Protect the "Create account during checkout" path.
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

final class AccountCreate extends AbstractIntegration {

	public static function option_key(): string {
		return 'wc_account_create';
	}

	protected function action(): string {
		return Action::ACCOUNT_CREATE;
	}

	public function init(): void {
		// During shortcode-style checkout, Woo fires `woocommerce_after_checkout_registration_form`
		// only if account creation is enabled and the user isn't logged in.
		add_action( 'woocommerce_after_checkout_registration_form', [ $this, 'render_field' ] );
		// Account creation validation hook. Fired before user registration.
		add_action( 'woocommerce_register_post', [ $this, 'validate_account_create' ], 9, 3 );
	}

	public function render_field(): void {
		$this->print_widget();
	}

	/**
	 * @param string    $username
	 * @param string    $email
	 * @param \WP_Error $errors
	 */
	public function validate_account_create( $username, $email, $errors ): void {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}
		$result = $this->validate();
		if ( ! $result->isValid() && $errors instanceof \WP_Error ) {
			$errors->add( 'captchala_failed', $this->plugin->error_message( $result->getError() ) );
		}
	}
}
