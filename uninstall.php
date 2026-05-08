<?php
/**
 * CaptchaLa uninstall handler — drops every `captchala_*` option and the
 * archived legacy keys produced by the migration wizard.
 *
 * @package Captchala\Wp
 */

declare( strict_types=1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Direct delete of settings option.
delete_option( 'captchala_settings' );
delete_option( 'captchala_wizard_dismissed' ); // legacy, may not exist
delete_user_meta( 0, 'captchala_wizard_dismissed' );

// Drop any auto-generated _archived_ snapshots and any other captchala_* keys.
// Direct DB query is required: the WP options API has no LIKE-based bulk
// delete and looping get_option/delete_option for an unknown key set would
// require a full options table scan in PHP. Caching is irrelevant — this
// runs exactly once when the plugin is uninstalled.
$captchala_prefix = $wpdb->esc_like( 'captchala_' );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- one-shot uninstall cleanup; no API equivalent for prefix-LIKE delete.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$captchala_prefix . '%'
	)
);
// Clean up per-user dismiss flags.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- one-shot uninstall cleanup; no API equivalent for cross-user usermeta delete.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s",
		'captchala_wizard_dismissed'
	)
);
