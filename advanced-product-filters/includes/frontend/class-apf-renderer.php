<?php
/**
 * Renders the filter sidebar and its individual sections.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Turns a Filter Set's section configuration into HTML, computing live
 * facet counts for each taxonomy-driven section along the way.
 */
final class APF_Renderer {

	/**
	 * Renders the complete sidebar (offcanvas wrapper + every enabled
	 * section) for the given Filter Set.
	 *
	 * @param APF_Filter_Set        $filter_set      Filter Set to render.
	 * @param array<string, mixed>  $applied_filters Normalised applied filters (see `APF_Query_Builder::parse_request()`).
	 * @param array<string, mixed>  $context_extra   Fixed WP_Query args restricting the base product set (e.g. the current archive term).
	 * @return string
	 */
	public static function render_sidebar( APF_Filter_Set $filter_set, array $applied_filters, array $context_extra = array() ): string {
		$sections_html = '';

		foreach ( $filter_set->get_enabled_sections() as $section ) {
			$sections_html .= self::render_section( $section, $filter_set, $applied_filters, $context_extra );
		}

		return APF_Template_Loader::get(
			'sidebar',
			array(
				'filter_set'         => $filter_set,
				'sections_html'      => $sections_html,
				'instant_ajax'       => (bool) $filter_set->get_setting( 'instant_ajax' ),
				'show_apply_button'  => (bool) $filter_set->get_setting( 'show_apply_button' ),
				'show_clear_button'  => (bool) $filter_set->get_setting( 'show_clear_button' ),
				'url_sync'           => (bool) $filter_set->get_setting( 'url_sync' ),
				'sticky_sidebar'     => (bool) $filter_set->get_setting( 'sticky_sidebar' ),
				'offcanvas_position' => APF_Settings::get( 'offcanvas_position', 'left' ),
			)
		);
	}

	/**
	 * Renders a single section as a standalone widget, independent of any
	 * stored Filter Set — used by the single-purpose Elementor widgets
	 * (Price Slider, Color Swatches, Category Filter, ...) so they can be
	 * dropped anywhere on a page. Every standalone widget sharing the
	 * same `$scope` is treated as one cohesive filter form by the
	 * storefront JS and kept in sync with any Product Grid widget using
	 * the same scope.
	 *
	 * @param array<string, mixed> $section_config Section configuration (type, label, input_type, taxonomy, ...).
	 * @param string                $scope          Identifier grouping related standalone widgets together (defaults to "context").
	 * @return string
	 */
	public static function render_standalone_section( array $section_config, string $scope = 'context' ): string {
		$section_config = wp_parse_args(
			$section_config,
			array(
				'id'         => $section_config['type'] ?? 'section',
				'enabled'    => true,
				'collapsed'  => false,
				'show_count' => true,
			)
		);

		$filter_set       = APF_Filter_Sets::get_for_current_context() ?? new APF_Filter_Set( 0, '', true, 0, array(), array(), array() );
		$applied_filters  = APF_Query_Builder::parse_request( $_GET, $filter_set ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$context_extra    = APF_Context::get_archive_restriction();

		$inner = self::render_section( $section_config, $filter_set, $applied_filters, $context_extra );

		return sprintf(
			'<div class="apf-standalone-widget" data-apf-scope="%s" data-filter-set-id="%d">%s</div>',
			esc_attr( $scope ),
			$filter_set->get_id(),
			$inner
		);
	}

	/**
	 * Renders a single section.
	 *
	 * @param array<string, mixed> $section         Section configuration.
	 * @param APF_Filter_Set       $filter_set      Owning Filter Set.
	 * @param array<string, mixed> $applied_filters Normalised applied filters.
	 * @param array<string, mixed> $context_extra   Fixed WP_Query args restricting the base product set.
	 * @return string
	 */
	public static function render_section( array $section, APF_Filter_Set $filter_set, array $applied_filters, array $context_extra = array() ): string {
		$type = $section['type'] ?? '';

		switch ( $type ) {
			case 'active_filters':
				return self::render_active_filters( $section, $applied_filters );

			case 'price':
				return self::render_price( $section, $applied_filters, $context_extra );

			case 'category':
				return self::render_terms( $section, 'product_cat', $applied_filters, $context_extra );

			case 'tag':
				return self::render_terms( $section, 'product_tag', $applied_filters, $context_extra );

			case 'brand':
				$taxonomy = APF_Taxonomies::get_brand_taxonomy();
				return $taxonomy ? self::render_terms( $section, $taxonomy, $applied_filters, $context_extra ) : '';

			case 'attribute':
			case 'custom_taxonomy':
				$taxonomy = $section['taxonomy'] ?? '';
				return $taxonomy && taxonomy_exists( $taxonomy ) ? self::render_terms( $section, $taxonomy, $applied_filters, $context_extra ) : '';

			case 'rating':
				return self::render_rating( $section, $applied_filters, $context_extra );

			case 'stock':
				return self::render_stock( $section, $applied_filters, $context_extra );

			case 'sale':
				return self::render_toggle( $section, $applied_filters, $context_extra, 'sale', 'apf_sale' );

			case 'featured':
				return self::render_toggle( $section, $applied_filters, $context_extra, 'featured', 'apf_featured' );

			case 'newest':
				return self::render_toggle( $section, $applied_filters, $context_extra, 'new', 'apf_new' );

			default:
				/**
				 * Fires for unrecognised section types, letting add-ons render
				 * their own custom sections.
				 *
				 * @param array<string, mixed> $section Section configuration.
				 */
				return (string) apply_filters( 'apf_render_custom_section', '', $section, $applied_filters, $context_extra );
		}
	}

	/**
	 * Renders the "Active Filters" chip list.
	 *
	 * @param array<string, mixed> $section         Section configuration.
	 * @param array<string, mixed> $applied_filters  Normalised applied filters.
	 * @return string
	 */
	private static function render_active_filters( array $section, array $applied_filters ): string {
		$chips = array();

		foreach ( $applied_filters['tax'] as $taxonomy => $slugs ) {
			foreach ( $slugs as $slug ) {
				$term = get_term_by( 'slug', $slug, $taxonomy );

				if ( $term && ! is_wp_error( $term ) ) {
					$chips[] = array(
						'label' => $term->name,
						'param' => APF_Query_Builder::filterable_taxonomies()[ $taxonomy ] ?? $taxonomy,
						'value' => $slug,
					);
				}
			}
		}

		if ( null !== $applied_filters['price']['min'] || null !== $applied_filters['price']['max'] ) {
			$chips[] = array(
				'label' => self::format_price_chip( $applied_filters['price']['min'], $applied_filters['price']['max'] ),
				'param' => 'apf_price',
				'value' => '',
			);
		}

		if ( ! empty( $applied_filters['rating'] ) ) {
			$chips[] = array(
				/* translators: %d: minimum star rating */
				'label' => sprintf( __( '%d Stars & Up', 'advanced-product-filters' ), $applied_filters['rating'] ),
				'param' => 'apf_rating',
				'value' => '',
			);
		}

		foreach ( $applied_filters['stock'] as $status ) {
			$chips[] = array(
				'label' => self::stock_label( $status ),
				'param' => 'apf_stock',
				'value' => $status,
			);
		}

		if ( ! empty( $applied_filters['sale'] ) ) {
			$chips[] = array( 'label' => __( 'On Sale', 'advanced-product-filters' ), 'param' => 'apf_sale', 'value' => '' );
		}

		if ( ! empty( $applied_filters['featured'] ) ) {
			$chips[] = array( 'label' => __( 'Featured', 'advanced-product-filters' ), 'param' => 'apf_featured', 'value' => '' );
		}

		if ( ! empty( $applied_filters['new'] ) ) {
			$chips[] = array( 'label' => __( 'New Arrivals', 'advanced-product-filters' ), 'param' => 'apf_new', 'value' => '' );
		}

		return APF_Template_Loader::get(
			'active-filters',
			array(
				'section' => $section,
				'chips'   => $chips,
			)
		);
	}

	/**
	 * Renders the dual-handle price range slider.
	 *
	 * @param array<string, mixed> $section         Section configuration.
	 * @param array<string, mixed> $applied_filters  Normalised applied filters.
	 * @param array<string, mixed> $context_extra    Fixed WP_Query args.
	 * @return string
	 */
	private static function render_price( array $section, array $applied_filters, array $context_extra ): string {
		$without_price          = $applied_filters;
		$without_price['price'] = array( 'min' => null, 'max' => null );

		list( $min, $max ) = APF_Facet_Counts::get_price_range( $without_price, $context_extra );

		if ( $min === $max ) {
			$max += 1;
		}

		return APF_Template_Loader::get(
			'section-price',
			array(
				'section'      => $section,
				'range_min'    => floor( $min ),
				'range_max'    => ceil( $max ),
				'selected_min' => $applied_filters['price']['min'] ?? floor( $min ),
				'selected_max' => $applied_filters['price']['max'] ?? ceil( $max ),
				'currency_symbol' => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$',
			)
		);
	}

	/**
	 * Renders a taxonomy-driven section (categories, attributes, brand,
	 * tags, custom taxonomies) using whichever input type is configured.
	 *
	 * @param array<string, mixed> $section         Section configuration.
	 * @param string                $taxonomy        Taxonomy slug.
	 * @param array<string, mixed> $applied_filters  Normalised applied filters.
	 * @param array<string, mixed> $context_extra    Fixed WP_Query args.
	 * @return string
	 */
	private static function render_terms( array $section, string $taxonomy, array $applied_filters, array $context_extra ): string {
		$param = APF_Query_Builder::filterable_taxonomies()[ $taxonomy ] ?? 'apf_tax_' . $taxonomy;
		$input_type = $section['input_type'] ?? ( is_taxonomy_hierarchical( $taxonomy ) ? 'tree' : 'checkbox' );

		$hierarchical = is_taxonomy_hierarchical( $taxonomy ) && 'tree' === $input_type;

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => ! empty( $section['term_limit'] ) ? $section['term_limit'] : 0,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}

		$without_this          = $applied_filters;
		unset( $without_this['tax'][ $taxonomy ] );

		$counts   = ! empty( $section['show_count'] ) ? APF_Facet_Counts::get_term_counts( $taxonomy, $without_this, $context_extra ) : array();
		$selected = $applied_filters['tax'][ $taxonomy ] ?? array();

		return APF_Template_Loader::get(
			'section-terms',
			array(
				'section'      => $section,
				'taxonomy'     => $taxonomy,
				'param'        => $param,
				'input_type'   => $input_type,
				'terms'        => $hierarchical ? self::build_term_tree( $terms ) : array_map( static fn( $t ) => array( 'term' => $t, 'children' => array() ), $terms ),
				'counts'       => $counts,
				'selected'     => $selected,
				'show_count'   => ! empty( $section['show_count'] ),
			)
		);
	}

	/**
	 * Builds a nested parent/child term tree for hierarchical taxonomies.
	 *
	 * @param WP_Term[] $terms Flat term list.
	 * @return array<int, array{term: WP_Term, children: array}>
	 */
	private static function build_term_tree( array $terms ): array {
		$by_parent = array();

		foreach ( $terms as $term ) {
			$by_parent[ $term->parent ][] = $term;
		}

		$build = static function ( int $parent_id ) use ( &$build, $by_parent ): array {
			$branch = array();

			foreach ( $by_parent[ $parent_id ] ?? array() as $term ) {
				$branch[] = array(
					'term'     => $term,
					'children' => $build( $term->term_id ),
				);
			}

			return $branch;
		};

		return $build( 0 );
	}

	/**
	 * Renders the star rating filter.
	 *
	 * @param array<string, mixed> $section         Section configuration.
	 * @param array<string, mixed> $applied_filters  Normalised applied filters.
	 * @param array<string, mixed> $context_extra    Fixed WP_Query args.
	 * @return string
	 */
	private static function render_rating( array $section, array $applied_filters, array $context_extra ): string {
		$scale  = (int) APF_Settings::get( 'ratings_scale', 5 );
		$counts = array();

		if ( ! empty( $section['show_count'] ) ) {
			$without_rating = $applied_filters;
			$without_rating['rating'] = 0;

			for ( $stars = 1; $stars <= $scale; $stars++ ) {
				$with_rating           = $without_rating;
				$with_rating['rating'] = $stars;
				$counts[ $stars ]      = APF_Facet_Counts::get_total_matched( $with_rating, $context_extra );
			}
		}

		return APF_Template_Loader::get(
			'section-rating',
			array(
				'section'  => $section,
				'scale'    => $scale,
				'selected' => $applied_filters['rating'] ?? 0,
				'counts'   => $counts,
			)
		);
	}

	/**
	 * Renders the stock status filter.
	 *
	 * @param array<string, mixed> $section         Section configuration.
	 * @param array<string, mixed> $applied_filters  Normalised applied filters.
	 * @param array<string, mixed> $context_extra    Fixed WP_Query args.
	 * @return string
	 */
	private static function render_stock( array $section, array $applied_filters, array $context_extra ): string {
		$statuses = array( 'instock', 'outofstock', 'onbackorder' );
		$counts   = array();

		if ( ! empty( $section['show_count'] ) ) {
			$without_stock = $applied_filters;
			$without_stock['stock'] = array();

			foreach ( $statuses as $status ) {
				$with_status           = $without_stock;
				$with_status['stock']  = array( $status );
				$counts[ $status ]     = APF_Facet_Counts::get_total_matched( $with_status, $context_extra );
			}
		}

		return APF_Template_Loader::get(
			'section-stock',
			array(
				'section'  => $section,
				'statuses' => $statuses,
				'selected' => $applied_filters['stock'] ?? array(),
				'counts'   => $counts,
			)
		);
	}

	/**
	 * Renders a boolean toggle section (On Sale / Featured / New Arrivals).
	 *
	 * @param array<string, mixed> $section         Section configuration.
	 * @param array<string, mixed> $applied_filters  Normalised applied filters.
	 * @param array<string, mixed> $context_extra    Fixed WP_Query args.
	 * @param string                $filter_key      Key inside `$applied_filters` (`sale`, `featured`, `new`).
	 * @param string                $param           Query string parameter name.
	 * @return string
	 */
	private static function render_toggle( array $section, array $applied_filters, array $context_extra, string $filter_key, string $param ): string {
		$count = null;

		if ( ! empty( $section['show_count'] ) ) {
			$with_toggle               = $applied_filters;
			$with_toggle[ $filter_key ] = true;
			$count                      = APF_Facet_Counts::get_total_matched( $with_toggle, $context_extra );
		}

		return APF_Template_Loader::get(
			'section-toggle',
			array(
				'section'  => $section,
				'param'    => $param,
				'selected' => ! empty( $applied_filters[ $filter_key ] ),
				'count'    => $count,
			)
		);
	}

	/**
	 * Formats the "Active Filters" chip label for the price range.
	 *
	 * @param float|null $min Minimum price.
	 * @param float|null $max Maximum price.
	 * @return string
	 */
	private static function format_price_chip( ?float $min, ?float $max ): string {
		if ( ! function_exists( 'wc_price' ) ) {
			return sprintf( '%s - %s', $min ?? '', $max ?? '' );
		}

		if ( null !== $min && null !== $max ) {
			return wp_strip_all_tags( wc_price( $min ) ) . ' – ' . wp_strip_all_tags( wc_price( $max ) );
		}

		if ( null !== $min ) {
			/* translators: %s: minimum price */
			return sprintf( __( 'From %s', 'advanced-product-filters' ), wp_strip_all_tags( wc_price( $min ) ) );
		}

		/* translators: %s: maximum price */
		return sprintf( __( 'Up to %s', 'advanced-product-filters' ), wp_strip_all_tags( wc_price( $max ) ) );
	}

	/**
	 * Human readable label for a stock status slug.
	 *
	 * @param string $status Stock status slug.
	 * @return string
	 */
	private static function stock_label( string $status ): string {
		$labels = array(
			'instock'     => __( 'In Stock', 'advanced-product-filters' ),
			'outofstock'  => __( 'Out of Stock', 'advanced-product-filters' ),
			'onbackorder' => __( 'On Backorder', 'advanced-product-filters' ),
		);

		return $labels[ $status ] ?? $status;
	}
}
