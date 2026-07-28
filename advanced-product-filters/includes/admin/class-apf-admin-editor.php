<?php
/**
 * Renders the Filter Sets list and the drag-and-drop builder page.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * The list page shows every Filter Set with reorder handles, enable
 * toggles and quick actions. The editor page hydrates a JSON snapshot of
 * a single Filter Set into `assets/js/admin-builder.js`, which renders
 * the interactive locations + sections builder and the live preview pane.
 */
final class APF_Admin_Editor {

	/**
	 * Renders the "Filter Sets" list page.
	 *
	 * @return void
	 */
	public function render_list_page(): void {
		if ( ! current_user_can( APF_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'advanced-product-filters' ) );
		}

		$filter_sets = APF_Filter_Sets::get_all();

		require APF_PATH . 'includes/admin/views/view-list.php';
	}

	/**
	 * Renders the Filter Set add/edit builder page.
	 *
	 * @return void
	 */
	public function render_editor_page(): void {
		if ( ! current_user_can( APF_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'advanced-product-filters' ) );
		}

		$id         = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$filter_set = $id ? APF_Filter_Sets::get( $id ) : null;

		$data = $filter_set
			? $filter_set->to_array()
			: array(
				'id'        => 0,
				'name'      => '',
				'enabled'   => true,
				'priority'  => 10,
				'locations' => array(),
				'sections'  => APF_Filter_Set::default_sections(),
				'settings'  => array(),
			);

		$preview_url = $this->get_preview_url( $id );

		require APF_PATH . 'includes/admin/views/view-editor.php';
	}

	/**
	 * Builds the front-end URL used for the live preview iframe, forcing
	 * this Filter Set to render regardless of its normal location rules.
	 *
	 * @param int $id Filter Set post ID (0 for an unsaved set).
	 * @return string
	 */
	private function get_preview_url( int $id ): string {
		$base = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );

		if ( ! $id ) {
			return $base;
		}

		return add_query_arg(
			array(
				'apf_preview_id'    => $id,
				'apf_preview_nonce' => wp_create_nonce( 'apf_preview_' . $id ),
			),
			$base
		);
	}
}
