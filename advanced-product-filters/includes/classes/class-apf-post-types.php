<?php
/**
 * Registers the internal `apf_filter_set` storage post type.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Filter Sets are stored as a private post type so WordPress core handles
 * revisions, ordering (menu_order) and CRUD for us, while all rendering
 * configuration lives in post meta.
 */
final class APF_Post_Types {

	/**
	 * The custom post type slug used to persist Filter Sets.
	 *
	 * @var string
	 */
	public const FILTER_SET = 'apf_filter_set';

	/**
	 * Wires the registration hook.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Registers the `apf_filter_set` post type.
	 *
	 * @return void
	 */
	public function register(): void {
		register_post_type(
			self::FILTER_SET,
			array(
				'label'               => __( 'Filter Sets', 'advanced-product-filters' ),
				'public'              => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'hierarchical'        => false,
				'supports'            => array( 'title' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);
	}
}
