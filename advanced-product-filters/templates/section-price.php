<?php
/**
 * Template: Price dual-handle range slider.
 *
 * @package AdvancedProductFilters
 *
 * @var array<string, mixed> $section         Section configuration.
 * @var float                 $range_min       Lowest possible price across the matched catalog.
 * @var float                 $range_max       Highest possible price across the matched catalog.
 * @var float                 $selected_min    Currently selected minimum.
 * @var float                 $selected_max    Currently selected maximum.
 * @var string                $currency_symbol Store currency symbol.
 */

defined( 'ABSPATH' ) || exit;

apf_section_open( $section );
?>
<div
	class="apf-price-slider"
	data-min="<?php echo esc_attr( (string) $range_min ); ?>"
	data-max="<?php echo esc_attr( (string) $range_max ); ?>"
>
	<div class="apf-price-slider-track">
		<div class="apf-price-slider-range" id="apf-price-range-fill"></div>
	</div>
	<input
		type="range"
		class="apf-price-input apf-price-input-min"
		id="apf-price-input-min"
		min="<?php echo esc_attr( (string) $range_min ); ?>"
		max="<?php echo esc_attr( (string) $range_max ); ?>"
		value="<?php echo esc_attr( (string) $selected_min ); ?>"
		step="1"
		aria-label="<?php esc_attr_e( 'Minimum price', 'advanced-product-filters' ); ?>"
		data-param="apf_price_min"
	/>
	<input
		type="range"
		class="apf-price-input apf-price-input-max"
		id="apf-price-input-max"
		min="<?php echo esc_attr( (string) $range_min ); ?>"
		max="<?php echo esc_attr( (string) $range_max ); ?>"
		value="<?php echo esc_attr( (string) $selected_max ); ?>"
		step="1"
		aria-label="<?php esc_attr_e( 'Maximum price', 'advanced-product-filters' ); ?>"
		data-param="apf_price_max"
	/>
</div>
<div class="apf-price-labels">
	<span id="apf-price-label-min"><?php echo esc_html( $currency_symbol . number_format_i18n( $selected_min ) ); ?></span>
	<span class="apf-price-labels-separator">—</span>
	<span id="apf-price-label-max"><?php echo esc_html( $currency_symbol . number_format_i18n( $selected_max ) ); ?></span>
</div>
<?php
apf_section_close();
