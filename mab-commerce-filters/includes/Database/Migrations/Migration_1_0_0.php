<?php
/**
 * Initial schema migration: creates all core tables.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Database\Migrations;

use MABCommerceFilters\Database\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates the filter_sets, filters, locations, settings, logs and
 * migrations tables via dbDelta.
 */
final class Migration_1_0_0 {

	/**
	 * Version this migration upgrades the schema to.
	 *
	 * @var string
	 */
	public const VERSION = '1.0.0';

	/**
	 * Runs the migration.
	 */
	public function run(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( Schema::definitions() as $sql ) {
			dbDelta( $sql );
		}
	}
}
