<?php
/**
 * WooCommerce global attribute filter: color swatches, image swatches,
 * size pills, or a plain checkbox list — driven by attribute taxonomy.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Filters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles any `pa_*` product attribute taxonomy.
 */
final class AttributeFilterType extends AbstractFilterType {

	/**
	 * Fallback color palette keyed by lowercase term slug/name, used when
	 * no explicit color meta has been set for an attribute term.
	 *
	 * @var array<string, string>
	 */
	private const DEFAULT_PALETTE = array(
		'black'  => '#000000',
		'white'  => '#ffffff',
		'grey'   => '#8c8c8c',
		'gray'   => '#8c8c8c',
		'blue'   => '#1e73be',
		'navy'   => '#1b1f3b',
		'green'  => '#81d742',
		'orange' => '#dd9933',
		'red'    => '#dd3333',
		'yellow' => '#eeee22',
		'pink'   => '#ea9ce6',
		'purple' => '#7a3bcf',
		'brown'  => '#8a5a3c',
		'beige'  => '#e0d3b8',
		'gold'   => '#cfa030',
		'silver' => '#c0c0c0',
	);

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'attribute';
	}

	/**
	 * Resolves the `pa_*` taxonomy this filter reads from.
	 *
	 * @param array<string, mixed> $filter Filter row.
	 */
	private function taxonomy( array $filter ): string {
		$key = $filter['source_key'] ?? '';

		return str_starts_with( $key, 'pa_' ) ? $key : ( 'pa_' . sanitize_title( $key ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_options( array $filter, array $query_context ): array {
		$taxonomy = $this->taxonomy( $filter );

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => (bool) $this->setting( $filter, 'hide_empty', true ),
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$options = array();

		foreach ( $terms as $term ) {
			$count = $this->count_products(
				$query_context,
				array(
					'tax_query' => array(
						'taxonomy' => $taxonomy,
						'field'    => 'term_id',
						'terms'    => array( $term->term_id ),
					),
				)
			);

			$options[] = array(
				'value' => $term->slug,
				'label' => $term->name,
				'count' => $count,
				'color' => $this->resolve_color( $term ),
				'image' => esc_url_raw( (string) get_term_meta( $term->term_id, 'mabcf_image', true ) ),
			);
		}

		return $options;
	}

	/**
	 * Resolves the hex color for an attribute term: explicit meta first,
	 * then a case-insensitive lookup against the default palette.
	 *
	 * @param \WP_Term $term Attribute term.
	 */
	private function resolve_color( \WP_Term $term ): string {
		foreach ( array( 'mabcf_color', 'product_attribute_color', 'color' ) as $meta_key ) {
			$value = get_term_meta( $term->term_id, $meta_key, true );
			if ( $value && preg_match( '/^#?[0-9a-fA-F]{3,6}$/', (string) $value ) ) {
				return '#' === substr( (string) $value, 0, 1 ) ? $value : ( '#' . $value );
			}
		}

		$key = strtolower( $term->slug );

		return self::DEFAULT_PALETTE[ $key ] ?? '#cccccc';
	}

	/**
	 * {@inheritDoc}
	 */
	public function render( array $filter, array $query_context, array $selected ): string {
		$options = $this->get_options( $filter, $query_context );

		if ( ! $options ) {
			return '';
		}

		$style        = $filter['display_style'] ?? 'list';
		$show_count   = (bool) $this->setting( $filter, 'show_count', false );
		$selected_set = array_flip( $this->to_array( $selected ) );

		$html = '<div class="mabcf-swatches mabcf-swatches--' . esc_attr( sanitize_html_class( $style ) ) . '">';

		foreach ( $options as $option ) {
			$is_selected = isset( $selected_set[ $option['value'] ] );
			$count_html  = $show_count ? sprintf( '<span class="mabcf-swatch__count">%d</span>', (int) $option['count'] ) : '';

			$inner = '';
			if ( 'color' === $style ) {
				$inner = sprintf( '<span class="mabcf-swatch__bg" style="background-color:%s"></span>', esc_attr( $option['color'] ) );
			} elseif ( 'image' === $style && $option['image'] ) {
				$inner = sprintf( '<img class="mabcf-swatch__img" src="%s" alt="%s" loading="lazy">', esc_url( $option['image'] ), esc_attr( $option['label'] ) );
			} elseif ( 'pill' === $style ) {
				$inner = '<span class="mabcf-swatch__pill-label">' . esc_html( $option['label'] ) . '</span>';
			} else {
				$inner = '<span class="mabcf-swatch__name">' . esc_html( $option['label'] ) . '</span>';
			}

			$disabled = 0 === (int) $option['count'] && ! $is_selected ? ' mabcf-swatch--disabled' : '';

			$html .= sprintf(
				'<label class="mabcf-swatch mabcf-swatch--%1$s%2$s%3$s" data-value="%4$s" title="%5$s">
					<input type="checkbox" name="mabcf_filter[%6$d][]" value="%4$s" %7$s>
					%8$s
					%9$s
				</label>',
				esc_attr( sanitize_html_class( $style ) ),
				$is_selected ? ' mabcf-swatch--active' : '',
				esc_attr( $disabled ),
				esc_attr( $option['value'] ),
				esc_attr( $option['label'] ),
				(int) $filter['id'],
				checked( $is_selected, true, false ),
				$inner, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$count_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			);
		}

		$html .= '</div>';

		return $this->wrap( $filter, $html );
	}

	/**
	 * {@inheritDoc}
	 */
	public function apply_query( array $filter, array &$args, $selected ): void {
		$values = $this->to_array( $selected );

		if ( ! $values ) {
			return;
		}

		$args['tax_query']   = $args['tax_query'] ?? array();
		$args['tax_query'][] = array(
			'taxonomy' => $this->taxonomy( $filter ),
			'field'    => 'slug',
			'terms'    => $values,
			'operator' => 'IN',
		);
	}
}
