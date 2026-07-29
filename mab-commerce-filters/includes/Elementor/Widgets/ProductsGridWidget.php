<?php
/**
 * Elementor widget: filtered WooCommerce Products Grid.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Elementor\Widgets;

use Elementor\Controls_Manager;
use MABCommerceFilters\Frontend\ProductGridRenderer;
use MABCommerceFilters\Repositories\FilterRepository;
use MABCommerceFilters\Services\FilterQueryService;
use MABCommerceFilters\Services\LocationResolver;
use MABCommerceFilters\Services\UrlSyncService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the product grid driven by whichever filter widgets are on the
 * page (matched to the same filter set), wrapped for AJAX replacement.
 */
final class ProductsGridWidget extends AbstractMabcfWidget {

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'mabcf-products-grid';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Products Grid (Filtered)', 'mab-commerce-filters' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon(): string {
		return 'eicon-products';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'section_content',
			array( 'label' => __( 'Products Grid', 'mab-commerce-filters' ) )
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

		$this->add_control(
			'columns',
			array(
				'label'   => __( 'Columns', 'mab-commerce-filters' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 4,
				'min'     => 1,
				'max'     => 6,
			)
		);

		$this->add_control(
			'show_sorting',
			array(
				'label'        => __( 'Show Sorting Bar', 'mab-commerce-filters' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'infinite_scroll',
			array(
				'label'   => __( 'Infinite Scroll', 'mab-commerce-filters' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => '',
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
				echo '<p>' . esc_html__( 'No active filter set found.', 'mab-commerce-filters' ) . '</p>';
			}
			return;
		}

		$resolver  = new LocationResolver();
		$context   = $resolver->current_context();
		$base_args = $resolver->base_args_for_context( $context );

		$filters   = ( new FilterRepository() )->get_for_set( (int) $set['id'] );
		$selection = ( new UrlSyncService() )->from_request( $filters, wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$query_svc = new FilterQueryService();
		$args      = $query_svc->build_query_args( $filters, $selection, $base_args );
		$query     = $query_svc->run( $args );

		printf( '<div class="mabcf-products-target" data-mabcf-ajax-target="1" data-filter-set-id="%d">', (int) $set['id'] );

		echo ( new ProductGridRenderer() )->render(
			$query,
			array(
				'columns'         => (int) ( $settings['columns'] ?? 4 ),
				'show_sorting'    => 'yes' === ( $settings['show_sorting'] ?? 'yes' ),
				'show_pagination' => 'yes' !== ( $settings['infinite_scroll'] ?? '' ),
				'infinite_scroll' => 'yes' === ( $settings['infinite_scroll'] ?? '' ),
			)
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		echo '</div>';
	}
}
