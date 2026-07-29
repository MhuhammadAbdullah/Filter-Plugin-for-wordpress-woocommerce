<?php
/**
 * Generic custom field / meta filter: text, number, boolean or date.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Filters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Filters products by an arbitrary post meta key (source_key), rendered
 * according to the filter's `field` setting: text, number (range),
 * boolean (toggle) or date (range).
 */
final class MetaFilterType extends AbstractFilterType {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'meta';
	}

	/**
	 * Resolves the `field` presentation: text|number|boolean|date.
	 *
	 * @param array<string, mixed> $filter Filter row.
	 */
	private function field( array $filter ): string {
		$field = $this->setting( $filter, 'field', $filter['type'] ?? 'text' );

		return in_array( $field, array( 'text', 'number', 'boolean', 'date' ), true ) ? $field : 'text';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_options( array $filter, array $query_context ): array {
		global $wpdb;

		$meta_key = $filter['source_key'] ?? '';

		if ( '' === $meta_key || 'text' !== $this->field( $filter ) ) {
			return array();
		}

		$cache_key = $this->cache->make_key( 'meta_values', $meta_key );
		$cached    = $this->cache->get( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$values = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = %s AND p.post_type = 'product' AND p.post_status = 'publish' AND pm.meta_value != ''
				ORDER BY pm.meta_value ASC LIMIT 200", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$meta_key
			)
		);

		$options = array();

		foreach ( $values ?: array() as $value ) {
			$options[] = array(
				'value' => $value,
				'label' => $value,
				'count' => $this->count_products( $query_context, array( 'meta_query' => array( 'key' => $meta_key, 'value' => $value, 'compare' => '=' ) ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			);
		}

		$this->cache->set( $cache_key, $options );

		return $options;
	}

	/**
	 * {@inheritDoc}
	 */
	public function render( array $filter, array $query_context, array $selected ): string {
		$field    = $this->field( $filter );
		$meta_key = $filter['source_key'] ?? '';

		if ( 'boolean' === $field ) {
			$checked_val = ! empty( $selected['value'] );

			return $this->wrap(
				$filter,
				sprintf(
					'<label class="mabcf-toggle">
						<input type="checkbox" name="mabcf_filter[%1$d][value]" value="1" %2$s>
						<span class="mabcf-toggle__track"><span class="mabcf-toggle__thumb"></span></span>
					</label>',
					(int) $filter['id'],
					checked( $checked_val, true, false )
				)
			);
		}

		if ( 'number' === $field ) {
			$min = isset( $selected['min'] ) ? esc_attr( $selected['min'] ) : '';
			$max = isset( $selected['max'] ) ? esc_attr( $selected['max'] ) : '';

			return $this->wrap(
				$filter,
				sprintf(
					'<div class="mabcf-range-inputs">
						<input type="number" name="mabcf_filter[%1$d][min]" placeholder="%2$s" value="%3$s">
						<span class="mabcf-range-inputs__sep">–</span>
						<input type="number" name="mabcf_filter[%1$d][max]" placeholder="%4$s" value="%5$s">
					</div>',
					(int) $filter['id'],
					esc_attr__( 'Min', 'mab-commerce-filters' ),
					$min,
					esc_attr__( 'Max', 'mab-commerce-filters' ),
					$max
				)
			);
		}

		if ( 'date' === $field ) {
			$from = isset( $selected['from'] ) ? esc_attr( $selected['from'] ) : '';
			$to   = isset( $selected['to'] ) ? esc_attr( $selected['to'] ) : '';

			return $this->wrap(
				$filter,
				sprintf(
					'<div class="mabcf-range-inputs mabcf-range-inputs--date">
						<input type="date" name="mabcf_filter[%1$d][from]" value="%2$s">
						<span class="mabcf-range-inputs__sep">–</span>
						<input type="date" name="mabcf_filter[%1$d][to]" value="%3$s">
					</div>',
					(int) $filter['id'],
					$from,
					$to
				)
			);
		}

		$options      = $this->get_options( $filter, $query_context );
		$selected_set = array_flip( $this->flatten_selected_values( $selected ) );
		$html         = '<ul class="mabcf-option-list">';

		foreach ( $options as $option ) {
			$html .= sprintf(
				'<li class="mabcf-option" data-value="%1$s">
					<label class="mabcf-option__label">
						<input type="checkbox" name="mabcf_filter[%2$d][][value]" value="%1$s" %3$s>
						<span class="mabcf-option__name">%4$s</span>
						<span class="mabcf-option__count">%5$d</span>
					</label>
				</li>',
				esc_attr( $option['value'] ),
				(int) $filter['id'],
				checked( isset( $selected_set[ $option['value'] ] ), true, false ),
				esc_html( $option['label'] ),
				(int) $option['count']
			);
		}

		$html .= '</ul>';

		return $this->wrap( $filter, $html );
	}

	/**
	 * Flattens a selected value into a plain string array, accepting both
	 * shapes this filter's selection can arrive in: a list of
	 * `['value' => x]` entries (posted from the checkbox list markup
	 * above) or a single `['value' => x|x[]]` wrapper (from the
	 * query-string fallback).
	 *
	 * @param mixed $selected Raw selected value.
	 * @return string[]
	 */
	private function flatten_selected_values( $selected ): array {
		if ( ! is_array( $selected ) ) {
			return array();
		}

		$values = array();

		foreach ( $selected as $key => $entry ) {
			if ( is_array( $entry ) && isset( $entry['value'] ) && ! is_array( $entry['value'] ) ) {
				$values[] = (string) $entry['value'];
			} elseif ( 'value' === $key && is_array( $entry ) ) {
				foreach ( $entry as $v ) {
					if ( ! is_array( $v ) ) {
						$values[] = (string) $v;
					}
				}
			} elseif ( 'value' === $key && ! is_array( $entry ) ) {
				$values[] = (string) $entry;
			}
		}

		return $values;
	}

	/**
	 * {@inheritDoc}
	 */
	public function apply_query( array $filter, array &$args, $selected ): void {
		$meta_key = $filter['source_key'] ?? '';

		if ( '' === $meta_key || ! is_array( $selected ) ) {
			return;
		}

		$field                = $this->field( $filter );
		$args['meta_query']   = $args['meta_query'] ?? array(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query

		if ( 'boolean' === $field && ! empty( $selected['value'] ) ) {
			$args['meta_query'][] = array( 'key' => $meta_key, 'value' => array( '1', 'yes', 'true' ), 'compare' => 'IN' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			return;
		}

		if ( 'number' === $field && ( isset( $selected['min'] ) || isset( $selected['max'] ) ) ) {
			if ( isset( $selected['min'], $selected['max'] ) ) {
				$args['meta_query'][] = array( 'key' => $meta_key, 'value' => array( (float) $selected['min'], (float) $selected['max'] ), 'compare' => 'BETWEEN', 'type' => 'NUMERIC' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			} elseif ( isset( $selected['min'] ) ) {
				$args['meta_query'][] = array( 'key' => $meta_key, 'value' => (float) $selected['min'], 'compare' => '>=', 'type' => 'NUMERIC' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			} else {
				$args['meta_query'][] = array( 'key' => $meta_key, 'value' => (float) $selected['max'], 'compare' => '<=', 'type' => 'NUMERIC' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			}
			return;
		}

		if ( 'date' === $field && ( isset( $selected['from'] ) || isset( $selected['to'] ) ) ) {
			if ( isset( $selected['from'], $selected['to'] ) ) {
				$args['meta_query'][] = array( 'key' => $meta_key, 'value' => array( $selected['from'], $selected['to'] ), 'compare' => 'BETWEEN', 'type' => 'DATE' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			} elseif ( isset( $selected['from'] ) ) {
				$args['meta_query'][] = array( 'key' => $meta_key, 'value' => $selected['from'], 'compare' => '>=', 'type' => 'DATE' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			} else {
				$args['meta_query'][] = array( 'key' => $meta_key, 'value' => $selected['to'], 'compare' => '<=', 'type' => 'DATE' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			}
			return;
		}

		$values = array();
		foreach ( $selected as $entry ) {
			if ( is_array( $entry ) && isset( $entry['value'] ) && '' !== $entry['value'] ) {
				$values[] = (string) $entry['value'];
			}
		}

		if ( $values ) {
			$args['meta_query'][] = array( 'key' => $meta_key, 'value' => $values, 'compare' => 'IN' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		}
	}
}
