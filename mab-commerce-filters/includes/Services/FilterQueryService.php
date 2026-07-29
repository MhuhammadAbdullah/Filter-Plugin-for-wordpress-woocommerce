<?php
/**
 * Builds and executes the WooCommerce product query for a filter set.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Services;

use MABCommerceFilters\Filters\FilterTypeRegistry;
use MABCommerceFilters\Repositories\FilterRepository;
use MABCommerceFilters\Repositories\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Translates a set of active filters + the current request selections
 * into WP_Query args, executes the query, and renders each filter's
 * facet HTML using live (filtered) product counts.
 */
final class FilterQueryService {

	/**
	 * Filter repository.
	 *
	 * @var FilterRepository
	 */
	private FilterRepository $filters;

	/**
	 * Settings repository.
	 *
	 * @var SettingsRepository
	 */
	private SettingsRepository $settings;

	/**
	 * Filter type registry.
	 *
	 * @var FilterTypeRegistry
	 */
	private FilterTypeRegistry $registry;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->filters  = new FilterRepository();
		$this->settings = new SettingsRepository();
		$this->registry = FilterTypeRegistry::instance();
	}

	/**
	 * Extracts selected values per filter ID from a raw request array
	 * (typically `$_GET['mabcf_filter']` or an equivalent AJAX payload).
	 *
	 * @param array<int|string, mixed> $raw Raw `mabcf_filter` request array.
	 * @return array<int, mixed>
	 */
	public function parse_selection( array $raw ): array {
		$selection = array();

		foreach ( $raw as $filter_id => $value ) {
			$selection[ (int) $filter_id ] = $value;
		}

		return $selection;
	}

	/**
	 * Builds the base WP_Query args (post_type/status plus a starting
	 * tax_query/meta_query) shared by count queries and the main query.
	 *
	 * @param array<string, mixed> $extra Extra base args (e.g. a category archive's own tax_query).
	 * @return array<string, mixed>
	 */
	public function base_args( array $extra = array() ): array {
		return array_merge(
			array(
				'post_type'   => 'product',
				'post_status' => 'publish',
			),
			$extra
		);
	}

	/**
	 * Applies every filter's currently selected values on top of base args,
	 * producing the args used for the final product query.
	 *
	 * @param array<int, array<string, mixed>> $filter_rows Filters belonging to the active set.
	 * @param array<int, mixed>                $selection   Selected values keyed by filter ID.
	 * @param array<string, mixed>             $base_args   Base WP_Query args.
	 * @return array<string, mixed>
	 */
	public function build_query_args( array $filter_rows, array $selection, array $base_args ): array {
		$args = $base_args;

		foreach ( $filter_rows as $filter ) {
			$id = (int) $filter['id'];

			if ( ! array_key_exists( $id, $selection ) ) {
				continue;
			}

			$type = $this->registry->get( $filter['type'] );

			if ( ! $type ) {
				continue;
			}

			$type->apply_query( $filter, $args, $selection[ $id ] );
		}

		if ( ! empty( $args['tax_query'] ) && count( $args['tax_query'] ) > 1 && ! isset( $args['tax_query']['relation'] ) ) {
			$args['tax_query']['relation'] = 'AND';
		}

		if ( ! empty( $args['meta_query'] ) && count( $args['meta_query'] ) > 1 && ! isset( $args['meta_query']['relation'] ) ) {
			$args['meta_query']['relation'] = 'AND';
		}

		if ( ! empty( $args['mabcf_search_sku'] ) ) {
			add_filter( 'posts_clauses', array( $this, 'widen_search_to_sku' ), 10, 2 );
		}

		return $args;
	}

	/**
	 * Widens a `s`-based search query to also match the `_sku` product
	 * meta, so search filters find products by SKU too. Joins postmeta
	 * once and ORs a SKU match into the existing title/content search
	 * group; if WordPress ever changes that group's exact SQL shape this
	 * degrades gracefully to a plain title/content search instead of
	 * corrupting the query.
	 *
	 * @param array<string, string> $clauses SQL clause pieces.
	 * @param \WP_Query              $query   Current query.
	 * @return array<string, string>
	 */
	public function widen_search_to_sku( array $clauses, \WP_Query $query ): array {
		global $wpdb;

		$term = $query->get( 'mabcf_search_sku' );

		if ( ! $term ) {
			return $clauses;
		}

		$like    = '%' . $wpdb->esc_like( $term ) . '%';
		$search  = "({$wpdb->posts}.post_title LIKE '" . esc_sql( $like ) . "'";
		$widened = "( ( {$wpdb->postmeta}.meta_value LIKE '" . esc_sql( $like ) . "' AND {$wpdb->postmeta}.meta_key = '_sku' ) OR {$wpdb->posts}.post_title LIKE '" . esc_sql( $like ) . "'";

		if ( str_contains( $clauses['where'], $search ) && ! str_contains( $clauses['join'], "{$wpdb->postmeta} " ) ) {
			$clauses['join']  .= " LEFT JOIN {$wpdb->postmeta} ON ( {$wpdb->postmeta}.post_id = {$wpdb->posts}.ID )";
			$clauses['where']  = str_replace( $search, $widened, $clauses['where'] );
			$clauses['groupby'] = $clauses['groupby'] ? $clauses['groupby'] : "{$wpdb->posts}.ID";
		}

		return $clauses;
	}

	/**
	 * Renders every filter belonging to a set, computing live counts based
	 * on all *other* filters' current selections.
	 *
	 * @param array<int, array<string, mixed>> $filter_rows Filters belonging to the active set.
	 * @param array<int, mixed>                $selection   Selected values keyed by filter ID.
	 * @param array<string, mixed>             $base_args   Base WP_Query args (archive context).
	 * @return string
	 */
	public function render_filters( array $filter_rows, array $selection, array $base_args ): string {
		$html = '';

		foreach ( $filter_rows as $filter ) {
			$id   = (int) $filter['id'];
			$type = $this->registry->get( $filter['type'] );

			if ( ! $type ) {
				continue;
			}

			$context_selection = $selection;
			unset( $context_selection[ $id ] );

			$context = $this->build_query_args( $filter_rows, $context_selection, $base_args );

			$html .= $type->render( $filter, $context, is_array( $selection[ $id ] ?? null ) ? $selection[ $id ] : array( 'value' => $selection[ $id ] ?? '' ) );
		}

		return $html;
	}

	/**
	 * Runs the final, fully-filtered product query.
	 *
	 * @param array<string, mixed> $args WP_Query args produced by build_query_args().
	 */
	public function run( array $args ): \WP_Query {
		$query = new \WP_Query( $args );

		remove_filter( 'posts_clauses', array( $this, 'widen_search_to_sku' ), 10 );

		return $query;
	}
}
