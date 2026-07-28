<?php
/**
 * Resolves the current front-end context and matches it against Filter Set
 * location rules.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * A "context" is a snapshot of where the visitor currently is (the shop
 * page, a category archive, a specific page, ...). `APF_Filter_Sets`
 * compares every Filter Set's location rules against this snapshot to
 * decide which one should render.
 */
final class APF_Context {

	/**
	 * Builds a snapshot of the current front-end request.
	 *
	 * @return array<string, mixed>
	 */
	public static function current(): array {
		$context = array(
			'is_shop'       => function_exists( 'is_shop' ) && is_shop(),
			'is_product'    => is_singular( 'product' ),
			'page_id'       => is_page() ? get_queried_object_id() : 0,
			'taxonomy'      => '',
			'term_id'       => 0,
			'term_ancestors' => array(),
			'elementor_template_id' => self::current_elementor_template_id(),
		);

		if ( is_tax() || is_category() || is_tag() ) {
			$queried = get_queried_object();

			if ( $queried instanceof WP_Term ) {
				$context['taxonomy']       = $queried->taxonomy;
				$context['term_id']        = $queried->term_id;
				$context['term_ancestors'] = get_ancestors( $queried->term_id, $queried->taxonomy, 'taxonomy' );
			}
		}

		return $context;
	}

	/**
	 * Scores how specifically a set of location rules matches a context.
	 *
	 * Returns -1 when nothing matches. Higher scores represent a more
	 * specific match (an exact term match outranks a "whole taxonomy"
	 * style catch-all, which in turn outranks the global shop fallback).
	 *
	 * @param array<int, array<string, mixed>> $locations Location rules.
	 * @param array<string, mixed>             $context   Context snapshot from self::current().
	 * @return int
	 */
	public static function match_score( array $locations, array $context ): int {
		$score = -1;

		foreach ( $locations as $location ) {
			$type = $location['type'] ?? '';

			switch ( $type ) {
				case 'shop':
					if ( $context['is_shop'] ) {
						$score = max( $score, 10 );
					}
					break;

				case 'all_archives':
					if ( $context['is_shop'] || '' !== $context['taxonomy'] ) {
						$score = max( $score, 5 );
					}
					break;

				case 'product_cat':
				case 'product_tag':
				case 'brand':
				case 'taxonomy':
					$taxonomy = 'taxonomy' === $type ? ( $location['taxonomy'] ?? '' ) : self::type_to_taxonomy( $type );

					if ( $taxonomy && $context['taxonomy'] === $taxonomy ) {
						$ids = array_map( 'absint', $location['ids'] ?? array() );

						if ( empty( $ids ) ) {
							$score = max( $score, 20 );
						} elseif ( in_array( $context['term_id'], $ids, true ) ) {
							$score = max( $score, 40 );
						} elseif ( array_intersect( $ids, $context['term_ancestors'] ) ) {
							$score = max( $score, 30 );
						}
					}
					break;

				case 'page':
					$ids = array_map( 'absint', $location['ids'] ?? array() );

					if ( $context['page_id'] && in_array( $context['page_id'], $ids, true ) ) {
						$score = max( $score, 50 );
					}
					break;

				case 'elementor_template':
					$ids = array_map( 'absint', $location['ids'] ?? array() );

					if ( $context['elementor_template_id'] && in_array( $context['elementor_template_id'], $ids, true ) ) {
						$score = max( $score, 50 );
					}
					break;
			}
		}

		return $score;
	}

	/**
	 * Builds a fixed `tax_query` restriction for the taxonomy archive
	 * currently being viewed (a category, tag, brand or attribute term
	 * page), so facet counts and the AJAX-filtered grid only ever
	 * consider products that belong to that archive.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_archive_restriction(): array {
		if ( ! is_tax() && ! is_category() && ! is_tag() ) {
			return array();
		}

		$queried = get_queried_object();

		if ( ! $queried instanceof WP_Term ) {
			return array();
		}

		return array(
			'tax_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => $queried->taxonomy,
					'field'    => 'term_id',
					'terms'    => array( $queried->term_id ),
				),
			),
		);
	}

	/**
	 * Maps a simplified location "type" to its backing taxonomy slug.
	 *
	 * @param string $type Location type (`product_cat`, `product_tag`, `brand`).
	 * @return string
	 */
	private static function type_to_taxonomy( string $type ): string {
		if ( 'brand' === $type ) {
			return (string) APF_Taxonomies::get_brand_taxonomy();
		}

		return $type;
	}

	/**
	 * Attempts to detect the Elementor template currently being rendered,
	 * covering both direct template previews and Elementor Theme Builder
	 * locations (header, footer, single, archive) where Elementor exposes
	 * a "current document" while the location is being rendered.
	 *
	 * @return int
	 */
	private static function current_elementor_template_id(): int {
		if ( is_singular( 'elementor_library' ) ) {
			return get_queried_object_id();
		}

		if ( did_action( 'elementor/loaded' ) && class_exists( '\Elementor\Plugin' ) ) {
			$documents = \Elementor\Plugin::$instance->documents ?? null;
			$current   = $documents ? $documents->get_current() : null;

			if ( $current && is_object( $current ) && method_exists( $current, 'get_id' ) ) {
				return (int) $current->get_id();
			}
		}

		return 0;
	}
}
