<?php
/**
 * Contact Form 7 integration. Auto-injects the CaptchaLa widget into all
 * CF7 form markup right before the submit button, and validates via the
 * `wpcf7_validate` filter.
 *
 * @package Captchala\Wp
 */

declare( strict_types=1 );

namespace Captchala\Wp\Integrations;

use Captchala\Cms\Action;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Cf7 extends AbstractIntegration {

	public static function is_available(): bool {
		return class_exists( 'WPCF7' );
	}

	public static function option_key(): string {
		return 'cf7';
	}

	protected function action(): string {
		return Action::CONTACT_FORM;
	}

	public function init(): void {
		add_filter( 'wpcf7_form_elements', [ $this, 'inject' ], 99, 1 );
		add_filter( 'wpcf7_validate',      [ $this, 'validate_submit' ], 20, 2 );
	}

	public function inject( string $content ): string {
		$widget = $this->render();
		if ( $widget === '' ) {
			return $content;
		}
		// Place the widget right before the submit input. Falls back to append.
		$replaced = preg_replace(
			'/(<input[^>]*type=["\']submit["\'][^>]*>)/i',
			$widget . '$1',
			$content,
			1,
			$count
		);
		return ( is_string( $replaced ) && $count > 0 ) ? $replaced : $content . $widget;
	}

	/**
	 * @param mixed $result \WPCF7_Validation
	 * @param mixed $tags
	 * @return mixed
	 */
	public function validate_submit( $result, $tags ) {
		static $ran = false;
		if ( $ran ) {
			return $result;
		}
		$ran      = true;
		$validate = $this->validate();
		if ( ! $validate->isValid() && is_object( $result ) && method_exists( $result, 'invalidate' ) ) {
			$result->invalidate(
				[ 'type' => 'captcha', 'name' => 'captchala_token' ],
				$this->plugin->error_message( $validate->getError() )
			);
		}
		return $result;
	}
}
