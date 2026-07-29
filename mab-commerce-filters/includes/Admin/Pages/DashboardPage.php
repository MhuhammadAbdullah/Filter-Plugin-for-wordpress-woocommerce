<?php
/**
 * Admin dashboard: quick stats and recent activity.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Admin\Pages;

use MABCommerceFilters\Repositories\FilterRepository;
use MABCommerceFilters\Repositories\FilterSetRepository;
use MABCommerceFilters\Repositories\LogRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Landing screen summarising the store's filter setup.
 */
final class DashboardPage extends AbstractAdminPage {

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'mabcf-dashboard';
	}

	/**
	 * {@inheritDoc}
	 */
	public function title(): string {
		return __( 'Dashboard', 'mab-commerce-filters' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function render_content(): void {
		$sets       = new FilterSetRepository();
		$filters    = new FilterRepository();
		$all_sets   = $sets->get_all();
		$active     = array_filter( $all_sets, static fn( $s ) => 'active' === $s['status'] );
		$logs       = ( new LogRepository() )->recent( '', 10 );

		echo '<div class="mabcf-cards">';
		$this->stat_card( __( 'Filter Sets', 'mab-commerce-filters' ), (string) count( $all_sets ) );
		$this->stat_card( __( 'Active Sets', 'mab-commerce-filters' ), (string) count( $active ) );
		$this->stat_card( __( 'Total Filters', 'mab-commerce-filters' ), (string) $filters->count() );
		$this->stat_card( __( 'WooCommerce', 'mab-commerce-filters' ), defined( 'WC_VERSION' ) ? 'v' . WC_VERSION : __( 'Not detected', 'mab-commerce-filters' ) );
		echo '</div>';

		echo '<div class="mabcf-panel">';
		echo '<h2>' . esc_html__( 'Quick Start', 'mab-commerce-filters' ) . '</h2>';
		echo '<p>' . esc_html__( 'Create a filter set, add filters with the drag & drop builder, then assign it to your shop, a category, a page, or drop it in with the [mabcf_filters] shortcode / Elementor widget.', 'mab-commerce-filters' ) . '</p>';
		printf(
			'<a class="button button-primary" href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=mabcf-filter-builder&set=0' ) ),
			esc_html__( 'Create Your First Filter Set', 'mab-commerce-filters' )
		);
		echo '</div>';

		echo '<div class="mabcf-panel">';
		echo '<h2>' . esc_html__( 'Recent Activity', 'mab-commerce-filters' ) . '</h2>';

		if ( ! $logs ) {
			echo '<p>' . esc_html__( 'No activity logged yet. Enable Debug Mode in Settings to see detailed AJAX and query logs here.', 'mab-commerce-filters' ) . '</p>';
		} else {
			echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Time', 'mab-commerce-filters' ) . '</th><th>' . esc_html__( 'Channel', 'mab-commerce-filters' ) . '</th><th>' . esc_html__( 'Message', 'mab-commerce-filters' ) . '</th></tr></thead><tbody>';
			foreach ( $logs as $log ) {
				printf(
					'<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
					esc_html( $log['created_at'] ),
					esc_html( $log['channel'] ),
					esc_html( $log['message'] )
				);
			}
			echo '</tbody></table>';
		}

		echo '</div>';
	}

	/**
	 * Renders a single stat card.
	 *
	 * @param string $label Card label.
	 * @param string $value Card value.
	 */
	private function stat_card( string $label, string $value ): void {
		printf(
			'<div class="mabcf-card"><span class="mabcf-card__value">%s</span><span class="mabcf-card__label">%s</span></div>',
			esc_html( $value ),
			esc_html( $label )
		);
	}
}
