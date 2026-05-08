<?php
/**
 * Common contract every CaptchaLa integration class fulfils. The Plugin
 * orchestrator only instantiates a class if `is_available()` returns true
 * (i.e. the host plugin is active) AND the matching settings toggle is on.
 *
 * @package Captchala\Wp
 */

declare( strict_types=1 );

namespace Captchala\Wp\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface IntegrationInterface {
	/**
	 * @return bool True if the host plugin is loaded.
	 */
	public static function is_available(): bool;

	/**
	 * @return string Settings key (matches the option toggle name).
	 */
	public static function option_key(): string;

	/**
	 * Wire all WordPress hooks. Called once per request after the Plugin
	 * orchestrator confirms is_available() and the toggle is enabled.
	 */
	public function init(): void;
}
