<?php
/**
 * Elementor widget: Tag Filter.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Standalone product tag filter, rendered as pills by default.
 */
final class APF_Widget_Tag_Filter extends APF_Widget_Section_Base {

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'apf-tag-filter';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Tag Filter', 'advanced-product-filters' );
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
		return 'tag';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function section_config(): array {
		$config              = parent::section_config();
		$settings            = $this->get_settings_for_display();
		$config['input_type'] = sanitize_key( $settings['display_type'] ?? 'label' );

		return $config;
	}

	/**
	 * Registers the widget's controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'apf_section_content',
			array( 'label' => __( 'Tag Filter', 'advanced-product-filters' ) )
		);

		$this->add_control(
			'display_type',
			array(
				'label'   => __( 'Display As', 'advanced-product-filters' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'label',
				'options' => array(
					'label'    => __( 'Pills', 'advanced-product-filters' ),
					'checkbox' => __( 'Checkbox List', 'advanced-product-filters' ),
				),
			)
		);

		$this->register_common_controls();

		$this->end_controls_section();
	}
}
