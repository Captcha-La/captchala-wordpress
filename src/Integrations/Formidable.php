<?php
/**
 * Formidable Forms integration.
 *
 * @package Captchala\Wp
 */

declare( strict_types=1 );

namespace Captchala\Wp\Integrations;

use Captchala\Cms\Action;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Formidable extends AbstractIntegration {

	public static function is_available(): bool {
		return class_exists( 'FrmAppController' );
	}

	public static function option_key(): string {
		return 'formidable';
	}

	protected function action(): string {
		return Action::CONTACT_FORM;
	}

	public function init(): void {
		add_action( 'frm_after_replace_shortcodes', [ $this, 'render_field' ], 10, 4 );
		add_filter( 'frm_validate_entry',           [ $this, 'validate_entry' ], 10, 2 );
	}

	public function render_field(): void {
		$this->print_widget();
	}

	/**
	 * @param array $errors
	 * @param array $values
	 */
	public function validate_entry( $errors, $values ): array {
		$result = $this->validate();
		if ( ! $result->isValid() ) {
			$errors['captchala'] = $this->plugin->error_message( $result->getError() );
		}
		return $errors;
	}
}
