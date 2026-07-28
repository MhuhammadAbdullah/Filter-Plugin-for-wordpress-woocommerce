<?php
/**
 * Small presentation helpers shared by the admin view files.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'apf_describe_locations' ) ) {
	/**
	 * Renders a human readable summary of a Filter Set's location rules
	 * for the list table (e.g. "Shop page", "Categories: Bags, Shoes").
	 *
	 * @param array<int, array<string, mixed>> $locations Location rules.
	 * @return string
	 */
	function apf_describe_locations( array $locations ): string {
		if ( empty( $locations ) ) {
			return __( 'Not assigned', 'advanced-product-filters' );
		}

		$labels = array();

		foreach ( $locations as $location ) {
			$type = $location['type'] ?? '';

			switch ( $type ) {
				case 'shop':
					$labels[] = __( 'Shop Page', 'advanced-product-filters' );
					break;

				case 'all_archives':
					$labels[] = __( 'All Product Archives', 'advanced-product-filters' );
					break;

				case 'page':
					$labels[] = apf_describe_terms_or_pages( $location['ids'] ?? array(), 'page' );
					break;

				case 'elementor_template':
					$labels[] = __( 'Elementor Template', 'advanced-product-filters' );
					break;

				case 'product_cat':
					$labels[] = apf_describe_terms_or_pages( $location['ids'] ?? array(), 'product_cat', __( 'Categories', 'advanced-product-filters' ) );
					break;

				case 'product_tag':
					$labels[] = apf_describe_terms_or_pages( $location['ids'] ?? array(), 'product_tag', __( 'Tags', 'advanced-product-filters' ) );
					break;

				case 'brand':
					$brand_taxonomy = APF_Taxonomies::get_brand_taxonomy();
					$labels[]       = $brand_taxonomy
						? apf_describe_terms_or_pages( $location['ids'] ?? array(), $brand_taxonomy, __( 'Brands', 'advanced-product-filters' ) )
						: __( 'Brand', 'advanced-product-filters' );
					break;

				case 'taxonomy':
					$taxonomy = $location['taxonomy'] ?? '';
					$object   = $taxonomy ? get_taxonomy( $taxonomy ) : false;
					$labels[] = apf_describe_terms_or_pages( $location['ids'] ?? array(), $taxonomy, $object ? $object->label : $taxonomy );
					break;
			}
		}

		return implode( ' • ', array_filter( $labels ) );
	}
}

if ( ! function_exists( 'apf_describe_terms_or_pages' ) ) {
	/**
	 * Resolves a list of term/page IDs into a short, comma separated
	 * label, prefixed with an optional group name.
	 *
	 * @param int[]       $ids     Term or page IDs.
	 * @param string      $context Taxonomy slug, or "page" for `WP_Post` IDs.
	 * @param string|null $prefix  Optional label prefix (e.g. "Categories").
	 * @return string
	 */
	function apf_describe_terms_or_pages( array $ids, string $context, ?string $prefix = null ): string {
		if ( empty( $ids ) ) {
			return $prefix ? sprintf( '%s (%s)', $prefix, __( 'all', 'advanced-product-filters' ) ) : '';
		}

		$names = array();

		foreach ( array_slice( $ids, 0, 5 ) as $id ) {
			if ( 'page' === $context ) {
				$post    = get_post( $id );
				$names[] = $post ? $post->post_title : null;
			} else {
				$term    = get_term( $id, $context );
				$names[] = ( $term && ! is_wp_error( $term ) ) ? $term->name : null;
			}
		}

		$names = array_filter( $names );
		$label = implode( ', ', $names );

		if ( count( $ids ) > 5 ) {
			/* translators: %d: number of additional items */
			$label .= ' ' . sprintf( __( '+%d more', 'advanced-product-filters' ), count( $ids ) - 5 );
		}

		return $prefix ? sprintf( '%s: %s', $prefix, $label ) : $label;
	}
}
