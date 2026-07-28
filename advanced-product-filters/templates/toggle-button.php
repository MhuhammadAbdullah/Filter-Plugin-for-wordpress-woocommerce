<?php
/**
 * Template: mobile "Filters" toggle button that opens the offcanvas sidebar.
 *
 * @package AdvancedProductFilters
 *
 * @var int $active_count Number of currently active filters, for the badge.
 */

defined( 'ABSPATH' ) || exit;
?>
<button type="button" class="apf-toggle-button" id="apf-toggle-sidebar" aria-haspopup="true" aria-controls="apf-sidebar" aria-expanded="false">
	<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
		<path d="M1 3H15M4 8H12M6.5 13H9.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
	</svg>
	<span><?php esc_html_e( 'Filters', 'advanced-product-filters' ); ?></span>
	<?php if ( $active_count > 0 ) : ?>
		<span class="apf-toggle-badge" id="apf-toggle-badge"><?php echo esc_html( (string) $active_count ); ?></span>
	<?php endif; ?>
</button>
