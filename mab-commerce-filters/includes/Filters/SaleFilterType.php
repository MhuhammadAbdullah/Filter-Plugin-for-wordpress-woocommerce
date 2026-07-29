<?php
/**
 * Merchandising filter: sale, featured, new arrivals, best selling,
 * top rated, most viewed and recently viewed products.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Filters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Each option narrows the product query using the most efficient signal
 * WooCommerce/core already maintains for it (product_visibility terms,
 * post_date, `total_sales` meta, average rating meta, or the plugin's own
 * lightweight view counter).
 */
final class SaleFilterType extends AbstractFilterType {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'sale';
	}

	/**
	 * Returns the fixed set of merchandising options enabled for a filter.
	 *
	 * @param array<string, mixed> $filter Filter row.
	 * @return string[]
	 */
	private function enabled_options( array $filter ): array {
		$all = array( 'sale', 'featured', 'new', 'bestselling', 'toprated', 'mostviewed', 'recentlyviewed' );

		$enabled = $this->setting( $filter, 'options', $all );

		return array_values( array_intersect( $all, is_array( $enabled ) ? $enabled : $all ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_options( array $filter, array $query_context ): array {
		$labels = array(
			'sale'           => __( 'On Sale', 'mab-commerce-filters' ),
			'featured'       => __( 'Featured', 'mab-commerce-filters' ),
			'new'            => __( 'New Arrivals', 'mab-commerce-filters' ),
			'bestselling'    => __( 'Best Selling', 'mab-commerce-filters' ),
			'toprated'       => __( 'Top Rated', 'mab-commerce-filters' ),
			'mostviewed'     => __( 'Most Viewed', 'mab-commerce-filters' ),
			'recentlyviewed' => __( 'Recently Viewed', 'mab-commerce-filters' ),
		);

		$options = array();

		foreach ( $this->enabled_options( $filter ) as $key ) {
			$overlay = $this->overlay_for( $key, $filter );
			$count   = null === $overlay ? 0 : $this->count_products( $query_context, $overlay );

			$options[] = array(
				'value' => $key,
				'label' => $labels[ $key ],
				'count' => $count,
			);
		}

		return $options;
	}

	/**
	 * Builds the count-overlay (tax_query/meta_query/post__in) for one
	 * merchandising option.
	 *
	 * @param string                $key    Option key.
	 * @param array<string, mixed> $filter Filter row.
	 * @return array<string, mixed>|null
	 */
	private function overlay_for( string $key, array $filter ): ?array {
		switch ( $key ) {
			case 'sale':
				$ids = wc_get_product_ids_on_sale();
				return array( 'post__in' => $ids ?: array( 0 ) );

			case 'featured':
				return array(
					'tax_query' => array(
						'taxonomy' => 'product_visibility',
						'field'    => 'name',
						'terms'    => array( 'featured' ),
					),
				);

			case 'new':
				$days = (int) $this->setting( $filter, 'new_days', 30 );
				return array( 'date_after' => gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) ) );

			case 'bestselling':
				return array(
					'meta_query' => array( 'key' => 'total_sales', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				);

			case 'toprated':
				return array(
					'meta_query' => array( 'key' => '_wc_average_rating', 'value' => '4', 'compare' => '>=', 'type' => 'DECIMAL(3,2)' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				);

			case 'mostviewed':
				return array(
					'meta_query' => array( 'key' => '_mabcf_view_count', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				);

			case 'recentlyviewed':
				$ids = $this->recently_viewed_ids();
				return array( 'post__in' => $ids ?: array( 0 ) );
		}

		return null;
	}

	/**
	 * Reads the woocommerce_recently_viewed cookie into an ID array.
	 *
	 * @return int[]
	 */
	private function recently_viewed_ids(): array {
		if ( empty( $_COOKIE['woocommerce_recently_viewed'] ) ) {
			return array();
		}

		$raw = sanitize_text_field( wp_unslash( $_COOKIE['woocommerce_recently_viewed'] ) );

		return array_filter( array_map( 'absint', explode( '|', $raw ) ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function render( array $filter, array $query_context, array $selected ): string {
		$options      = $this->get_options( $filter, $query_context );
		$selected_set = array_flip( $this->to_array( $selected ) );

		if ( ! $options ) {
			return '';
		}

		$html = '<ul class="mabcf-option-list mabcf-option-list--sale">';

		foreach ( $options as $option ) {
			$is_selected = isset( $selected_set[ $option['value'] ] );

			$html .= sprintf(
				'<li class="mabcf-option" data-value="%1$s">
					<label class="mabcf-option__label">
						<input type="checkbox" name="mabcf_filter[%2$d][]" value="%1$s" %3$s>
						<span class="mabcf-option__name">%4$s</span>
						<span class="mabcf-option__count">%5$d</span>
					</label>
				</li>',
				esc_attr( $option['value'] ),
				(int) $filter['id'],
				checked( $is_selected, true, false ),
				esc_html( $option['label'] ),
				(int) $option['count']
			);
		}

		$html .= '</ul>';

		return $this->wrap( $filter, $html );
	}

	/**
	 * {@inheritDoc}
	 */
	public function apply_query( array $filter, array &$args, $selected ): void {
		$values = $this->to_array( $selected );

		if ( ! $values ) {
			return;
		}

		$post_in_sets = array();

		foreach ( $values as $value ) {
			$overlay = $this->overlay_for( $value, $filter );

			if ( null === $overlay ) {
				continue;
			}

			if ( isset( $overlay['tax_query'] ) ) {
				$args['tax_query']   = $args['tax_query'] ?? array();
				$args['tax_query'][] = $overlay['tax_query'];
			}

			if ( isset( $overlay['meta_query'] ) ) {
				$args['meta_query']   = $args['meta_query'] ?? array(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				$args['meta_query'][] = $overlay['meta_query'];
			}

			if ( isset( $overlay['date_after'] ) ) {
				$args['date_query']   = $args['date_query'] ?? array();
				$args['date_query'][] = array( 'after' => $overlay['date_after'], 'inclusive' => true );
			}

			if ( isset( $overlay['post__in'] ) ) {
				$post_in_sets[] = $overlay['post__in'];
			}
		}

		if ( $post_in_sets ) {
			$merged             = count( $post_in_sets ) > 1 ? array_unique( array_merge( ...$post_in_sets ) ) : $post_in_sets[0];
			$args['post__in']   = isset( $args['post__in'] ) ? array_intersect( $args['post__in'], $merged ) : $merged;
			$args['post__in']   = $args['post__in'] ?: array( 0 );
		}
	}
}
