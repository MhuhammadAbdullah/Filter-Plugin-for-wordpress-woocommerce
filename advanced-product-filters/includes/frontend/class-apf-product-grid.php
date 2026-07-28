<?php
/**
 * Renders a WooCommerce-compatible product grid from arbitrary query args.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Runs a standalone `WP_Query` and renders it through WooCommerce's own
 * `content-product.php` template part (temporarily swapping the global
 * query, the same technique WooCommerce's own `[products]` shortcode
 * uses) so every product type — simple, variable, grouped, external —
 * and every theme/plugin hook on the loop keeps working unmodified.
 */
final class APF_Product_Grid {

	/**
	 * Runs the query and renders the resulting grid + pagination.
	 *
	 * @param array<string, mixed> $query_args WP_Query arguments.
	 * @return array{html: string, found: int, max_num_pages: int, page: int}
	 */
	public static function render( array $query_args ): array {
		$query_args = wp_parse_args(
			$query_args,
			array(
				'post_type'   => 'product',
				'post_status' => 'publish',
				'paged'       => 1,
			)
		);

		$query = new WP_Query( $query_args );

		$html = self::render_query( $query );

		wp_reset_postdata();

		return array(
			'html'          => $html,
			'found'         => (int) $query->found_posts,
			'max_num_pages' => (int) $query->max_num_pages,
			'page'          => max( 1, (int) ( $query_args['paged'] ?? 1 ) ),
		);
	}

	/**
	 * Renders an already-executed `WP_Query` through WooCommerce's loop
	 * template parts.
	 *
	 * @param WP_Query $query Executed product query.
	 * @return string
	 */
	private static function render_query( WP_Query $query ): string {
		if ( ! function_exists( 'wc_get_template_part' ) ) {
			return '';
		}

		ob_start();

		if ( $query->have_posts() ) {
			$original_query        = $GLOBALS['wp_query'] ?? null;
			$GLOBALS['wp_query']   = $query;

			woocommerce_product_loop_start();

			while ( $query->have_posts() ) {
				$query->the_post();
				wc_get_template_part( 'content', 'product' );
			}

			woocommerce_product_loop_end();

			if ( null !== $original_query ) {
				$GLOBALS['wp_query'] = $original_query;
			}
		} else {
			wc_get_template( 'loop/no-products-found.php' );
		}

		return (string) ob_get_clean();
	}
}
