<?php
/**
 * Repository for the filters table.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Repositories;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD access to individual filters that belong to a filter set.
 */
final class FilterRepository extends AbstractRepository {

	/**
	 * {@inheritDoc}
	 */
	protected array $json_columns = array( 'settings' );

	/**
	 * {@inheritDoc}
	 */
	protected function table_key(): string {
		return 'filters';
	}

	/**
	 * Returns every active filter belonging to a filter set, ordered by
	 * sort_order.
	 *
	 * @param int $filter_set_id Filter set ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_for_set( int $filter_set_id ): array {
		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM {$this->table} WHERE filter_set_id = %d AND status = %s ORDER BY sort_order ASC, id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$filter_set_id,
				'active'
			),
			ARRAY_A
		);

		return array_map( array( $this, 'decode' ), $rows ?: array() );
	}

	/**
	 * Deletes every filter that belongs to a filter set.
	 *
	 * @param int $filter_set_id Filter set ID.
	 */
	public function delete_for_set( int $filter_set_id ): void {
		$this->db->delete( $this->table, array( 'filter_set_id' => $filter_set_id ), array( '%d' ) );
	}

	/**
	 * Persists the sort order for a batch of filter IDs.
	 *
	 * @param int[] $ordered_ids Filter IDs in their new display order.
	 */
	public function reorder( array $ordered_ids ): void {
		foreach ( array_values( $ordered_ids ) as $index => $id ) {
			$this->db->update( $this->table, array( 'sort_order' => $index ), array( 'id' => (int) $id ), array( '%d' ), array( '%d' ) );
		}
	}
}
