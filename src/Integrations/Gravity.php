<?php
/**
 * Gravity Forms integration.
 *
 * @package Captchala\Wp
 */

declare( strict_types=1 );

namespace Captchala\Wp\Integrations;

use Captchala\Cms\Action;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gravity extends AbstractIntegration {

	public static function is_available(): bool {
		return class_exists( 'GFForms' );
	}

	public static function option_key(): string {
		return 'gravity';
	}

	protected function action(): string {
		return Action::CONTACT_FORM;
	}

	public function init(): void {
		// Inject the widget right before the submit button on every form.
		add_filter( 'gform_submit_button',          [ $this, 'inject' ], 10, 2 );
		// Validation hook.
		add_filter( 'gform_validation',             [ $this, 'validate_form' ], 10, 1 );
	}

	/**
	 * @param string $button
	 * @param array  $form
	 */
	public function inject( $button, $form ): string {
		return $this->render() . (string) $button;
	}

	/**
	 * @param array{is_valid:bool,form:array} $validation_result
	 * @return array
	 */
	public function validate_form( $validation_result ) {
		$result = $this->validate();
		if ( ! $result->isValid() ) {
			$validation_result['is_valid'] = false;
			$form = $validation_result['form'];
			if ( isset( $form['fields'] ) && is_array( $form['fields'] ) ) {
				foreach ( $form['fields'] as &$field ) {
					if ( isset( $field->type ) && $field->type === 'submit' ) {
						$field->failed_validation  = true;
						$field->validation_message = $this->plugin->error_message( $result->getError() );
					}
				}
				unset( $field );
				$validation_result['form'] = $form;
			}
		}
		return $validation_result;
	}
}
