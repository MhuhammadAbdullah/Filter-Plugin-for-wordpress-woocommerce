<?php
/**
 * Registry of the visual input types a taxonomy-driven section can render.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Each input type maps to a template partial inside `templates/inputs/` and
 * declares whether it needs term meta (colour/image) to render correctly.
 */
final class APF_Filter_Input_Types {

	/**
	 * Cached, filtered list of input types.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private static ?array $types = null;

	/**
	 * Returns every registered input type keyed by its unique slug.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function all(): array {
		if ( null !== self::$types ) {
			return self::$types;
		}

		$types = array(
			'checkbox'     => array(
				'label'    => __( 'Checkbox List', 'advanced-product-filters' ),
				'template' => 'input-checkbox',
			),
			'radio'        => array(
				'label'    => __( 'Radio List', 'advanced-product-filters' ),
				'template' => 'input-radio',
			),
			'buttons'      => array(
				'label'    => __( 'Buttons', 'advanced-product-filters' ),
				'template' => 'input-buttons',
			),
			'color'        => array(
				'label'    => __( 'Color Swatches', 'advanced-product-filters' ),
				'template' => 'input-color',
				'requires_meta' => 'color',
			),
			'image'        => array(
				'label'    => __( 'Image Swatches', 'advanced-product-filters' ),
				'template' => 'input-image',
				'requires_meta' => 'image',
			),
			'label'        => array(
				'label'    => __( 'Label Swatches (Pills)', 'advanced-product-filters' ),
				'template' => 'input-label',
			),
			'dropdown'     => array(
				'label'    => __( 'Dropdown', 'advanced-product-filters' ),
				'template' => 'input-dropdown',
			),
			'search'       => array(
				'label'    => __( 'AJAX Search Box', 'advanced-product-filters' ),
				'template' => 'input-search',
			),
			'tree'         => array(
				'label'    => __( 'Tree View', 'advanced-product-filters' ),
				'template' => 'input-tree',
			),
			'range_slider' => array(
				'label'    => __( 'Range Slider', 'advanced-product-filters' ),
				'template' => 'input-range-slider',
			),
			'toggle'       => array(
				'label'    => __( 'Toggle', 'advanced-product-filters' ),
				'template' => 'input-toggle',
			),
		);

		/**
		 * Filters the registered filter input types.
		 *
		 * @param array<string, array<string, mixed>> $types Input type definitions.
		 */
		self::$types = apply_filters( 'apf_filter_input_types', $types );

		return self::$types;
	}

	/**
	 * Returns a single input type definition.
	 *
	 * @param string $type Input type slug.
	 * @return array<string, mixed>|null
	 */
	public static function get( string $type ): ?array {
		return self::all()[ $type ] ?? null;
	}
}
