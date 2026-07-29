<?php
/**
 * Small stateless helper utilities shared across the plugin.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Grab-bag of pure helper functions: capability, sanitisation and
 * asset-URL helpers used by both the admin and front-end layers.
 */
final class Helper {

	/**
	 * Capability required to manage the plugin.
	 */
	public const CAPABILITY = 'manage_woocommerce';

	/**
	 * Whether the current user can manage MAB Commerce Filters.
	 */
	public static function current_user_can_manage(): bool {
		return current_user_can( self::CAPABILITY );
	}

	/**
	 * Recursively sanitises an arbitrary settings array read from a
	 * request: keys are reduced to safe identifiers, string leaves are
	 * passed through sanitize_text_field(), scalars kept as-is.
	 *
	 * @param mixed $value Raw value.
	 * @return mixed
	 */
	public static function sanitize_recursive( $value ) {
		if ( is_array( $value ) ) {
			$clean = array();

			foreach ( $value as $key => $item ) {
				$clean_key           = is_string( $key ) ? sanitize_key( $key ) : $key;
				$clean[ $clean_key ] = self::sanitize_recursive( $item );
			}

			return $clean;
		}

		if ( is_string( $value ) ) {
			return sanitize_text_field( wp_unslash( $value ) );
		}

		return $value;
	}

	/**
	 * Builds a versioned asset URL under the plugin's /assets directory.
	 *
	 * @param string $path Relative path, e.g. "css/frontend.css".
	 */
	public static function asset_url( string $path ): string {
		return MABCF_URL . 'assets/' . ltrim( $path, '/' ) . '?v=' . MABCF_VERSION;
	}

	/**
	 * Sends a standard JSON success response for admin/frontend AJAX
	 * endpoints and terminates the request.
	 *
	 * @param mixed $data Response payload.
	 */
	public static function json_success( $data = null ): void {
		wp_send_json_success( $data );
	}

	/**
	 * Sends a standard JSON error response and terminates the request.
	 *
	 * @param string $message Error message.
	 * @param int    $code    HTTP-ish status code to embed in the payload.
	 */
	public static function json_error( string $message, int $code = 400 ): void {
		wp_send_json_error( array( 'message' => $message, 'code' => $code ) );
	}

	/**
	 * Converts a comma or array list of scalars into a clean int array.
	 *
	 * @param mixed $value Raw value.
	 * @return int[]
	 */
	public static function to_int_array( $value ): array {
		if ( ! is_array( $value ) ) {
			$value = array_filter( array_map( 'trim', explode( ',', (string) $value ) ), 'strlen' );
		}

		return array_values( array_map( 'intval', $value ) );
	}
}
