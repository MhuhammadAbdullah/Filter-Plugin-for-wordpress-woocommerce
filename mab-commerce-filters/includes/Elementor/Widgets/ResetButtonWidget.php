<?php
/**
 * Elementor widget: Reset Filters button.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Elementor\Widgets;

use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A standalone reset button that clears the nearest `.mabcf-form` on the
 * page and re-runs the AJAX query, driven entirely by assets/js/frontend.js
 * delegated click handling (no inline script needed).
 */
final class ResetButtonWidget extends AbstractMabcfWidget {

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'mabcf-reset-button';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Reset Filters Button', 'mab-commerce-filters' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon(): string {
		return 'eicon-restart';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'section_content',
			array( 'label' => __( 'Reset Button', 'mab-commerce-filters' ) )
		);

		$this->add_control(
			'label',
			array(
				'label'   => __( 'Label', 'mab-commerce-filters' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'Reset Filters', 'mab-commerce-filters' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * {@inheritDoc}
	 */
	protected function render(): void {
		$this->ensure_assets();

		$settings = $this->get_settings_for_display();

		printf(
			'<button type="button" class="mabcf-button mabcf-button--ghost mabcf-reset mabcf-reset--standalone">%s</button>',
			esc_html( $settings['label'] ?? __( 'Reset Filters', 'mab-commerce-filters' ) )
		);
	}
}
