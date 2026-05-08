<?php
/**
 * MailPoet integration — protects subscription forms.
 *
 * @package Captchala\Wp
 */

declare( strict_types=1 );

namespace Captchala\Wp\Integrations;

use Captchala\Cms\Action;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MailPoet extends AbstractIntegration {

	public static function is_available(): bool {
		return class_exists( '\\MailPoet\\API\\API' );
	}

	public static function option_key(): string {
		return 'mailpoet';
	}

	protected function action(): string {
		return Action::CONTACT_FORM;
	}

	public function init(): void {
		add_filter( 'mailpoet_form_widget_post_process', [ $this, 'inject' ], 10, 1 );
		add_filter( 'mailpoet_subscription_before_subscribe', [ $this, 'validate_submit' ], 10, 2 );
	}

	public function inject( $form_html ): string {
		$form_html = (string) $form_html;
		$widget    = $this->render();
		$pos       = stripos( $form_html, '</form>' );
		if ( $pos === false ) {
			return $form_html . $widget;
		}
		return substr( $form_html, 0, $pos ) . $widget . substr( $form_html, $pos );
	}

	public function validate_submit( $subscriber_data, $form ) {
		$result = $this->validate();
		if ( ! $result->isValid() ) {
			throw new \Exception( esc_html( $this->plugin->error_message( $result->getError() ) ) );
		}
		return $subscriber_data;
	}
}
