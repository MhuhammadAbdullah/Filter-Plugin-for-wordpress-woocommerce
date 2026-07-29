<?php
/**
 * Manages where each filter set is displayed (shop, archives, pages...).
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Admin\Pages;

use MABCommerceFilters\Repositories\FilterSetRepository;
use MABCommerceFilters\Repositories\LocationRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A single table of location assignments plus a form to add new ones.
 * Categories/tags/brands are picked from real taxonomy terms; pages from
 * real published pages; Elementor templates and shortcode keys accept a
 * free-form identifier.
 */
final class LocationsPage extends AbstractAdminPage {

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'mabcf-locations';
	}

	/**
	 * {@inheritDoc}
	 */
	public function title(): string {
		return __( 'Locations', 'mab-commerce-filters' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function render_content(): void {
		$sets      = ( new FilterSetRepository() )->get_all();
		$locations = new LocationRepository();

		echo '<div class="mabcf-panel">';
		echo '<h2>' . esc_html__( 'Assign Filter Sets to Locations', 'mab-commerce-filters' ) . '</h2>';

		if ( ! $sets ) {
			echo '<p>' . esc_html__( 'Create a filter set first.', 'mab-commerce-filters' ) . '</p></div>';
			return;
		}

		echo '<form id="mabcf-add-location" class="mabcf-inline-form">';
		echo '<input type="hidden" id="mabcf-locations-nonce" value="' . esc_attr( wp_create_nonce( 'mabcf_admin' ) ) . '">';

		echo '<select id="mabcf-location-set">';
		foreach ( $sets as $set ) {
			printf( '<option value="%d">%s</option>', (int) $set['id'], esc_html( $set['name'] ) );
		}
		echo '</select>';

		echo '<select id="mabcf-location-type">';
		$types = array(
			'global'   => __( 'Everywhere', 'mab-commerce-filters' ),
			'shop'     => __( 'Shop Page', 'mab-commerce-filters' ),
			'category' => __( 'Category Archive', 'mab-commerce-filters' ),
			'tag'      => __( 'Tag Archive', 'mab-commerce-filters' ),
			'brand'    => __( 'Brand Archive', 'mab-commerce-filters' ),
			'page'     => __( 'Specific Page', 'mab-commerce-filters' ),
			'template' => __( 'Elementor Template', 'mab-commerce-filters' ),
		);
		foreach ( $types as $value => $label ) {
			printf( '<option value="%s">%s</option>', esc_attr( $value ), esc_html( $label ) );
		}
		echo '</select>';

		echo '<select id="mabcf-location-value" data-placeholder="' . esc_attr__( 'Select…', 'mab-commerce-filters' ) . '">';
		echo '<option value="">' . esc_html__( 'N/A', 'mab-commerce-filters' ) . '</option>';
		foreach ( get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) ) as $term ) {
			printf( '<option value="%s" data-type="category">%s</option>', esc_attr( $term->slug ), esc_html( $term->name ) );
		}
		foreach ( get_terms( array( 'taxonomy' => 'product_tag', 'hide_empty' => false ) ) as $term ) {
			printf( '<option value="%s" data-type="tag">%s</option>', esc_attr( $term->slug ), esc_html( $term->name ) );
		}
		if ( taxonomy_exists( 'product_brand' ) ) {
			foreach ( get_terms( array( 'taxonomy' => 'product_brand', 'hide_empty' => false ) ) as $term ) {
				printf( '<option value="%s" data-type="brand">%s</option>', esc_attr( $term->slug ), esc_html( $term->name ) );
			}
		}
		foreach ( get_pages() as $page ) {
			printf( '<option value="%d" data-type="page">%s</option>', (int) $page->ID, esc_html( $page->post_title ) );
		}
		echo '</select>';

		printf( '<button type="submit" class="button button-primary">%s</button>', esc_html__( 'Add Location', 'mab-commerce-filters' ) );
		echo '</form>';

		echo '<table class="widefat striped mabcf-table" id="mabcf-locations-table">';
		echo '<thead><tr><th>' . esc_html__( 'Filter Set', 'mab-commerce-filters' ) . '</th><th>' . esc_html__( 'Type', 'mab-commerce-filters' ) . '</th><th>' . esc_html__( 'Value', 'mab-commerce-filters' ) . '</th><th>' . esc_html__( 'Actions', 'mab-commerce-filters' ) . '</th></tr></thead><tbody>';

		$sets_by_id = wp_list_pluck( $sets, 'name', 'id' );

		foreach ( $sets as $set ) {
			foreach ( $locations->get_for_set( (int) $set['id'] ) as $location ) {
				printf(
					'<tr><td>%s</td><td>%s</td><td>%s</td><td><button type="button" class="button-link-delete mabcf-delete-location" data-id="%d">%s</button></td></tr>',
					esc_html( $sets_by_id[ $set['id'] ] ?? '' ),
					esc_html( $location['location_type'] ),
					esc_html( $location['location_value'] ?: '—' ),
					(int) $location['id'],
					esc_html__( 'Remove', 'mab-commerce-filters' )
				);
			}
		}

		echo '</tbody></table></div>';
	}
}
