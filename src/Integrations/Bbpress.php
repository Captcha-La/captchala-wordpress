<?php
/**
 * bbPress integration — protects new topic / new reply forms.
 *
 * @package Captchala\Wp
 */

declare( strict_types=1 );

namespace Captchala\Wp\Integrations;

use Captchala\Cms\Action;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bbpress extends AbstractIntegration {

	public static function is_available(): bool {
		return class_exists( 'bbPress' );
	}

	public static function option_key(): string {
		return 'bbpress';
	}

	protected function action(): string {
		return Action::FORUM_POST;
	}

	public function init(): void {
		add_action( 'bbp_theme_before_topic_form_submit_wrapper', [ $this, 'render_field' ] );
		add_action( 'bbp_theme_before_reply_form_submit_wrapper', [ $this, 'render_field' ] );
		add_action( 'bbp_new_topic_pre_extras', [ $this, 'validate_submit' ] );
		add_action( 'bbp_new_reply_pre_extras', [ $this, 'validate_submit' ] );
	}

	public function render_field(): void {
		if ( $this->plugin->should_skip_for_user() ) {
			return;
		}
		$this->print_widget();
	}

	public function validate_submit(): void {
		if ( $this->plugin->should_skip_for_user() ) {
			return;
		}
		$result = $this->validate();
		if ( ! $result->isValid() && function_exists( 'bbp_add_error' ) ) {
			bbp_add_error( 'captchala_failed', $this->plugin->error_message( $result->getError() ) );
		}
	}
}
