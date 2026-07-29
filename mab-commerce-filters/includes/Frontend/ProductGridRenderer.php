<?php
/**
 * Renders the filtered WooCommerce product grid, sorting bar and pagination.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reuses WooCommerce's own `content-product.php` template part for each
 * product so themes keep their normal card styling, and wraps the loop in
 * the plugin's grid/sorting/pagination chrome.
 */
final class ProductGridRenderer {

	/**
	 * Renders the product grid for a completed WP_Query.
	 *
	 * @param \WP_Query             $query   Executed product query.
	 * @param array<string, mixed>  $options Render options: columns, show_sorting, show_pagination, infinite_scroll.
	 */
	public function render( \WP_Query $query, array $options = array() ): string {
		$columns          = (int) ( $options['columns'] ?? 4 );
		$show_sorting     = ! empty( $options['show_sorting'] );
		$show_pagination  = ! empty( $options['show_pagination'] );
		$infinite_scroll  = ! empty( $options['infinite_scroll'] );

		ob_start();

		echo '<div class="mabcf-grid-wrap">';

		if ( $show_sorting ) {
			$this->render_sorting_bar( $query );
		}

		if ( $query->have_posts() ) {
			printf( '<ul class="mabcf-grid products columns-%d">', esc_attr( $columns ) );

			while ( $query->have_posts() ) {
				$query->the_post();
				global $product;

				if ( ! $product instanceof \WC_Product ) {
					$product = wc_get_product( get_the_ID() );
				}

				wc_get_template_part( 'content', 'product' );
			}

			echo '</ul>';

			if ( $show_pagination && ! $infinite_scroll ) {
				$this->render_pagination( $query );
			}

			if ( $infinite_scroll && $query->max_num_pages > 1 ) {
				printf(
					'<div class="mabcf-infinite-scroll" data-page="1" data-max-pages="%d"><span class="mabcf-spinner"></span></div>',
					(int) $query->max_num_pages
				);
			}
		} else {
			printf( '<p class="mabcf-no-products">%s</p>', esc_html__( 'No products were found matching your selection.', 'mab-commerce-filters' ) );
		}

		echo '</div>';

		wp_reset_postdata();

		return (string) ob_get_clean();
	}

	/**
	 * Renders the result-count + WooCommerce ordering dropdown.
	 *
	 * @param \WP_Query $query Executed product query.
	 */
	private function render_sorting_bar( \WP_Query $query ): void {
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : apply_filters( 'woocommerce_default_catalog_orderby', get_option( 'woocommerce_default_catalog_orderby', 'menu_order' ) );

		$options = function_exists( 'wc_get_catalog_ordering_args' ) ? apply_filters(
			'woocommerce_catalog_orderby',
			array(
				'menu_order' => __( 'Default sorting', 'mab-commerce-filters' ),
				'popularity' => __( 'Sort by popularity', 'mab-commerce-filters' ),
				'rating'     => __( 'Sort by average rating', 'mab-commerce-filters' ),
				'date'       => __( 'Sort by latest', 'mab-commerce-filters' ),
				'price'      => __( 'Sort by price: low to high', 'mab-commerce-filters' ),
				'price-desc' => __( 'Sort by price: high to low', 'mab-commerce-filters' ),
			)
		) : array();

		echo '<div class="mabcf-toolbar">';
		printf(
			'<p class="mabcf-toolbar__count">%s</p>',
			esc_html(
				sprintf(
					/* translators: %d: number of matching products. */
					_n( '%d product found', '%d products found', (int) $query->found_posts, 'mab-commerce-filters' ),
					(int) $query->found_posts
				)
			)
		);

		echo '<select class="mabcf-toolbar__orderby" name="orderby" aria-label="' . esc_attr__( 'Shop order', 'mab-commerce-filters' ) . '">';
		foreach ( $options as $id => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $id ), selected( $orderby, $id, false ), esc_html( $label ) );
		}
		echo '</select>';
		echo '</div>';
	}

	/**
	 * Renders numbered pagination for the current query.
	 *
	 * @param \WP_Query $query Executed product query.
	 */
	private function render_pagination( \WP_Query $query ): void {
		$paged = max( 1, (int) ( $query->get( 'paged' ) ?: 1 ) );

		/*
		 * This grid is rendered from two very different contexts: a normal
		 * page load (correct current URL) and an admin-ajax.php AJAX
		 * response (current URL would be admin-ajax.php itself). Either
		 * way, front-end JS intercepts every pagination click and reads
		 * the page number back out of the query string — it never
		 * actually navigates to these hrefs — so what matters is that the
		 * links reliably carry a `paged=N` query arg, not that they are
		 * "correct" URLs. Building the base from a placeholder integer
		 * (WordPress core's own paginate_links() idiom) guarantees a
		 * query-string link regardless of permalink structure, avoiding
		 * the default `/page/N/` path form that the JS wouldn't parse.
		 */
		$big  = 999999999;
		$base = str_replace( (string) $big, '%#%', esc_url( add_query_arg( 'paged', $big ) ) );

		$links = paginate_links(
			array(
				'base'      => $base,
				'format'    => '',
				'total'     => (int) $query->max_num_pages,
				'current'   => $paged,
				'type'      => 'array',
				'prev_text' => __( '&larr;', 'mab-commerce-filters' ),
				'next_text' => __( '&rarr;', 'mab-commerce-filters' ),
			)
		);

		if ( empty( $links ) ) {
			return;
		}

		echo '<nav class="mabcf-pagination" aria-label="' . esc_attr__( 'Products pagination', 'mab-commerce-filters' ) . '"><ul>';
		foreach ( $links as $link ) {
			echo '<li>' . wp_kses_post( $link ) . '</li>';
		}
		echo '</ul></nav>';
	}
}
