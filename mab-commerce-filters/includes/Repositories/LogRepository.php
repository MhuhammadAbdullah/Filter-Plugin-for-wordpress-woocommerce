<?php
/**
 * Repository for the logs table (debug / AJAX / database logging).
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Repositories;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes and reads structured log entries used by Debug Mode.
 */
final class LogRepository extends AbstractRepository {

	/**
	 * {@inheritDoc}
	 */
	protected array $json_columns = array( 'context' );

	/**
	 * {@inheritDoc}
	 */
	protected function table_key(): string {
		return 'logs';
	}

	/**
	 * Writes a log entry.
	 *
	 * @param string                $channel Logical channel, e.g. query|ajax|database.
	 * @param string                $message Human-readable message.
	 * @param array<string, mixed>  $context Extra structured context.
	 * @param string                $type    Severity: info|warning|error.
	 */
	public function log( string $channel, string $message, array $context = array(), string $type = 'info' ): void {
		$this->create(
			array(
				'log_type' => $type,
				'channel'  => $channel,
				'message'  => $message,
				'context'  => $context,
			)
		);
	}

	/**
	 * Returns the most recent log entries, optionally filtered by channel.
	 *
	 * @param string $channel Optional channel filter.
	 * @param int    $limit   Max rows to return.
	 * @return array<int, array<string, mixed>>
	 */
	public function recent( string $channel = '', int $limit = 200 ): array {
		if ( $channel ) {
			$rows = $this->db->get_results(
				$this->db->prepare(
					"SELECT * FROM {$this->table} WHERE channel = %s ORDER BY id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$channel,
					$limit
				),
				ARRAY_A
			);
		} else {
			$rows = $this->db->get_results(
				$this->db->prepare( "SELECT * FROM {$this->table} ORDER BY id DESC LIMIT %d", $limit ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				ARRAY_A
			);
		}

		return array_map( array( $this, 'decode' ), $rows ?: array() );
	}

	/**
	 * Deletes every log entry.
	 */
	public function truncate(): void {
		$this->db->query( "TRUNCATE TABLE {$this->table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Deletes log entries older than the given number of days.
	 *
	 * @param int $days Retention window in days.
	 */
	public function prune_older_than( int $days ): void {
		$this->db->query(
			$this->db->prepare( "DELETE FROM {$this->table} WHERE created_at < %s", gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}
}
