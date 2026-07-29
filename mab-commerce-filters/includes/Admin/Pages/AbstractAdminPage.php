<?php
/**
 * Shared scaffolding for every admin screen.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Admin\Pages;

use MABCommerceFilters\Helpers\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides the common page chrome (title, tab nav, capability gate) so
 * concrete admin pages only implement render_content().
 */
abstract class AbstractAdminPage {

	/**
	 * Page slug used in the submenu and as `?page=`.
	 */
	abstract public function slug(): string;

	/**
	 * Menu / page title.
	 */
	abstract public function title(): string;

	/**
	 * Renders the page-specific content.
	 */
	abstract protected function render_content(): void;

	/**
	 * Renders the full admin page, including the shared header/tab nav.
	 */
	final public function render(): void {
		if ( ! Helper::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'mab-commerce-filters' ) );
		}

		echo '<div class="wrap mabcf-admin">';
		echo '<div class="mabcf-admin__header">';
		echo '<h1 class="mabcf-admin__logo">' . esc_html__( 'MAB Commerce Filters', 'mab-commerce-filters' ) . '</h1>';
		$this->render_tabs();
		echo '</div>';
		echo '<div class="mabcf-admin__body">';
		$this->render_content();
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Renders the shared top-level tab navigation across all screens.
	 */
	private function render_tabs(): void {
		$tabs = array(
			'mabcf-dashboard'     => __( 'Dashboard', 'mab-commerce-filters' ),
			'mabcf-filter-sets'   => __( 'Filter Sets', 'mab-commerce-filters' ),
			'mabcf-locations'     => __( 'Locations', 'mab-commerce-filters' ),
			'mabcf-appearance'    => __( 'Appearance', 'mab-commerce-filters' ),
			'mabcf-performance'   => __( 'Performance', 'mab-commerce-filters' ),
			'mabcf-import-export' => __( 'Import/Export', 'mab-commerce-filters' ),
			'mabcf-settings'      => __( 'Settings', 'mab-commerce-filters' ),
			'mabcf-status'        => __( 'System Status', 'mab-commerce-filters' ),
			'mabcf-logs'          => __( 'Logs', 'mab-commerce-filters' ),
			'mabcf-license'       => __( 'License', 'mab-commerce-filters' ),
		);

		$current = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'mabcf-dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo '<nav class="mabcf-admin__tabs">';
		foreach ( $tabs as $page => $label ) {
			$active = ( $current === $page || ( 'mabcf-filter-sets' === $page && 'mabcf-filter-builder' === $current ) ) ? ' is-active' : '';
			printf(
				'<a href="%s" class="mabcf-admin__tab%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=' . $page ) ),
				esc_attr( $active ),
				esc_html( $label )
			);
		}
		echo '</nav>';
	}

	/**
	 * Prints a dismissible admin notice.
	 *
	 * @param string $message Notice text (already translated).
	 * @param string $type    success|error|warning|info.
	 */
	protected function notice( string $message, string $type = 'success' ): void {
		printf(
			'<div class="notice notice-%1$s is-dismissible mabcf-notice"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}
}
