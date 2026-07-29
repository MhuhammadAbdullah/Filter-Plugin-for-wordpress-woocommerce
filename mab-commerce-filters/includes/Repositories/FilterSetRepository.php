<?php
/**
 * Repository for the filter_sets table.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Repositories;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD access to filter sets, plus lookups by slug and status.
 */
final class FilterSetRepository extends AbstractRepository {

	/**
	 * {@inheritDoc}
	 */
	protected array $json_columns = array( 'settings' );

	/**
	 * {@inheritDoc}
	 */
	protected function table_key(): string {
		return 'filter_sets';
	}

	/**
	 * Finds a filter set by its unique slug.
	 *
	 * @param string $slug Filter set slug.
	 */
	public function find_by_slug( string $slug ): ?array {
		$row = $this->db->get_row(
			$this->db->prepare( "SELECT * FROM {$this->table} WHERE slug = %s", $slug ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return $row ? $this->decode( $row ) : null;
	}

	/**
	 * Returns all active filter sets ordered by priority.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_active(): array {
		$rows = $this->db->get_results(
			$this->db->prepare( "SELECT * FROM {$this->table} WHERE status = %s ORDER BY priority ASC, id ASC", 'active' ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return array_map( array( $this, 'decode' ), $rows ?: array() );
	}

	/**
	 * Returns all filter sets ordered by priority, regardless of status.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_all(): array {
		$rows = $this->db->get_results( "SELECT * FROM {$this->table} ORDER BY priority ASC, id DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array_map( array( $this, 'decode' ), $rows ?: array() );
	}

	/**
	 * Generates a unique slug from a proposed name.
	 *
	 * @param string $name       Proposed filter set name.
	 * @param int    $ignore_id  ID to exclude from the uniqueness check (for updates).
	 */
	public function generate_unique_slug( string $name, int $ignore_id = 0 ): string {
		$base = sanitize_title( $name );
		$base = $base ?: 'filter-set';
		$slug = $base;
		$i    = 2;

		while ( true ) {
			$existing = $this->find_by_slug( $slug );

			if ( ! $existing || (int) $existing['id'] === $ignore_id ) {
				return $slug;
			}

			$slug = $base . '-' . $i;
			++$i;
		}
	}
}
