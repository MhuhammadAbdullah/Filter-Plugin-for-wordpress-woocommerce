<?php
/**
 * Contract implemented by every filter type (category, price, attribute...).
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A filter type knows how to render itself and how to narrow a product
 * query based on the current request values for its source key.
 */
interface FilterTypeInterface {

	/**
	 * Machine name of this filter type, e.g. "category", "price", "attribute".
	 */
	public function get_type(): string;

	/**
	 * Builds the list of selectable options (or bounds, for range filters)
	 * for the given filter configuration.
	 *
	 * @param array<string, mixed> $filter        Filter row (label, source_key, settings, ...).
	 * @param array<string, mixed> $query_context Base tax_query/meta_query/args used for counting.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_options( array $filter, array $query_context ): array;

	/**
	 * Renders the filter's frontend HTML.
	 *
	 * @param array<string, mixed> $filter        Filter row.
	 * @param array<string, mixed> $query_context Base query context, used for live counts.
	 * @param array<string, mixed> $selected      Currently selected request values for this filter.
	 */
	public function render( array $filter, array $query_context, array $selected ): string;

	/**
	 * Mutates WP_Query args (tax_query / meta_query / price bounds) to apply
	 * the currently selected values for this filter.
	 *
	 * @param array<string, mixed> $filter   Filter row.
	 * @param array<string, mixed> $args     WP_Query args being built, passed by reference.
	 * @param mixed                 $selected Selected request value(s) for this filter.
	 */
	public function apply_query( array $filter, array &$args, $selected ): void;
}
