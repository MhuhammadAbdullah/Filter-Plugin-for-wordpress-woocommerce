<?php
/**
 * Translates request parameters into WooCommerce-compatible query args.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Owns the single source of truth for how an "applied filters" array
 * becomes `tax_query` / `meta_query` / `date_query` arguments, and hooks
 * into `pre_get_posts` so the very first (non-AJAX) page load already
 * reflects whatever is in the URL — keeping archive URLs bookmarkable,
 * crawlable and back-button friendly.
 */
final class APF_Query_Builder {

	/**
	 * Query string prefix used for every filter parameter this plugin owns.
	 *
	 * @var string
	 */
	public const PREFIX = 'apf_';

	/**
	 * Wires the `pre_get_posts` integration for SEO-friendly, server
	 * rendered archive filtering (in addition to the AJAX endpoint).
	 */
	public function __construct() {
		add_action( 'pre_get_posts', array( $this, 'apply_to_main_query' ) );
	}

	/**
	 * Applies the current request's filters to the main shop/archive query.
	 *
	 * @param WP_Query $query The query being executed.
	 * @return void
	 */
	public function apply_to_main_query( WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( ! function_exists( 'is_shop' ) || ! ( is_shop() || is_product_taxonomy() ) ) {
			return;
		}

		$filter_set = APF_Filter_Sets::get_for_current_context();

		if ( ! $filter_set ) {
			return;
		}

		$filters = self::parse_request( $_GET, $filter_set ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$this->apply_filters_to_query_args( $query, $filters );

		if ( ! empty( $filters['products_per_page'] ) ) {
			$query->set( 'posts_per_page', $filters['products_per_page'] );
		}
	}

	/**
	 * Parses raw request parameters (from `$_GET` or a decoded AJAX
	 * payload) into a normalised "applied filters" structure.
	 *
	 * @param array<string, mixed> $source     Raw request parameters.
	 * @param APF_Filter_Set|null  $filter_set Active Filter Set, used to know which taxonomies are filterable.
	 * @return array<string, mixed>
	 */
	public static function parse_request( array $source, ?APF_Filter_Set $filter_set = null ): array {
		$filters = array(
			'tax'    => array(),
			'price'  => array(
				'min' => isset( $source['apf_price_min'] ) ? (float) $source['apf_price_min'] : null,
				'max' => isset( $source['apf_price_max'] ) ? (float) $source['apf_price_max'] : null,
			),
			'rating'   => isset( $source['apf_rating'] ) ? absint( $source['apf_rating'] ) : 0,
			'stock'    => isset( $source['apf_stock'] ) ? self::split_csv( $source['apf_stock'] ) : array(),
			'sale'     => ! empty( $source['apf_sale'] ),
			'featured' => ! empty( $source['apf_featured'] ),
			'new'      => ! empty( $source['apf_new'] ),
			'paged'    => isset( $source['paged'] ) ? max( 1, absint( $source['paged'] ) ) : 1,
			'orderby'  => isset( $source['orderby'] ) ? sanitize_key( $source['orderby'] ) : '',
			'products_per_page' => 0,
		);

		foreach ( self::filterable_taxonomies( $filter_set ) as $taxonomy => $param ) {
			if ( isset( $source[ $param ] ) && '' !== $source[ $param ] ) {
				$slugs = is_array( $source[ $param ] ) ? array_map( 'sanitize_title', $source[ $param ] ) : self::split_csv( $source[ $param ] );

				if ( ! empty( $slugs ) ) {
					$filters['tax'][ $taxonomy ] = $slugs;
				}
			}
		}

		return $filters;
	}

	/**
	 * Maps each filterable taxonomy to the query-string parameter name it
	 * is read from on the front end.
	 *
	 * @param APF_Filter_Set|null $filter_set Active Filter Set.
	 * @return array<string, string> Taxonomy slug => query parameter name.
	 */
	public static function filterable_taxonomies( ?APF_Filter_Set $filter_set = null ): array {
		$map = array(
			'product_cat' => 'apf_cat',
			'product_tag' => 'apf_tag',
		);

		$brand = APF_Taxonomies::get_brand_taxonomy();

		if ( $brand ) {
			$map[ $brand ] = 'apf_brand';
		}

		foreach ( APF_Taxonomies::get_attribute_taxonomies() as $taxonomy => $label ) {
			$map[ $taxonomy ] = 'apf_attr_' . str_replace( 'pa_', '', $taxonomy );
		}

		foreach ( APF_Taxonomies::get_custom_taxonomies() as $taxonomy => $label ) {
			$map[ $taxonomy ] = 'apf_tax_' . $taxonomy;
		}

		return $map;
	}

	/**
	 * Builds the WP_Query arguments (tax_query, meta_query, date_query, ...)
	 * for a full, standalone product query — used by the AJAX handler and
	 * the Elementor Product Grid widget.
	 *
	 * @param array<string, mixed> $filters Normalised applied filters.
	 * @param array<string, mixed> $extra   Extra WP_Query args to merge in (e.g. a fixed category restriction).
	 * @return array<string, mixed>
	 */
	public static function build_query_args( array $filters, array $extra = array() ): array {
		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'ignore_sticky_posts' => true,
			'paged'          => $filters['paged'] ?? 1,
			'tax_query'      => array( 'relation' => 'AND' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			'meta_query'     => array( 'relation' => 'AND' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		);

		foreach ( $filters['tax'] as $taxonomy => $slugs ) {
			if ( empty( $slugs ) ) {
				continue;
			}

			$args['tax_query'][] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => $slugs,
				'operator' => 'IN',
			);
		}

		if ( null !== ( $filters['price']['min'] ?? null ) || null !== ( $filters['price']['max'] ?? null ) ) {
			$args['meta_query'][] = self::price_meta_query( $filters['price']['min'] ?? null, $filters['price']['max'] ?? null );
		}

		if ( ! empty( $filters['rating'] ) ) {
			$args['meta_query'][] = array(
				'key'     => '_wc_average_rating',
				'value'   => $filters['rating'],
				'compare' => '>=',
				'type'    => 'DECIMAL(10,2)',
			);
		}

		if ( ! empty( $filters['stock'] ) ) {
			$args['meta_query'][] = array(
				'key'     => '_stock_status',
				'value'   => array_intersect( $filters['stock'], array( 'instock', 'outofstock', 'onbackorder' ) ),
				'compare' => 'IN',
			);
		}

		if ( ! empty( $filters['featured'] ) ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'product_visibility',
				'field'    => 'name',
				'terms'    => 'featured',
			);
		}

		if ( ! empty( $filters['sale'] ) && function_exists( 'wc_get_product_ids_on_sale' ) ) {
			$on_sale = wc_get_product_ids_on_sale();
			$args['post__in'] = ! empty( $args['post__in'] ) ? array_intersect( $args['post__in'], $on_sale ) : ( empty( $on_sale ) ? array( 0 ) : $on_sale );
		}

		if ( ! empty( $filters['new'] ) ) {
			$days = (int) APF_Settings::get( 'new_arrival_days', 30 );

			$args['date_query'] = array(
				array(
					'column' => 'post_date',
					'after'  => gmdate( 'Y-m-d H:i:s', time() - ( DAY_IN_SECONDS * max( 1, $days ) ) ),
				),
			);
		}

		if ( ! empty( $filters['orderby'] ) ) {
			$args = array_merge( $args, self::orderby_args( $filters['orderby'] ) );
		}

		// Merge in the fixed context restriction (e.g. "must belong to this
		// category archive") before the array_merge below, so its tax_query /
		// meta_query clauses are combined with — never overwritten by — the
		// clauses already built from the applied filters.
		if ( ! empty( $extra['tax_query'] ) ) {
			$args['tax_query'] = array_merge( $args['tax_query'], $extra['tax_query'] );
			unset( $extra['tax_query'] );
		}

		if ( ! empty( $extra['meta_query'] ) ) {
			$args['meta_query'] = array_merge( $args['meta_query'], $extra['meta_query'] );
			unset( $extra['meta_query'] );
		}

		if ( 1 === count( $args['tax_query'] ) ) {
			unset( $args['tax_query'] );
		}

		if ( 1 === count( $args['meta_query'] ) ) {
			unset( $args['meta_query'] );
		}

		return array_merge( $args, $extra );
	}

	/**
	 * Applies the built query args directly onto a `WP_Query` instance
	 * (used from the `pre_get_posts` hook, where we must mutate rather
	 * than replace the query object).
	 *
	 * @param WP_Query             $query   Query instance to mutate.
	 * @param array<string, mixed> $filters Normalised applied filters.
	 * @return void
	 */
	private function apply_filters_to_query_args( WP_Query $query, array $filters ): void {
		$args = self::build_query_args( $filters );

		unset( $args['post_type'], $args['post_status'], $args['paged'], $args['ignore_sticky_posts'] );

		foreach ( array( 'tax_query', 'meta_query', 'date_query', 'post__in', 'orderby', 'order', 'meta_key' ) as $key ) {
			if ( ! isset( $args[ $key ] ) ) {
				continue;
			}

			if ( 'tax_query' === $key ) {
				$existing              = (array) $query->get( 'tax_query' );
				$query->set( 'tax_query', array_merge( $existing, $args[ $key ] ) );
				continue;
			}

			if ( 'meta_query' === $key ) {
				$existing              = (array) $query->get( 'meta_query' );
				$query->set( 'meta_query', array_merge( $existing, $args[ $key ] ) );
				continue;
			}

			if ( 'post__in' === $key && $query->get( 'post__in' ) ) {
				$query->set( 'post__in', array_intersect( (array) $query->get( 'post__in' ), $args[ $key ] ) );
				continue;
			}

			$query->set( $key, $args[ $key ] );
		}
	}

	/**
	 * Builds the price range `meta_query` clause.
	 *
	 * @param float|null $min Minimum price, inclusive.
	 * @param float|null $max Maximum price, inclusive.
	 * @return array<string, mixed>
	 */
	private static function price_meta_query( ?float $min, ?float $max ): array {
		if ( null !== $min && null !== $max ) {
			return array(
				'key'     => '_price',
				'value'   => array( $min, $max ),
				'compare' => 'BETWEEN',
				'type'    => 'DECIMAL(10,2)',
			);
		}

		if ( null !== $min ) {
			return array(
				'key'     => '_price',
				'value'   => $min,
				'compare' => '>=',
				'type'    => 'DECIMAL(10,2)',
			);
		}

		return array(
			'key'     => '_price',
			'value'   => $max,
			'compare' => '<=',
			'type'    => 'DECIMAL(10,2)',
		);
	}

	/**
	 * Translates a sort key coming from the front end into WP_Query args.
	 *
	 * @param string $orderby Sort key (`price`, `price-desc`, `rating`, `date`, `popularity`).
	 * @return array<string, mixed>
	 */
	private static function orderby_args( string $orderby ): array {
		switch ( $orderby ) {
			case 'price':
				return array(
					'orderby'  => 'meta_value_num',
					'order'    => 'ASC',
					'meta_key' => '_price', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				);

			case 'price-desc':
				return array(
					'orderby'  => 'meta_value_num',
					'order'    => 'DESC',
					'meta_key' => '_price', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				);

			case 'rating':
				return array(
					'orderby'  => 'meta_value_num',
					'order'    => 'DESC',
					'meta_key' => '_wc_average_rating', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				);

			case 'popularity':
				return array(
					'orderby'  => 'meta_value_num',
					'order'    => 'DESC',
					'meta_key' => 'total_sales', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				);

			case 'date':
				return array(
					'orderby' => 'date',
					'order'   => 'DESC',
				);

			default:
				return array();
		}
	}

	/**
	 * Splits a comma separated request value into a clean array of slugs.
	 *
	 * @param string|array $value Raw request value.
	 * @return string[]
	 */
	private static function split_csv( $value ): array {
		if ( is_array( $value ) ) {
			return array_map( 'sanitize_title', $value );
		}

		$parts = explode( ',', (string) $value );

		return array_values( array_filter( array_map( 'sanitize_title', $parts ) ) );
	}
}
