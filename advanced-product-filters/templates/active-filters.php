<?php
/**
 * Template: Active Filters chip list.
 *
 * @package AdvancedProductFilters
 *
 * @var array<string, mixed>              $section Section configuration.
 * @var array<int, array<string, string>> $chips   Chip list: label, param, value.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="apf-section apf-active-filters" data-section-type="active_filters" <?php echo empty( $chips ) ? 'hidden' : ''; ?>>
	<div class="apf-active-filters-header">
		<span class="apf-section-label"><?php echo esc_html( $section['label'] ?? __( 'Active Filters', 'advanced-product-filters' ) ); ?></span>
		<button type="button" class="apf-reset-all" id="apf-reset-all">
			<?php esc_html_e( 'Reset', 'advanced-product-filters' ); ?>
		</button>
	</div>
	<div class="apf-chip-list" id="apf-chip-list">
		<?php foreach ( $chips as $chip ) : ?>
			<span class="apf-chip" data-param="<?php echo esc_attr( $chip['param'] ); ?>" data-value="<?php echo esc_attr( $chip['value'] ); ?>">
				<?php echo esc_html( $chip['label'] ); ?>
				<button type="button" class="apf-chip-remove" aria-label="<?php
					/* translators: %s: active filter label */
					printf( esc_attr__( 'Remove %s filter', 'advanced-product-filters' ), esc_attr( $chip['label'] ) );
				?>">
					<svg width="8" height="8" viewBox="0 0 8 8" fill="none" aria-hidden="true"><path d="M1 1L7 7M7 1L1 7" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" /></svg>
				</button>
			</span>
		<?php endforeach; ?>
	</div>
</div>
