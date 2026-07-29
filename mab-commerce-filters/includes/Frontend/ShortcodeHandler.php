<?php
/**
 * Registers the [mabcf_filters] and [mabcf_products] shortcodes.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Frontend;

use MABCommerceFilters\Repositories\FilterRepository;
use MABCommerceFilters\Services\FilterQueryService;
use MABCommerceFilters\Services\LocationResolver;
use MABCommerceFilters\Services\UrlSyncService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `[mabcf_filters set="slug-or-id"]` renders a filter widget standalone.
 * `[mabcf_products set="slug-or-id" columns="4"]` renders a filtered grid
 * standalone (useful outside the main shop loop, e.g. on a landing page).
 */
final class ShortcodeHandler {

	/**
	 * Registers both shortcodes.
	 */
	public function register(): void {
		add_shortcode( 'mabcf_filters', array( $this, 'render_filters' ) );
		add_shortcode( 'mabcf_products', array( $this, 'render_products' ) );
	}

	/**
	 * Renders the `[mabcf_filters]` shortcode.
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 */
	public function render_filters( $atts ): string {
		$atts = shortcode_atts( array( 'set' => '' ), (array) $atts, 'mabcf_filters' );

		$resolver = new LocationResolver();
		$set      = $atts['set'] ? $resolver->resolve_explicit( $atts['set'] ) : $resolver->resolve_current();

		if ( ! $set ) {
			return '';
		}

		$filters   = ( new FilterRepository() )->get_for_set( (int) $set['id'] );
		$url_sync  = new UrlSyncService();
		$selection = $url_sync->from_request( $filters, wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$context   = $resolver->current_context();
		$base_args = $resolver->base_args_for_context( $context );

		return ( new Renderer() )->render( $set, $base_args, $selection, $context );
	}

	/**
	 * Renders the `[mabcf_products]` shortcode.
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 */
	public function render_products( $atts ): string {
		$atts = shortcode_atts(
			array(
				'set'     => '',
				'columns' => 4,
			),
			(array) $atts,
			'mabcf_products'
		);

		$resolver = new LocationResolver();
		$set      = $atts['set'] ? $resolver->resolve_explicit( $atts['set'] ) : $resolver->resolve_current();

		if ( ! $set ) {
			return '';
		}

		$filters   = ( new FilterRepository() )->get_for_set( (int) $set['id'] );
		$url_sync  = new UrlSyncService();
		$selection = $url_sync->from_request( $filters, wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$context   = $resolver->current_context();
		$base_args = $resolver->base_args_for_context( $context );

		$query_svc = new FilterQueryService();
		$args      = $query_svc->build_query_args( $filters, $selection, $base_args );
		$query     = $query_svc->run( $args );

		$grid = ( new ProductGridRenderer() )->render(
			$query,
			array(
				'columns'         => (int) $atts['columns'],
				'show_sorting'    => true,
				'show_pagination' => true,
			)
		);

		return '<div class="mabcf-products-target" data-mabcf-ajax-target="1">' . $grid . '</div>';
	}
}
