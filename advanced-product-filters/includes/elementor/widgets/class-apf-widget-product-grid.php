<?php
/**
 * Elementor widget: Product Grid.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * A standalone, AJAX-filterable product grid. Shares its "scope" with
 * any Filter Sidebar / single-purpose filter widgets placed on the same
 * page so the storefront JS keeps them in sync without any extra setup.
 */
final class APF_Widget_Product_Grid extends \Elementor\Widget_Base {

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'apf-product-grid';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Product Grid (Filterable)', 'advanced-product-filters' );
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
	public function get_categories(): array {
		return array( 'advanced-product-filters' );
	}

	/**
	 * Registers the widget's controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'apf_section_content',
			array( 'label' => __( 'Product Grid', 'advanced-product-filters' ) )
		);

		$this->add_control(
			'columns',
			array(
				'label'   => __( 'Columns', 'advanced-product-filters' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '4',
				'options' => array(
					'2' => '2',
					'3' => '3',
					'4' => '4',
					'5' => '5',
				),
			)
		);

		$this->add_control(
			'products_per_page',
			array(
				'label'   => __( 'Products Per Page', 'advanced-product-filters' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'default' => 12,
				'min'     => 1,
				'max'     => 100,
			)
		);

		$this->add_control(
			'orderby',
			array(
				'label'   => __( 'Order By', 'advanced-product-filters' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'date',
				'options' => array(
					'date'       => __( 'Newest', 'advanced-product-filters' ),
					'price'      => __( 'Price: Low to High', 'advanced-product-filters' ),
					'price-desc' => __( 'Price: High to Low', 'advanced-product-filters' ),
					'popularity' => __( 'Popularity', 'advanced-product-filters' ),
					'rating'     => __( 'Rating', 'advanced-product-filters' ),
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Renders the widget.
	 *
	 * @return void
	 */
	protected function render(): void {
		$settings = $this->get_settings_for_display();

		APF_Frontend::ensure_assets();

		$filter_set       = APF_Filter_Sets::get_for_current_context();
		$applied_filters  = APF_Query_Builder::parse_request( $_GET, $filter_set ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( empty( $applied_filters['orderby'] ) ) {
			$applied_filters['orderby'] = sanitize_key( $settings['orderby'] ?? 'date' );
		}

		$applied_filters['products_per_page'] = absint( $settings['products_per_page'] ?? 12 );

		$query_args = APF_Query_Builder::build_query_args( $applied_filters, APF_Context::get_archive_restriction() );
		$query_args['posts_per_page'] = $applied_filters['products_per_page'];

		$grid = APF_Product_Grid::render( $query_args );

		printf(
			'<div class="apf-standalone-grid apf-columns-%1$d" data-apf-grid="1" data-apf-scope="context" data-filter-set-id="%2$d" data-pagination-mode="%3$s">%4$s</div>',
			absint( $settings['columns'] ?? 4 ),
			$filter_set ? $filter_set->get_id() : 0,
			esc_attr( (string) APF_Settings::get( 'pagination_mode', 'pages' ) ),
			$grid['html'] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce loop markup, already escaped by its own templates.
		);
	}
}
