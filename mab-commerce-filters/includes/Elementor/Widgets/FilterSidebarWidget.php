<?php
/**
 * Elementor widget: Advanced Filter Sidebar (renders a whole filter set).
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Elementor\Widgets;

use Elementor\Controls_Manager;
use MABCommerceFilters\Frontend\Renderer;
use MABCommerceFilters\Repositories\FilterRepository;
use MABCommerceFilters\Services\LocationResolver;
use MABCommerceFilters\Services\UrlSyncService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The primary widget: drop a complete filter set (accordion sections,
 * active filters bar, apply/clear buttons) anywhere on an Elementor page.
 */
final class FilterSidebarWidget extends AbstractMabcfWidget {

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'mabcf-filter-sidebar';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Advanced Filter Sidebar', 'mab-commerce-filters' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon(): string {
		return 'eicon-filter';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'section_content',
			array( 'label' => __( 'Filter Sidebar', 'mab-commerce-filters' ) )
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
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<p>' . esc_html__( 'No active filter set found. Create one under MAB Commerce Filters → Filter Sets.', 'mab-commerce-filters' ) . '</p>';
			}
			return;
		}

		$resolver  = new LocationResolver();
		$context   = $resolver->current_context();
		$base_args = $resolver->base_args_for_context( $context );

		$filters   = ( new FilterRepository() )->get_for_set( (int) $set['id'] );
		$selection = ( new UrlSyncService() )->from_request( $filters, wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo ( new Renderer() )->render( $set, $base_args, $selection, $context ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
