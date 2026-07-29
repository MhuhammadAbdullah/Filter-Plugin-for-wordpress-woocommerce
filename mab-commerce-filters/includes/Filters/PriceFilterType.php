<?php
/**
 * Dual-handle price range slider filter.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Filters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Computes the min/max price bounds across the currently filtered product
 * set (excluding the price filter's own selection) and renders/applies a
 * dual-handle range slider against the `_price` product meta.
 */
final class PriceFilterType extends AbstractFilterType {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'price';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_options( array $filter, array $query_context ): array {
		$cache_key = $this->cache->make_key( 'price_bounds', $query_context );
		$cached    = $this->cache->get( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$base = $this->build_count_args( $query_context );
		unset( $base['fields'], $base['posts_per_page'], $base['no_found_rows'] );

		$min = $this->edge_price( $base, 'ASC' );
		$max = $this->edge_price( $base, 'DESC' );

		if ( $min > $max ) {
			[ $min, $max ] = array( 0, 0 );
		}

		$bounds = array(
			'min' => $min,
			'max' => $max,
		);

		$this->cache->set( $cache_key, $bounds );

		return $bounds;
	}

	/**
	 * Finds the lowest or highest `_price` among matching products.
	 *
	 * @param array<string, mixed> $base  Shared WP_Query args (tax_query/meta_query etc).
	 * @param string                $order ASC for min, DESC for max.
	 */
	private function edge_price( array $base, string $order ): float {
		$args = array_merge(
			$base,
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'orderby'        => 'meta_value_num',
				'order'          => $order,
				'meta_key'       => '_price', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_query'     => array_merge( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					$base['meta_query'] ?? array(),
					array(
						array(
							'key'     => '_price',
							'value'   => '',
							'compare' => '!=',
						),
					)
				),
			)
		);

		$query = new \WP_Query( $args );

		if ( empty( $query->posts ) ) {
			return 0.0;
		}

		return (float) get_post_meta( (int) $query->posts[0], '_price', true );
	}

	/**
	 * {@inheritDoc}
	 */
	public function render( array $filter, array $query_context, array $selected ): string {
		$bounds = $this->get_options( $filter, $query_context );
		$min    = floor( $bounds['min'] );
		$max    = ceil( $bounds['max'] );

		if ( $max <= $min ) {
			$max = $min + 1;
		}

		$current_min = isset( $selected['min'] ) ? (float) $selected['min'] : $min;
		$current_max = isset( $selected['max'] ) ? (float) $selected['max'] : $max;
		$current_min = max( $min, min( $current_min, $max ) );
		$current_max = min( $max, max( $current_max, $min ) );

		$symbol      = get_woocommerce_currency_symbol();
		$apply       = (bool) $this->setting( $filter, 'apply_button', false );
		$step        = (float) $this->setting( $filter, 'step', 1 );

		$html = sprintf(
			'<div class="mabcf-price-slider" data-min="%1$s" data-max="%2$s" data-step="%3$s" data-current-min="%4$s" data-current-max="%5$s" data-filter-id="%6$d">
				<div class="mabcf-price-slider__track">
					<div class="mabcf-price-slider__range"></div>
					<span class="mabcf-price-slider__handle mabcf-price-slider__handle--min" tabindex="0" role="slider" aria-valuemin="%1$s" aria-valuemax="%2$s" aria-valuenow="%4$s"></span>
					<span class="mabcf-price-slider__handle mabcf-price-slider__handle--max" tabindex="0" role="slider" aria-valuemin="%1$s" aria-valuemax="%2$s" aria-valuenow="%5$s"></span>
				</div>
				<input type="hidden" name="mabcf_filter[%6$d][min]" class="mabcf-price-slider__input-min" value="%4$s">
				<input type="hidden" name="mabcf_filter[%6$d][max]" class="mabcf-price-slider__input-max" value="%5$s">
				<div class="mabcf-price-slider__labels">
					<span class="mabcf-price-slider__text">%7$s <span class="mabcf-price-slider__from">%8$s%4$s</span> — <span class="mabcf-price-slider__to">%8$s%5$s</span></span>
					%9$s
				</div>
			</div>',
			esc_attr( (string) $min ),
			esc_attr( (string) $max ),
			esc_attr( (string) $step ),
			esc_attr( (string) $current_min ),
			esc_attr( (string) $current_max ),
			(int) $filter['id'],
			esc_html__( 'Price:', 'mab-commerce-filters' ),
			esc_html( $symbol ),
			$apply ? '<button type="button" class="mabcf-price-slider__apply mabcf-button mabcf-button--sm">' . esc_html__( 'Filter', 'mab-commerce-filters' ) . '</button>' : ''
		);

		return $this->wrap( $filter, $html );
	}

	/**
	 * {@inheritDoc}
	 */
	public function apply_query( array $filter, array &$args, $selected ): void {
		if ( ! is_array( $selected ) || ( ! isset( $selected['min'] ) && ! isset( $selected['max'] ) ) ) {
			return;
		}

		$clause = array( 'key' => '_price', 'type' => 'DECIMAL(10,2)' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key

		if ( isset( $selected['min'], $selected['max'] ) ) {
			$clause['value']   = array( (float) $selected['min'], (float) $selected['max'] );
			$clause['compare'] = 'BETWEEN';
		} elseif ( isset( $selected['min'] ) ) {
			$clause['value']   = (float) $selected['min'];
			$clause['compare'] = '>=';
		} else {
			$clause['value']   = (float) $selected['max'];
			$clause['compare'] = '<=';
		}

		$args['meta_query']   = $args['meta_query'] ?? array(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		$args['meta_query'][] = $clause;
	}
}
