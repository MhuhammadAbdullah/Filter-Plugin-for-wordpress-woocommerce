<?php
/**
 * REST API endpoints under the `apf/v1` namespace.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Exposes the same filtering logic used by `admin-ajax.php` as a proper
 * REST route (for headless/theme-JS consumers and as the primary
 * transport for the storefront JS), plus lightweight lookup endpoints
 * the admin builder uses to populate its taxonomy/page/template pickers
 * without shipping the entire catalog to the browser up front.
 */
final class APF_Rest_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	private const NAMESPACE = 'apf/v1';

	/**
	 * Shared query builder instance.
	 *
	 * @var APF_Query_Builder
	 */
	private APF_Query_Builder $query_builder;

	/**
	 * Registers the `rest_api_init` hook.
	 *
	 * @param APF_Query_Builder $query_builder Shared query builder.
	 */
	public function __construct( APF_Query_Builder $query_builder ) {
		$this->query_builder = $query_builder;

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers every route.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/filter',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'filter_products' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'filter_set_id' => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/terms',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_terms' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'taxonomy' => array(
						'type'     => 'string',
						'required' => true,
					),
					'search'   => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/pages',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_pages' ),
				'permission_callback' => array( $this, 'admin_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/elementor-templates',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_elementor_templates' ),
				'permission_callback' => array( $this, 'admin_permission' ),
			)
		);
	}

	/**
	 * Permission callback shared by every admin-only endpoint.
	 *
	 * @return bool
	 */
	public function admin_permission(): bool {
		return current_user_can( APF_Admin::CAPABILITY );
	}

	/**
	 * `GET /apf/v1/filter` — the primary storefront filtering endpoint.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function filter_products( WP_REST_Request $request ) {
		$filter_set_id = absint( $request->get_param( 'filter_set_id' ) );
		$filter_set    = $filter_set_id ? APF_Filter_Sets::get( $filter_set_id ) : APF_Filter_Sets::get_for_current_context();

		if ( ! $filter_set ) {
			return new WP_Error( 'apf_no_filter_set', __( 'No Filter Set is active for this page.', 'advanced-product-filters' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( APF_Ajax::build_response( $request->get_params(), $filter_set ) );
	}

	/**
	 * `GET /apf/v1/terms` — searchable term lookup used by the AJAX
	 * Search input type and the admin location picker.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_terms( WP_REST_Request $request ) {
		$taxonomy = sanitize_key( $request->get_param( 'taxonomy' ) );

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new WP_Error( 'apf_invalid_taxonomy', __( 'Unknown taxonomy.', 'advanced-product-filters' ), array( 'status' => 400 ) );
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'search'     => (string) $request->get_param( 'search' ),
				'number'     => 50,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return $terms;
		}

		return rest_ensure_response(
			array_map(
				static fn( WP_Term $term ): array => array(
					'id'    => $term->term_id,
					'name'  => $term->name,
					'slug'  => $term->slug,
					'count' => $term->count,
				),
				$terms
			)
		);
	}

	/**
	 * `GET /apf/v1/pages` — powers the "Specific Pages" location picker.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function get_pages( WP_REST_Request $request ) {
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				's'              => (string) $request->get_param( 'search' ),
				'posts_per_page' => 50,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		return rest_ensure_response(
			array_map(
				static fn( WP_Post $page ): array => array(
					'id'   => $page->ID,
					'name' => $page->post_title,
				),
				$pages
			)
		);
	}

	/**
	 * `GET /apf/v1/elementor-templates` — powers the "Specific Elementor
	 * Templates" location picker.
	 *
	 * @return WP_REST_Response
	 */
	public function get_elementor_templates() {
		if ( ! post_type_exists( 'elementor_library' ) ) {
			return rest_ensure_response( array() );
		}

		$templates = get_posts(
			array(
				'post_type'      => 'elementor_library',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		return rest_ensure_response(
			array_map(
				static fn( WP_Post $template ): array => array(
					'id'   => $template->ID,
					'name' => $template->post_title,
				),
				$templates
			)
		);
	}
}
