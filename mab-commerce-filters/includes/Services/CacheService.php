<?php
/**
 * Thin caching wrapper: object cache when available, transients otherwise.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Services;

use MABCommerceFilters\Repositories\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Caches expensive filter-count and query results.
 */
final class CacheService {

	/**
	 * Cache group used for wp_cache_* calls.
	 *
	 * @var string
	 */
	private const GROUP = 'mabcf';

	/**
	 * Settings repository, used to read cache toggles/TTL.
	 *
	 * @var SettingsRepository
	 */
	private SettingsRepository $settings;

	/**
	 * Constructor.
	 *
	 * @param SettingsRepository|null $settings Injected settings repository.
	 */
	public function __construct( ?SettingsRepository $settings = null ) {
		$this->settings = $settings ?? new SettingsRepository();
	}

	/**
	 * Whether caching is currently enabled via plugin settings.
	 */
	public function is_enabled(): bool {
		return '1' === (string) $this->settings->get( 'cache_enabled', '1' );
	}

	/**
	 * Configured cache TTL in seconds.
	 */
	public function ttl(): int {
		return max( 30, (int) $this->settings->get( 'cache_ttl', 300 ) );
	}

	/**
	 * Reads a cached value.
	 *
	 * @param string $key Cache key.
	 * @return mixed|false False when missing or caching disabled.
	 */
	public function get( string $key ) {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		if ( wp_using_ext_object_cache() ) {
			return wp_cache_get( $key, self::GROUP );
		}

		return get_transient( self::transient_key( $key ) );
	}

	/**
	 * Writes a value to cache.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to store.
	 * @param int    $ttl   Optional TTL override in seconds.
	 */
	public function set( string $key, $value, int $ttl = 0 ): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$ttl = $ttl > 0 ? $ttl : $this->ttl();

		if ( wp_using_ext_object_cache() ) {
			wp_cache_set( $key, $value, self::GROUP, $ttl );
			return;
		}

		set_transient( self::transient_key( $key ), $value, $ttl );
	}

	/**
	 * Deletes a single cached value.
	 *
	 * @param string $key Cache key.
	 */
	public function delete( string $key ): void {
		if ( wp_using_ext_object_cache() ) {
			wp_cache_delete( $key, self::GROUP );
		}

		delete_transient( self::transient_key( $key ) );
	}

	/**
	 * Flushes every cached value written by this service (transient path)
	 * and bumps the object-cache group when possible.
	 */
	public function flush(): void {
		global $wpdb;

		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( self::GROUP );
		}

		$wpdb->query(
			$wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '\\_transient\\_mabcf\\_%' ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		$wpdb->query(
			$wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '\\_transient\\_timeout\\_mabcf\\_%' ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Builds a stable cache key from arbitrary parts.
	 *
	 * @param string $prefix Logical prefix, e.g. "options" or "count".
	 * @param mixed  ...$parts Additional parts hashed into the key.
	 */
	public function make_key( string $prefix, ...$parts ): string {
		return 'mabcf_' . $prefix . '_' . md5( wp_json_encode( $parts ) );
	}

	/**
	 * Namespaces a cache key for transient storage (64 char limit safe).
	 *
	 * @param string $key Raw cache key.
	 */
	private static function transient_key( string $key ): string {
		return substr( $key, 0, 172 );
	}
}
