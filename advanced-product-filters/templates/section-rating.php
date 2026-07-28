<?php
/**
 * Template: star rating filter ("4 Stars & Up" style options).
 *
 * @package AdvancedProductFilters
 *
 * @var array<string, mixed> $section  Section configuration.
 * @var int                  $scale    Maximum star rating (usually 5).
 * @var int                  $selected Currently selected minimum rating.
 * @var array<int, int>      $counts   Star value => product count.
 */

defined( 'ABSPATH' ) || exit;

apf_section_open( $section );
?>
<ul class="apf-term-list apf-rating-list">
	<?php for ( $stars = $scale; $stars >= 1; $stars-- ) : ?>
		<li class="apf-term-item">
			<label class="apf-checkbox-label apf-rating-label">
				<input type="radio" name="apf_rating" value="<?php echo esc_attr( (string) $stars ); ?>" <?php checked( $selected === $stars ); ?> />
				<span class="apf-checkbox-box" aria-hidden="true"></span>
				<span class="apf-stars" aria-hidden="true">
					<?php for ( $i = 1; $i <= $scale; $i++ ) : ?>
						<svg width="12" height="12" viewBox="0 0 12 12" class="<?php echo $i <= $stars ? 'apf-star-filled' : 'apf-star-empty'; ?>"><path d="M6 0l1.8 3.9L12 4.6 9 7.5l.8 4.3L6 9.8l-3.8 2 .8-4.3L0 4.6l4.2-.7L6 0z" fill="currentColor" /></svg>
					<?php endfor; ?>
				</span>
				<span class="apf-term-name"><?php esc_html_e( '& Up', 'advanced-product-filters' ); ?></span>
				<?php if ( isset( $counts[ $stars ] ) ) : ?>
					<span class="apf-term-count">(<?php echo esc_html( (string) $counts[ $stars ] ); ?>)</span>
				<?php endif; ?>
			</label>
		</li>
	<?php endfor; ?>
</ul>
<?php
apf_section_close();
