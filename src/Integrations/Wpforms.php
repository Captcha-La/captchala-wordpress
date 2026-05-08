<?php
/**
 * WPForms integration.
 *
 * @package Captchala\Wp
 */

declare( strict_types=1 );

namespace Captchala\Wp\Integrations;

use Captchala\Cms\Action;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Wpforms extends AbstractIntegration {

	public static function is_available(): bool {
		return class_exists( 'WPForms' ) || function_exists( 'wpforms' );
	}

	public static function option_key(): string {
		return 'wpforms';
	}

	protected function action(): string {
		return Action::CONTACT_FORM;
	}

	public function init(): void {
		add_action( 'wpforms_frontend_output',        [ $this, 'render_widget' ], 20, 5 );
		add_filter( 'wpforms_process_initial_errors', [ $this, 'on_initial_errors' ], 10, 2 );
	}

	public function render_widget(): void {
		$this->print_widget();
	}

	/**
	 * @param array $errors
	 * @param array $form_data
	 * @return array
	 */
	public function on_initial_errors( $errors, $form_data ) {
		$result = $this->validate();
		if ( ! $result->isValid() ) {
			$form_id = isset( $form_data['id'] ) ? (string) $form_data['id'] : '0';
			if ( ! isset( $errors[ $form_id ] ) || ! is_array( $errors[ $form_id ] ) ) {
				$errors[ $form_id ] = [];
			}
			$errors[ $form_id ]['header'] = $this->plugin->error_message( $result->getError() );
		}
		return $errors;
	}
}
