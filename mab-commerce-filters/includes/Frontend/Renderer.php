<?php
/**
 * Renders the full filter widget markup for the front end.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Frontend;

use MABCommerceFilters\Filters\FilterTypeRegistry;
use MABCommerceFilters\Repositories\FilterRepository;
use MABCommerceFilters\Services\FilterQueryService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the `<form class="mabcf-form">` widget: active-filters bar,
 * accordion sections for each filter, and apply/clear buttons — matching
 * the sidebar layout referenced in the design.
 */
final class Renderer {

	/**
	 * Filter repository.
	 *
	 * @var FilterRepository
	 */
	private FilterRepository $filters;

	/**
	 * Filter query service.
	 *
	 * @var FilterQueryService
	 */
	private FilterQueryService $query_service;

	/**
	 * Filter type registry.
	 *
	 * @var FilterTypeRegistry
	 */
	private FilterTypeRegistry $registry;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->filters       = new FilterRepository();
		$this->query_service = new FilterQueryService();
		$this->registry      = FilterTypeRegistry::instance();
	}

	/**
	 * Renders a filter set's widget markup for the current request.
	 *
	 * @param array<string, mixed>                                  $filter_set Filter set row.
	 * @param array<string, mixed>                                  $base_args  Base WP_Query args for the current archive context.
	 * @param array<int, mixed>                                     $selection  Selected values keyed by filter ID.
	 * @param array{type: string, taxonomy: string, value: string}|null $context Archive context, used to replay the same base query over AJAX.
	 */
	public function render( array $filter_set, array $base_args = array(), array $selection = array(), ?array $context = null ): string {
		$filter_rows = $this->filters->get_for_set( (int) $filter_set['id'] );

		if ( ! $filter_rows ) {
			return '';
		}

		$settings = is_array( $filter_set['settings'] ?? null ) ? $filter_set['settings'] : array();
		$ajax     = ! empty( $settings['ajax'] );
		$instant  = ! empty( $settings['instant'] );
		$has_apply = ! $instant;

		$layout = sanitize_html_class( $filter_set['layout'] ?? 'sidebar' );

		$context = $context ?? array( 'type' => 'global', 'taxonomy' => '', 'value' => '' );

		$attrs = array(
			'ajax'             => $ajax,
			'instant'          => $instant,
			'change_url'       => ! empty( $settings['change_url'] ),
			'filterSetId'      => (int) $filter_set['id'],
			'contextType'      => $context['type'],
			'contextTaxonomy'  => $context['taxonomy'],
			'contextValue'     => $context['value'],
			'target'           => '.mabcf-products-target',
		);

		$active_bar   = $this->render_active_bar( $filter_rows, $selection );
		$filters_html = $this->query_service->render_filters( $filter_rows, $selection, $base_args );

		$reset_url = remove_query_arg( array_merge( array( 'mabcf_filter' ), wp_list_pluck( $filter_rows, 'source_key' ) ) );

		return sprintf(
			'<div class="mabcf mabcf--%1$s" data-filter-set-id="%2$d">
				<form class="mabcf-form" method="get" action="%3$s" data-settings=\'%4$s\'>
					%5$s
					<div class="mabcf-filters">%6$s</div>
					<div class="mabcf-actions">
						%7$s
						<button type="reset" class="mabcf-button mabcf-button--ghost mabcf-reset">%8$s</button>
					</div>
					<div class="mabcf-loader" aria-hidden="true"><span class="mabcf-spinner"></span></div>
				</form>
			</div>',
			esc_attr( $layout ),
			(int) $filter_set['id'],
			esc_url( $this->current_url() ),
			esc_attr( (string) wp_json_encode( $attrs ) ),
			$active_bar, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$filters_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$has_apply ? '<button type="submit" class="mabcf-button mabcf-button--primary mabcf-apply">' . esc_html__( 'Apply', 'mab-commerce-filters' ) . '</button>' : '',
			esc_html__( 'Clear', 'mab-commerce-filters' )
		);
	}

	/**
	 * Renders the "Active filters" bar with removable pills for every
	 * currently selected value, plus a "Clear All" action.
	 *
	 * @param array<int, array<string, mixed>> $filter_rows Filters in the set.
	 * @param array<int, mixed>                $selection   Selected values keyed by filter ID.
	 */
	public function render_active_bar( array $filter_rows, array $selection ): string {
		$pills = '';

		foreach ( $filter_rows as $filter ) {
			$id = (int) $filter['id'];

			if ( empty( $selection[ $id ] ) ) {
				continue;
			}

			$values = is_array( $selection[ $id ] ) ? ( $selection[ $id ]['value'] ?? $selection[ $id ] ) : $selection[ $id ];
			$values = is_array( $values ) ? $values : array( $values );

			foreach ( $values as $value ) {
				if ( '' === $value || null === $value ) {
					continue;
				}

				$pills .= sprintf(
					'<span class="mabcf-pill" data-filter-id="%1$d" data-value="%2$s">%3$s: %4$s <button type="button" class="mabcf-pill__remove" aria-label="%5$s">&times;</button></span>',
					$id,
					esc_attr( (string) $value ),
					esc_html( $filter['label'] ),
					esc_html( (string) $value ),
					esc_attr__( 'Remove filter', 'mab-commerce-filters' )
				);
			}
		}

		$hidden_class = $pills ? '' : ' hidden';

		return sprintf(
			'<div class="mabcf-active%1$s">
				<div class="mabcf-active__head">
					<h6 class="mabcf-active__title">%2$s</h6>
					<button type="button" class="mabcf-button mabcf-button--subtle mabcf-clear-all">%3$s</button>
				</div>
				<div class="mabcf-active__items">%4$s</div>
			</div>',
			esc_attr( $hidden_class ),
			esc_html__( 'Active filters', 'mab-commerce-filters' ),
			esc_html__( 'Reset', 'mab-commerce-filters' ),
			$pills // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	/**
	 * Returns the current request URL without the scheme host mismatch
	 * issues (used as the filter form's action for no-JS fallback).
	 */
	private function current_url(): string {
		global $wp;

		return home_url( add_query_arg( array(), $wp->request ) );
	}
}
