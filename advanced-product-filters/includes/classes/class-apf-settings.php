<?php
/**
 * Global plugin settings stored in a single `wp_options` row.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Centralises reading, writing, sanitising and resetting the plugin's
 * global settings so every other class has one source of truth.
 */
final class APF_Settings {

	/**
	 * The option name the settings are persisted under.
	 *
	 * @var string
	 */
	public const OPTION = 'apf_settings';

	/**
	 * In-memory cache of the loaded settings for the current request.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $settings = null;

	/**
	 * Returns the factory default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'instant_ajax'       => true,
			'show_apply_button'  => false,
			'show_clear_button'  => true,
			'url_sync'           => true,
			'mobile_breakpoint'  => 782,
			'offcanvas_position' => 'left',
			'enable_cache'       => true,
			'cache_ttl'          => 300,
			'primary_color'      => '#111111',
			'accent_color'       => '#2271b1',
			'products_per_page'  => 24,
			'new_arrival_days'   => 30,
			'sticky_sidebar'     => true,
			'ratings_scale'      => 5,
			'pagination_mode'    => 'pages',
		);
	}

	/**
	 * Returns a single setting value, falling back to its default.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $fallback Fallback value when the key is entirely unknown.
	 * @return mixed
	 */
	public static function get( string $key, $fallback = null ) {
		$all = self::all();

		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}

		return $fallback;
	}

	/**
	 * Returns every current setting merged over the factory defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		if ( null === self::$settings ) {
			$stored         = get_option( self::OPTION, array() );
			self::$settings = wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
		}

		return self::$settings;
	}

	/**
	 * Persists a full settings array after sanitising each known key.
	 *
	 * @param array<string, mixed> $settings Raw settings, usually from `$_POST`.
	 * @return void
	 */
	public static function update( array $settings ): void {
		$sanitised = self::sanitise( wp_parse_args( $settings, self::defaults() ) );

		update_option( self::OPTION, $sanitised, false );

		self::$settings = null;

		APF_Cache::flush();
	}

	/**
	 * Deletes the stored settings, reverting to factory defaults.
	 *
	 * @return void
	 */
	public static function reset(): void {
		delete_option( self::OPTION );
		self::$settings = null;
		APF_Cache::flush();
	}

	/**
	 * Sanitises a raw settings array according to each field's expected type.
	 *
	 * @param array<string, mixed> $settings Raw settings.
	 * @return array<string, mixed>
	 */
	private static function sanitise( array $settings ): array {
		$booleans = array( 'instant_ajax', 'show_apply_button', 'show_clear_button', 'url_sync', 'enable_cache', 'sticky_sidebar' );
		$integers = array( 'mobile_breakpoint', 'cache_ttl', 'products_per_page', 'new_arrival_days', 'ratings_scale' );
		$colors   = array( 'primary_color', 'accent_color' );

		foreach ( $booleans as $key ) {
			$settings[ $key ] = ! empty( $settings[ $key ] );
		}

		foreach ( $integers as $key ) {
			$settings[ $key ] = max( 0, absint( $settings[ $key ] ?? 0 ) );
		}

		foreach ( $colors as $key ) {
			$sanitised_color  = sanitize_hex_color( $settings[ $key ] ?? '' );
			$settings[ $key ] = $sanitised_color ? $sanitised_color : self::defaults()[ $key ];
		}

		$settings['offcanvas_position'] = in_array( $settings['offcanvas_position'] ?? '', array( 'left', 'right' ), true )
			? $settings['offcanvas_position']
			: 'left';

		$settings['pagination_mode'] = in_array( $settings['pagination_mode'] ?? '', array( 'pages', 'infinite' ), true )
			? $settings['pagination_mode']
			: 'pages';

		return $settings;
	}
}
