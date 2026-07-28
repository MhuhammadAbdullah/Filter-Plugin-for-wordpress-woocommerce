<?php
/**
 * Runs once when the plugin is activated.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Seeds default options and a starter Filter Set so the plugin is
 * immediately useful on activation rather than showing an empty screen.
 */
final class APF_Activator {

	/**
	 * Activation callback registered in the bootstrap file.
	 *
	 * @return void
	 */
	public static function activate(): void {
		if ( false === get_option( APF_Settings::OPTION, false ) ) {
			update_option( APF_Settings::OPTION, APF_Settings::defaults(), false );
		}

		if ( false === get_option( 'apf_cache_salt', false ) ) {
			update_option( 'apf_cache_salt', wp_generate_password( 12, false ), false );
		}

		( new APF_Post_Types() )->register();

		self::maybe_seed_default_filter_set();

		update_option( 'apf_version', APF_VERSION, false );

		flush_rewrite_rules();
	}

	/**
	 * Creates a "Default Shop Filters" Filter Set assigned to the shop page
	 * the very first time the plugin is activated.
	 *
	 * @return void
	 */
	private static function maybe_seed_default_filter_set(): void {
		if ( get_option( 'apf_seeded_default_set', false ) ) {
			return;
		}

		update_option( 'apf_seeded_default_set', true, false );

		$existing = get_posts(
			array(
				'post_type'      => APF_Post_Types::FILTER_SET,
				'posts_per_page' => 1,
				'post_status'    => array( 'publish', 'draft' ),
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $existing ) ) {
			return;
		}

		$sections = APF_Filter_Set::default_sections();

		$sections[] = array(
			'id'         => 'tags',
			'type'       => 'tag',
			'label'      => __( 'Tags', 'advanced-product-filters' ),
			'enabled'    => true,
			'collapsed'  => false,
			'input_type' => 'label',
		);

		APF_Filter_Sets::save(
			array(
				'name'      => __( 'Default Shop Filters', 'advanced-product-filters' ),
				'enabled'   => true,
				'priority'  => 10,
				'locations' => array( array( 'type' => 'shop' ) ),
				'sections'  => $sections,
				'settings'  => array(),
			)
		);
	}
}
