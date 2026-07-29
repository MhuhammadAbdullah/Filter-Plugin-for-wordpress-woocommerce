<?php
/**
 * Global appearance settings: colors, radius, sticky sidebar, breakpoints.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Admin\Pages;

use MABCommerceFilters\Repositories\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controls the CSS custom properties injected into the front end (colors,
 * radius, breakpoints) plus layout toggles like sticky sidebar and dark
 * mode.
 */
final class AppearancePage extends AbstractAdminPage {

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'mabcf-appearance';
	}

	/**
	 * {@inheritDoc}
	 */
	public function title(): string {
		return __( 'Appearance', 'mab-commerce-filters' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function render_content(): void {
		$settings = new SettingsRepository();

		if ( isset( $_POST['mabcf_appearance_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mabcf_appearance_nonce'] ) ), 'mabcf_appearance' ) ) {
			$settings->set_many(
				array(
					'primary_color'      => sanitize_hex_color( wp_unslash( $_POST['primary_color'] ?? '' ) ) ?: '#111111',
					'accent_color'       => sanitize_hex_color( wp_unslash( $_POST['accent_color'] ?? '' ) ) ?: '#111111',
					'border_radius'      => absint( $_POST['border_radius'] ?? 8 ),
					'mobile_breakpoint'  => absint( $_POST['mobile_breakpoint'] ?? 1024 ),
					'sticky_sidebar'     => isset( $_POST['sticky_sidebar'] ) ? '1' : '0',
					'show_product_count' => isset( $_POST['show_product_count'] ) ? '1' : '0',
					'dark_mode'          => isset( $_POST['dark_mode'] ) ? '1' : '0',
				)
			);
			$this->notice( __( 'Appearance settings saved.', 'mab-commerce-filters' ) );
		}

		echo '<div class="mabcf-panel"><form method="post">';
		wp_nonce_field( 'mabcf_appearance', 'mabcf_appearance_nonce' );

		echo '<h2>' . esc_html__( 'Appearance', 'mab-commerce-filters' ) . '</h2>';

		echo '<table class="form-table"><tbody>';
		$this->color_row( 'primary_color', __( 'Primary Color', 'mab-commerce-filters' ), (string) $settings->get( 'primary_color', '#111111' ) );
		$this->color_row( 'accent_color', __( 'Accent Color', 'mab-commerce-filters' ), (string) $settings->get( 'accent_color', '#111111' ) );
		$this->number_row( 'border_radius', __( 'Border Radius (px)', 'mab-commerce-filters' ), (int) $settings->get( 'border_radius', 8 ) );
		$this->number_row( 'mobile_breakpoint', __( 'Mobile Breakpoint (px)', 'mab-commerce-filters' ), (int) $settings->get( 'mobile_breakpoint', 1024 ) );
		$this->checkbox_row( 'sticky_sidebar', __( 'Sticky Sidebar', 'mab-commerce-filters' ), '1' === (string) $settings->get( 'sticky_sidebar', '1' ) );
		$this->checkbox_row( 'show_product_count', __( 'Show Product Counts', 'mab-commerce-filters' ), '1' === (string) $settings->get( 'show_product_count', '1' ) );
		$this->checkbox_row( 'dark_mode', __( 'Enable Dark Mode Support', 'mab-commerce-filters' ), '1' === (string) $settings->get( 'dark_mode', '0' ) );
		echo '</tbody></table>';

		submit_button( __( 'Save Appearance', 'mab-commerce-filters' ) );
		echo '</form></div>';
	}

	/**
	 * Renders a color-picker settings row.
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @param string $value Current hex value.
	 */
	private function color_row( string $name, string $label, string $value ): void {
		printf(
			'<tr><th><label for="%1$s">%2$s</label></th><td><input type="text" class="mabcf-color-field" name="%1$s" id="%1$s" value="%3$s"></td></tr>',
			esc_attr( $name ),
			esc_html( $label ),
			esc_attr( $value )
		);
	}

	/**
	 * Renders a number settings row.
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @param int    $value Current value.
	 */
	private function number_row( string $name, string $label, int $value ): void {
		printf(
			'<tr><th><label for="%1$s">%2$s</label></th><td><input type="number" name="%1$s" id="%1$s" value="%3$d"></td></tr>',
			esc_attr( $name ),
			esc_html( $label ),
			$value
		);
	}

	/**
	 * Renders a checkbox settings row.
	 *
	 * @param string $name    Field name.
	 * @param string $label   Field label.
	 * @param bool   $checked Whether checked.
	 */
	private function checkbox_row( string $name, string $label, bool $checked ): void {
		printf(
			'<tr><th>%2$s</th><td><label><input type="checkbox" name="%1$s" %3$s> %2$s</label></td></tr>',
			esc_attr( $name ),
			esc_html( $label ),
			checked( $checked, true, false )
		);
	}
}
