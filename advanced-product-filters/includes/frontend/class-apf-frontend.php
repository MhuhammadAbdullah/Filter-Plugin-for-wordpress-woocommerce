<?php
/**
 * Front-end bootstrap — injects the sidebar into WooCommerce archives and
 * loads the storefront assets.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wraps WooCommerce's own content wrapper hooks in a flex layout so the
 * Filter Set sidebar renders next to the product grid without needing a
 * theme-specific integration, while an Elementor widget and shortcode
 * remain available for page builders that skip those hooks entirely.
 */
final class APF_Frontend {

	/**
	 * Shared query builder instance.
	 *
	 * @var APF_Query_Builder
	 */
	private APF_Query_Builder $query_builder;

	/**
	 * The Filter Set resolved for the current request, if any.
	 *
	 * @var APF_Filter_Set|null
	 */
	private ?APF_Filter_Set $filter_set = null;

	/**
	 * Wires the front-end hooks.
	 *
	 * @param APF_Query_Builder $query_builder Shared query builder.
	 */
	public function __construct( APF_Query_Builder $query_builder ) {
		$this->query_builder = $query_builder;

		add_action( 'wp', array( $this, 'resolve_filter_set' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		add_action( 'woocommerce_before_main_content', array( $this, 'open_layout' ), 5 );
		add_action( 'woocommerce_after_main_content', array( $this, 'close_layout' ), 20 );
		// Priority 35 keeps WooCommerce's own result-count (20) and
		// ordering dropdown (30) outside the AJAX-replaceable wrapper —
		// only the product loop and pagination get swapped out, while the
		// result count text is instead updated in place by the JS using
		// the AJAX response's `result_count_text`.
		add_action( 'woocommerce_before_shop_loop', array( $this, 'open_grid_target' ), 35 );
		add_action( 'woocommerce_after_shop_loop', array( $this, 'close_grid_target' ), 100 );
	}

	/**
	 * Resolves the applicable Filter Set once, early in the request.
	 *
	 * @return void
	 */
	public function resolve_filter_set(): void {
		if ( function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() ) ) {
			$this->filter_set = APF_Filter_Sets::get_for_current_context();
		}
	}

	/**
	 * Enqueues the storefront CSS/JS, only when a Filter Set will render.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! $this->filter_set ) {
			return;
		}

		self::ensure_assets( $this->filter_set->get_id() );
	}

	/**
	 * Registers (once) and enqueues the storefront CSS/JS, localising the
	 * shared `APF_CONFIG` object. Safe to call from multiple places
	 * (the shortcode, every Elementor widget) — repeat calls are no-ops
	 * beyond re-enqueuing.
	 *
	 * @param int $filter_set_id Filter Set ID to expose to the JS as the default scope.
	 * @return void
	 */
	public static function ensure_assets( int $filter_set_id = 0 ): void {
		if ( ! wp_style_is( 'apf-frontend', 'registered' ) ) {
			wp_register_style( 'apf-frontend', APF_URL . 'assets/css/advanced-product-filters.css', array(), APF_VERSION );

			$settings = APF_Settings::all();

			wp_add_inline_style(
				'apf-frontend',
				sprintf(
					':root{--apf-primary:%s;--apf-accent:%s;}',
					esc_attr( $settings['primary_color'] ),
					esc_attr( $settings['accent_color'] )
				)
			);

			wp_add_inline_style( 'apf-frontend', self::responsive_css( (int) $settings['mobile_breakpoint'] ) );
		}

		wp_enqueue_style( 'apf-frontend' );

		if ( ! wp_script_is( 'apf-frontend', 'registered' ) ) {
			wp_register_script( 'apf-frontend', APF_URL . 'assets/js/advanced-product-filters.js', array(), APF_VERSION, true );

			wp_localize_script(
				'apf-frontend',
				'APF_CONFIG',
				array(
					'restUrl'     => esc_url_raw( rest_url( 'apf/v1' ) ),
					'nonce'       => wp_create_nonce( 'wp_rest' ),
					'filterSetId' => $filter_set_id,
					'i18n'        => array(
						'noResults' => __( 'No products matched your filters.', 'advanced-product-filters' ),
						'loading'   => __( 'Loading…', 'advanced-product-filters' ),
					),
				)
			);
		}

		wp_enqueue_script( 'apf-frontend' );
	}

	/**
	 * Opens the shared layout wrapper and prints the sidebar, just before
	 * WooCommerce prints its own content wrapper markup.
	 *
	 * @return void
	 */
	public function open_layout(): void {
		if ( ! $this->filter_set ) {
			return;
		}

		$applied_filters = APF_Query_Builder::parse_request( $_GET, $this->filter_set ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$context_extra    = APF_Context::get_archive_restriction();

		echo '<div class="apf-shop-layout">';
		echo APF_Renderer::render_sidebar( $this->filter_set, $applied_filters, $context_extra ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="apf-shop-main">';
	}

	/**
	 * Closes the shared layout wrapper opened in `open_layout()`.
	 *
	 * @return void
	 */
	public function close_layout(): void {
		if ( ! $this->filter_set ) {
			return;
		}

		echo '</div></div>';
	}

	/**
	 * Opens the AJAX-replaceable region wrapping the result count, the
	 * mobile "Filters" toggle button, the product loop and its
	 * pagination — everything the storefront JS swaps out in one go
	 * whenever a filter changes.
	 *
	 * @return void
	 */
	public function open_grid_target(): void {
		if ( ! $this->filter_set ) {
			return;
		}

		$applied_filters = APF_Query_Builder::parse_request( $_GET, $this->filter_set ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		printf(
			'<div id="apf-ajax-grid-target" data-filter-set-id="%d" data-pagination-mode="%s">',
			esc_attr( (string) $this->filter_set->get_id() ),
			esc_attr( (string) $this->filter_set->get_setting( 'pagination_mode' ) )
		);

		APF_Template_Loader::render(
			'toggle-button',
			array( 'active_count' => APF_Frontend::count_active_filters( $applied_filters ) )
		);
	}

	/**
	 * Closes the wrapper opened in `open_grid_target()`.
	 *
	 * @return void
	 */
	public function close_grid_target(): void {
		if ( ! $this->filter_set ) {
			return;
		}

		echo '</div>';
	}

	/**
	 * Generates the mobile offcanvas `@media` rules for the configured
	 * breakpoint. CSS custom properties cannot be used inside an
	 * `@media` feature value, so the real pixel number has to be baked
	 * into the rule itself rather than referenced via `var()`.
	 *
	 * @param int $breakpoint Breakpoint in pixels, below which the sidebar becomes an offcanvas panel.
	 * @return string
	 */
	private static function responsive_css( int $breakpoint ): string {
		$breakpoint = max( 320, $breakpoint );

		return "
			@media (max-width: {$breakpoint}px) {
				.apf-shop-layout { display: block; }
				.apf-toggle-button { display: inline-flex; margin-bottom: 16px; }
				.apf-sidebar {
					position: fixed; top: 0; bottom: 0; width: min(85vw, 360px); max-width: 360px;
					background: var(--apf-bg); z-index: var(--apf-z-offcanvas); padding: 20px;
					transform: translateX(-110%); transition: transform 260ms ease;
					box-shadow: 2px 0 24px rgba(0,0,0,0.15);
				}
				.apf-sidebar.apf-offcanvas-right { right: 0; left: auto; transform: translateX(110%); }
				.apf-sidebar.apf-offcanvas-left { left: 0; }
				.apf-sidebar.apf-open { transform: translateX(0); }
				.apf-sidebar-header {
					display: flex; align-items: center; justify-content: space-between;
					padding-bottom: 14px; border-bottom: 1px solid var(--apf-border); margin-bottom: 8px;
				}
				.apf-sidebar-title { font-size: 16px; font-weight: 700; }
				.apf-close-offcanvas { display: block; background: none; border: 0; padding: 6px; color: var(--apf-text); cursor: pointer; }
				.apf-sidebar-body { overflow-y: auto; height: calc(100% - 60px); }
				.apf-overlay { display: block; }
			}
		";
	}

	/**
	 * Counts how many discrete filter values are currently applied, used
	 * for the toggle button's notification badge.
	 *
	 * @param array<string, mixed> $filters Normalised applied filters.
	 * @return int
	 */
	public static function count_active_filters( array $filters ): int {
		$count = 0;

		foreach ( $filters['tax'] as $slugs ) {
			$count += count( $slugs );
		}

		$count += count( $filters['stock'] );
		$count += ( null !== $filters['price']['min'] || null !== $filters['price']['max'] ) ? 1 : 0;
		$count += $filters['rating'] ? 1 : 0;
		$count += $filters['sale'] ? 1 : 0;
		$count += $filters['featured'] ? 1 : 0;
		$count += $filters['new'] ? 1 : 0;

		return $count;
	}
}
