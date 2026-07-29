<?php
/**
 * Read-only diagnostics: PHP/WP/WC/Elementor versions, DB table health.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Admin\Pages;

use MABCommerceFilters\Database\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Support-ticket-friendly environment report.
 */
final class SystemStatusPage extends AbstractAdminPage {

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'mabcf-status';
	}

	/**
	 * {@inheritDoc}
	 */
	public function title(): string {
		return __( 'System Status', 'mab-commerce-filters' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function render_content(): void {
		global $wpdb;

		echo '<div class="mabcf-panel">';
		echo '<h2>' . esc_html__( 'Environment', 'mab-commerce-filters' ) . '</h2>';
		echo '<table class="widefat striped"><tbody>';

		$this->row( __( 'Plugin Version', 'mab-commerce-filters' ), MABCF_VERSION );
		$this->row( __( 'PHP Version', 'mab-commerce-filters' ), PHP_VERSION, version_compare( PHP_VERSION, MABCF_MIN_PHP, '>=' ) );
		$this->row( __( 'WordPress Version', 'mab-commerce-filters' ), get_bloginfo( 'version' ), version_compare( get_bloginfo( 'version' ), MABCF_MIN_WP, '>=' ) );
		$this->row( __( 'WooCommerce', 'mab-commerce-filters' ), defined( 'WC_VERSION' ) ? WC_VERSION : __( 'Not active', 'mab-commerce-filters' ), defined( 'WC_VERSION' ) );
		$this->row( __( 'Elementor', 'mab-commerce-filters' ), defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : __( 'Not active', 'mab-commerce-filters' ), defined( 'ELEMENTOR_VERSION' ) );
		$this->row( __( 'Object Cache', 'mab-commerce-filters' ), wp_using_ext_object_cache() ? __( 'Persistent', 'mab-commerce-filters' ) : __( 'Database (transients)', 'mab-commerce-filters' ), wp_using_ext_object_cache() );
		$this->row( __( 'Memory Limit', 'mab-commerce-filters' ), ini_get( 'memory_limit' ) );
		$this->row( __( 'Max Input Vars', 'mab-commerce-filters' ), (string) ini_get( 'max_input_vars' ) );
		$this->row( __( 'Permalink Structure', 'mab-commerce-filters' ), get_option( 'permalink_structure' ) ?: __( 'Plain (not recommended)', 'mab-commerce-filters' ), (bool) get_option( 'permalink_structure' ) );

		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'WooCommerce Feature Compatibility', 'mab-commerce-filters' ) . '</h2>';
		echo '<table class="widefat striped"><tbody>';

		$features_util_available = class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class );

		$this->row(
			__( 'High-Performance Order Storage (HPOS)', 'mab-commerce-filters' ),
			$features_util_available ? __( 'Declared compatible', 'mab-commerce-filters' ) : __( 'N/A — WooCommerce version predates the Features API', 'mab-commerce-filters' ),
			$features_util_available ? true : null
		);
		$this->row(
			__( 'Cart & Checkout Blocks', 'mab-commerce-filters' ),
			$features_util_available ? __( 'Declared compatible', 'mab-commerce-filters' ) : __( 'N/A — WooCommerce version predates the Features API', 'mab-commerce-filters' ),
			$features_util_available ? true : null
		);

		if ( class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class ) ) {
			$hpos_enabled = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
			$this->row(
				__( 'HPOS Currently Enabled on This Store', 'mab-commerce-filters' ),
				$hpos_enabled ? __( 'Yes (orders table)', 'mab-commerce-filters' ) : __( 'No (post table)', 'mab-commerce-filters' ),
				null
			);
		}

		echo '</tbody></table>';
		echo '<p class="description">' . esc_html__( 'MAB Commerce Filters only ever reads/filters WooCommerce products — it never reads or writes order data, so it works identically whether HPOS or the legacy post-based order storage is active, and it does not render any classic cart/checkout markup that could conflict with the Cart & Checkout blocks.', 'mab-commerce-filters' ) . '</p>';

		echo '<h2>' . esc_html__( 'Database Tables', 'mab-commerce-filters' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Table', 'mab-commerce-filters' ) . '</th><th>' . esc_html__( 'Status', 'mab-commerce-filters' ) . '</th><th>' . esc_html__( 'Rows', 'mab-commerce-filters' ) . '</th></tr></thead><tbody>';

		foreach ( Schema::table_keys() as $key ) {
			$table  = Schema::table( $key );
			$exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			$count  = $exists ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) : 0; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

			printf(
				'<tr><td><code>%s</code></td><td>%s</td><td>%d</td></tr>',
				esc_html( $table ),
				$exists ? '<span class="mabcf-badge mabcf-badge--success">' . esc_html__( 'OK', 'mab-commerce-filters' ) . '</span>' : '<span class="mabcf-badge mabcf-badge--error">' . esc_html__( 'Missing', 'mab-commerce-filters' ) . '</span>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$count
			);
		}

		echo '</tbody></table>';
		echo '<p><em>' . esc_html__( 'DB Version:', 'mab-commerce-filters' ) . ' ' . esc_html( get_option( 'mabcf_db_version', __( 'unknown', 'mab-commerce-filters' ) ) ) . '</em></p>';
		echo '</div>';
	}

	/**
	 * Renders a status table row, optionally colour-coded.
	 *
	 * @param string    $label Row label.
	 * @param string    $value Row value.
	 * @param bool|null $ok    True/false to badge, null to leave neutral.
	 */
	private function row( string $label, string $value, ?bool $ok = null ): void {
		$badge = '';

		if ( null !== $ok ) {
			$badge = $ok
				? ' <span class="mabcf-badge mabcf-badge--success">' . esc_html__( 'OK', 'mab-commerce-filters' ) . '</span>'
				: ' <span class="mabcf-badge mabcf-badge--error">' . esc_html__( 'Check', 'mab-commerce-filters' ) . '</span>';
		}

		printf( '<tr><td>%s</td><td>%s%s</td></tr>', esc_html( $label ), esc_html( $value ), $badge ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
