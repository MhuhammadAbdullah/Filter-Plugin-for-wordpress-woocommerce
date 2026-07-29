<?php
/**
 * Repository for the locations table (filter-set assignment rules).
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Repositories;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD access to filter set location assignments: shop, category/tag/brand
 * archives, specific pages, Elementor templates and shortcodes.
 */
final class LocationRepository extends AbstractRepository {

	/**
	 * {@inheritDoc}
	 */
	protected function table_key(): string {
		return 'locations';
	}

	/**
	 * Returns all locations assigned to a filter set.
	 *
	 * @param int $filter_set_id Filter set ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_for_set( int $filter_set_id ): array {
		return $this->find_by( array( 'filter_set_id' => $filter_set_id ) );
	}

	/**
	 * Deletes every location assigned to a filter set.
	 *
	 * @param int $filter_set_id Filter set ID.
	 */
	public function delete_for_set( int $filter_set_id ): void {
		$this->db->delete( $this->table, array( 'filter_set_id' => $filter_set_id ), array( '%d' ) );
	}

	/**
	 * Replaces all locations for a filter set with a new list.
	 *
	 * @param int                              $filter_set_id Filter set ID.
	 * @param array<int, array<string,string>> $locations     List of ['type' => ..., 'value' => ...].
	 */
	public function sync_for_set( int $filter_set_id, array $locations ): void {
		$this->delete_for_set( $filter_set_id );

		foreach ( $locations as $location ) {
			$this->create(
				array(
					'filter_set_id'  => $filter_set_id,
					'location_type'  => sanitize_key( $location['type'] ?? '' ),
					'location_value' => sanitize_text_field( $location['value'] ?? '' ),
				)
			);
		}
	}

	/**
	 * Finds every filter_set_id assigned to a given type/value pair, used
	 * to resolve which filter sets apply to the current request.
	 *
	 * @param string $type  Location type, e.g. shop|category|tag|brand|page|template|shortcode.
	 * @param string $value Location value (term slug, page ID, template ID, shortcode key).
	 * @return int[]
	 */
	public function find_filter_set_ids( string $type, string $value = '' ): array {
		$rows = $this->db->get_col(
			$this->db->prepare(
				"SELECT filter_set_id FROM {$this->table} WHERE location_type = %s AND location_value = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$type,
				$value
			)
		);

		return array_map( 'intval', $rows ?: array() );
	}
}
