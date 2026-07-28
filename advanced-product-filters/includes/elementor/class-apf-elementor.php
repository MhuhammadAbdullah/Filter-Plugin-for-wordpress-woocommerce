<?php
/**
 * Elementor integration — widget category + registration.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the "Advanced Product Filters" Elementor widget category and
 * every widget the plugin ships, targeting both the legacy
 * `elementor/widgets/widgets_registered` hook and the modern
 * `elementor/widgets/register` hook so the plugin works across the range
 * of Elementor versions still in active use.
 */
final class APF_Elementor {

	/**
	 * Shared query builder instance (unused directly here, but kept so
	 * widgets constructed with dependencies can be extended consistently).
	 *
	 * @var APF_Query_Builder
	 */
	private APF_Query_Builder $query_builder;

	/**
	 * Guards against registering every widget twice when both the modern
	 * and legacy Elementor hooks fire on the same request.
	 *
	 * @var bool
	 */
	private bool $registered = false;

	/**
	 * Wires the Elementor hooks.
	 *
	 * @param APF_Query_Builder $query_builder Shared query builder.
	 */
	public function __construct( APF_Query_Builder $query_builder ) {
		$this->query_builder = $query_builder;

		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
		add_action( 'elementor/widgets/widgets_registered', array( $this, 'register_widgets_legacy' ) );
		add_action( 'elementor/frontend/after_enqueue_styles', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Registers the "Advanced Product Filters" widget category.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elementor elements manager.
	 * @return void
	 */
	public function register_category( $elements_manager ): void {
		$elements_manager->add_category(
			'advanced-product-filters',
			array(
				'title' => __( 'Advanced Product Filters', 'advanced-product-filters' ),
				'icon'  => 'eicon-filter',
			)
		);
	}

	/**
	 * Registers every widget (Elementor 3.5+ API).
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 * @return void
	 */
	public function register_widgets( $widgets_manager ): void {
		if ( $this->registered ) {
			return;
		}

		$this->registered = true;

		foreach ( $this->widget_classes() as $class ) {
			$widgets_manager->register( new $class() );
		}
	}

	/**
	 * Registers every widget on Elementor versions older than 3.5, which
	 * never fire `elementor/widgets/register`.
	 *
	 * @return void
	 */
	public function register_widgets_legacy(): void {
		if ( $this->registered || ! class_exists( '\Elementor\Plugin' ) ) {
			return;
		}

		$this->registered = true;

		$manager = \Elementor\Plugin::instance()->widgets_manager;

		foreach ( $this->widget_classes() as $class ) {
			$manager->register( new $class() );
		}
	}

	/**
	 * Ensures the storefront stylesheet loads inside the Elementor editor
	 * preview iframe so widgets look correct while being built.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		APF_Frontend::ensure_assets();
	}

	/**
	 * Every widget class the plugin registers.
	 *
	 * @return string[]
	 */
	private function widget_classes(): array {
		return array(
			'APF_Widget_Sidebar',
			'APF_Widget_Product_Grid',
			'APF_Widget_Toggle_Button',
			'APF_Widget_Active_Filters',
			'APF_Widget_Price_Slider',
			'APF_Widget_Color_Swatches',
			'APF_Widget_Category_Filter',
			'APF_Widget_Tag_Filter',
			'APF_Widget_Brand_Filter',
		);
	}
}
