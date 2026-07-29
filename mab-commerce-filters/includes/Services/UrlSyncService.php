<?php
/**
 * Maps filter selections to/from clean, human-readable query string params.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * For taxonomy-based filters (category, tag, brand, attribute) and price,
 * produces query vars matching WooCommerce/community convention
 * (`product_cat`, `product_tag`, `filter_{attribute}`, `min_price`,
 * `max_price`) so URLs stay short and shareable. Every other filter type
 * falls back to a generic `mabcf_filter[id]` query var, which is what the
 * AJAX engine also reads back on request.
 */
final class UrlSyncService {

	/**
	 * Resolves the public query var name for a filter row.
	 *
	 * @param array<string, mixed> $filter Filter row.
	 */
	public function query_var_for( array $filter ): string {
		switch ( $filter['type'] ) {
			case 'category':
				return 'product_cat';
			case 'tag':
				return 'product_tag';
			case 'brand':
				return (string) ( $filter['source_key'] ?: 'product_brand' );
			case 'attribute':
				return 'filter_' . preg_replace( '/^pa_/', '', (string) $filter['source_key'] );
			case 'price':
				return 'price';
			default:
				return 'mabcf_filter_' . (int) $filter['id'];
		}
	}

	/**
	 * Builds the full set of query args representing the current
	 * selection, ready for add_query_arg()/history.pushState().
	 *
	 * @param array<int, array<string, mixed>> $filter_rows Filters in the set.
	 * @param array<int, mixed>                $selection   Selected values keyed by filter ID.
	 * @return array<string, string>
	 */
	public function to_query_args( array $filter_rows, array $selection ): array {
		$args = array();

		foreach ( $filter_rows as $filter ) {
			$id = (int) $filter['id'];

			if ( empty( $selection[ $id ] ) ) {
				continue;
			}

			$value = $selection[ $id ];
			$var   = $this->query_var_for( $filter );

			if ( 'price' === $filter['type'] && is_array( $value ) ) {
				if ( isset( $value['min'] ) ) {
					$args['min_price'] = (string) $value['min'];
				}
				if ( isset( $value['max'] ) ) {
					$args['max_price'] = (string) $value['max'];
				}
				continue;
			}

			if ( is_array( $value ) && isset( $value['value'] ) ) {
				$value = $value['value'];
			}

			if ( is_array( $value ) ) {
				$value = implode( ',', array_map( 'strval', $value ) );
			}

			if ( '' !== (string) $value ) {
				$args[ $var ] = (string) $value;
			}
		}

		return $args;
	}

	/**
	 * Reverse of to_query_args(): reads $_GET-shaped request vars back
	 * into a per-filter-ID selection array, so a shared/bookmarked URL
	 * (or the no-JS form fallback) restores the same filter state.
	 *
	 * @param array<int, array<string, mixed>> $filter_rows Filters in the set.
	 * @param array<string, mixed>             $request     Request vars, e.g. $_GET.
	 * @return array<int, mixed>
	 */
	public function from_request( array $filter_rows, array $request ): array {
		$selection = array();

		if ( isset( $request['mabcf_filter'] ) && is_array( $request['mabcf_filter'] ) ) {
			foreach ( $request['mabcf_filter'] as $id => $value ) {
				$selection[ (int) $id ] = $value;
			}
		}

		foreach ( $filter_rows as $filter ) {
			$id = (int) $filter['id'];

			if ( isset( $selection[ $id ] ) ) {
				continue;
			}

			if ( 'price' === $filter['type'] ) {
				if ( isset( $request['min_price'] ) || isset( $request['max_price'] ) ) {
					$selection[ $id ] = array_filter(
						array(
							'min' => isset( $request['min_price'] ) ? (float) $request['min_price'] : null,
							'max' => isset( $request['max_price'] ) ? (float) $request['max_price'] : null,
						),
						static fn( $v ) => null !== $v
					);
				}
				continue;
			}

			$var = $this->query_var_for( $filter );

			if ( ! isset( $request[ $var ] ) || '' === $request[ $var ] ) {
				continue;
			}

			$raw = sanitize_text_field( wp_unslash( $request[ $var ] ) );

			$selection[ $id ] = 'search' === $filter['type']
				? array( 'value' => $raw )
				: array( 'value' => explode( ',', $raw ) );
		}

		return $selection;
	}
}
