<?php
/**
 * Elementor widget: Advanced Filter Sidebar.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders a complete Filter Set sidebar (every enabled section, active
 * filters, offcanvas shell) anywhere Elementor can place a widget,
 * including Theme Builder archive/single templates.
 */
final class APF_Widget_Sidebar extends \Elementor\Widget_Base {

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'apf-filter-sidebar';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Advanced Filter Sidebar', 'advanced-product-filters' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon(): string {
		return 'eicon-filter';
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
			array( 'label' => __( 'Filter Set', 'advanced-product-filters' ) )
		);

		$this->add_control(
			'filter_set_id',
			array(
				'label'   => __( 'Filter Set', 'advanced-product-filters' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '0',
				'options' => $this->filter_set_options(),
				'description' => __( 'Leave on "Auto-detect" to use the Filter Set assigned to the current page.', 'advanced-product-filters' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Builds the Filter Set dropdown options, "Auto-detect" first.
	 *
	 * @return array<string, string>
	 */
	private function filter_set_options(): array {
		$options = array( '0' => __( 'Auto-detect (recommended)', 'advanced-product-filters' ) );

		foreach ( APF_Filter_Sets::get_all() as $filter_set ) {
			$options[ (string) $filter_set->get_id() ] = $filter_set->get_name();
		}

		return $options;
	}

	/**
	 * Renders the widget on the front end (and inside the Elementor editor preview).
	 *
	 * @return void
	 */
	protected function render(): void {
		$settings      = $this->get_settings_for_display();
		$filter_set_id = absint( $settings['filter_set_id'] ?? 0 );

		$filter_set = $filter_set_id ? APF_Filter_Sets::get( $filter_set_id ) : APF_Filter_Sets::get_for_current_context();

		if ( ! $filter_set ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<p>' . esc_html__( 'No matching Filter Set found for this page yet. Create one under Advanced Product Filters → Filter Sets.', 'advanced-product-filters' ) . '</p>';
			}
			return;
		}

		APF_Frontend::ensure_assets( $filter_set->get_id() );

		$applied_filters = APF_Query_Builder::parse_request( $_GET, $filter_set ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo APF_Renderer::render_sidebar( $filter_set, $applied_filters, APF_Context::get_archive_restriction() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
