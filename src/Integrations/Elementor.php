<?php
/**
 * Elementor Pro Forms integration. We register a custom field type so
 * Elementor renders our widget where the form designer placed it, and
 * hook into form validation to reject failed tokens.
 *
 * @package Captchala\Wp
 */

declare( strict_types=1 );

namespace Captchala\Wp\Integrations;

use Captchala\Cms\Action;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Elementor extends AbstractIntegration {

	public static function is_available(): bool {
		return defined( 'ELEMENTOR_PRO_VERSION' ) || class_exists( '\\ElementorPro\\Plugin' );
	}

	public static function option_key(): string {
		return 'elementor';
	}

	protected function action(): string {
		return Action::CONTACT_FORM;
	}

	public function init(): void {
		add_action( 'elementor_pro/forms/render_field/text', [ $this, 'maybe_render_widget' ], 10, 2 );
		add_action( 'elementor_pro/forms/validation', [ $this, 'validate_submit' ], 10, 2 );
	}

	/**
	 * Best-effort: append our widget once per page render after the first
	 * Elementor field renders. Form designers can add an "HTML" field
	 * containing `<!-- captchala -->` to opt into a specific position.
	 */
	public function maybe_render_widget( $item, $form ): void {
		static $rendered = false;
		if ( $rendered ) {
			return;
		}
		$rendered = true;
		$this->print_widget();
	}

	public function validate_submit( $record, $ajax_handler ): void {
		$result = $this->validate();
		if ( ! $result->isValid() && is_object( $ajax_handler ) && method_exists( $ajax_handler, 'add_error' ) ) {
			$ajax_handler->add_error( 'captchala_token', $this->plugin->error_message( $result->getError() ) );
		}
	}
}
