<?php
/**
 * Elementor widget: Filter Toggle Button.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Standalone "Filters" button that opens the offcanvas sidebar — useful
 * inside an Elementor-built header or toolbar where the automatic
 * mobile toggle button isn't rendered.
 */
final class APF_Widget_Toggle_Button extends \Elementor\Widget_Base {

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'apf-toggle-button';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Filter Toggle Button', 'advanced-product-filters' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon(): string {
		return 'eicon-toggle';
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
			array( 'label' => __( 'Button', 'advanced-product-filters' ) )
		);

		$this->add_control(
			'label',
			array(
				'label'   => __( 'Label', 'advanced-product-filters' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Filters', 'advanced-product-filters' ),
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
		APF_Frontend::ensure_assets();

		$filter_set      = APF_Filter_Sets::get_for_current_context();
		$applied_filters = $filter_set ? APF_Query_Builder::parse_request( $_GET, $filter_set ) : APF_Query_Builder::parse_request( array() ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		APF_Template_Loader::render(
			'toggle-button',
			array( 'active_count' => APF_Frontend::count_active_filters( $applied_filters ) )
		);
	}
}
