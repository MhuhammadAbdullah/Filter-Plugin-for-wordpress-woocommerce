<?php
/**
 * Template: sidebar wrapper (offcanvas shell + section list).
 *
 * Override by copying this file to `yourtheme/advanced-product-filters/sidebar.php`.
 *
 * @package AdvancedProductFilters
 *
 * @var APF_Filter_Set $filter_set         Active Filter Set.
 * @var string          $sections_html      Pre-rendered section markup.
 * @var bool            $instant_ajax       Whether filtering updates instantly.
 * @var bool            $show_apply_button  Whether to show an explicit "Apply" button.
 * @var bool            $show_clear_button  Whether to show a "Reset All" button.
 * @var bool            $url_sync           Whether filters are reflected in the URL.
 * @var bool            $sticky_sidebar     Whether the sidebar should stick while scrolling.
 * @var string           $offcanvas_position "left" or "right".
 */

defined( 'ABSPATH' ) || exit;
?>
<div
	class="apf-overlay"
	id="apf-overlay"
	hidden
></div>
<aside
	class="apf-sidebar apf-offcanvas-<?php echo esc_attr( $offcanvas_position ); ?><?php echo $sticky_sidebar ? ' apf-sticky' : ''; ?>"
	id="apf-sidebar"
	data-filter-set-id="<?php echo esc_attr( (string) $filter_set->get_id() ); ?>"
	data-instant="<?php echo $instant_ajax ? '1' : '0'; ?>"
	data-url-sync="<?php echo $url_sync ? '1' : '0'; ?>"
	aria-label="<?php esc_attr_e( 'Product filters', 'advanced-product-filters' ); ?>"
>
	<div class="apf-sidebar-header">
		<span class="apf-sidebar-title"><?php esc_html_e( 'Filters', 'advanced-product-filters' ); ?></span>
		<button type="button" class="apf-close-offcanvas" id="apf-close-offcanvas" aria-label="<?php esc_attr_e( 'Close filters', 'advanced-product-filters' ); ?>">
			<svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M1 1L13 13M13 1L1 13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg>
		</button>
	</div>

	<div class="apf-sidebar-body">
		<?php echo $sections_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-rendered, self-escaped section markup. ?>
	</div>

	<?php if ( $show_apply_button ) : ?>
		<div class="apf-sidebar-footer">
			<button type="button" class="apf-btn apf-btn-primary" id="apf-apply-filters"><?php esc_html_e( 'Apply Filters', 'advanced-product-filters' ); ?></button>
		</div>
	<?php endif; ?>
</aside>
