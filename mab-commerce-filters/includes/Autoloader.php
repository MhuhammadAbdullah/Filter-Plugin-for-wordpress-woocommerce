<?php
/**
 * PSR-4 autoloader for the MABCommerceFilters namespace.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps the MABCommerceFilters\ namespace root to /includes and
 * autoloads classes, interfaces and traits on demand.
 */
final class Autoloader {

	/**
	 * Namespace prefix handled by this autoloader.
	 *
	 * @var string
	 */
	private const PREFIX = __NAMESPACE__ . '\\';

	/**
	 * Base directory containing the namespace root.
	 *
	 * @var string
	 */
	private static string $base_dir = '';

	/**
	 * Registers the autoloader with the SPL autoload stack.
	 */
	public static function register(): void {
		self::$base_dir = __DIR__ . '/';
		spl_autoload_register( array( __CLASS__, 'load' ) );
	}

	/**
	 * Resolves a fully qualified class name to a file path and requires it.
	 *
	 * @param string $class Fully qualified class name.
	 */
	public static function load( string $class ): void {
		if ( ! str_starts_with( $class, self::PREFIX ) ) {
			return;
		}

		$relative = substr( $class, strlen( self::PREFIX ) );
		$relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );
		$file     = self::$base_dir . $relative . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
