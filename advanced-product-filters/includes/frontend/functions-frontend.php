<?php
/**
 * Shared markup helpers used by every collapsible section template.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'apf_section_open' ) ) {
	/**
	 * Prints the opening markup for a collapsible section: the header
	 * button (label + chevron) and the start of the collapsible content
	 * wrapper. Every section template must pair this with `apf_section_close()`.
	 *
	 * @param array<string, mixed> $section Section configuration.
	 * @return void
	 */
	function apf_section_open( array $section ): void {
		$id        = $section['id'] ?? $section['type'] ?? wp_generate_uuid4();
		$label     = $section['label'] ?? '';
		$collapsed = ! empty( $section['collapsed'] );
		$panel_id  = 'apf-section-panel-' . sanitize_html_class( $id );
		?>
		<div class="apf-section" data-section-id="<?php echo esc_attr( $id ); ?>" data-section-type="<?php echo esc_attr( $section['type'] ?? '' ); ?>">
			<h3 class="apf-section-title">
				<button
					type="button"
					class="apf-section-toggle"
					aria-expanded="<?php echo $collapsed ? 'false' : 'true'; ?>"
					aria-controls="<?php echo esc_attr( $panel_id ); ?>"
				>
					<span class="apf-section-label"><?php echo esc_html( $label ); ?></span>
					<svg class="apf-chevron" width="10" height="6" viewBox="0 0 10 6" fill="none" aria-hidden="true">
						<path d="M1 1L5 5L9 1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
					</svg>
				</button>
			</h3>
			<div class="apf-section-content" id="<?php echo esc_attr( $panel_id ); ?>" <?php echo $collapsed ? 'hidden' : ''; ?>>
		<?php
	}
}

if ( ! function_exists( 'apf_section_close' ) ) {
	/**
	 * Prints the closing markup opened by `apf_section_open()`.
	 *
	 * @return void
	 */
	function apf_section_close(): void {
		?>
			</div>
		</div>
		<?php
	}
}
