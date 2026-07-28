<?php
/**
 * Elementor widget: Brand Filter.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Standalone brand filter, automatically detecting whichever brand
 * taxonomy is active (see `APF_Taxonomies::get_brand_taxonomy()`).
 */
final class APF_Widget_Brand_Filter extends APF_Widget_Section_Base {

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'apf-brand-filter';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Brand Filter', 'advanced-product-filters' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon(): string {
		return 'eicon-flip-box';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function section_type(): string {
		return 'brand';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function section_config(): array {
		$config              = parent::section_config();
		$settings            = $this->get_settings_for_display();
		$config['input_type'] = sanitize_key( $settings['display_type'] ?? 'checkbox' );

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
			array( 'label' => __( 'Brand Filter', 'advanced-product-filters' ) )
		);

		if ( ! APF_Taxonomies::get_brand_taxonomy() ) {
			$this->add_control(
				'no_brand_notice',
				array(
					'type' => \Elementor\Controls_Manager::RAW_HTML,
					'raw'  => __( 'No Brand taxonomy was detected. Install a brand plugin (or add products to a "pa_brand" attribute) to use this widget.', 'advanced-product-filters' ),
					'content_classes' => 'elementor-panel-alert elementor-panel-alert-warning',
				)
			);
		}

		$this->add_control(
			'display_type',
			array(
				'label'   => __( 'Display As', 'advanced-product-filters' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'checkbox',
				'options' => array(
					'checkbox' => __( 'Checkbox List', 'advanced-product-filters' ),
					'buttons'  => __( 'Buttons', 'advanced-product-filters' ),
					'label'    => __( 'Pills', 'advanced-product-filters' ),
					'dropdown' => __( 'Dropdown', 'advanced-product-filters' ),
					'search'   => __( 'AJAX Search', 'advanced-product-filters' ),
				),
			)
		);

		$this->register_common_controls();

		$this->end_controls_section();
	}
}
