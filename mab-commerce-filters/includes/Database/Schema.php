<?php
/**
 * Defines the custom database schema used by the plugin.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central source of truth for table names and their dbDelta definitions.
 */
final class Schema {

	/**
	 * Returns the fully prefixed table name for a short table key.
	 *
	 * @param string $key One of filter_sets|filters|locations|settings|logs|migrations.
	 */
	public static function table( string $key ): string {
		global $wpdb;

		return $wpdb->prefix . 'mabcf_' . $key;
	}

	/**
	 * Returns an array of table_key => CREATE TABLE SQL (dbDelta compatible).
	 *
	 * @return array<string, string>
	 */
	public static function definitions(): array {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$filter_sets = self::table( 'filter_sets' );
		$filters     = self::table( 'filters' );
		$locations   = self::table( 'locations' );
		$settings    = self::table( 'settings' );
		$logs        = self::table( 'logs' );
		$migrations  = self::table( 'migrations' );

		$sql = array();

		$sql['filter_sets'] = "CREATE TABLE {$filter_sets} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			slug VARCHAR(191) NOT NULL,
			description TEXT NULL,
			layout VARCHAR(50) NOT NULL DEFAULT 'sidebar',
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			settings LONGTEXT NULL,
			priority INT NOT NULL DEFAULT 10,
			created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:01',
			updated_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:01',
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY status (status)
		) {$charset_collate};";

		$sql['filters'] = "CREATE TABLE {$filters} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			filter_set_id BIGINT UNSIGNED NOT NULL,
			type VARCHAR(50) NOT NULL,
			label VARCHAR(191) NOT NULL,
			source_key VARCHAR(191) NULL,
			display_style VARCHAR(50) NOT NULL DEFAULT 'list',
			settings LONGTEXT NULL,
			sort_order INT NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:01',
			updated_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:01',
			PRIMARY KEY  (id),
			KEY filter_set_id (filter_set_id),
			KEY type (type)
		) {$charset_collate};";

		$sql['locations'] = "CREATE TABLE {$locations} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			filter_set_id BIGINT UNSIGNED NOT NULL,
			location_type VARCHAR(50) NOT NULL,
			location_value VARCHAR(191) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:01',
			PRIMARY KEY  (id),
			KEY filter_set_id (filter_set_id),
			KEY location_type (location_type),
			KEY location_value (location_value)
		) {$charset_collate};";

		$sql['settings'] = "CREATE TABLE {$settings} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			setting_key VARCHAR(191) NOT NULL,
			setting_value LONGTEXT NULL,
			autoload VARCHAR(3) NOT NULL DEFAULT 'yes',
			updated_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:01',
			PRIMARY KEY  (id),
			UNIQUE KEY setting_key (setting_key)
		) {$charset_collate};";

		$sql['logs'] = "CREATE TABLE {$logs} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			log_type VARCHAR(30) NOT NULL DEFAULT 'info',
			channel VARCHAR(50) NOT NULL DEFAULT 'general',
			message TEXT NOT NULL,
			context LONGTEXT NULL,
			created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:01',
			PRIMARY KEY  (id),
			KEY log_type (log_type),
			KEY channel (channel),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql['migrations'] = "CREATE TABLE {$migrations} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			version VARCHAR(20) NOT NULL,
			migrated_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:01',
			PRIMARY KEY  (id),
			UNIQUE KEY version (version)
		) {$charset_collate};";

		return $sql;
	}

	/**
	 * Returns the short keys for every managed table, in dependency order.
	 *
	 * @return string[]
	 */
	public static function table_keys(): array {
		return array( 'filter_sets', 'filters', 'locations', 'settings', 'logs', 'migrations' );
	}
}
