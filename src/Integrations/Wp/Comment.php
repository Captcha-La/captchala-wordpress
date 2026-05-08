<?php
/**
 * Protect the WordPress comment form.
 *
 * @package Captchala\Wp
 */

declare( strict_types=1 );

namespace Captchala\Wp\Integrations\Wp;

use Captchala\Cms\Action;
use Captchala\Wp\Integrations\AbstractIntegration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Comment extends AbstractIntegration {

	public static function option_key(): string {
		return 'wp_comment';
	}

	protected function action(): string {
		return Action::COMMENT;
	}

	public function init(): void {
		// `comment_form_submit_field` filters the entire <p class="form-submit">
		// block, including the submit button — we prepend our widget so it
		// always lands directly above Submit, regardless of theme (classic
		// or block-based) and regardless of logged-in state.
		add_filter( 'comment_form_submit_field',  [ $this, 'inject_into_submit_field' ], 10, 2 );
		add_filter( 'preprocess_comment',         [ $this, 'validate_comment' ], 1 );
	}

	/**
	 * @param string              $submit_field The full <p class="form-submit">…</p> block.
	 * @param array<string,mixed> $args         comment_form() args.
	 */
	public function inject_into_submit_field( string $submit_field, array $args = [] ): string {
		if ( $this->plugin->should_skip_for_user() ) {
			return $submit_field;
		}
		return $this->render() . $submit_field;
	}

	/**
	 * @param array<string,mixed> $commentdata
	 * @return array<string,mixed>
	 */
	public function validate_comment( $commentdata ) {
		// Pingback / trackback / REST: skip CAPTCHA.
		if ( ! empty( $commentdata['comment_type'] ) && in_array( $commentdata['comment_type'], [ 'pingback', 'trackback' ], true ) ) {
			return $commentdata;
		}
		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ) {
			return $commentdata;
		}
		if ( $this->plugin->should_skip_for_user() ) {
			return $commentdata;
		}
		$result = $this->validate();
		if ( ! $result->isValid() ) {
			wp_die(
				esc_html( $this->plugin->error_message( $result->getError() ) ),
				esc_html__( 'Comment blocked', 'captchala' ),
				[ 'response' => 403, 'back_link' => true ]
			);
		}
		return $commentdata;
	}
}
