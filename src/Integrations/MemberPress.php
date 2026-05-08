<?php
/**
 * MemberPress integration — login + register forms.
 *
 * @package Captchala\Wp
 */

declare( strict_types=1 );

namespace Captchala\Wp\Integrations;

use Captchala\Cms\Action;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MemberPress extends AbstractIntegration {

	public static function is_available(): bool {
		return class_exists( 'MeprBaseCtrl' ) || class_exists( 'MeprCtrlFactory' );
	}

	public static function option_key(): string {
		return 'memberpress';
	}

	protected function action(): string {
		return Action::REGISTER;
	}

	public function init(): void {
		add_action( 'mepr-register-form-before-submit',  [ $this, 'render_field' ] );
		add_action( 'mepr-login-form-before-submit',     [ $this, 'render_field' ] );
		add_filter( 'mepr-validate-signup',              [ $this, 'validate_signup' ] );
		add_filter( 'mepr-validate-login',               [ $this, 'validate_login' ] );
	}

	public function render_field(): void {
		$this->print_widget();
	}

	public function validate_signup( $errors ) {
		$result = $this->validate();
		if ( ! $result->isValid() ) {
			$errors[] = $this->plugin->error_message( $result->getError() );
		}
		return $errors;
	}

	public function validate_login( $errors ) {
		return $this->validate_signup( $errors );
	}
}
