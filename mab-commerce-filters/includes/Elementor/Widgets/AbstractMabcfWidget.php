<?php
/**
 * Shared plumbing for every MAB Commerce Filters Elementor widget.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Elementor\Widgets;

use Elementor\Widget_Base;
use MABCommerceFilters\Helpers\Helper;
use MABCommerceFilters\Repositories\FilterSetRepository;
use MABCommerceFilters\Services\LocationResolver;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides the common "MAB Commerce Filters" category, front-end asset
 * loading, and a resolved-filter-set helper reused by every widget.
 */
abstract class AbstractMabcfWidget extends Widget_Base {

	/**
	 * {@inheritDoc}
	 */
	public function get_categories(): array {
		return array( 'mab-commerce-filters' );
	}

	/**
	 * Ensures front-end CSS/JS are enqueued when Elementor renders this
	 * widget (editor preview and live front end alike).
	 */
	protected function ensure_assets(): void {
		wp_enqueue_style( 'mabcf-frontend' );
		wp_enqueue_script( 'mabcf-frontend' );
	}

	/**
	 * Builds a filter-set-id => name map for a Select control, prefixed
	 * with an "Auto" option that follows the page's assigned set.
	 *
	 * @return array<string, string>
	 */
	protected function filter_set_options(): array {
		$options = array( '0' => __( 'Auto (current page location)', 'mab-commerce-filters' ) );

		foreach ( ( new FilterSetRepository() )->get_all() as $set ) {
			$options[ (string) $set['id'] ] = $set['name'];
		}

		return $options;
	}

	/**
	 * Resolves the filter set a widget instance should render, given its
	 * `filter_set_id` control value.
	 *
	 * @param int $filter_set_id 0 for auto, otherwise a specific filter set ID.
	 * @return array<string, mixed>|null
	 */
	protected function resolve_set( int $filter_set_id ): ?array {
		$resolver = new LocationResolver();

		return $filter_set_id ? ( new FilterSetRepository() )->find( $filter_set_id ) : $resolver->resolve_current();
	}

	/**
	 * Whether the current user is allowed to configure widgets (defensive
	 * check; Elementor already gates editor access itself).
	 */
	protected function can_edit(): bool {
		return Helper::current_user_can_manage() || current_user_can( 'edit_posts' );
	}
}
