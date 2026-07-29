<?php
/**
 * Runs pending database migrations in order and records applied versions.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Database;

use MABCommerceFilters\Database\Migrations\Migration_1_0_0;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tracks and applies schema migrations against the mabcf_migrations table.
 */
final class Migrator {

	/**
	 * Ordered list of migration class names, keyed by version.
	 *
	 * @var array<string, string>
	 */
	private const MIGRATIONS = array(
		'1.0.0' => Migration_1_0_0::class,
	);

	/**
	 * Runs every migration that has not yet been recorded as applied.
	 * Safe to call on every plugin load; dbDelta itself is idempotent.
	 */
	public function migrate(): void {
		global $wpdb;

		$this->ensure_migrations_table();

		$applied = $this->get_applied_versions();

		foreach ( self::MIGRATIONS as $version => $class ) {
			if ( in_array( $version, $applied, true ) ) {
				continue;
			}

			/** @var object{run: callable} $migration */
			$migration = new $class();
			$migration->run();

			$wpdb->insert(
				Schema::table( 'migrations' ),
				array(
					'version'     => $version,
					'migrated_at' => current_time( 'mysql' ),
				),
				array( '%s', '%s' )
			);
		}

		update_option( 'mabcf_db_version', MABCF_DB_VERSION );
	}

	/**
	 * Creates the migrations tracking table if it does not exist yet,
	 * so migrate() can query it even on a brand-new install.
	 */
	private function ensure_migrations_table(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$definitions = Schema::definitions();
		dbDelta( $definitions['migrations'] );
	}

	/**
	 * Fetches the list of already-applied migration versions.
	 *
	 * @return string[]
	 */
	private function get_applied_versions(): array {
		global $wpdb;

		$table = Schema::table( 'migrations' );

		// Table name is generated internally from a fixed prefix, not user input.
		$versions = $wpdb->get_col( "SELECT version FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return is_array( $versions ) ? $versions : array();
	}
}
