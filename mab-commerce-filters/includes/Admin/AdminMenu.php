<?php
/**
 * Registers the plugin's top-level admin menu and submenu pages.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Admin;

use MABCommerceFilters\Admin\Pages\AppearancePage;
use MABCommerceFilters\Admin\Pages\DashboardPage;
use MABCommerceFilters\Admin\Pages\FilterBuilderPage;
use MABCommerceFilters\Admin\Pages\FilterSetsPage;
use MABCommerceFilters\Admin\Pages\ImportExportPage;
use MABCommerceFilters\Admin\Pages\LicensePage;
use MABCommerceFilters\Admin\Pages\LocationsPage;
use MABCommerceFilters\Admin\Pages\LogsPage;
use MABCommerceFilters\Admin\Pages\PerformancePage;
use MABCommerceFilters\Admin\Pages\SettingsPage;
use MABCommerceFilters\Admin\Pages\SystemStatusPage;
use MABCommerceFilters\Helpers\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires every admin page class into the WordPress admin menu and loads
 * admin-only assets on plugin screens.
 */
final class AdminMenu {

	/**
	 * Registers WordPress hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Registers the top-level menu and every submenu page.
	 */
	public function add_menus(): void {
		$capability = Helper::CAPABILITY;

		add_menu_page(
			__( 'MAB Commerce Filters', 'mab-commerce-filters' ),
			__( 'Commerce Filters', 'mab-commerce-filters' ),
			$capability,
			'mabcf-dashboard',
			array( new DashboardPage(), 'render' ),
			'dashicons-filter',
			56
		);

		$pages = array(
			'mabcf-dashboard'     => new DashboardPage(),
			'mabcf-filter-sets'   => new FilterSetsPage(),
			'mabcf-filter-builder' => new FilterBuilderPage(),
			'mabcf-locations'     => new LocationsPage(),
			'mabcf-appearance'    => new AppearancePage(),
			'mabcf-performance'   => new PerformancePage(),
			'mabcf-import-export' => new ImportExportPage(),
			'mabcf-settings'      => new SettingsPage(),
			'mabcf-status'        => new SystemStatusPage(),
			'mabcf-logs'          => new LogsPage(),
			'mabcf-license'       => new LicensePage(),
		);

		foreach ( $pages as $slug => $page ) {
			$hidden = 'mabcf-filter-builder' === $slug; // Reached only via "Add/Edit" links.

			add_submenu_page(
				$hidden ? null : 'mabcf-dashboard',
				$page->title(),
				$page->title(),
				$capability,
				$slug,
				array( $page, 'render' )
			);
		}

		remove_submenu_page( 'mabcf-dashboard', 'mabcf-dashboard' );
	}

	/**
	 * Enqueues admin CSS/JS only on this plugin's own screens.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( ! isset( $_GET['page'] ) || ! str_starts_with( (string) wp_unslash( $_GET['page'] ), 'mabcf-' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		wp_enqueue_style( 'mabcf-admin', Helper::asset_url( 'css/admin.css' ), array(), MABCF_VERSION );
		wp_enqueue_script( 'mabcf-admin', Helper::asset_url( 'js/admin.js' ), array(), MABCF_VERSION, true );
		wp_enqueue_media();

		wp_localize_script(
			'mabcf-admin',
			'mabcfAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'mabcf_admin' ),
				'i18n'    => array(
					'confirmDelete' => __( 'Are you sure you want to delete this? This cannot be undone.', 'mab-commerce-filters' ),
					'saved'         => __( 'Saved.', 'mab-commerce-filters' ),
					'error'         => __( 'Something went wrong. Please try again.', 'mab-commerce-filters' ),
				),
			)
		);
	}
}
