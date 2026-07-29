<?php
/**
 * Star rating filter (5, 4+, 3+, 2+, 1+) with live product counts.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Filters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Filters products by WooCommerce's `_wc_average_rating` product meta.
 */
final class RatingFilterType extends AbstractFilterType {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'rating';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_options( array $filter, array $query_context ): array {
		$options = array();

		for ( $stars = 5; $stars >= 1; $stars-- ) {
			$count = $this->count_products(
				$query_context,
				array(
					'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						'key'     => '_wc_average_rating', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
						'value'   => (string) $stars,
						'compare' => '>=',
						'type'    => 'DECIMAL(3,2)',
					),
				)
			);

			$options[] = array(
				'value' => (string) $stars,
				'label' => 5 === $stars
					? __( '5 Stars', 'mab-commerce-filters' )
					/* translators: %d: minimum star rating. */
					: sprintf( __( '%d+ Stars', 'mab-commerce-filters' ), $stars ),
				'stars' => $stars,
				'count' => $count,
			);
		}

		return $options;
	}

	/**
	 * {@inheritDoc}
	 */
	public function render( array $filter, array $query_context, array $selected ): string {
		$options      = $this->get_options( $filter, $query_context );
		$selected_set = array_flip( $this->to_array( $selected['value'] ?? array() ) );

		$html = '<ul class="mabcf-option-list mabcf-option-list--rating">';

		foreach ( $options as $option ) {
			$is_selected = isset( $selected_set[ $option['value'] ] );
			$stars_html  = str_repeat( '<span class="mabcf-star mabcf-star--full"></span>', $option['stars'] ) . str_repeat( '<span class="mabcf-star mabcf-star--empty"></span>', 5 - $option['stars'] );

			$html .= sprintf(
				'<li class="mabcf-option" data-value="%1$s">
					<label class="mabcf-option__label">
						<input type="checkbox" name="mabcf_filter[%2$d][]" value="%1$s" %3$s>
						<span class="mabcf-stars">%4$s</span>
						<span class="mabcf-option__name">%5$s</span>
						<span class="mabcf-option__count">%6$d</span>
					</label>
				</li>',
				esc_attr( $option['value'] ),
				(int) $filter['id'],
				checked( $is_selected, true, false ),
				$stars_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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

		$min_rating = min( array_map( 'intval', $values ) );

		$args['meta_query']   = $args['meta_query'] ?? array(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		$args['meta_query'][] = array(
			'key'     => '_wc_average_rating', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'value'   => (string) $min_rating,
			'compare' => '>=',
			'type'    => 'DECIMAL(3,2)',
		);
	}
}
