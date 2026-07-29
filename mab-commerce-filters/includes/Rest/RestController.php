<?php
/**
 * Public REST API for headless / decoupled front ends.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Rest;

use MABCommerceFilters\Repositories\FilterRepository;
use MABCommerceFilters\Repositories\FilterSetRepository;
use MABCommerceFilters\Services\FilterRequestHandler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers `mabcf/v1` REST routes: list filter sets, describe a set's
 * filters, and run a filtered product query. Read-only routes are public;
 * the query route is public too (it never exposes more than the shop
 * front end already would) but is rate-limited by WordPress core's
 * standard REST throttling plugins if installed.
 */
final class RestController {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	private const NAMESPACE = 'mabcf/v1';

	/**
	 * Registers the REST routes.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * Defines the routes.
	 */
	public function routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/filter-sets',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_sets' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/filter-sets/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_set' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array( 'validate_callback' => 'is_numeric' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/filter-sets/(?P<id>\d+)/query',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'query_set' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array( 'validate_callback' => 'is_numeric' ),
				),
			)
		);
	}

	/**
	 * GET /filter-sets — lists active filter sets.
	 */
	public function list_sets(): \WP_REST_Response {
		$sets = ( new FilterSetRepository() )->get_active();

		$payload = array_map(
			static fn( array $set ) => array(
				'id'     => (int) $set['id'],
				'name'   => $set['name'],
				'slug'   => $set['slug'],
				'layout' => $set['layout'],
			),
			$sets
		);

		return new \WP_REST_Response( $payload, 200 );
	}

	/**
	 * GET /filter-sets/{id} — describes a set's filters.
	 *
	 * @param \WP_REST_Request $request REST request.
	 */
	public function get_set( \WP_REST_Request $request ): \WP_REST_Response {
		$set = ( new FilterSetRepository() )->find( (int) $request['id'] );

		if ( ! $set || 'active' !== $set['status'] ) {
			return new \WP_REST_Response( array( 'message' => __( 'Filter set not found.', 'mab-commerce-filters' ) ), 404 );
		}

		$filters = ( new FilterRepository() )->get_for_set( (int) $set['id'] );

		return new \WP_REST_Response(
			array(
				'id'      => (int) $set['id'],
				'name'    => $set['name'],
				'slug'    => $set['slug'],
				'layout'  => $set['layout'],
				'filters' => array_map(
					static fn( array $filter ) => array(
						'id'            => (int) $filter['id'],
						'type'          => $filter['type'],
						'label'         => $filter['label'],
						'source_key'    => $filter['source_key'],
						'display_style' => $filter['display_style'],
					),
					$filters
				),
			),
			200
		);
	}

	/**
	 * POST /filter-sets/{id}/query — runs a filtered product query.
	 *
	 * @param \WP_REST_Request $request REST request.
	 */
	public function query_set( \WP_REST_Request $request ): \WP_REST_Response {
		$params  = $request->get_json_params() ?: $request->get_params();
		$context = is_array( $params['context'] ?? null ) ? $params['context'] : array();

		$result = ( new FilterRequestHandler() )->handle(
			(int) $request['id'],
			is_array( $params['mabcf_filter'] ?? null ) ? $params['mabcf_filter'] : array(),
			$context,
			(int) ( $params['paged'] ?? 1 ),
			(string) ( $params['orderby'] ?? 'menu_order' )
		);

		if ( ! $result['ok'] ) {
			return new \WP_REST_Response( array( 'message' => $result['error'] ), 404 );
		}

		return new \WP_REST_Response( $result['data'], 200 );
	}
}
