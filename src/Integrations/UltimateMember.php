<?php
/**
 * Ultimate Member integration — login + register forms.
 *
 * @package Captchala\Wp
 */

declare( strict_types=1 );

namespace Captchala\Wp\Integrations;

use Captchala\Cms\Action;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class UltimateMember extends AbstractIntegration {

	public static function is_available(): bool {
		return class_exists( 'UM' );
	}

	public static function option_key(): string {
		return 'ultimatemember';
	}

	protected function action(): string {
		return Action::REGISTER;
	}

	public function init(): void {
		add_action( 'um_after_register_fields',         [ $this, 'render_field' ] );
		add_action( 'um_after_login_fields',            [ $this, 'render_field' ] );
		add_action( 'um_submit_form_errors_hook__registration', [ $this, 'validate_submit' ] );
		add_action( 'um_submit_form_errors_hook_login',          [ $this, 'validate_submit' ] );
	}

	public function render_field(): void {
		$this->print_widget();
	}

	/**
	 * @param array $args
	 */
	public function validate_submit( $args ): void {
		$result = $this->validate();
		if ( ! $result->isValid() && function_exists( 'UM' ) ) {
			UM()->form()->add_error( 'captchala_token', $this->plugin->error_message( $result->getError() ) );
		}
	}
}
