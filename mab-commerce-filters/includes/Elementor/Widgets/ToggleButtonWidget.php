<?php
/**
 * Elementor widget: mobile filter Toggle Button (opens the offcanvas).
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Elementor\Widgets;

use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Toggles the `.mabcf--offcanvas` drawer open/closed on small screens;
 * behaviour lives in assets/js/frontend.js.
 */
final class ToggleButtonWidget extends AbstractMabcfWidget {

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'mabcf-toggle-button';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Filter Toggle Button', 'mab-commerce-filters' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon(): string {
		return 'eicon-menu-bar';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'section_content',
			array( 'label' => __( 'Toggle Button', 'mab-commerce-filters' ) )
		);

		$this->add_control(
			'label',
			array(
				'label'   => __( 'Label', 'mab-commerce-filters' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'Filters', 'mab-commerce-filters' ),
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
			'<button type="button" class="mabcf-button mabcf-button--primary mabcf-offcanvas-toggle" aria-expanded="false"><span class="mabcf-offcanvas-toggle__icon" aria-hidden="true"></span>%s</button>',
			esc_html( $settings['label'] ?? __( 'Filters', 'mab-commerce-filters' ) )
		);
	}
}
