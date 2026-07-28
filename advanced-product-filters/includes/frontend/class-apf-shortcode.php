<?php
/**
 * `[advanced_product_filters]` shortcode.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lets a store owner drop the filter sidebar anywhere a shortcode can run
 * — a widget area, a Gutenberg Shortcode block, or a page builder text
 * element — independently of the automatic WooCommerce archive injection
 * handled by `APF_Frontend`.
 */
final class APF_Shortcode {

	/**
	 * Registers the shortcode tag.
	 */
	public function __construct() {
		add_shortcode( 'advanced_product_filters', array( $this, 'render' ) );
	}

	/**
	 * Renders the shortcode.
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'filter_set_id' => 0,
			),
			(array) $atts,
			'advanced_product_filters'
		);

		$filter_set = absint( $atts['filter_set_id'] )
			? APF_Filter_Sets::get( absint( $atts['filter_set_id'] ) )
			: APF_Filter_Sets::get_for_current_context();

		if ( ! $filter_set ) {
			return '';
		}

		APF_Frontend::ensure_assets( $filter_set->get_id() );

		$applied_filters = APF_Query_Builder::parse_request( $_GET, $filter_set ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return APF_Renderer::render_sidebar( $filter_set, $applied_filters, APF_Context::get_archive_restriction() );
	}
}
