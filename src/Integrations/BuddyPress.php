<?php
/**
 * BuddyPress integration — protects signup form.
 *
 * @package Captchala\Wp
 */

declare( strict_types=1 );

namespace Captchala\Wp\Integrations;

use Captchala\Cms\Action;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class BuddyPress extends AbstractIntegration {

	public static function is_available(): bool {
		return class_exists( 'BuddyPress' );
	}

	public static function option_key(): string {
		return 'buddypress';
	}

	protected function action(): string {
		return Action::REGISTER;
	}

	public function init(): void {
		add_action( 'bp_before_registration_submit_buttons', [ $this, 'render_field' ] );
		add_action( 'bp_signup_validate', [ $this, 'validate_submit' ] );
	}

	public function render_field(): void {
		$this->print_widget();
	}

	public function validate_submit(): void {
		$result = $this->validate();
		if ( ! $result->isValid() ) {
			global $bp;
			if ( isset( $bp->signup->errors ) && is_array( $bp->signup->errors ) ) {
				$bp->signup->errors['captchala'] = $this->plugin->error_message( $result->getError() );
			}
		}
	}
}
