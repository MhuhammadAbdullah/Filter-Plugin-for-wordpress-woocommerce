<?php
/**
 * Elementor widget: Color Swatches.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Standalone colour swatch filter for any WooCommerce product attribute
 * (defaults to `pa_color` when that attribute exists).
 */
final class APF_Widget_Color_Swatches extends APF_Widget_Section_Base {

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'apf-color-swatches';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Color Swatches', 'advanced-product-filters' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon(): string {
		return 'eicon-color-filter';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function section_type(): string {
		return 'attribute';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function section_config(): array {
		$config              = parent::section_config();
		$settings            = $this->get_settings_for_display();
		$config['taxonomy']  = sanitize_key( $settings['attribute'] ?? $this->default_attribute() );
		$config['input_type'] = 'color';

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
			array( 'label' => __( 'Color Swatches', 'advanced-product-filters' ) )
		);

		$this->add_control(
			'attribute',
			array(
				'label'   => __( 'Attribute', 'advanced-product-filters' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => $this->default_attribute(),
				'options' => APF_Taxonomies::get_attribute_taxonomies(),
			)
		);

		$this->register_common_controls();

		$this->end_controls_section();
	}

	/**
	 * The attribute taxonomy to use when none is explicitly chosen.
	 *
	 * @return string
	 */
	private function default_attribute(): string {
		$attributes = APF_Taxonomies::get_attribute_taxonomies();

		if ( isset( $attributes['pa_color'] ) ) {
			return 'pa_color';
		}

		$keys = array_keys( $attributes );

		return $keys[0] ?? '';
	}
}
