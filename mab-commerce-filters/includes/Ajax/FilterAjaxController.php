<?php
/**
 * Handles the public AJAX endpoint that powers instant product filtering.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Ajax;

use MABCommerceFilters\Helpers\Helper;
use MABCommerceFilters\Services\FilterRequestHandler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin admin-ajax adapter around FilterRequestHandler: reads and
 * sanitises the POST payload, verifies the public nonce, and returns the
 * shared handler's result as a JSON response.
 */
final class FilterAjaxController {

	/**
	 * Registers the public (logged-in + guest) AJAX action.
	 */
	public function register(): void {
		add_action( 'wp_ajax_mabcf_filter_products', array( $this, 'handle' ) );
		add_action( 'wp_ajax_nopriv_mabcf_filter_products', array( $this, 'handle' ) );
	}

	/**
	 * Handles the AJAX request.
	 */
	public function handle(): void {
		check_ajax_referer( 'mabcf_public', 'nonce' );

		$filter_set_id = isset( $_POST['filter_set_id'] ) ? absint( $_POST['filter_set_id'] ) : 0;

		$context = array(
			'type'     => isset( $_POST['context_type'] ) ? sanitize_key( wp_unslash( $_POST['context_type'] ) ) : 'global',
			'taxonomy' => isset( $_POST['context_taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['context_taxonomy'] ) ) : '',
			'value'    => isset( $_POST['context_value'] ) ? sanitize_text_field( wp_unslash( $_POST['context_value'] ) ) : '',
		);

		$raw_selection = isset( $_POST['mabcf_filter'] ) && is_array( $_POST['mabcf_filter'] ) ? wp_unslash( $_POST['mabcf_filter'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash

		$paged   = isset( $_POST['paged'] ) ? absint( $_POST['paged'] ) : 1;
		$orderby = isset( $_POST['orderby'] ) ? sanitize_text_field( wp_unslash( $_POST['orderby'] ) ) : 'menu_order';

		$result = ( new FilterRequestHandler() )->handle( $filter_set_id, $raw_selection, $context, $paged, $orderby );

		if ( ! $result['ok'] ) {
			Helper::json_error( $result['error'], 404 );
			return;
		}

		Helper::json_success( $result['data'] );
	}
}
