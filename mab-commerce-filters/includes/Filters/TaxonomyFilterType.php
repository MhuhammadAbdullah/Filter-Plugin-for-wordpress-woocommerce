<?php
/**
 * Generic taxonomy filter: tags, brand, and any custom taxonomy.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Filters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles product_tag, product brand taxonomies (pwb-brand / product_brand)
 * and any other registered custom taxonomy, driven entirely by the
 * filter's source_key. Supports list, pills, checkbox/radio, and an image
 * or logo per term (read from term meta) for brand-style presentation.
 */
final class TaxonomyFilterType extends AbstractFilterType {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'taxonomy';
	}

	/**
	 * Resolves the taxonomy name for this filter, defaulting sensibly.
	 *
	 * @param array<string, mixed> $filter Filter row.
	 */
	private function taxonomy( array $filter ): string {
		return ! empty( $filter['source_key'] ) ? $filter['source_key'] : 'product_tag';
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
				'image' => esc_url_raw( (string) get_term_meta( $term->term_id, 'mabcf_image', true ) ),
				'logo'  => esc_url_raw( (string) get_term_meta( $term->term_id, 'mabcf_logo', true ) ),
			);
		}

		return $options;
	}

	/**
	 * {@inheritDoc}
	 */
	public function render( array $filter, array $query_context, array $selected ): string {
		$options = $this->get_options( $filter, $query_context );

		if ( ! $options ) {
			return '';
		}

		$style        = $this->setting( $filter, 'style', $filter['display_style'] ?? 'list' );
		$input_type   = 'radio' === $this->setting( $filter, 'input', 'checkbox' ) ? 'radio' : 'checkbox';
		$show_count   = (bool) $this->setting( $filter, 'show_count', true );
		$view         = $this->setting( $filter, 'view', 'list' );
		$selected_set = array_flip( $this->to_array( $selected['value'] ?? array() ) );

		$class = 'mabcf-option-list--' . sanitize_html_class( $style ) . ' mabcf-option-list--' . sanitize_html_class( $view );
		$html  = '<ul class="mabcf-option-list ' . esc_attr( $class ) . '">';

		foreach ( $options as $option ) {
			$is_selected = isset( $selected_set[ $option['value'] ] );
			$count_html  = $show_count ? sprintf( '<span class="mabcf-option__count">%d</span>', (int) $option['count'] ) : '';
			$media       = '';

			if ( 'brand-logo' === $style && $option['logo'] ) {
				$media = sprintf( '<img class="mabcf-option__logo" src="%s" alt="%s" loading="lazy">', esc_url( $option['logo'] ), esc_attr( $option['label'] ) );
			} elseif ( 'brand-image' === $style && $option['image'] ) {
				$media = sprintf( '<img class="mabcf-option__image" src="%s" alt="%s" loading="lazy">', esc_url( $option['image'] ), esc_attr( $option['label'] ) );
			}

			$html .= sprintf(
				'<li class="mabcf-option" data-value="%1$s">
					<label class="mabcf-option__label">
						<input type="%2$s" name="mabcf_filter[%3$d][]" value="%1$s" %4$s>
						%5$s
						<span class="mabcf-option__name">%6$s</span>
						%7$s
					</label>
				</li>',
				esc_attr( $option['value'] ),
				esc_attr( $input_type ),
				(int) $filter['id'],
				checked( $is_selected, true, false ),
				$media, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				esc_html( $option['label'] ),
				$count_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			);
		}

		$html .= '</ul>';

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
