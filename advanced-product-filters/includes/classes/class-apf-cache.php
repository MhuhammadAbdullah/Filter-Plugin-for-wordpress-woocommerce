<?php
/**
 * Caching wrapper used for facet counts, price ranges and rendered markup.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Prefers a persistent object cache (Redis/Memcached) when the host
 * provides one and transparently falls back to transients so the plugin
 * stays fast on shared hosting with 100,000+ product catalogs.
 */
final class APF_Cache {

	/**
	 * Cache group used for `wp_cache_*` calls.
	 *
	 * @var string
	 */
	private const GROUP = 'advanced_product_filters';

	/**
	 * Reads a cached value.
	 *
	 * @param string $key Cache key.
	 * @return mixed|false False when the key is missing or caching is disabled.
	 */
	public static function get( string $key ) {
		if ( ! self::is_enabled() ) {
			return false;
		}

		if ( wp_using_ext_object_cache() ) {
			return wp_cache_get( $key, self::GROUP );
		}

		return get_transient( self::transient_key( $key ) );
	}

	/**
	 * Writes a value to the cache.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to store.
	 * @param int    $ttl   Time to live in seconds. Defaults to the configured setting.
	 * @return bool
	 */
	public static function set( string $key, $value, int $ttl = 0 ): bool {
		if ( ! self::is_enabled() ) {
			return false;
		}

		if ( $ttl <= 0 ) {
			$ttl = (int) APF_Settings::get( 'cache_ttl', 300 );
		}

		if ( wp_using_ext_object_cache() ) {
			return wp_cache_set( $key, $value, self::GROUP, $ttl );
		}

		return set_transient( self::transient_key( $key ), $value, $ttl );
	}

	/**
	 * Deletes a single cached value.
	 *
	 * @param string $key Cache key.
	 * @return bool
	 */
	public static function delete( string $key ): bool {
		if ( wp_using_ext_object_cache() ) {
			return wp_cache_delete( $key, self::GROUP );
		}

		return delete_transient( self::transient_key( $key ) );
	}

	/**
	 * Flushes every value the plugin has cached. Used when a product is
	 * saved/deleted so facet counts never go stale, and from the admin
	 * "Reset Plugin Settings" action.
	 *
	 * @return void
	 */
	public static function flush(): void {
		if ( wp_using_ext_object_cache() ) {
			// Object caches rarely support group flushing on shared hosts,
			// so we bump the cache-busting salt instead of trying to wipe it.
			update_option( 'apf_cache_salt', wp_generate_password( 12, false ), false );
			return;
		}

		global $wpdb;

		$prefix = $wpdb->esc_like( '_transient_apf_' ) . '%';
		$prefix_timeout = $wpdb->esc_like( '_transient_timeout_apf_' ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $prefix, $prefix_timeout ) );
	}

	/**
	 * Builds a namespaced transient key, respecting the 172 character limit
	 * WordPress imposes on `option_name`.
	 *
	 * @param string $key Raw cache key.
	 * @return string
	 */
	private static function transient_key( string $key ): string {
		$salt = get_option( 'apf_cache_salt', 'default' );

		return 'apf_' . substr( md5( $salt . $key ), 0, 40 );
	}

	/**
	 * Whether caching is enabled via the plugin settings.
	 *
	 * @return bool
	 */
	private static function is_enabled(): bool {
		return (bool) APF_Settings::get( 'enable_cache', true );
	}
}
