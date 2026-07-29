<?php
/**
 * Handles plugin deactivation. Data is intentionally preserved; only
 * transient runtime state is cleared. See uninstall.php for data removal.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs on register_deactivation_hook.
 */
final class Deactivator {

	/**
	 * Clears scheduled events and cached data, then flushes rewrite rules.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'mabcf_daily_maintenance' );

		global $wpdb;
		// Table name comes from $wpdb, not user input; LIKE value is still prepared.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_mabcf_%' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_timeout_mabcf_%' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		flush_rewrite_rules();
	}
}
