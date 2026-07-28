<?php
/**
 * Taxonomy discovery helpers (attributes, brands, custom taxonomies).
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce does not ship a native "Brand" taxonomy, so several
 * ecosystem plugins invented their own (`product_brand`, `pwb-brand`,
 * `pa_brand`, ...). This helper detects whichever one is present instead
 * of hard-coding a single slug.
 */
final class APF_Taxonomies {

	/**
	 * Taxonomy slugs, in priority order, recognised as "Brand" taxonomies
	 * by popular WooCommerce brand plugins.
	 *
	 * @var string[]
	 */
	private const KNOWN_BRAND_TAXONOMIES = array(
		'product_brand',
		'pwb-brand',
		'yith_product_brand',
		'pa_brand',
	);

	/**
	 * Detects the active "Brand" taxonomy, if any.
	 *
	 * @return string|null Taxonomy slug or null when no brand taxonomy exists.
	 */
	public static function get_brand_taxonomy(): ?string {
		/**
		 * Filters the list of taxonomy slugs treated as "Brand" taxonomies.
		 *
		 * @param string[] $taxonomies Candidate taxonomy slugs, most preferred first.
		 */
		$candidates = apply_filters( 'apf_brand_taxonomies', self::KNOWN_BRAND_TAXONOMIES );

		foreach ( $candidates as $taxonomy ) {
			if ( taxonomy_exists( $taxonomy ) ) {
				return $taxonomy;
			}
		}

		return null;
	}

	/**
	 * Returns every WooCommerce global product attribute taxonomy
	 * (`pa_color`, `pa_size`, ...) that currently has at least one term.
	 *
	 * @return array<string, string> Taxonomy slug => attribute label.
	 */
	public static function get_attribute_taxonomies(): array {
		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
			return array();
		}

		$attributes = wc_get_attribute_taxonomies();
		$taxonomies = array();

		foreach ( $attributes as $attribute ) {
			$taxonomy = wc_attribute_taxonomy_name( $attribute->attribute_name );

			if ( taxonomy_exists( $taxonomy ) ) {
				$taxonomies[ $taxonomy ] = $attribute->attribute_label;
			}
		}

		return $taxonomies;
	}

	/**
	 * Returns every custom taxonomy registered against `product` that is
	 * not one of the built-in taxonomies handled by their own dedicated
	 * section types (category, tag, brand, attributes).
	 *
	 * @return array<string, string> Taxonomy slug => taxonomy label.
	 */
	public static function get_custom_taxonomies(): array {
		$excluded = array_merge(
			array( 'product_cat', 'product_tag', 'product_type', 'product_shipping_class', 'product_visibility' ),
			array_keys( self::get_attribute_taxonomies() ),
			array_filter( array( self::get_brand_taxonomy() ) )
		);

		$taxonomies = get_object_taxonomies( 'product', 'objects' );
		$custom     = array();

		foreach ( $taxonomies as $slug => $taxonomy ) {
			if ( in_array( $slug, $excluded, true ) || 0 === strpos( $slug, 'pa_' ) ) {
				continue;
			}

			$custom[ $slug ] = $taxonomy->label;
		}

		/**
		 * Filters the custom taxonomies made available to the Filter Set builder.
		 *
		 * @param array<string, string> $custom Taxonomy slug => label.
		 */
		return apply_filters( 'apf_custom_taxonomies', $custom );
	}

	/**
	 * Returns every taxonomy the plugin is able to build a filter section
	 * for, keyed by slug.
	 *
	 * @return array<string, string>
	 */
	public static function get_all_filterable_taxonomies(): array {
		$taxonomies = array(
			'product_cat' => __( 'Product Categories', 'advanced-product-filters' ),
			'product_tag' => __( 'Product Tags', 'advanced-product-filters' ),
		);

		$brand = self::get_brand_taxonomy();

		if ( $brand ) {
			$taxonomy_object            = get_taxonomy( $brand );
			$taxonomies[ $brand ] = $taxonomy_object ? $taxonomy_object->label : __( 'Brand', 'advanced-product-filters' );
		}

		return $taxonomies + self::get_attribute_taxonomies() + self::get_custom_taxonomies();
	}
}
