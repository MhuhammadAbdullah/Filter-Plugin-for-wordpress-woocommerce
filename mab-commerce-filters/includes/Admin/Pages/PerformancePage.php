<?php
/**
 * Caching and query performance settings.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Admin\Pages;

use MABCommerceFilters\Repositories\SettingsRepository;
use MABCommerceFilters\Services\CacheService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Toggles for object/transient caching, cache TTL, pagination size and
 * infinite scroll, plus a one-click "Clear Cache" action.
 */
final class PerformancePage extends AbstractAdminPage {

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'mabcf-performance';
	}

	/**
	 * {@inheritDoc}
	 */
	public function title(): string {
		return __( 'Performance', 'mab-commerce-filters' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function render_content(): void {
		$settings = new SettingsRepository();

		if ( isset( $_POST['mabcf_performance_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mabcf_performance_nonce'] ) ), 'mabcf_performance' ) ) {
			$settings->set_many(
				array(
					'cache_enabled'     => isset( $_POST['cache_enabled'] ) ? '1' : '0',
					'cache_ttl'         => max( 30, absint( $_POST['cache_ttl'] ?? 300 ) ),
					'products_per_page' => max( 1, absint( $_POST['products_per_page'] ?? 12 ) ),
					'infinite_scroll'   => isset( $_POST['infinite_scroll'] ) ? '1' : '0',
				)
			);
			$this->notice( __( 'Performance settings saved.', 'mab-commerce-filters' ) );
		}

		echo '<div class="mabcf-panel"><form method="post">';
		wp_nonce_field( 'mabcf_performance', 'mabcf_performance_nonce' );

		echo '<h2>' . esc_html__( 'Performance', 'mab-commerce-filters' ) . '</h2>';

		printf(
			'<p>%s <strong>%s</strong></p>',
			esc_html__( 'Object cache backend:', 'mab-commerce-filters' ),
			esc_html( wp_using_ext_object_cache() ? __( 'Persistent object cache detected (Redis/Memcached).', 'mab-commerce-filters' ) : __( 'None — falling back to database transients.', 'mab-commerce-filters' ) )
		);

		echo '<table class="form-table"><tbody>';
		printf(
			'<tr><th>%1$s</th><td><label><input type="checkbox" name="cache_enabled" %2$s> %1$s</label></td></tr>',
			esc_html__( 'Enable Query Caching', 'mab-commerce-filters' ),
			checked( '1' === (string) $settings->get( 'cache_enabled', '1' ), true, false )
		);
		printf(
			'<tr><th><label for="cache_ttl">%1$s</label></th><td><input type="number" min="30" name="cache_ttl" id="cache_ttl" value="%2$d"></td></tr>',
			esc_html__( 'Cache TTL (seconds)', 'mab-commerce-filters' ),
			(int) $settings->get( 'cache_ttl', 300 )
		);
		printf(
			'<tr><th><label for="products_per_page">%1$s</label></th><td><input type="number" min="1" name="products_per_page" id="products_per_page" value="%2$d"></td></tr>',
			esc_html__( 'Products Per Page', 'mab-commerce-filters' ),
			(int) $settings->get( 'products_per_page', 12 )
		);
		printf(
			'<tr><th>%1$s</th><td><label><input type="checkbox" name="infinite_scroll" %2$s> %1$s</label></td></tr>',
			esc_html__( 'Infinite Scroll by default', 'mab-commerce-filters' ),
			checked( '1' === (string) $settings->get( 'infinite_scroll', '0' ), true, false )
		);
		echo '</tbody></table>';

		submit_button( __( 'Save Performance Settings', 'mab-commerce-filters' ) );
		echo '</form>';

		echo '<hr>';
		echo '<h3>' . esc_html__( 'Cache', 'mab-commerce-filters' ) . '</h3>';
		echo '<p>' . esc_html__( 'Clear cached filter counts and price bounds. This happens automatically on save, but you can force it here.', 'mab-commerce-filters' ) . '</p>';
		printf( '<button type="button" class="button mabcf-clear-cache" data-nonce="%s">%s</button>', esc_attr( wp_create_nonce( 'mabcf_admin' ) ), esc_html__( 'Clear Cache Now', 'mab-commerce-filters' ) );
		echo '<span class="mabcf-save-status" aria-live="polite"></span>';
		echo '</div>';
	}
}
