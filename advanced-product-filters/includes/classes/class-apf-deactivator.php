<?php
/**
 * Runs once when the plugin is deactivated.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Deactivation intentionally keeps all Filter Sets and settings intact —
 * only `uninstall.php` (triggered by deleting the plugin) removes data.
 */
final class APF_Deactivator {

	/**
	 * Deactivation callback registered in the bootstrap file.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		APF_Cache::flush();
		flush_rewrite_rules();
	}
}
