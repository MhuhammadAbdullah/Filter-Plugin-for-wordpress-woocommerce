<?php
/**
 * Elementor widget: standalone Active Filters bar.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Elementor\Widgets;

use Elementor\Controls_Manager;
use MABCommerceFilters\Frontend\Renderer;
use MABCommerceFilters\Repositories\FilterRepository;
use MABCommerceFilters\Services\UrlSyncService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders just the removable "Active filters" pill bar for the resolved
 * filter set, useful when the filter sidebar itself lives elsewhere on
 * the page (e.g. an offcanvas drawer) but you want the active pills in
 * the main content column.
 */
final class ActiveFiltersWidget extends AbstractMabcfWidget {

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'mabcf-active-filters';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Active Filters', 'mab-commerce-filters' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon(): string {
		return 'eicon-tags';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'section_content',
			array( 'label' => __( 'Active Filters', 'mab-commerce-filters' ) )
		);

		$this->add_control(
			'filter_set_id',
			array(
				'label'   => __( 'Filter Set', 'mab-commerce-filters' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '0',
				'options' => $this->filter_set_options(),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * {@inheritDoc}
	 */
	protected function render(): void {
		$this->ensure_assets();

		$settings = $this->get_settings_for_display();
		$set      = $this->resolve_set( (int) ( $settings['filter_set_id'] ?? 0 ) );

		if ( ! $set ) {
			return;
		}

		$filters   = ( new FilterRepository() )->get_for_set( (int) $set['id'] );
		$selection = ( new UrlSyncService() )->from_request( $filters, wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		printf( '<div class="mabcf mabcf--standalone-active" data-filter-set-id="%d">', (int) $set['id'] );
		echo ( new Renderer() )->render_active_bar( $filters, $selection ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
	}
}
