<?php
/**
 * Contract implemented by every custom-table repository.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Standard CRUD contract for repositories backed by a custom table.
 */
interface RepositoryInterface {

	/**
	 * Finds a single record by primary key.
	 *
	 * @param int $id Record ID.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array;

	/**
	 * Finds records matching simple equality criteria.
	 *
	 * @param array<string, mixed> $criteria Column => value pairs.
	 * @return array<int, array<string, mixed>>
	 */
	public function find_by( array $criteria = array() ): array;

	/**
	 * Inserts a new record.
	 *
	 * @param array<string, mixed> $data Column => value pairs.
	 * @return int Newly inserted ID, or 0 on failure.
	 */
	public function create( array $data ): int;

	/**
	 * Updates an existing record.
	 *
	 * @param int                   $id   Record ID.
	 * @param array<string, mixed> $data Column => value pairs.
	 * @return bool
	 */
	public function update( int $id, array $data ): bool;

	/**
	 * Deletes a record.
	 *
	 * @param int $id Record ID.
	 * @return bool
	 */
	public function delete( int $id ): bool;
}
