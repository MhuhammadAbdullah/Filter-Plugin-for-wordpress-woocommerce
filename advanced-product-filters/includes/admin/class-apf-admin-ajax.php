<?php
/**
 * Admin-side AJAX handlers for the Filter Set builder.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Every action here is capability-gated to `manage_woocommerce` and
 * nonce-checked against the `apf_admin_nonce` action localised to
 * `assets/js/admin-builder.js`.
 */
final class APF_Admin_Ajax {

	/**
	 * Registers every `wp_ajax_apf_*` action.
	 */
	public function __construct() {
		$actions = array(
			'apf_save_filter_set',
			'apf_toggle_filter_set',
			'apf_reorder_filter_sets',
			'apf_duplicate_filter_set',
			'apf_delete_filter_set',
			'apf_save_settings',
			'apf_reset_plugin',
			'apf_export_settings',
			'apf_import_settings',
		);

		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_' . $action, array( $this, str_replace( 'apf_', 'handle_', $action ) ) );
		}
	}

	/**
	 * Verifies the shared admin nonce and capability, halting the request
	 * with a JSON error response when either check fails.
	 *
	 * @return void
	 */
	private function guard(): void {
		if ( ! current_user_can( APF_Admin::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'advanced-product-filters' ) ), 403 );
		}

		check_ajax_referer( 'apf_admin_nonce', 'nonce' );
	}

	/**
	 * Saves a Filter Set from the builder page.
	 *
	 * @return void
	 */
	public function handle_save_filter_set(): void {
		$this->guard();

		$raw = isset( $_POST['filter_set'] ) ? wp_unslash( $_POST['filter_set'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$data = json_decode( is_string( $raw ) ? $raw : '', true );

		if ( ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid Filter Set payload.', 'advanced-product-filters' ) ) );
		}

		$id = APF_Filter_Sets::save( $data );

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Could not save the Filter Set.', 'advanced-product-filters' ) ) );
		}

		$filter_set = APF_Filter_Sets::get( $id );

		wp_send_json_success( array( 'filterSet' => $filter_set ? $filter_set->to_array() : null ) );
	}

	/**
	 * Toggles a Filter Set's enabled state from the list page switch.
	 *
	 * @return void
	 */
	public function handle_toggle_filter_set(): void {
		$this->guard();

		$id      = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$enabled = ! empty( $_POST['enabled'] );

		$filter_set = APF_Filter_Sets::get( $id );

		if ( ! $filter_set ) {
			wp_send_json_error( array( 'message' => __( 'Filter Set not found.', 'advanced-product-filters' ) ) );
		}

		$data            = $filter_set->to_array();
		$data['enabled'] = $enabled;

		APF_Filter_Sets::save( $data );

		wp_send_json_success();
	}

	/**
	 * Persists the drag-and-drop priority order from the list page.
	 *
	 * @return void
	 */
	public function handle_reorder_filter_sets(): void {
		$this->guard();

		$ids = isset( $_POST['order'] ) && is_array( $_POST['order'] ) ? array_map( 'absint', wp_unslash( $_POST['order'] ) ) : array();

		APF_Filter_Sets::reorder( $ids );

		wp_send_json_success();
	}

	/**
	 * Duplicates a Filter Set.
	 *
	 * @return void
	 */
	public function handle_duplicate_filter_set(): void {
		$this->guard();

		$id     = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$new_id = APF_Filter_Sets::duplicate( $id );

		if ( ! $new_id ) {
			wp_send_json_error( array( 'message' => __( 'Could not duplicate the Filter Set.', 'advanced-product-filters' ) ) );
		}

		wp_send_json_success( array( 'id' => $new_id ) );
	}

	/**
	 * Deletes a Filter Set.
	 *
	 * @return void
	 */
	public function handle_delete_filter_set(): void {
		$this->guard();

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		wp_send_json_success( array( 'deleted' => APF_Filter_Sets::delete( $id ) ) );
	}

	/**
	 * Saves the global settings form.
	 *
	 * @return void
	 */
	public function handle_save_settings(): void {
		if ( ! current_user_can( APF_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'advanced-product-filters' ) );
		}

		check_admin_referer( 'apf_settings_save', 'apf_settings_nonce' );

		APF_Settings::update( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		wp_safe_redirect( add_query_arg( array( 'page' => 'apf-settings', 'apf-saved' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Deletes every Filter Set and resets settings to factory defaults.
	 *
	 * @return void
	 */
	public function handle_reset_plugin(): void {
		$this->guard();

		foreach ( APF_Filter_Sets::get_all() as $filter_set ) {
			APF_Filter_Sets::delete( $filter_set->get_id() );
		}

		APF_Settings::reset();

		wp_send_json_success();
	}

	/**
	 * Builds the JSON export payload (all Filter Sets + global settings).
	 *
	 * @return void
	 */
	public function handle_export_settings(): void {
		$this->guard();

		wp_send_json_success( APF_Import_Export::export() );
	}

	/**
	 * Imports a previously exported JSON payload.
	 *
	 * @return void
	 */
	public function handle_import_settings(): void {
		$this->guard();

		$raw     = isset( $_POST['payload'] ) ? wp_unslash( $_POST['payload'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$payload = json_decode( is_string( $raw ) ? $raw : '', true );

		if ( ! is_array( $payload ) ) {
			wp_send_json_error( array( 'message' => __( 'That file does not look like a valid Advanced Product Filters export.', 'advanced-product-filters' ) ) );
		}

		$result = APF_Import_Export::import( $payload );

		wp_send_json_success( $result );
	}
}
