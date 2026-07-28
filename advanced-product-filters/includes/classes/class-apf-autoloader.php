<?php
/**
 * PSR-4-ish autoloader for the plugin's `APF_` prefixed classes.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Maps `APF_Class_Name` to `includes/**\/class-apf-class-name.php` without
 * requiring a Composer dependency, keeping the plugin dependency-free.
 */
final class APF_Autoloader {

	/**
	 * Directories that are searched for class files, relative to APF_PATH.
	 *
	 * @var string[]
	 */
	private static array $directories = array(
		'includes/classes/',
		'includes/admin/',
		'includes/frontend/',
		'includes/ajax/',
		'includes/elementor/',
		'includes/elementor/widgets/',
	);

	/**
	 * Registers the autoloader with the SPL autoload stack.
	 *
	 * @return void
	 */
	public static function register(): void {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Attempts to load a class file for the given class name.
	 *
	 * @param string $class Fully qualified class name.
	 * @return void
	 */
	public static function autoload( string $class ): void {
		if ( 0 !== strpos( $class, 'APF_' ) ) {
			return;
		}

		$file_name = 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';

		foreach ( self::$directories as $directory ) {
			$path = APF_PATH . $directory . $file_name;

			if ( is_readable( $path ) ) {
				require_once $path;
				return;
			}
		}
	}
}
