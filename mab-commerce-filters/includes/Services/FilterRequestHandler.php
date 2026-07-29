<?php
/**
 * Shared request-handling logic reused by the AJAX and REST controllers.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Services;

use MABCommerceFilters\Frontend\ProductGridRenderer;
use MABCommerceFilters\Helpers\Helper;
use MABCommerceFilters\Repositories\FilterRepository;
use MABCommerceFilters\Repositories\FilterSetRepository;
use MABCommerceFilters\Repositories\LogRepository;
use MABCommerceFilters\Repositories\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Given a filter_set_id, a raw `mabcf_filter` selection payload, a
 * context descriptor and pagination/ordering, builds and executes the
 * product query and returns rendered facets + grid + pushState query
 * args. Used by both admin-ajax and the REST API so both transports stay
 * behaviourally identical.
 */
final class FilterRequestHandler {

	/**
	 * Filter query service.
	 *
	 * @var FilterQueryService
	 */
	private FilterQueryService $query_service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->query_service = new FilterQueryService();
	}

	/**
	 * Handles a filter request.
	 *
	 * @param int                   $filter_set_id Filter set ID.
	 * @param array<string, mixed> $raw_selection Raw `mabcf_filter` array from the request.
	 * @param array<string, mixed> $context       ['type' => ..., 'taxonomy' => ..., 'value' => ...].
	 * @param int                   $paged         Requested page number.
	 * @param string                $orderby       Requested ordering key.
	 * @return array{ok: bool, error?: string, data?: array<string, mixed>}
	 */
	public function handle( int $filter_set_id, array $raw_selection, array $context, int $paged, string $orderby ): array {
		$sets = new FilterSetRepository();
		$set  = $filter_set_id ? $sets->find( $filter_set_id ) : null;

		if ( ! $set || 'active' !== $set['status'] ) {
			return array( 'ok' => false, 'error' => __( 'This filter set is no longer available.', 'mab-commerce-filters' ) );
		}

		$filters  = ( new FilterRepository() )->get_for_set( (int) $set['id'] );
		$url_sync = new UrlSyncService();
		$resolver = new LocationResolver();
		$settings = new SettingsRepository();

		$context = array_merge( array( 'type' => 'global', 'taxonomy' => '', 'value' => '' ), $context );
		$base_args = $resolver->base_args_for_context( $context );

		$selection = $this->query_service->parse_selection( Helper::sanitize_recursive( $raw_selection ) );

		$per_page = (int) $settings->get( 'products_per_page', 12 );
		$args     = $this->query_service->build_query_args( $filters, $selection, $base_args );

		$args['paged']          = max( 1, $paged );
		$args['posts_per_page'] = $per_page > 0 ? $per_page : 12;

		$this->apply_ordering( $args, $orderby );

		/** This filter is documented in includes/Ajax/FilterAjaxController.php. */
		$args = apply_filters( 'mabcf_ajax_query_args', $args, $set, $selection );

		$query = $this->query_service->run( $args );

		$grid_options = array(
			'columns'         => (int) ( $set['settings']['columns'] ?? 4 ),
			'show_sorting'    => ! empty( $set['settings']['show_sorting'] ),
			'show_pagination' => empty( $set['settings']['infinite_scroll'] ),
			'infinite_scroll' => ! empty( $set['settings']['infinite_scroll'] ),
		);

		$grid_html    = ( new ProductGridRenderer() )->render( $query, $grid_options );
		$filters_html = $this->query_service->render_filters( $filters, $selection, $base_args );
		$active_html  = ( new \MABCommerceFilters\Frontend\Renderer() )->render_active_bar( $filters, $selection );
		$query_args   = $url_sync->to_query_args( $filters, $selection );

		if ( '1' === (string) $settings->get( 'debug_mode', '0' ) ) {
			( new LogRepository() )->log( 'ajax', 'Filter request handled', array( 'args' => $args, 'selection' => $selection ), 'info' );
		}

		return array(
			'ok'   => true,
			'data' => array(
				'filters_html' => $filters_html,
				'active_html'  => $active_html,
				'grid_html'    => $grid_html,
				'found_posts'  => (int) $query->found_posts,
				'max_pages'    => (int) $query->max_num_pages,
				'paged'        => max( 1, $paged ),
				'query_args'   => $query_args,
			),
		);
	}

	/**
	 * Applies a WooCommerce-compatible ordering to the query args.
	 *
	 * @param array<string, mixed> $args    Query args, passed by reference.
	 * @param string                $orderby Requested ordering key.
	 */
	private function apply_ordering( array &$args, string $orderby ): void {
		switch ( $orderby ) {
			case 'popularity':
				$args['meta_key'] = 'total_sales'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'DESC';
				break;
			case 'rating':
				$args['meta_key'] = '_wc_average_rating'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'DESC';
				break;
			case 'date':
				$args['orderby'] = 'date';
				$args['order']   = 'DESC';
				break;
			case 'price':
				$args['meta_key'] = '_price'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'ASC';
				break;
			case 'price-desc':
				$args['meta_key'] = '_price'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'DESC';
				break;
			default:
				$args['orderby'] = 'menu_order title';
				$args['order']   = 'ASC';
				break;
		}
	}
}
