<?php
/**
 * Shared helpers for concrete filter type implementations.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Filters;

use MABCommerceFilters\Interfaces\FilterTypeInterface;
use MABCommerceFilters\Services\CacheService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base class providing option counting, escaping and markup helpers reused
 * by every concrete filter type.
 */
abstract class AbstractFilterType implements FilterTypeInterface {

	/**
	 * Cache service used to memoise expensive product counts.
	 *
	 * @var CacheService
	 */
	protected CacheService $cache;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->cache = new CacheService();
	}

	/**
	 * Human readable label for admin UI selects.
	 */
	public function get_label(): string {
		return ucfirst( $this->get_type() );
	}

	/**
	 * Reads a per-filter setting with a default fallback.
	 *
	 * @param array<string, mixed> $filter  Filter row.
	 * @param string                $key     Setting key.
	 * @param mixed                 $default Default value.
	 * @return mixed
	 */
	protected function setting( array $filter, string $key, $default = null ) {
		$settings = $filter['settings'] ?? array();

		return is_array( $settings ) && array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * Counts published, purchasable products matching the base query
	 * context plus one additional WP_Query args overlay. Results are
	 * cached per request context to keep facet rendering fast.
	 *
	 * @param array<string, mixed> $query_context Base args (tax_query, meta_query, ...).
	 * @param array<string, mixed> $overlay       Extra args merged on top (e.g. one extra tax_query clause).
	 */
	protected function count_products( array $query_context, array $overlay = array() ): int {
		$args = $this->build_count_args( $query_context, $overlay );

		$cache_key = $this->cache->make_key( 'count', $args );
		$cached    = $this->cache->get( $cache_key );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		$query = new \WP_Query( $args );
		$count = (int) $query->found_posts;

		$this->cache->set( $cache_key, $count );

		return $count;
	}

	/**
	 * Merges a base query context with an overlay of extra query pieces,
	 * producing final WP_Query args suitable for a count-only query.
	 *
	 * @param array<string, mixed> $query_context Base args.
	 * @param array<string, mixed> $overlay       Extra args.
	 * @return array<string, mixed>
	 */
	protected function build_count_args( array $query_context, array $overlay = array() ): array {
		$args = array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'fields'                 => 'ids',
			'posts_per_page'         => 1,
			'no_found_rows'          => false,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		$args = array_merge( $args, $query_context );

		if ( ! empty( $overlay['tax_query'] ) ) {
			$args['tax_query']   = $args['tax_query'] ?? array();
			$args['tax_query'][] = $overlay['tax_query'];
			if ( count( $args['tax_query'] ) > 1 && ! isset( $args['tax_query']['relation'] ) ) {
				$args['tax_query']['relation'] = 'AND';
			}
		}

		if ( ! empty( $overlay['meta_query'] ) ) {
			$args['meta_query']   = $args['meta_query'] ?? array();
			$args['meta_query'][] = $overlay['meta_query'];
			if ( count( $args['meta_query'] ) > 1 && ! isset( $args['meta_query']['relation'] ) ) {
				$args['meta_query']['relation'] = 'AND';
			}
		}

		foreach ( array( 'meta_key', 'orderby', 'order' ) as $passthrough ) {
			if ( isset( $overlay[ $passthrough ] ) ) {
				$args[ $passthrough ] = $overlay[ $passthrough ];
			}
		}

		return $args;
	}

	/**
	 * Builds the shared accordion wrapper markup used by every filter type.
	 *
	 * @param array<string, mixed> $filter Filter row.
	 * @param string                 $inner  Inner control HTML, already escaped.
	 */
	protected function wrap( array $filter, string $inner ): string {
		$id       = 'mabcf-filter-' . (int) $filter['id'];
		$type     = sanitize_html_class( $filter['type'] );
		$label    = esc_html( $filter['label'] );
		$collapsible = $this->setting( $filter, 'collapsible', true ) ? ' mabcf-filter--collapsible' : '';

		return sprintf(
			'<div class="mabcf-filter mabcf-filter--%1$s%2$s" data-filter-id="%3$d" data-filter-type="%1$s" data-source="%4$s">
				<button type="button" class="mabcf-filter__toggle" aria-expanded="true" aria-controls="%5$s">
					<span class="mabcf-filter__label">%6$s</span>
					<span class="mabcf-filter__icon" aria-hidden="true"></span>
				</button>
				<div class="mabcf-filter__body" id="%5$s">%7$s</div>
			</div>',
			esc_attr( $type ),
			$collapsible, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			(int) $filter['id'],
			esc_attr( $filter['source_key'] ?? '' ),
			esc_attr( $id ),
			$label, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$inner // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	/**
	 * Normalises a selected request value into a flat string array.
	 *
	 * Selection reaches filter types in two different shapes depending on
	 * where the request came from, and this must accept both:
	 *
	 * - Raw checkbox POST (AJAX/admin-ajax): `mabcf_filter[12][]=a&...`
	 *   parses to a plain, 0-indexed array — `['a', 'b']`.
	 * - Query-string driven selection (`Services\UrlSyncService::from_request()`,
	 *   used for the initial page load, the Elementor widgets' own
	 *   server-side render, and WooCommerce main-query integration on
	 *   shared/bookmarked URLs or after a browser back/forward reload) —
	 *   wrapped as `['value' => ['a', 'b']]`.
	 *
	 * Both `apply_query()` (query building) and `render()` (checked-state)
	 * call this, so keeping it shape-agnostic here is what keeps those two
	 * code paths — and every entry point that feeds them — consistent.
	 *
	 * @param mixed $selected Raw selected value(s), in either shape above.
	 * @return string[]
	 */
	protected function to_array( $selected ): array {
		if ( is_array( $selected ) ) {
			if ( array_key_exists( 'value', $selected ) ) {
				return $this->to_array( $selected['value'] );
			}

			$values = array();

			foreach ( $selected as $item ) {
				if ( is_scalar( $item ) ) {
					$values[] = (string) $item;
				}
			}

			return $values;
		}

		if ( '' === $selected || null === $selected ) {
			return array();
		}

		return array_filter( array_map( 'trim', explode( ',', (string) $selected ) ), 'strlen' );
	}
}
