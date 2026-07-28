<?php
/**
 * Shared base for the single-purpose section widgets.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Active Filters, Price Slider, Color Swatches, Category Filter, Tag
 * Filter and Brand Filter are all "render one section standalone"
 * widgets; this base class holds what they have in common so each
 * subclass only declares its own section type and extra controls.
 */
abstract class APF_Widget_Section_Base extends \Elementor\Widget_Base {

	/**
	 * {@inheritDoc}
	 */
	public function get_categories(): array {
		return array( 'advanced-product-filters' );
	}

	/**
	 * The underlying section type key from `APF_Section_Types`.
	 *
	 * @return string
	 */
	abstract protected function section_type(): string;

	/**
	 * Builds the section configuration array passed to
	 * `APF_Renderer::render_standalone_section()`. Subclasses override
	 * this to inject their taxonomy / input type / label controls.
	 *
	 * @return array<string, mixed>
	 */
	protected function section_config(): array {
		$settings = $this->get_settings_for_display();

		return array(
			'type'       => $this->section_type(),
			'label'      => $settings['label'] ?? '',
			'show_count' => ! empty( $settings['show_count'] ),
			'collapsed'  => false,
		);
	}

	/**
	 * Registers the "Label" and "Show Count" controls shared by every
	 * section widget. Subclasses call this from their own
	 * `register_controls()` alongside their own fields.
	 *
	 * @return void
	 */
	protected function register_common_controls(): void {
		$this->add_control(
			'label',
			array(
				'label'   => __( 'Section Label', 'advanced-product-filters' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => $this->get_title(),
			)
		);

		$this->add_control(
			'show_count',
			array(
				'label'        => __( 'Show Product Count', 'advanced-product-filters' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);
	}

	/**
	 * Renders the widget by delegating to `APF_Renderer::render_standalone_section()`.
	 *
	 * @return void
	 */
	protected function render(): void {
		APF_Frontend::ensure_assets();

		echo APF_Renderer::render_standalone_section( $this->section_config() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
