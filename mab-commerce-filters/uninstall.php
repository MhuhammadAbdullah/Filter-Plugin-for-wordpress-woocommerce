<?php
/**
 * Fires when the plugin is deleted from the Plugins screen. Only removes
 * data when the store owner has explicitly opted in via
 * Settings → "Remove all data on uninstall" (default: off, data kept).
 *
 * @package MABCommerceFilters
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$prefix        = $wpdb->prefix . 'mabcf_';
$settings_table = $prefix . 'settings';

$table_exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $settings_table ) );

$remove_data = false;

if ( $table_exists ) {
	$value = $wpdb->get_var(
		$wpdb->prepare( "SELECT setting_value FROM {$settings_table} WHERE setting_key = %s", 'remove_data_on_uninstall' ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	);

	$remove_data = '1' === (string) $value || '"1"' === (string) $value;
}

if ( ! $remove_data ) {
	return;
}

foreach ( array( 'filter_sets', 'filters', 'locations', 'settings', 'logs', 'migrations' ) as $table_key ) {
	$table = $prefix . $table_key;
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
}

$options = array(
	'mabcf_db_version',
	'mabcf_activated_at',
	'mabcf_default_set_seeded',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

$wpdb->query(
	$wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '\\_transient\\_mabcf\\_%' ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
);
$wpdb->query(
	$wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '\\_transient\\_timeout\\_mabcf\\_%' ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
);

// Remove the plugin's per-product view-count and per-visitor cookies-backed meta.
$wpdb->query(
	$wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s", '_mabcf_view_count' ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
);
