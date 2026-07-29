<?php
/**
 * Stores an optional license key for future update-notification use.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Admin\Pages;

use MABCommerceFilters\Repositories\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The plugin does not phone home to any licensing server; this screen
 * simply stores the key locally so a future update-checker integration
 * (e.g. a self-hosted update server) has somewhere to read it from.
 */
final class LicensePage extends AbstractAdminPage {

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'mabcf-license';
	}

	/**
	 * {@inheritDoc}
	 */
	public function title(): string {
		return __( 'License', 'mab-commerce-filters' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function render_content(): void {
		$settings = new SettingsRepository();

		if ( isset( $_POST['mabcf_license_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mabcf_license_nonce'] ) ), 'mabcf_license' ) ) {
			$settings->set( 'license_key', sanitize_text_field( wp_unslash( $_POST['license_key'] ?? '' ) ) );
			$this->notice( __( 'License key saved.', 'mab-commerce-filters' ) );
		}

		echo '<div class="mabcf-panel"><form method="post">';
		wp_nonce_field( 'mabcf_license', 'mabcf_license_nonce' );

		echo '<h2>' . esc_html__( 'License', 'mab-commerce-filters' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Store your license key here. It is kept locally and is not sent anywhere by this plugin.', 'mab-commerce-filters' ) . '</p>';

		printf(
			'<p><input type="text" class="regular-text" name="license_key" value="%s" placeholder="%s"></p>',
			esc_attr( (string) $settings->get( 'license_key', '' ) ),
			esc_attr__( 'XXXX-XXXX-XXXX-XXXX', 'mab-commerce-filters' )
		);

		submit_button( __( 'Save License Key', 'mab-commerce-filters' ) );
		echo '</form></div>';
	}
}
