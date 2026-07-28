<?php
/**
 * Elementor widget: Price Slider.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Standalone dual-handle price range slider.
 */
final class APF_Widget_Price_Slider extends APF_Widget_Section_Base {

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'apf-price-slider';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Price Slider', 'advanced-product-filters' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon(): string {
		return 'eicon-price-list';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function section_type(): string {
		return 'price';
	}

	/**
	 * Registers the widget's controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'apf_section_content',
			array( 'label' => __( 'Price Slider', 'advanced-product-filters' ) )
		);

		$this->add_control(
			'label',
			array(
				'label'   => __( 'Section Label', 'advanced-product-filters' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => $this->get_title(),
			)
		);

		$this->end_controls_section();
	}
}
