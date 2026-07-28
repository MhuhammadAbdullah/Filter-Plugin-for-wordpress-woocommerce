<?php
/**
 * Fires when the plugin is deleted from wp-admin, removing every trace of
 * data it created. Deactivating the plugin does NOT trigger this file —
 * only deletion does.
 *
 * @package AdvancedProductFilters
 */

// Guard against direct access; WordPress defines this constant when it
// itself calls this file during plugin deletion.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

/**
 * Deletes every `apf_filter_set` post (and its meta) on a single site.
 *
 * @return void
 */
function apf_uninstall_delete_filter_sets(): void {
	$ids = get_posts(
		array(
			'post_type'      => 'apf_filter_set',
			'post_status'    => array( 'publish', 'draft', 'trash' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	foreach ( $ids as $id ) {
		wp_delete_post( $id, true );
	}
}

/**
 * Deletes every plugin option and transient on a single site.
 *
 * @return void
 */
function apf_uninstall_delete_options(): void {
	global $wpdb;

	delete_option( 'apf_settings' );
	delete_option( 'apf_version' );
	delete_option( 'apf_cache_salt' );
	delete_option( 'apf_seeded_default_set' );

	$like = $wpdb->esc_like( '_transient_apf_' ) . '%';
	$timeout_like = $wpdb->esc_like( '_transient_timeout_apf_' ) . '%';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $like, $timeout_like ) );
}

if ( is_multisite() ) {
	$site_ids = get_sites( array( 'fields' => 'ids' ) );

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		apf_uninstall_delete_filter_sets();
		apf_uninstall_delete_options();
		restore_current_blog();
	}
} else {
	apf_uninstall_delete_filter_sets();
	apf_uninstall_delete_options();
}
