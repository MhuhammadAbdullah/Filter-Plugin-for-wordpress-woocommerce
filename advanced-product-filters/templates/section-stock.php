<?php
/**
 * Template: stock status filter.
 *
 * @package AdvancedProductFilters
 *
 * @var array<string, mixed> $section  Section configuration.
 * @var string[]             $statuses Stock status slugs.
 * @var string[]             $selected Currently selected statuses.
 * @var array<string, int>   $counts   Status slug => product count.
 */

defined( 'ABSPATH' ) || exit;

$labels = array(
	'instock'     => __( 'In Stock', 'advanced-product-filters' ),
	'outofstock'  => __( 'Out of Stock', 'advanced-product-filters' ),
	'onbackorder' => __( 'On Backorder', 'advanced-product-filters' ),
);

apf_section_open( $section );
?>
<ul class="apf-term-list">
	<?php foreach ( $statuses as $status ) : ?>
		<li class="apf-term-item">
			<label class="apf-checkbox-label">
				<input type="checkbox" name="apf_stock[]" value="<?php echo esc_attr( $status ); ?>" <?php checked( in_array( $status, $selected, true ) ); ?> />
				<span class="apf-checkbox-box" aria-hidden="true"></span>
				<span class="apf-term-name"><?php echo esc_html( $labels[ $status ] ?? $status ); ?></span>
				<?php if ( isset( $counts[ $status ] ) ) : ?>
					<span class="apf-term-count">(<?php echo esc_html( (string) $counts[ $status ] ); ?>)</span>
				<?php endif; ?>
			</label>
		</li>
	<?php endforeach; ?>
</ul>
<?php
apf_section_close();
