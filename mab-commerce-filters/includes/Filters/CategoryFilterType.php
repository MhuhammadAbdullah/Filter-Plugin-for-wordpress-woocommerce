<?php
/**
 * Product category filter: hierarchical tree or flat list with counts.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Filters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and applies the product_cat taxonomy filter.
 */
final class CategoryFilterType extends AbstractFilterType {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'category';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_options( array $filter, array $query_context ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
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
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => array( $term->term_id ),
					),
				)
			);

			$options[] = array(
				'value'     => $term->slug,
				'label'     => $term->name,
				'count'     => $count,
				'parent'    => $term->parent,
				'term_id'   => $term->term_id,
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

		$hierarchical = (bool) $this->setting( $filter, 'hierarchical', true );
		$show_count   = (bool) $this->setting( $filter, 'show_count', true );
		$searchable   = (bool) $this->setting( $filter, 'searchable', false );
		$limit        = (int) $this->setting( $filter, 'show_limit', 10 );
		$selected_set = array_flip( $this->to_array( $selected['value'] ?? array() ) );

		$search_html = $searchable
			? '<input type="search" class="mabcf-filter__search" placeholder="' . esc_attr__( 'Search categories…', 'mab-commerce-filters' ) . '">'
			: '';

		if ( $hierarchical ) {
			$tree = $this->build_tree( $options );
			$list = $this->render_tree( $tree, 0, $filter, $selected_set );
		} else {
			$list = '<ul class="mabcf-option-list">';
			foreach ( $options as $option ) {
				$list .= $this->render_item( $filter, $option, isset( $selected_set[ $option['value'] ] ), $show_count );
			}
			$list .= '</ul>';
		}

		$more = count( $options ) > $limit ? '<button type="button" class="mabcf-show-more">' . esc_html__( 'See More', 'mab-commerce-filters' ) . '</button>' : '';

		return $this->wrap( $filter, $search_html . '<div class="mabcf-option-list__scroller" data-limit="' . (int) $limit . '">' . $list . '</div>' . $more );
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
			'taxonomy' => 'product_cat',
			'field'    => 'slug',
			'terms'    => $values,
			'operator' => 'IN',
		);
	}

	/**
	 * Builds a parent_id => children[] tree from flat term options.
	 *
	 * @param array<int, array<string, mixed>> $options Flat term options.
	 * @return array<int, array<int, array<string, mixed>>>
	 */
	private function build_tree( array $options ): array {
		$tree = array();

		foreach ( $options as $option ) {
			$tree[ (int) $option['parent'] ][] = $option;
		}

		return $tree;
	}

	/**
	 * Recursively renders a category tree level.
	 *
	 * @param array<int, array<int, array<string, mixed>>> $tree         Tree grouped by parent ID.
	 * @param int                                            $parent_id    Current parent term ID.
	 * @param array<string, mixed>                            $filter       Filter row.
	 * @param array<string, int>                              $selected_set Selected slug lookup.
	 */
	private function render_tree( array $tree, int $parent_id, array $filter, array $selected_set ): string {
		if ( empty( $tree[ $parent_id ] ) ) {
			return '';
		}

		$show_count = (bool) $this->setting( $filter, 'show_count', true );
		$html       = '<ul class="mabcf-option-list mabcf-option-list--tree">';

		foreach ( $tree[ $parent_id ] as $option ) {
			$has_children = ! empty( $tree[ (int) $option['term_id'] ] );
			$item         = $this->render_item( $filter, $option, isset( $selected_set[ $option['value'] ] ), $show_count, $has_children );

			if ( $has_children ) {
				$item = str_replace( '</li>', $this->render_tree( $tree, (int) $option['term_id'], $filter, $selected_set ) . '</li>', $item );
			}

			$html .= $item;
		}

		$html .= '</ul>';

		return $html;
	}

	/**
	 * Renders a single checkbox list item.
	 *
	 * @param array<string, mixed> $filter       Filter row.
	 * @param array<string, mixed> $option       Option data.
	 * @param bool                  $is_selected  Whether currently selected.
	 * @param bool                  $show_count   Whether to render the count badge.
	 * @param bool                  $has_children Whether this node has an expand toggle.
	 */
	private function render_item( array $filter, array $option, bool $is_selected, bool $show_count, bool $has_children = false ): string {
		$count_html = $show_count ? sprintf( '<span class="mabcf-option__count">%d</span>', (int) $option['count'] ) : '';
		$expand     = $has_children ? '<span class="mabcf-option__expand" aria-hidden="true"></span>' : '';
		$disabled   = 0 === (int) $option['count'] && ! $is_selected ? ' mabcf-option--disabled' : '';

		return sprintf(
			'<li class="mabcf-option%1$s" data-value="%2$s">
				<label class="mabcf-option__label">
					<input type="checkbox" name="mabcf_filter[%3$d][]" value="%2$s" %4$s>
					<span class="mabcf-option__name">%5$s</span>
					%6$s
				</label>
				%7$s
			</li>',
			esc_attr( $disabled ),
			esc_attr( $option['value'] ),
			(int) $filter['id'],
			checked( $is_selected, true, false ),
			esc_html( $option['label'] ),
			$count_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$expand // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}
}
