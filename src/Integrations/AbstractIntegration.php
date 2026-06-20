<?php
/**
 * Shared base class for CaptchaLa integrations — holds the Plugin
 * reference and provides convenience methods for rendering the widget
 * and validating posted tokens.
 *
 * @package Captchala\Wp
 */

declare( strict_types=1 );

namespace Captchala\Wp\Integrations;

use Captchala\Wp\Plugin;
use Captchala\ValidateResult;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AbstractIntegration implements IntegrationInterface {

	protected Plugin $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/** Default availability for "always present" integrations (WP core, Woo). */
	public static function is_available(): bool {
		return true;
	}

	/** Action constant from Captchala\Cms\Action that this integration uses. */
	abstract protected function action(): string;

	/**
	 * Render the canonical CaptchaLa widget for this integration, escaped
	 * through wp_kses with our data-* allow-list so the markup is safe at
	 * the output boundary.
	 *
	 * Use this whenever the widget HTML is *returned* into a WordPress
	 * filter (comment form, Woo block actions, CF7 form body, …) — WP will
	 * echo the filter result without escaping, so the escaping has to be
	 * baked into the returned string itself.
	 *
	 * The rendered fragment is just a `<div data-*>` plus a hidden
	 * `<input>` — the SDK's loader.js and per-widget bootstrap are enqueued
	 * separately via wp_enqueue_script / wp_add_inline_script in
	 * Plugin::render_widget(), so there is never any JS in this markup.
	 *
	 * @param array<string,mixed> $extra Forwarded to Widget::renderParts.
	 */
	protected function render( array $extra = [] ): string {
		return wp_kses(
			$this->plugin->render_widget( $this->action(), $extra ),
			self::widget_allowed_tags()
		);
	}

	/**
	 * Echo the widget markup (already wp_kses-escaped by render()). Used by
	 * integrations that hook an `action` (echo context) rather than a
	 * `filter` (return context).
	 *
	 * @param array<string,mixed> $extra Forwarded to Widget::renderParts.
	 */
	protected function print_widget( array $extra = [] ): void {
		// render() already ran the markup through wp_kses with our
		// allow-list, so it is safe to echo here. phpcs can't follow the
		// escaping across the helper boundary, hence the annotation.
		echo $this->render( $extra ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped via wp_kses in render().
	}

	/**
	 * Allow-list of tags + attributes wp_kses() needs to keep the widget
	 * markup intact. All data-* attrs are explicitly enumerated because
	 * wp_kses strips unknown attributes by default.
	 *
	 * @return array<string, array<string, bool>>
	 */
	private static function widget_allowed_tags(): array {
		return array(
			'div'   => array(
				'id'                  => true,
				'class'               => true,
				'style'               => true,
				'data-captchala'      => true,
				'data-app-key'        => true,
				'data-server-token'   => true,
				'data-action'         => true,
				'data-product'        => true,
				'data-lang'           => true,
				'data-theme'          => true,
				'data-bind-to'        => true,
				'data-refresh-url'    => true,
				'data-refresh-action' => true,
				'data-refresh-scene'  => true,
			),
			'input' => array(
				'type'  => true,
				'name'  => true,
				'value' => true,
			),
		);
	}

	/**
	 * Validate the posted token.
	 */
	protected function validate(): ValidateResult {
		return $this->plugin->validate_request( $this->action() );
	}
}
