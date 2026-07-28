<?php
/**
 * Registry of the built-in filter section types.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Describes every section that can appear inside a Filter Set: its label,
 * icon, the taxonomy it maps to (when relevant), which input types it
 * supports and its factory defaults. Third-party code can register
 * additional section types through the `apf_section_types` filter.
 */
final class APF_Section_Types {

	/**
	 * Cached, filtered list of registered section types.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private static ?array $types = null;

	/**
	 * Returns every registered section type keyed by its unique slug.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function all(): array {
		if ( null !== self::$types ) {
			return self::$types;
		}

		$types = array(
			'active_filters' => array(
				'label'        => __( 'Active Filters', 'advanced-product-filters' ),
				'taxonomy'     => null,
				'input_types'  => array(),
				'collapsible'  => false,
				'multi_select' => false,
			),
			'category'       => array(
				'label'        => __( 'Categories', 'advanced-product-filters' ),
				'taxonomy'     => 'product_cat',
				'input_types'  => array( 'checkbox', 'tree', 'dropdown' ),
				'collapsible'  => true,
				'multi_select' => true,
			),
			'price'          => array(
				'label'        => __( 'Price', 'advanced-product-filters' ),
				'taxonomy'     => null,
				'input_types'  => array( 'range_slider' ),
				'collapsible'  => true,
				'multi_select' => false,
			),
			'attribute'      => array(
				'label'        => __( 'Attribute', 'advanced-product-filters' ),
				'taxonomy'     => null,
				'input_types'  => array( 'checkbox', 'buttons', 'color', 'image', 'label', 'dropdown' ),
				'collapsible'  => true,
				'multi_select' => true,
			),
			'brand'          => array(
				'label'        => __( 'Brand', 'advanced-product-filters' ),
				'taxonomy'     => null,
				'input_types'  => array( 'checkbox', 'buttons', 'label', 'dropdown', 'search' ),
				'collapsible'  => true,
				'multi_select' => true,
			),
			'tag'            => array(
				'label'        => __( 'Tags', 'advanced-product-filters' ),
				'taxonomy'     => 'product_tag',
				'input_types'  => array( 'label', 'checkbox' ),
				'collapsible'  => true,
				'multi_select' => true,
			),
			'rating'         => array(
				'label'        => __( 'Rating', 'advanced-product-filters' ),
				'taxonomy'     => null,
				'input_types'  => array( 'checkbox' ),
				'collapsible'  => true,
				'multi_select' => true,
			),
			'stock'          => array(
				'label'        => __( 'Availability', 'advanced-product-filters' ),
				'taxonomy'     => null,
				'input_types'  => array( 'checkbox' ),
				'collapsible'  => true,
				'multi_select' => true,
			),
			'sale'           => array(
				'label'        => __( 'On Sale', 'advanced-product-filters' ),
				'taxonomy'     => null,
				'input_types'  => array( 'toggle' ),
				'collapsible'  => true,
				'multi_select' => false,
			),
			'featured'       => array(
				'label'        => __( 'Featured', 'advanced-product-filters' ),
				'taxonomy'     => null,
				'input_types'  => array( 'toggle' ),
				'collapsible'  => true,
				'multi_select' => false,
			),
			'newest'         => array(
				'label'        => __( 'New Arrivals', 'advanced-product-filters' ),
				'taxonomy'     => null,
				'input_types'  => array( 'toggle' ),
				'collapsible'  => true,
				'multi_select' => false,
			),
			'custom_taxonomy' => array(
				'label'        => __( 'Custom Taxonomy', 'advanced-product-filters' ),
				'taxonomy'     => null,
				'input_types'  => array( 'checkbox', 'buttons', 'label', 'dropdown' ),
				'collapsible'  => true,
				'multi_select' => true,
			),
		);

		/**
		 * Filters the registered section types.
		 *
		 * @param array<string, array<string, mixed>> $types Section type definitions.
		 */
		self::$types = apply_filters( 'apf_section_types', $types );

		return self::$types;
	}

	/**
	 * Returns a single section type definition.
	 *
	 * @param string $type Section type slug.
	 * @return array<string, mixed>|null
	 */
	public static function get( string $type ): ?array {
		$all = self::all();

		return $all[ $type ] ?? null;
	}

	/**
	 * Whether a section type slug is registered.
	 *
	 * @param string $type Section type slug.
	 * @return bool
	 */
	public static function exists( string $type ): bool {
		return null !== self::get( $type );
	}
}
