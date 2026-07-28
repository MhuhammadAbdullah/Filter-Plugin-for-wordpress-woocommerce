<?php
/**
 * View: Filter Set builder (locations, sections, behaviour, live preview).
 *
 * @package AdvancedProductFilters
 *
 * @var array<string, mixed> $data        Filter Set data (existing or factory defaults).
 * @var string               $preview_url Live preview iframe URL.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap apf-wrap apf-editor" id="apf-editor-root">
	<div class="apf-editor-header">
		<a class="apf-back-link" href="<?php echo esc_url( admin_url( 'admin.php?page=apf-filter-sets' ) ); ?>">&larr; <?php esc_html_e( 'All Filter Sets', 'advanced-product-filters' ); ?></a>
		<input type="text" id="apf-fs-name" class="apf-name-input" placeholder="<?php esc_attr_e( 'Filter Set name', 'advanced-product-filters' ); ?>" value="<?php echo esc_attr( $data['name'] ); ?>" />
		<div class="apf-editor-header-actions">
			<label class="apf-switch apf-switch-inline">
				<input type="checkbox" id="apf-fs-enabled" <?php checked( ! empty( $data['enabled'] ) ); ?> />
				<span class="apf-switch-track"></span>
			</label>
			<span><?php esc_html_e( 'Enabled', 'advanced-product-filters' ); ?></span>
			<button type="button" class="button button-primary button-hero" id="apf-save-fs"><?php esc_html_e( 'Save Filter Set', 'advanced-product-filters' ); ?></button>
		</div>
	</div>

	<div id="apf-save-notice" class="apf-notice" hidden></div>

	<div class="apf-editor-grid">
		<div class="apf-editor-column apf-editor-locations">
			<h2><?php esc_html_e( 'Assign To', 'advanced-product-filters' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Choose where this Filter Set should appear. When several Filter Sets match, priority (below) decides which one wins.', 'advanced-product-filters' ); ?></p>
			<div id="apf-locations-app"></div>

			<h2><?php esc_html_e( 'Priority', 'advanced-product-filters' ); ?></h2>
			<input type="number" id="apf-fs-priority" min="0" max="999" value="<?php echo esc_attr( (string) $data['priority'] ); ?>" />
			<p class="description"><?php esc_html_e( 'Higher priority Filter Sets win over lower priority ones on overlapping locations.', 'advanced-product-filters' ); ?></p>

			<h2><?php esc_html_e( 'Behaviour', 'advanced-product-filters' ); ?></h2>
			<div id="apf-behavior-app"></div>
		</div>

		<div class="apf-editor-column apf-editor-sections">
			<h2><?php esc_html_e( 'Sections', 'advanced-product-filters' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Drag to reorder. Toggle a section off to hide it without deleting its configuration.', 'advanced-product-filters' ); ?></p>
			<div id="apf-sections-app"></div>
			<div class="apf-add-section-row">
				<select id="apf-add-section-type"></select>
				<button type="button" class="button" id="apf-add-section-btn"><?php esc_html_e( 'Add Section', 'advanced-product-filters' ); ?></button>
			</div>
		</div>

		<div class="apf-editor-column apf-editor-preview">
			<h2><?php esc_html_e( 'Live Preview', 'advanced-product-filters' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Save to refresh the preview with your latest changes.', 'advanced-product-filters' ); ?></p>
			<div class="apf-preview-frame-wrap">
				<iframe id="apf-preview-frame" src="<?php echo esc_url( $preview_url ); ?>" title="<?php esc_attr_e( 'Live preview', 'advanced-product-filters' ); ?>"></iframe>
			</div>
		</div>
	</div>

	<script type="application/json" id="apf-editor-data"><?php echo wp_json_encode( $data ); ?></script>
</div>
