<?php
/**
 * Front-end AJAX filtering — the `admin-ajax.php` transport, sharing its
 * core logic with the REST controller so either transport can be used.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds the JSON payload a filter interaction needs: the re-rendered
 * product grid, the re-rendered sidebar (so facet counts stay accurate),
 * pagination info and the active filter count for the mobile toggle
 * badge. `APF_Rest_Controller` calls `build_response()` directly so both
 * transports stay perfectly in sync.
 */
final class APF_Ajax {

	/**
	 * Shared query builder instance.
	 *
	 * @var APF_Query_Builder
	 */
	private APF_Query_Builder $query_builder;

	/**
	 * Registers the `admin-ajax.php` hooks.
	 *
	 * @param APF_Query_Builder $query_builder Shared query builder.
	 */
	public function __construct( APF_Query_Builder $query_builder ) {
		$this->query_builder = $query_builder;

		add_action( 'wp_ajax_apf_filter_products', array( $this, 'handle_filter_products' ) );
		add_action( 'wp_ajax_nopriv_apf_filter_products', array( $this, 'handle_filter_products' ) );
	}

	/**
	 * `admin-ajax.php` entry point.
	 *
	 * @return void
	 */
	public function handle_filter_products(): void {
		$filter_set_id = isset( $_REQUEST['filter_set_id'] ) ? absint( $_REQUEST['filter_set_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$filter_set    = $filter_set_id ? APF_Filter_Sets::get( $filter_set_id ) : APF_Filter_Sets::get_for_current_context();

		if ( ! $filter_set ) {
			wp_send_json_error( array( 'message' => __( 'No Filter Set is active for this page.', 'advanced-product-filters' ) ), 404 );
		}

		wp_send_json_success( self::build_response( wp_unslash( $_REQUEST ), $filter_set ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
	}

	/**
	 * Builds the full filter response payload for a given request and
	 * Filter Set. Shared by both `admin-ajax.php` and the REST controller.
	 *
	 * @param array<string, mixed> $request    Raw request parameters (`$_GET`/`$_POST`/REST params).
	 * @param APF_Filter_Set       $filter_set Filter Set to render against.
	 * @return array<string, mixed>
	 */
	public static function build_response( array $request, APF_Filter_Set $filter_set ): array {
		$filters       = APF_Query_Builder::parse_request( $request, $filter_set );
		$context_extra = APF_Context::get_archive_restriction();

		if ( empty( $filters['products_per_page'] ) ) {
			$filters['products_per_page'] = (int) $filter_set->get_setting( 'products_per_page' );
		}

		$query_args                     = APF_Query_Builder::build_query_args( $filters, $context_extra );
		$query_args['posts_per_page']   = $filters['products_per_page'];

		$grid = APF_Product_Grid::render( $query_args );

		return array(
			'grid_html'     => $grid['html'],
			'sidebar_html'  => APF_Renderer::render_sidebar( $filter_set, $filters, $context_extra ),
			'found'         => $grid['found'],
			'max_num_pages' => $grid['max_num_pages'],
			'page'          => $grid['page'],
			'active_count'  => APF_Frontend::count_active_filters( $filters ),
			/* translators: %d: number of matched products */
			'result_count_text' => sprintf( _n( '%d product found', '%d products found', $grid['found'], 'advanced-product-filters' ), $grid['found'] ),
		);
	}
}
