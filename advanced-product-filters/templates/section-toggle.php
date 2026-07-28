<?php
/**
 * Template: boolean toggle section (On Sale / Featured / New Arrivals).
 *
 * @package AdvancedProductFilters
 *
 * @var array<string, mixed> $section  Section configuration.
 * @var string                $param    Query string parameter name.
 * @var bool                  $selected Whether the toggle is currently on.
 * @var int|null              $count    Matched product count, when counting is enabled.
 */

defined( 'ABSPATH' ) || exit;

apf_section_open( $section );
?>
<label class="apf-toggle-row">
	<span class="apf-toggle-text">
		<?php echo esc_html( $section['label'] ?? '' ); ?>
		<?php if ( null !== $count ) : ?>
			<span class="apf-term-count">(<?php echo esc_html( (string) $count ); ?>)</span>
		<?php endif; ?>
	</span>
	<span class="apf-switch">
		<input type="checkbox" name="<?php echo esc_attr( $param ); ?>" value="1" <?php checked( $selected ); ?> />
		<span class="apf-switch-track"></span>
	</span>
</label>
<?php
apf_section_close();
