<?php
/**
 * Plugin Name:       CaptchaLa
 * Plugin URI:        https://captcha.la/integrations/wordpress
 * Description:       Smart privacy-first CAPTCHA + anti-spam. Protects WordPress core, WooCommerce, and 14+ third-party form plugins. Mandatory server-token anti-replay.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            CaptchaLa
 * Author URI:        https://captcha.la/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       captchala
 * Domain Path:       /languages
 *
 * WC requires at least: 3.0
 * WC tested up to:      9.3
 *
 * @package Captchala\Wp
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// -----------------------------------------------------------------------------
// Constants
// -----------------------------------------------------------------------------
const CAPTCHALA_WP_VERSION = '1.0.0';
define( 'CAPTCHALA_WP_FILE', __FILE__ );
define( 'CAPTCHALA_WP_PATH', plugin_dir_path( __FILE__ ) );
define( 'CAPTCHALA_WP_URL', plugin_dir_url( __FILE__ ) );

// -----------------------------------------------------------------------------
// Autoloader
// -----------------------------------------------------------------------------
// Prefer composer's autoloader (production builds bundle vendor/).
$captchala_wp_vendor = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $captchala_wp_vendor ) ) {
	require_once $captchala_wp_vendor;
} else {
	// Fallback: minimal PSR-4 loader covering this plugin and the bundled SDK
	// source so the plugin still boots if the user uploaded the source-only
	// tree (e.g. via `git clone`) without running `composer install`.
	spl_autoload_register(
		static function ( string $class ): void {
			$prefixes = [
				'Captchala\\Wp\\' => __DIR__ . '/src/',
				'Captchala\\'     => __DIR__ . '/../../sdk/php/src/',
			];
			foreach ( $prefixes as $prefix => $base_dir ) {
				$len = strlen( $prefix );
				if ( strncmp( $prefix, $class, $len ) !== 0 ) {
					continue;
				}
				$relative = substr( $class, $len );
				$file     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';
				if ( is_readable( $file ) ) {
					require_once $file;
					return;
				}
			}
		}
	);
}

// -----------------------------------------------------------------------------
// Boot
// -----------------------------------------------------------------------------
if ( class_exists( \Captchala\Wp\Plugin::class ) ) {
	add_action( 'plugins_loaded', [ \Captchala\Wp\Plugin::class, 'boot' ], 5 );
}
