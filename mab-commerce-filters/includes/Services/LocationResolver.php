<?php
/**
 * Resolves which filter set(s) apply to the current front-end request.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Services;

use MABCommerceFilters\Repositories\FilterSetRepository;
use MABCommerceFilters\Repositories\LocationRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Matches the current WordPress query (shop page, category/tag/brand
 * archive, specific page, or an explicit shortcode/template key) against
 * the locations table to find the applicable filter set.
 */
final class LocationResolver {

	/**
	 * Location repository.
	 *
	 * @var LocationRepository
	 */
	private LocationRepository $locations;

	/**
	 * Filter set repository.
	 *
	 * @var FilterSetRepository
	 */
	private FilterSetRepository $sets;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->locations = new LocationRepository();
		$this->sets      = new FilterSetRepository();
	}

	/**
	 * Returns the highest priority active filter set matching the current
	 * request context, or null when none matches.
	 *
	 * @return array<string, mixed>|null
	 */
	public function resolve_current(): ?array {
		foreach ( $this->current_location_candidates() as [ $type, $value ] ) {
			$ids = $this->locations->find_filter_set_ids( $type, $value );

			$set = $this->first_active( $ids );

			if ( $set ) {
				return $set;
			}
		}

		return null;
	}

	/**
	 * Resolves the filter set explicitly assigned to a shortcode key or
	 * Elementor widget "filter set" selection.
	 *
	 * @param int|string $set_identifier Filter set ID or slug.
	 * @return array<string, mixed>|null
	 */
	public function resolve_explicit( $set_identifier ): ?array {
		if ( is_numeric( $set_identifier ) ) {
			$set = $this->sets->find( (int) $set_identifier );

			if ( $set && 'active' === $set['status'] ) {
				return $set;
			}
		}

		return $this->sets->find_by_slug( (string) $set_identifier );
	}

	/**
	 * Builds an ordered list of [type, value] location candidates for the
	 * current request, most specific first.
	 *
	 * @return array<int, array{0: string, 1: string}>
	 */
	private function current_location_candidates(): array {
		$context = $this->current_context();

		return array(
			array( $context['type'], $context['value'] ),
			array( 'global', '' ),
		);
	}

	/**
	 * Describes the current request's most specific archive/page context:
	 * type (page|category|tag|brand|shop|global), taxonomy (when
	 * applicable) and value (term slug or page ID). Used both to resolve
	 * location assignments and to rebuild the same base product query
	 * from an AJAX request that has no access to the original main query.
	 *
	 * @return array{type: string, taxonomy: string, value: string}
	 */
	public function current_context(): array {
		if ( is_page() ) {
			return array( 'type' => 'page', 'taxonomy' => '', 'value' => (string) get_the_ID() );
		}

		if ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) {
			$term = get_queried_object();

			if ( $term instanceof \WP_Term ) {
				$taxonomy_type = 'product_cat' === $term->taxonomy ? 'category' : ( 'product_tag' === $term->taxonomy ? 'tag' : 'brand' );

				return array( 'type' => $taxonomy_type, 'taxonomy' => $term->taxonomy, 'value' => $term->slug );
			}
		}

		if ( function_exists( 'is_shop' ) && is_shop() ) {
			return array( 'type' => 'shop', 'taxonomy' => '', 'value' => '' );
		}

		return array( 'type' => 'global', 'taxonomy' => '', 'value' => '' );
	}

	/**
	 * Builds the base WP_Query args (post_type/status plus, for an
	 * archive context, a locking tax_query) for a given context array as
	 * produced by current_context().
	 *
	 * @param array{type: string, taxonomy: string, value: string} $context Context descriptor.
	 * @return array<string, mixed>
	 */
	public function base_args_for_context( array $context ): array {
		$args = array(
			'post_type'   => 'product',
			'post_status' => 'publish',
		);

		if ( in_array( $context['type'], array( 'category', 'tag', 'brand' ), true ) && ! empty( $context['value'] ) ) {
			$taxonomy = $context['taxonomy'] ?: ( 'category' === $context['type'] ? 'product_cat' : ( 'tag' === $context['type'] ? 'product_tag' : 'product_brand' ) );

			$args['tax_query'] = array(
				array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => array( $context['value'] ),
				),
			);
		}

		return $args;
	}

	/**
	 * Returns the first active filter set among a list of IDs (already in
	 * priority order from the repository).
	 *
	 * @param int[] $ids Filter set IDs.
	 * @return array<string, mixed>|null
	 */
	private function first_active( array $ids ): ?array {
		foreach ( $ids as $id ) {
			$set = $this->sets->find( $id );

			if ( $set && 'active' === $set['status'] ) {
				return $set;
			}
		}

		return null;
	}
}
