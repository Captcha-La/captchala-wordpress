<?php
/**
 * Divi Builder forms integration. Divi's contact module emits known DOM
 * shapes; we inject the widget right before its submit button, and
 * validate via the `et_pb_contact_form_validate_input` filter.
 *
 * @package Captchala\Wp
 */

declare( strict_types=1 );

namespace Captchala\Wp\Integrations;

use Captchala\Cms\Action;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Divi extends AbstractIntegration {

	public static function is_available(): bool {
		return class_exists( 'ET_Builder_Plugin' ) || function_exists( 'et_setup_theme' );
	}

	public static function option_key(): string {
		return 'divi';
	}

	protected function action(): string {
		return Action::CONTACT_FORM;
	}

	public function init(): void {
		add_filter( 'et_builder_module_contact_form_render', [ $this, 'inject' ], 10, 2 );
		add_filter( 'et_pb_contact_form_field_have_errors',  [ $this, 'validate_submit' ], 10, 2 );
	}

	/**
	 * @param string $output
	 */
	public function inject( $output, $instance = null ): string {
		$output = (string) $output;
		$widget = $this->render();
		$pos    = stripos( $output, '<button' );
		if ( $pos === false ) {
			return $output . $widget;
		}
		return substr( $output, 0, $pos ) . $widget . substr( $output, $pos );
	}

	/**
	 * @param bool  $errors
	 * @param array $processed_fields
	 */
	public function validate_submit( $errors, $processed_fields ): bool {
		$result = $this->validate();
		if ( ! $result->isValid() ) {
			return true; // Mark as having errors — Divi shows the generic "incorrect captcha" message.
		}
		return (bool) $errors;
	}
}
