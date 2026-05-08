<?php
/**
 * Easy Digital Downloads integration — protects checkout.
 *
 * @package Captchala\Wp
 */

declare( strict_types=1 );

namespace Captchala\Wp\Integrations;

use Captchala\Cms\Action;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Edd extends AbstractIntegration {

	public static function is_available(): bool {
		return class_exists( 'Easy_Digital_Downloads' );
	}

	public static function option_key(): string {
		return 'edd';
	}

	protected function action(): string {
		return Action::CHECKOUT;
	}

	public function init(): void {
		add_action( 'edd_purchase_form_before_submit', [ $this, 'render_field' ] );
		add_action( 'edd_checkout_error_checks',       [ $this, 'validate_submit' ], 10, 2 );
	}

	public function render_field(): void {
		$this->print_widget();
	}

	/**
	 * @param array $valid_data
	 * @param array $post
	 */
	public function validate_submit( $valid_data, $post ): void {
		$result = $this->validate();
		if ( ! $result->isValid() && function_exists( 'edd_set_error' ) ) {
			edd_set_error( 'captchala_failed', $this->plugin->error_message( $result->getError() ) );
		}
	}
}
