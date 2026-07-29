<?php
/**
 * Registers the plugin's native Elementor widgets and widget category.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Elementor;

use MABCommerceFilters\Elementor\Widgets\ActiveFiltersWidget;
use MABCommerceFilters\Elementor\Widgets\FilterSidebarWidget;
use MABCommerceFilters\Elementor\Widgets\ProductsGridWidget;
use MABCommerceFilters\Elementor\Widgets\ResetButtonWidget;
use MABCommerceFilters\Elementor\Widgets\SingleFilterWidget;
use MABCommerceFilters\Elementor\Widgets\ToggleButtonWidget;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hooks into Elementor's widget registration and category systems. Only
 * loaded when Elementor is active (checked by Plugin::boot()).
 */
final class ElementorIntegration {

	/**
	 * Registers WordPress/Elementor hooks.
	 */
	public function register(): void {
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
	}

	/**
	 * Registers the "MAB Commerce Filters" widget category.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elementor elements manager.
	 */
	public function register_category( $elements_manager ): void {
		$elements_manager->add_category(
			'mab-commerce-filters',
			array(
				'title' => __( 'MAB Commerce Filters', 'mab-commerce-filters' ),
				'icon'  => 'eicon-filter',
			)
		);
	}

	/**
	 * Registers every widget.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 */
	public function register_widgets( $widgets_manager ): void {
		$widgets_manager->register( new FilterSidebarWidget() );
		$widgets_manager->register( new ProductsGridWidget() );
		$widgets_manager->register( new ActiveFiltersWidget() );
		$widgets_manager->register( new ResetButtonWidget() );
		$widgets_manager->register( new ToggleButtonWidget() );

		$single_filter_widgets = array(
			'category'  => array( 'mabcf-category-filter', __( 'Category Filter', 'mab-commerce-filters' ), 'eicon-product-categories' ),
			'price'     => array( 'mabcf-price-filter', __( 'Price Filter', 'mab-commerce-filters' ), 'eicon-price-list' ),
			'color'     => array( 'mabcf-color-filter', __( 'Color Filter', 'mab-commerce-filters' ), 'eicon-color-filter' ),
			'size'      => array( 'mabcf-size-filter', __( 'Size Filter', 'mab-commerce-filters' ), 'eicon-t-letter' ),
			'brand'     => array( 'mabcf-brand-filter', __( 'Brand Filter', 'mab-commerce-filters' ), 'eicon-tags' ),
			'tag'       => array( 'mabcf-tag-filter', __( 'Tag Filter', 'mab-commerce-filters' ), 'eicon-tags' ),
			'rating'    => array( 'mabcf-rating-filter', __( 'Rating Filter', 'mab-commerce-filters' ), 'eicon-star' ),
			'stock'     => array( 'mabcf-stock-filter', __( 'Stock Filter', 'mab-commerce-filters' ), 'eicon-cart' ),
			'search'    => array( 'mabcf-search-filter', __( 'Search Filter', 'mab-commerce-filters' ), 'eicon-search' ),
		);

		foreach ( $single_filter_widgets as $mode => [ $name, $title, $icon ] ) {
			$widgets_manager->register( new SingleFilterWidget( $mode, $name, $title, $icon ) );
		}
	}
}
