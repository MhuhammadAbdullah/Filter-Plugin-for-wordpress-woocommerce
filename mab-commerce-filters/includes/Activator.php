<?php
/**
 * Handles plugin activation: schema install and default settings/data.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters;

use MABCommerceFilters\Database\Migrator;
use MABCommerceFilters\Repositories\FilterRepository;
use MABCommerceFilters\Repositories\FilterSetRepository;
use MABCommerceFilters\Repositories\LocationRepository;
use MABCommerceFilters\Repositories\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs on register_activation_hook.
 */
final class Activator {

	/**
	 * Installs database tables, seeds defaults and flushes rewrite rules.
	 */
	public static function activate(): void {
		( new Migrator() )->migrate();

		self::seed_default_settings();
		self::maybe_seed_default_filter_set();

		if ( false === get_option( 'mabcf_activated_at' ) ) {
			add_option( 'mabcf_activated_at', time() );
		}

		flush_rewrite_rules();
	}

	/**
	 * Writes default plugin settings into the settings table if unset.
	 */
	private static function seed_default_settings(): void {
		$settings = new SettingsRepository();

		$defaults = array(
			'ajax_mode'          => 'instant',
			'update_url'         => '1',
			'infinite_scroll'    => '0',
			'products_per_page'  => 12,
			'cache_enabled'      => '1',
			'cache_ttl'          => 300,
			'debug_mode'         => '0',
			'currency_position'  => get_option( 'woocommerce_currency_pos', 'left' ),
			'primary_color'      => '#111111',
			'accent_color'       => '#111111',
			'border_radius'      => 8,
			'mobile_breakpoint'  => 1024,
			'sticky_sidebar'     => '1',
			'show_product_count' => '1',
		);

		foreach ( $defaults as $key => $value ) {
			if ( null === $settings->get( $key, null ) ) {
				$settings->set( $key, $value );
			}
		}
	}

	/**
	 * Creates a ready-to-use "Default Shop Filters" set on first activation
	 * only, mirroring the reference UI (categories, price, color, size, tags).
	 */
	private static function maybe_seed_default_filter_set(): void {
		if ( get_option( 'mabcf_default_set_seeded' ) ) {
			return;
		}

		$filter_sets = new FilterSetRepository();
		$filters     = new FilterRepository();
		$locations   = new LocationRepository();

		$set_id = $filter_sets->create(
			array(
				'name'        => __( 'Default Shop Filters', 'mab-commerce-filters' ),
				'slug'        => 'default-shop-filters',
				'description' => __( 'Automatically created starter filter set.', 'mab-commerce-filters' ),
				'layout'      => 'sidebar',
				'status'      => 'active',
				'priority'    => 10,
				'settings'    => array(
					'ajax'          => true,
					'instant'       => true,
					'change_url'    => true,
					'apply_button'  => false,
					'collapsible'   => true,
				),
			)
		);

		if ( ! $set_id ) {
			return;
		}

		$blueprint = array(
			array(
				'type'          => 'category',
				'label'         => __( 'Categories', 'mab-commerce-filters' ),
				'source_key'    => 'product_cat',
				'display_style' => 'list',
				'sort_order'    => 1,
				'settings'      => array( 'show_count' => true, 'hierarchical' => true, 'searchable' => false ),
			),
			array(
				'type'          => 'price',
				'label'         => __( 'Price', 'mab-commerce-filters' ),
				'source_key'    => '_price',
				'display_style' => 'slider',
				'sort_order'    => 2,
				'settings'      => array( 'apply_button' => false, 'step' => 1 ),
			),
			array(
				'type'          => 'attribute',
				'label'         => __( 'Color', 'mab-commerce-filters' ),
				'source_key'    => 'pa_color',
				'display_style' => 'color',
				'sort_order'    => 3,
				'settings'      => array( 'show_count' => false ),
			),
			array(
				'type'          => 'attribute',
				'label'         => __( 'Size', 'mab-commerce-filters' ),
				'source_key'    => 'pa_size',
				'display_style' => 'pill',
				'sort_order'    => 4,
				'settings'      => array( 'show_count' => false ),
			),
			array(
				'type'          => 'tag',
				'label'         => __( 'Tags', 'mab-commerce-filters' ),
				'source_key'    => 'product_tag',
				'display_style' => 'pill',
				'sort_order'    => 5,
				'settings'      => array( 'show_count' => false ),
			),
		);

		foreach ( $blueprint as $filter ) {
			$filter['filter_set_id'] = $set_id;
			$filters->create( $filter );
		}

		$locations->create(
			array(
				'filter_set_id'  => $set_id,
				'location_type'  => 'shop',
				'location_value' => '',
			)
		);

		update_option( 'mabcf_default_set_seeded', 1 );
	}
}
