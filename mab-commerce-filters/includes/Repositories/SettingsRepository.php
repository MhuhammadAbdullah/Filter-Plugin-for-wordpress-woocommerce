<?php
/**
 * Repository for the settings table (key/value store, not wp_options).
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Repositories;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Typed get/set access to plugin settings persisted in mabcf_settings.
 */
final class SettingsRepository extends AbstractRepository {

	/**
	 * Request-scoped cache of loaded settings.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $cache = null;

	/**
	 * {@inheritDoc}
	 */
	protected function table_key(): string {
		return 'settings';
	}

	/**
	 * Returns a setting value, or the given default when unset.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback value.
	 * @return mixed
	 */
	public function get( string $key, $default = false ) {
		$all = $this->all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Persists a setting value (JSON-encoded transparently).
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Value to store.
	 */
	public function set( string $key, $value ): bool {
		$existing = $this->db->get_var(
			$this->db->prepare( "SELECT id FROM {$this->table} WHERE setting_key = %s", $key ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		$encoded = is_scalar( $value ) ? (string) $value : wp_json_encode( $value );

		if ( $existing ) {
			$result = $this->db->update(
				$this->table,
				array(
					'setting_value' => $encoded,
					'updated_at'    => current_time( 'mysql' ),
				),
				array( 'id' => (int) $existing ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$result = $this->db->insert(
				$this->table,
				array(
					'setting_key'   => $key,
					'setting_value' => $encoded,
					'updated_at'    => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%s' )
			);
		}

		$this->cache = null;

		return false !== $result;
	}

	/**
	 * Persists multiple settings at once.
	 *
	 * @param array<string, mixed> $values Key => value pairs.
	 */
	public function set_many( array $values ): void {
		foreach ( $values as $key => $value ) {
			$this->set( (string) $key, $value );
		}
	}

	/**
	 * Deletes a setting.
	 *
	 * @param string $key Setting key.
	 */
	public function forget( string $key ): void {
		$this->db->delete( $this->table, array( 'setting_key' => $key ), array( '%s' ) );
		$this->cache = null;
	}

	/**
	 * Loads and decodes every stored setting, memoised for this request.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		if ( null !== $this->cache ) {
			return $this->cache;
		}

		$rows = $this->db->get_results( "SELECT setting_key, setting_value FROM {$this->table}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$settings = array();

		foreach ( $rows ?: array() as $row ) {
			$value = $row['setting_value'];
			$decoded = json_decode( (string) $value, true );

			$settings[ $row['setting_key'] ] = ( null === $decoded && 'null' !== trim( (string) $value ) ) ? $value : $decoded;
		}

		$this->cache = $settings;

		return $this->cache;
	}
}
