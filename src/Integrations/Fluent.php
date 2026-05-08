<?php
/**
 * Fluent Forms integration.
 *
 * @package Captchala\Wp
 */

declare( strict_types=1 );

namespace Captchala\Wp\Integrations;

use Captchala\Cms\Action;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Fluent extends AbstractIntegration {

	public static function is_available(): bool {
		return defined( 'FLUENTFORM' ) || class_exists( '\\FluentForm\\App\\App' );
	}

	public static function option_key(): string {
		return 'fluent';
	}

	protected function action(): string {
		return Action::CONTACT_FORM;
	}

	public function init(): void {
		add_filter( 'fluentform/rendering_form', [ $this, 'on_render' ], 10, 2 );
		add_action( 'fluentform/before_insert_submission', [ $this, 'validate_submit' ], 10, 3 );
	}

	/**
	 * @param mixed $form
	 * @param mixed $extra
	 */
	public function on_render( $form, $extra = null ) {
		// Echo widget right when form renders. Fluent doesn't have a markup
		// filter so we ride the render hook for the side-effect.
		$this->print_widget();
		return $form;
	}

	/**
	 * @param array $insertData
	 * @param array $formData
	 * @param mixed $form
	 */
	public function validate_submit( $insertData, $formData, $form ): void {
		$result = $this->validate();
		if ( ! $result->isValid() ) {
			wp_send_json(
				[
					'errors' => [ 'captchala_token' => [ $this->plugin->error_message( $result->getError() ) ] ],
				],
				422
			);
		}
	}
}
