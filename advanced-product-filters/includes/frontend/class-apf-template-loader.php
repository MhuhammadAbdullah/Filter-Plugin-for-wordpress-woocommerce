<?php
/**
 * Locates and renders front-end templates, honouring theme overrides.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Mirrors the WooCommerce template override convention: a theme can
 * override any template by copying it to
 * `yourtheme/advanced-product-filters/{template}.php`.
 */
final class APF_Template_Loader {

	/**
	 * Renders a template immediately, echoing its output.
	 *
	 * @param string               $name Template file name, without extension, relative to `templates/`.
	 * @param array<string, mixed> $args Variables extracted into the template's local scope.
	 * @return void
	 */
	public static function render( string $name, array $args = array() ): void {
		echo self::get( $name, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- templates own their own escaping.
	}

	/**
	 * Renders a template and returns the resulting markup instead of
	 * echoing it, used when a section's output needs to be embedded in a
	 * JSON AJAX response.
	 *
	 * @param string               $name Template file name, without extension.
	 * @param array<string, mixed> $args Variables extracted into the template's local scope.
	 * @return string
	 */
	public static function get( string $name, array $args = array() ): string {
		$path = self::locate( $name );

		if ( ! $path ) {
			return '';
		}

		ob_start();

		( static function () use ( $path, $args ): void {
			extract( $args, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			include $path;
		} )();

		return (string) ob_get_clean();
	}

	/**
	 * Resolves the absolute path for a template, checking the active
	 * theme, its parent, then the plugin's bundled templates.
	 *
	 * @param string $name Template file name, without extension.
	 * @return string|null
	 */
	private static function locate( string $name ): ?string {
		$relative  = 'advanced-product-filters/' . $name . '.php';
		$candidates = array(
			trailingslashit( get_stylesheet_directory() ) . $relative,
			trailingslashit( get_template_directory() ) . $relative,
			APF_PATH . 'templates/' . $name . '.php',
		);

		/**
		 * Filters the template search path list before the first match wins.
		 *
		 * @param string[] $candidates Absolute file paths, most specific first.
		 * @param string   $name       Template name being located.
		 */
		$candidates = apply_filters( 'apf_template_candidates', $candidates, $name );

		foreach ( $candidates as $candidate ) {
			if ( is_readable( $candidate ) ) {
				return $candidate;
			}
		}

		return null;
	}
}
