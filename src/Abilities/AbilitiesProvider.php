<?php
/**
 * Registers a small set of read-only abilities under WP 6.5+'s Abilities API
 * so AI agents and automation tools can introspect CaptchaLa's status.
 *
 * v1 only ships:
 *   - captchala/get-config       returns redacted plugin config (key prefix, product, lang, toggles)
 *   - captchala/get-stats        placeholder — returns a minimal counters payload (zeroed for v1)
 *
 * Heavier "block offender" / threat-snapshot endpoints are deferred to v1.1
 * (mirroring hCaptcha-for-WP's later-stage features).
 *
 * @package Captchala\Wp
 */

declare( strict_types=1 );

namespace Captchala\Wp\Abilities;

use Captchala\Wp\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AbilitiesProvider {

	private const CATEGORY     = 'captchala';
	private const CAP_GET_CFG  = 'captchala/get-config';
	private const CAP_GET_STAT = 'captchala/get-stats';

	private Plugin $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function init(): void {
		// Only meaningful on WP versions that ship the Abilities API.
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		add_action( 'wp_abilities_api_categories_init', [ $this, 'register_categories' ] );
		add_action( 'wp_abilities_api_init',            [ $this, 'register_abilities' ] );
	}

	public function register_categories(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}
		wp_register_ability_category(
			self::CATEGORY,
			[
				'label'       => __( 'CaptchaLa', 'captchala' ),
				'description' => __( 'CaptchaLa plugin abilities for AI agents.', 'captchala' ),
			]
		);
	}

	public function register_abilities(): void {
		wp_register_ability(
			self::CAP_GET_CFG,
			[
				'label'               => __( 'Get CaptchaLa config', 'captchala' ),
				'description'         => __( 'Returns the redacted CaptchaLa plugin configuration.', 'captchala' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [ 'type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false ],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'configured' => [ 'type' => 'boolean' ],
						'app_key_prefix' => [ 'type' => 'string' ],
						'product'    => [ 'type' => 'string' ],
						'lang'       => [ 'type' => 'string' ],
						'integrations_enabled' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
					],
					'additionalProperties' => false,
					'required'   => [ 'configured', 'product', 'lang', 'integrations_enabled' ],
				],
				'permission_callback' => [ $this, 'can_read' ],
				'execute_callback'    => [ $this, 'execute_get_config' ],
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [ 'readonly' => true, 'idempotent' => true, 'destructive' => false ],
				],
			]
		);

		wp_register_ability(
			self::CAP_GET_STAT,
			[
				'label'               => __( 'Get CaptchaLa stats', 'captchala' ),
				'description'         => __( 'Returns recent CaptchaLa stats (counters reserved for v1.1).', 'captchala' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [ 'type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false ],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'plugin_version' => [ 'type' => 'string' ],
						'note'           => [ 'type' => 'string' ],
					],
					'additionalProperties' => true,
				],
				'permission_callback' => [ $this, 'can_read' ],
				'execute_callback'    => [ $this, 'execute_get_stats' ],
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [ 'readonly' => true, 'idempotent' => true, 'destructive' => false ],
				],
			]
		);
	}

	public function can_read(): bool {
		return current_user_can( 'manage_options' );
	}

	public function execute_get_config(): array {
		$key       = (string) $this->plugin->get_setting( 'app_key', '' );
		$enabled   = [];
		foreach ( $this->plugin->default_settings() as $k => $_v ) {
			if ( in_array( $k, [ 'app_key', 'app_secret', 'product', 'lang', 'skip_for_admins' ], true ) ) {
				continue;
			}
			if ( $this->plugin->is_enabled( $k ) ) {
				$enabled[] = $k;
			}
		}
		return [
			'configured'           => $this->plugin->is_configured(),
			'app_key_prefix'       => $key !== '' ? substr( $key, 0, 6 ) . '…' : '',
			'product'              => (string) $this->plugin->get_setting( 'product', 'bind' ),
			'lang'                 => (string) $this->plugin->get_setting( 'lang', 'auto' ),
			'integrations_enabled' => $enabled,
		];
	}

	public function execute_get_stats(): array {
		return [
			'plugin_version' => CAPTCHALA_WP_VERSION,
			'note'           => 'Detailed counters land in v1.1.',
		];
	}
}
