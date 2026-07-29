<?php
/**
 * Stock status filter: in stock, out of stock, backorder, low stock.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Filters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Filters products by WooCommerce's `_stock_status` meta and, for "low
 * stock", by remaining `_stock` quantity against the store's low-stock
 * threshold.
 */
final class StockFilterType extends AbstractFilterType {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'stock';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_options( array $filter, array $query_context ): array {
		$threshold = (int) get_option( 'woocommerce_notify_low_stock_amount', 2 );

		$definitions = array(
			'instock'    => array(
				'label' => __( 'In Stock', 'mab-commerce-filters' ),
				'meta'  => array( 'key' => '_stock_status', 'value' => 'instock', 'compare' => '=' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			),
			'outofstock' => array(
				'label' => __( 'Out of Stock', 'mab-commerce-filters' ),
				'meta'  => array( 'key' => '_stock_status', 'value' => 'outofstock', 'compare' => '=' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			),
			'onbackorder' => array(
				'label' => __( 'Backorder', 'mab-commerce-filters' ),
				'meta'  => array( 'key' => '_stock_status', 'value' => 'onbackorder', 'compare' => '=' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			),
			'lowstock'   => array(
				'label' => __( 'Low Stock', 'mab-commerce-filters' ),
				'meta'  => array(
					'relation' => 'AND',
					array( 'key' => '_stock', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					array( 'key' => '_stock', 'value' => $threshold, 'compare' => '<=', 'type' => 'NUMERIC' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				),
			),
		);

		$options = array();

		foreach ( $definitions as $value => $definition ) {
			$count = $this->count_products( $query_context, array( 'meta_query' => $definition['meta'] ) );

			$options[] = array(
				'value' => $value,
				'label' => $definition['label'],
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

		$html = '<ul class="mabcf-option-list mabcf-option-list--stock">';

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

		$threshold = (int) get_option( 'woocommerce_notify_low_stock_amount', 2 );
		$clauses   = array( 'relation' => 'OR' );

		foreach ( $values as $value ) {
			if ( 'lowstock' === $value ) {
				$clauses[] = array(
					'relation' => 'AND',
					array( 'key' => '_stock', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					array( 'key' => '_stock', 'value' => $threshold, 'compare' => '<=', 'type' => 'NUMERIC' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				);
			} else {
				$clauses[] = array( 'key' => '_stock_status', 'value' => sanitize_key( $value ), 'compare' => '=' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			}
		}

		$args['meta_query']   = $args['meta_query'] ?? array(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		$args['meta_query'][] = $clauses;
	}
}
