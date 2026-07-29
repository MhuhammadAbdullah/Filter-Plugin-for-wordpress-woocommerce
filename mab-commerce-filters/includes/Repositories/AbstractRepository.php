<?php
/**
 * Shared CRUD implementation for custom-table repositories.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Repositories;

use MABCommerceFilters\Database\Schema;
use MABCommerceFilters\Interfaces\RepositoryInterface;
use wpdb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base repository providing prepared-SQL CRUD helpers on top of $wpdb.
 */
abstract class AbstractRepository implements RepositoryInterface {

	/**
	 * WordPress database access object.
	 *
	 * @var wpdb
	 */
	protected wpdb $db;

	/**
	 * Fully prefixed table name.
	 *
	 * @var string
	 */
	protected string $table;

	/**
	 * Columns that hold JSON-encoded arrays and must be decoded on read.
	 *
	 * @var string[]
	 */
	protected array $json_columns = array();

	/**
	 * Sets up the table name from the short schema key.
	 */
	public function __construct() {
		global $wpdb;

		$this->db    = $wpdb;
		$this->table = Schema::table( $this->table_key() );
	}

	/**
	 * Short schema key identifying this repository's table.
	 */
	abstract protected function table_key(): string;

	/**
	 * {@inheritDoc}
	 */
	public function find( int $id ): ?array {
		$row = $this->db->get_row(
			$this->db->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return $row ? $this->decode( $row ) : null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function find_by( array $criteria = array() ): array {
		$where  = array( '1=1' );
		$values = array();

		foreach ( $criteria as $column => $value ) {
			$column   = preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $column );
			$where[]  = "{$column} = %s";
			$values[] = is_scalar( $value ) ? (string) $value : '';
		}

		$sql = "SELECT * FROM {$this->table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY id ASC';

		if ( $values ) {
			$sql = $this->db->prepare( $sql, $values ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		$rows = $this->db->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array_map( array( $this, 'decode' ), $rows ?: array() );
	}

	/**
	 * {@inheritDoc}
	 */
	public function create( array $data ): int {
		$data = $this->encode( $data );

		$now = current_time( 'mysql' );
		if ( $this->has_column( 'created_at' ) && ! isset( $data['created_at'] ) ) {
			$data['created_at'] = $now;
		}
		if ( $this->has_column( 'updated_at' ) && ! isset( $data['updated_at'] ) ) {
			$data['updated_at'] = $now;
		}

		$formats = $this->formats_for( $data );

		$inserted = $this->db->insert( $this->table, $data, $formats );

		return $inserted ? (int) $this->db->insert_id : 0;
	}

	/**
	 * {@inheritDoc}
	 */
	public function update( int $id, array $data ): bool {
		$data = $this->encode( $data );

		if ( $this->has_column( 'updated_at' ) ) {
			$data['updated_at'] = current_time( 'mysql' );
		}

		$formats = $this->formats_for( $data );

		$result = $this->db->update( $this->table, $data, array( 'id' => $id ), $formats, array( '%d' ) );

		return false !== $result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete( int $id ): bool {
		return (bool) $this->db->delete( $this->table, array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Counts all records optionally matching simple equality criteria.
	 *
	 * @param array<string, mixed> $criteria Column => value pairs.
	 */
	public function count( array $criteria = array() ): int {
		$where  = array( '1=1' );
		$values = array();

		foreach ( $criteria as $column => $value ) {
			$column   = preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $column );
			$where[]  = "{$column} = %s";
			$values[] = is_scalar( $value ) ? (string) $value : '';
		}

		$sql = "SELECT COUNT(*) FROM {$this->table} WHERE " . implode( ' AND ', $where );

		if ( $values ) {
			$sql = $this->db->prepare( $sql, $values ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		return (int) $this->db->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * JSON-encodes configured columns before writing to the database.
	 *
	 * @param array<string, mixed> $data Raw data.
	 * @return array<string, mixed>
	 */
	protected function encode( array $data ): array {
		foreach ( $this->json_columns as $column ) {
			if ( array_key_exists( $column, $data ) && ! is_string( $data[ $column ] ) ) {
				$data[ $column ] = wp_json_encode( $data[ $column ] );
			}
		}

		return $data;
	}

	/**
	 * JSON-decodes configured columns after reading from the database.
	 *
	 * @param array<string, mixed> $row Raw database row.
	 * @return array<string, mixed>
	 */
	protected function decode( array $row ): array {
		foreach ( $this->json_columns as $column ) {
			if ( isset( $row[ $column ] ) && is_string( $row[ $column ] ) && '' !== $row[ $column ] ) {
				$decoded         = json_decode( $row[ $column ], true );
				$row[ $column ] = is_array( $decoded ) ? $decoded : array();
			} elseif ( isset( $row[ $column ] ) ) {
				$row[ $column ] = array();
			}
		}

		return $row;
	}

	/**
	 * Whether the target table has the given column, cached per request.
	 *
	 * @param string $column Column name.
	 */
	protected function has_column( string $column ): bool {
		static $columns = array();

		if ( ! isset( $columns[ $this->table ] ) ) {
			$results                    = $this->db->get_col( "DESCRIBE {$this->table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
			$columns[ $this->table ]    = is_array( $results ) ? $results : array();
		}

		return in_array( $column, $columns[ $this->table ], true );
	}

	/**
	 * Infers %d/%f/%s formats for wpdb insert/update based on PHP types.
	 *
	 * @param array<string, mixed> $data Data being written.
	 * @return string[]
	 */
	protected function formats_for( array $data ): array {
		$formats = array();

		foreach ( $data as $value ) {
			if ( is_int( $value ) ) {
				$formats[] = '%d';
			} elseif ( is_float( $value ) ) {
				$formats[] = '%f';
			} else {
				$formats[] = '%s';
			}
		}

		return $formats;
	}
}
