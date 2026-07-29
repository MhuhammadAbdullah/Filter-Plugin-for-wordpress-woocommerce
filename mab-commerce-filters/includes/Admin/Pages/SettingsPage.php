<?php
/**
 * General plugin settings: AJAX mode, debug mode, uninstall behaviour.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Admin\Pages;

use MABCommerceFilters\Repositories\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Store-wide behaviour switches that aren't specific to one filter set.
 */
final class SettingsPage extends AbstractAdminPage {

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'mabcf-settings';
	}

	/**
	 * {@inheritDoc}
	 */
	public function title(): string {
		return __( 'Settings', 'mab-commerce-filters' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function render_content(): void {
		$settings = new SettingsRepository();

		if ( isset( $_POST['mabcf_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mabcf_settings_nonce'] ) ), 'mabcf_settings' ) ) {
			$settings->set_many(
				array(
					'ajax_mode'                   => in_array( $_POST['ajax_mode'] ?? '', array( 'instant', 'apply' ), true ) ? $_POST['ajax_mode'] : 'instant',
					'update_url'                  => isset( $_POST['update_url'] ) ? '1' : '0',
					'debug_mode'                  => isset( $_POST['debug_mode'] ) ? '1' : '0',
					'remove_data_on_uninstall'    => isset( $_POST['remove_data_on_uninstall'] ) ? '1' : '0',
				)
			);
			$this->notice( __( 'Settings saved.', 'mab-commerce-filters' ) );
		}

		echo '<div class="mabcf-panel"><form method="post">';
		wp_nonce_field( 'mabcf_settings', 'mabcf_settings_nonce' );

		echo '<h2>' . esc_html__( 'General Settings', 'mab-commerce-filters' ) . '</h2>';

		echo '<table class="form-table"><tbody>';

		echo '<tr><th>' . esc_html__( 'Default AJAX Mode', 'mab-commerce-filters' ) . '</th><td>';
		echo '<select name="ajax_mode">';
		printf( '<option value="instant"%s>%s</option>', selected( 'instant', (string) $settings->get( 'ajax_mode', 'instant' ), false ), esc_html__( 'Instant (filter as you click)', 'mab-commerce-filters' ) );
		printf( '<option value="apply"%s>%s</option>', selected( 'apply', (string) $settings->get( 'ajax_mode', 'instant' ), false ), esc_html__( 'Apply button required', 'mab-commerce-filters' ) );
		echo '</select></td></tr>';

		printf(
			'<tr><th>%1$s</th><td><label><input type="checkbox" name="update_url" %2$s> %1$s</label></td></tr>',
			esc_html__( 'Update browser URL when filtering', 'mab-commerce-filters' ),
			checked( '1' === (string) $settings->get( 'update_url', '1' ), true, false )
		);

		printf(
			'<tr><th>%1$s</th><td><label><input type="checkbox" name="debug_mode" %2$s> %1$s</label><p class="description">%3$s</p></td></tr>',
			esc_html__( 'Debug Mode', 'mab-commerce-filters' ),
			checked( '1' === (string) $settings->get( 'debug_mode', '0' ), true, false ),
			esc_html__( 'Logs every AJAX filter request to the Logs screen.', 'mab-commerce-filters' )
		);

		printf(
			'<tr><th>%1$s</th><td><label><input type="checkbox" name="remove_data_on_uninstall" %2$s> %1$s</label><p class="description">%3$s</p></td></tr>',
			esc_html__( 'Remove all data on uninstall', 'mab-commerce-filters' ),
			checked( '1' === (string) $settings->get( 'remove_data_on_uninstall', '0' ), true, false ),
			esc_html__( 'When enabled, deleting the plugin also drops its database tables and settings. Leave off to keep your filter sets if you reinstall later.', 'mab-commerce-filters' )
		);

		echo '</tbody></table>';

		submit_button( __( 'Save Settings', 'mab-commerce-filters' ) );
		echo '</form></div>';
	}
}
