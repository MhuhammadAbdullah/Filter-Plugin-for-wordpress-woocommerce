<?php
/**
 * Applies the active filter set to WooCommerce's own main archive query
 * and wraps the resulting loop so the AJAX engine can swap it in place.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Frontend;

use MABCommerceFilters\Repositories\FilterRepository;
use MABCommerceFilters\Services\FilterQueryService;
use MABCommerceFilters\Services\LocationResolver;
use MABCommerceFilters\Services\UrlSyncService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Works with any theme: on shop/category/tag archives it narrows
 * WooCommerce's own `pre_get_posts` query using the query-string
 * selection (so plain GET requests, back/forward and no-JS all work),
 * then wraps the rendered loop in a `.mabcf-products-target` container
 * that the front-end JS replaces after an AJAX filter request.
 */
final class MainQueryIntegration {

	/**
	 * Registers WordPress hooks.
	 */
	public function register(): void {
		add_action( 'pre_get_posts', array( $this, 'filter_main_query' ) );
		add_action( 'woocommerce_before_shop_loop', array( $this, 'open_wrapper' ), 1 );
		add_action( 'woocommerce_after_shop_loop', array( $this, 'close_wrapper' ), 100 );
		add_action( 'woocommerce_no_products_found', array( $this, 'open_wrapper' ), 1 );
		add_action( 'woocommerce_no_products_found', array( $this, 'close_wrapper' ), 100 );
	}

	/**
	 * Narrows the main WooCommerce archive query using the resolved
	 * filter set and the current request's query-string selection.
	 *
	 * @param \WP_Query $query The query being modified.
	 */
	public function filter_main_query( \WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( ! ( function_exists( 'is_shop' ) && ( is_shop() || ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) ) ) ) {
			return;
		}

		$resolver = new LocationResolver();
		$set      = $resolver->resolve_current();

		if ( ! $set ) {
			return;
		}

		$filters   = ( new FilterRepository() )->get_for_set( (int) $set['id'] );
		$url_sync  = new UrlSyncService();
		$selection = $url_sync->from_request( $filters, wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $selection ) {
			return;
		}

		$query_svc = new FilterQueryService();

		$existing_tax  = (array) $query->get( 'tax_query' );
		$existing_meta = (array) $query->get( 'meta_query' );

		$args = $query_svc->build_query_args( $filters, $selection, array( 'tax_query' => $existing_tax, 'meta_query' => $existing_meta ) );

		if ( ! empty( $args['tax_query'] ) ) {
			$query->set( 'tax_query', $args['tax_query'] );
		}

		if ( ! empty( $args['meta_query'] ) ) {
			$query->set( 'meta_query', $args['meta_query'] );
		}

		if ( ! empty( $args['post__in'] ) ) {
			$query->set( 'post__in', $args['post__in'] );
		}

		if ( ! empty( $args['s'] ) ) {
			$query->set( 's', $args['s'] );
		}
	}

	/**
	 * Opens the AJAX-swappable wrapper around the shop loop.
	 */
	public function open_wrapper(): void {
		$set = $this->resolved_set();

		if ( ! $set ) {
			return;
		}

		printf( '<div class="mabcf-products-target" data-mabcf-ajax-target="1" data-filter-set-id="%d">', (int) $set['id'] );
	}

	/**
	 * Closes the AJAX-swappable wrapper.
	 */
	public function close_wrapper(): void {
		if ( ! $this->resolved_set() ) {
			return;
		}

		echo '</div>';
	}

	/**
	 * Whether the current archive has an active filter set assigned, and
	 * which one — resolved once per request.
	 *
	 * @return array<string, mixed>|null
	 */
	private function resolved_set(): ?array {
		static $resolved = false;

		if ( false === $resolved ) {
			$resolved = ( new LocationResolver() )->resolve_current();
		}

		return $resolved;
	}
}
