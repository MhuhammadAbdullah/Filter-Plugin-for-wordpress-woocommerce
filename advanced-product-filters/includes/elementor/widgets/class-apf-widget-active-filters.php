<?php
/**
 * Elementor widget: Active Filters.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Standalone Active Filters chip list, usable independently of the full
 * sidebar (e.g. above the product grid instead of inside it).
 */
final class APF_Widget_Active_Filters extends APF_Widget_Section_Base {

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'apf-active-filters';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Active Filters', 'advanced-product-filters' );
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
	protected function section_type(): string {
		return 'active_filters';
	}

	/**
	 * Registers the widget's controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'apf_section_content',
			array( 'label' => __( 'Active Filters', 'advanced-product-filters' ) )
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
