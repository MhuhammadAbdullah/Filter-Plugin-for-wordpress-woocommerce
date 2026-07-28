<?php
/**
 * Import/export of Filter Sets and global settings as portable JSON.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Produces and consumes the JSON payload used by the Import/Export admin
 * page, so a store owner can move their filter configuration between a
 * staging site and production (or ship a preset with a theme).
 */
final class APF_Import_Export {

	/**
	 * Current export schema version, bumped whenever the payload shape
	 * changes in a way that requires migration on import.
	 *
	 * @var int
	 */
	private const SCHEMA_VERSION = 1;

	/**
	 * Builds the full export payload.
	 *
	 * @return array<string, mixed>
	 */
	public static function export(): array {
		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'plugin_version' => APF_VERSION,
			'exported_at'    => gmdate( 'c' ),
			'settings'       => APF_Settings::all(),
			'filter_sets'    => array_map(
				static fn( APF_Filter_Set $filter_set ): array => $filter_set->to_array(),
				APF_Filter_Sets::get_all()
			),
		);
	}

	/**
	 * Imports a previously exported payload. Existing Filter Sets are
	 * never removed or overwritten — every imported set is created fresh
	 * so an import can always be safely undone by deleting the new sets.
	 *
	 * @param array<string, mixed> $payload Decoded export payload.
	 * @return array<string, mixed> Summary of what was imported.
	 */
	public static function import( array $payload ): array {
		$imported = 0;

		if ( ! empty( $payload['settings'] ) && is_array( $payload['settings'] ) ) {
			APF_Settings::update( $payload['settings'] );
		}

		if ( ! empty( $payload['filter_sets'] ) && is_array( $payload['filter_sets'] ) ) {
			foreach ( $payload['filter_sets'] as $filter_set_data ) {
				if ( ! is_array( $filter_set_data ) ) {
					continue;
				}

				$filter_set_data['id'] = 0;

				if ( APF_Filter_Sets::save( $filter_set_data ) ) {
					++$imported;
				}
			}
		}

		return array(
			'imported_filter_sets' => $imported,
			'settings_updated'     => ! empty( $payload['settings'] ),
		);
	}
}
