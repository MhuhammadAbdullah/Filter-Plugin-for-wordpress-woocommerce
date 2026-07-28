<?php
/**
 * Computes live term counts and the price range for the currently
 * matched product set, without ever pulling large ID lists into PHP.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * For each section, "how many products would remain if I also picked
 * this option" needs to be computed against every *other* active filter
 * (so users can keep narrowing down instead of hitting dead ends). To
 * stay fast on catalogs with 100,000+ products, the matched product set
 * is never materialised as a PHP array: `WP_Query` is asked to build the
 * SQL for the ID lookup, and that raw SQL is reused as a subquery inside
 * a single aggregate `COUNT()` — the join runs entirely inside MySQL.
 */
final class APF_Facet_Counts {

	/**
	 * Returns product counts per term for a taxonomy, given the filters
	 * that should remain applied (i.e. every filter except this section's
	 * own taxonomy).
	 *
	 * @param string                $taxonomy       Taxonomy slug.
	 * @param array<string, mixed>  $context_filters Normalised filters with this taxonomy already removed.
	 * @param array<string, mixed>  $extra_args      Extra fixed WP_Query args (e.g. a category archive restriction).
	 * @return array<int, int> Term ID => product count.
	 */
	public static function get_term_counts( string $taxonomy, array $context_filters, array $extra_args = array() ): array {
		$cache_key = 'facet_' . md5( $taxonomy . wp_json_encode( $context_filters ) . wp_json_encode( $extra_args ) );
		$cached    = APF_Cache::get( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		$ids_sql = self::matched_ids_sql( $context_filters, $extra_args );

		if ( null === $ids_sql ) {
			return array();
		}

		// Escape any literal `%` in the embedded subquery so `$wpdb->prepare()`
		// below does not mistake it for one of its own format specifiers.
		$ids_sql = str_replace( '%', '%%', $ids_sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $ids_sql is fully escaped WP_Query SQL, taxonomy below is passed as a bound parameter.
		$sql = "SELECT tt.term_id AS term_id, COUNT(DISTINCT tr.object_id) AS product_count
				FROM {$wpdb->term_relationships} tr
				INNER JOIN ( {$ids_sql} ) AS matched_products ON tr.object_id = matched_products.ID
				INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				WHERE tt.taxonomy = %s
				GROUP BY tt.term_id";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $taxonomy ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$counts = array();

		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row['term_id'] ] = (int) $row['product_count'];
		}

		APF_Cache::set( $cache_key, $counts );

		return $counts;
	}

	/**
	 * Returns the [min, max] product price across the currently matched
	 * product set (filters applied, excluding the price filter itself),
	 * used to size the dual-handle price slider.
	 *
	 * @param array<string, mixed> $context_filters Normalised filters with price removed.
	 * @param array<string, mixed> $extra_args      Extra fixed WP_Query args.
	 * @return array{0: float, 1: float}
	 */
	public static function get_price_range( array $context_filters, array $extra_args = array() ): array {
		$cache_key = 'price_range_' . md5( wp_json_encode( $context_filters ) . wp_json_encode( $extra_args ) );
		$cached    = APF_Cache::get( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		$ids_sql = self::matched_ids_sql( $context_filters, $extra_args );

		if ( null === $ids_sql ) {
			return array( 0.0, 0.0 );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $ids_sql is fully escaped WP_Query SQL.
		$sql = "SELECT MIN(CAST(pm.meta_value AS DECIMAL(10,2))) AS min_price, MAX(CAST(pm.meta_value AS DECIMAL(10,2))) AS max_price
				FROM {$wpdb->postmeta} pm
				INNER JOIN ( {$ids_sql} ) AS matched_products ON pm.post_id = matched_products.ID
				WHERE pm.meta_key = '_price' AND pm.meta_value != ''";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row( $sql, ARRAY_A );

		$range = array(
			isset( $row['min_price'] ) ? (float) $row['min_price'] : 0.0,
			isset( $row['max_price'] ) ? (float) $row['max_price'] : 0.0,
		);

		APF_Cache::set( $cache_key, $range );

		return $range;
	}

	/**
	 * Returns the total number of products matched by the given filters,
	 * used for the AJAX response's result count and "no products found"
	 * state.
	 *
	 * @param array<string, mixed> $filters    Normalised applied filters.
	 * @param array<string, mixed> $extra_args Extra fixed WP_Query args.
	 * @return int
	 */
	public static function get_total_matched( array $filters, array $extra_args = array() ): int {
		$args = APF_Query_Builder::build_query_args( $filters, $extra_args );

		$args['fields']         = 'ids';
		$args['posts_per_page'] = 1;
		$args['no_found_rows']  = false;
		unset( $args['paged'] );

		$query = new WP_Query( $args );

		return (int) $query->found_posts;
	}

	/**
	 * Builds the raw, fully-escaped SQL for the set of product IDs
	 * matching the given filters, suitable for use as a subquery.
	 *
	 * @param array<string, mixed> $filters    Normalised applied filters.
	 * @param array<string, mixed> $extra_args Extra fixed WP_Query args.
	 * @return string|null Raw SQL selecting a single `ID` column, or null when it could not be built.
	 */
	private static function matched_ids_sql( array $filters, array $extra_args ): ?string {
		$args = APF_Query_Builder::build_query_args( $filters, $extra_args );

		$args['fields']         = 'ids';
		$args['posts_per_page'] = -1;
		$args['no_found_rows']  = true;
		$args['cache_results']  = false;
		unset( $args['paged'] );

		// `posts_pre_query` short-circuits WP_Query before it hits the
		// database, but the SQL it *would* have run is still compiled and
		// left on `$query->request` — reusing that string as a subquery
		// keeps this a single, index-friendly join instead of
		// materialising every matched product ID into PHP memory.
		add_filter( 'posts_pre_query', '__return_empty_array', 9999 );
		$query = new WP_Query( $args );
		remove_filter( 'posts_pre_query', '__return_empty_array', 9999 );

		if ( empty( $query->request ) ) {
			return null;
		}

		global $wpdb;

		// The compiled request selects every column WP_Query normally
		// needs; narrow it down to just the ID column for the subquery.
		return preg_replace( '/^SELECT\s+.*?\s+FROM/is', "SELECT {$wpdb->posts}.ID FROM", $query->request, 1 );
	}
}
