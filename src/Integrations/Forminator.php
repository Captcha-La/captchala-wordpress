<?php
/**
 * Forminator integration.
 *
 * @package Captchala\Wp
 */

declare( strict_types=1 );

namespace Captchala\Wp\Integrations;

use Captchala\Cms\Action;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Forminator extends AbstractIntegration {

	public static function is_available(): bool {
		return class_exists( 'Forminator_API' );
	}

	public static function option_key(): string {
		return 'forminator';
	}

	protected function action(): string {
		return Action::CONTACT_FORM;
	}

	public function init(): void {
		add_filter( 'forminator_render_form_markup', [ $this, 'inject' ], 10, 1 );
		add_filter( 'forminator_custom_form_submit_errors', [ $this, 'validate_submit' ], 10, 3 );
	}

	public function inject( $markup ): string {
		$markup = (string) $markup;
		$widget = $this->render();
		if ( $widget === '' ) {
			return $markup;
		}
		// Append before the closing </form>.
		$pos = stripos( $markup, '</form>' );
		if ( $pos === false ) {
			return $markup . $widget;
		}
		return substr( $markup, 0, $pos ) . $widget . substr( $markup, $pos );
	}

	/**
	 * @param array $errors
	 * @param int   $form_id
	 * @param array $field_data_array
	 */
	public function validate_submit( $errors, $form_id, $field_data_array ): array {
		$result = $this->validate();
		if ( ! $result->isValid() ) {
			$errors[]['captchala'] = $this->plugin->error_message( $result->getError() );
		}
		return is_array( $errors ) ? $errors : [];
	}
}
