<?php
/**
 * Lists all filter sets with edit/duplicate/delete/activate actions.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Admin\Pages;

use MABCommerceFilters\Repositories\FilterRepository;
use MABCommerceFilters\Repositories\FilterSetRepository;
use MABCommerceFilters\Repositories\LocationRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The filter set index screen: create, duplicate, delete, and see each
 * set's filter count, assigned locations and ready-to-copy shortcode.
 */
final class FilterSetsPage extends AbstractAdminPage {

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'mabcf-filter-sets';
	}

	/**
	 * {@inheritDoc}
	 */
	public function title(): string {
		return __( 'Filter Sets', 'mab-commerce-filters' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function render_content(): void {
		$sets      = ( new FilterSetRepository() )->get_all();
		$filters   = new FilterRepository();
		$locations = new LocationRepository();

		echo '<div class="mabcf-panel">';
		echo '<div class="mabcf-panel__toolbar">';
		echo '<h2>' . esc_html__( 'Filter Sets', 'mab-commerce-filters' ) . '</h2>';
		printf(
			'<a class="button button-primary" href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=mabcf-filter-builder&set=0' ) ),
			esc_html__( '+ Add New Filter Set', 'mab-commerce-filters' )
		);
		echo '</div>';

		if ( ! $sets ) {
			echo '<p>' . esc_html__( 'No filter sets yet. Create one to get started.', 'mab-commerce-filters' ) . '</p></div>';
			return;
		}

		echo '<table class="widefat striped mabcf-table">';
		echo '<thead><tr>
			<th>' . esc_html__( 'Name', 'mab-commerce-filters' ) . '</th>
			<th>' . esc_html__( 'Filters', 'mab-commerce-filters' ) . '</th>
			<th>' . esc_html__( 'Locations', 'mab-commerce-filters' ) . '</th>
			<th>' . esc_html__( 'Shortcode', 'mab-commerce-filters' ) . '</th>
			<th>' . esc_html__( 'Status', 'mab-commerce-filters' ) . '</th>
			<th>' . esc_html__( 'Actions', 'mab-commerce-filters' ) . '</th>
		</tr></thead><tbody>';

		foreach ( $sets as $set ) {
			$filter_count   = count( $filters->get_for_set( (int) $set['id'] ) );
			$location_count = count( $locations->get_for_set( (int) $set['id'] ) );
			$edit_url       = admin_url( 'admin.php?page=mabcf-filter-builder&set=' . (int) $set['id'] );

			printf(
				'<tr>
					<td><strong><a href="%1$s">%2$s</a></strong></td>
					<td>%3$d</td>
					<td>%4$d</td>
					<td><code>[mabcf_filters set="%5$s"]</code></td>
					<td><span class="mabcf-badge mabcf-badge--%6$s">%7$s</span></td>
					<td class="mabcf-row-actions">
						<a href="%1$s" class="button button-small">%8$s</a>
						<button type="button" class="button button-small mabcf-duplicate-set" data-id="%9$d">%10$s</button>
						<button type="button" class="button button-small mabcf-toggle-set" data-id="%9$d" data-status="%11$s">%12$s</button>
						<button type="button" class="button button-small button-link-delete mabcf-delete-set" data-id="%9$d">%13$s</button>
					</td>
				</tr>',
				esc_url( $edit_url ),
				esc_html( $set['name'] ),
				(int) $filter_count,
				(int) $location_count,
				esc_attr( $set['slug'] ),
				esc_attr( 'active' === $set['status'] ? 'success' : 'muted' ),
				esc_html( 'active' === $set['status'] ? __( 'Active', 'mab-commerce-filters' ) : __( 'Inactive', 'mab-commerce-filters' ) ),
				esc_html__( 'Edit', 'mab-commerce-filters' ),
				(int) $set['id'],
				esc_html__( 'Duplicate', 'mab-commerce-filters' ),
				esc_attr( $set['status'] ),
				esc_html( 'active' === $set['status'] ? __( 'Deactivate', 'mab-commerce-filters' ) : __( 'Activate', 'mab-commerce-filters' ) ),
				esc_html__( 'Delete', 'mab-commerce-filters' )
			);
		}

		echo '</tbody></table></div>';
	}
}
