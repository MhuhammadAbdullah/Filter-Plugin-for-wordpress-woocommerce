<?php
/**
 * Free-text product search filter (title/SKU/content).
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Filters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Narrows the product query using WP_Query's native `s` search parameter,
 * optionally widened to also match SKU via a `posts_where` filter.
 */
final class SearchFilterType extends AbstractFilterType {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'search';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_options( array $filter, array $query_context ): array {
		return array();
	}

	/**
	 * {@inheritDoc}
	 */
	public function render( array $filter, array $query_context, array $selected ): string {
		$value       = isset( $selected['value'] ) ? (string) $selected['value'] : '';
		$placeholder = (string) $this->setting( $filter, 'placeholder', __( 'Search products…', 'mab-commerce-filters' ) );

		$html = sprintf(
			'<div class="mabcf-search">
				<input type="search" class="mabcf-search__input" name="mabcf_filter[%1$d][value]" value="%2$s" placeholder="%3$s" autocomplete="off">
				<span class="mabcf-search__icon" aria-hidden="true"></span>
			</div>',
			(int) $filter['id'],
			esc_attr( $value ),
			esc_attr( $placeholder )
		);

		return $this->wrap( $filter, $html );
	}

	/**
	 * {@inheritDoc}
	 */
	public function apply_query( array $filter, array &$args, $selected ): void {
		$term = is_array( $selected ) ? ( $selected['value'] ?? '' ) : $selected;
		$term = trim( (string) $term );

		if ( '' === $term ) {
			return;
		}

		$args['s'] = $term;

		if ( (bool) $this->setting( $filter, 'search_sku', true ) ) {
			$args['mabcf_search_sku'] = $term;
		}
	}
}
