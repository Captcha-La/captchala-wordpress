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
	 * Render the canonical CaptchaLa widget for this integration.
	 *
	 * @param array<string,mixed> $extra Forwarded to Widget::renderHtml.
	 */
	protected function render( array $extra = [] ): string {
		return $this->plugin->render_widget( $this->action(), $extra );
	}

	/**
	 * Echo the canonical CaptchaLa widget HTML. The output is constructed
	 * entirely from a fixed template inside the SDK's Widget::renderHtml —
	 * attribute values are escaped with esc_attr at construction time and
	 * the inline <script> body is a hardcoded string with no user-input
	 * substitution. There is no path through this output that could be
	 * controlled by a request parameter.
	 *
	 * @param array<string,mixed> $extra Forwarded to Widget::renderHtml.
	 */
	protected function print_widget( array $extra = [] ): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- See docblock above; SDK widget HTML is escape-safe by construction.
		echo $this->render( $extra );
	}

	/**
	 * Validate the posted token.
	 */
	protected function validate(): ValidateResult {
		return $this->plugin->validate_request( $this->action() );
	}
}
