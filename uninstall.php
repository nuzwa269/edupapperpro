<?php
/**
 * Uninstall cleanup for EduPaper AI Chat.
 *
 * This file runs only when the plugin is uninstalled from WordPress.
 *
 * @package EduPaperAIChat
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/*
 * Delete plugin options.
 *
 * Keep these option names synced with the main plugin file.
 */
delete_option( 'epac_settings' );

/*
 * Delete rate-limit transients created by the plugin.
 *
 * WordPress stores transients in wp_options as:
 * _transient_{name}
 * _transient_timeout_{name}
 *
 * This direct query is acceptable here because uninstall cleanup needs
 * wildcard deletion and no user input is involved.
 */
global $wpdb;

$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options}
		WHERE option_name LIKE %s
		OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_epac_rate_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_epac_rate_' ) . '%'
	)
);
