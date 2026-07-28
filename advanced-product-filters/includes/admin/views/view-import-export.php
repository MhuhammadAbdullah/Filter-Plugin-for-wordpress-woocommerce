<?php
/**
 * View: import / export page.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap apf-wrap">
	<h1><?php esc_html_e( 'Advanced Product Filters — Import / Export', 'advanced-product-filters' ); ?></h1>

	<div class="apf-import-export-grid">
		<div class="apf-card">
			<h2><?php esc_html_e( 'Export', 'advanced-product-filters' ); ?></h2>
			<p><?php esc_html_e( 'Download every Filter Set and the global settings as a JSON file you can import on another site.', 'advanced-product-filters' ); ?></p>
			<button type="button" class="button button-primary" id="apf-export-btn"><?php esc_html_e( 'Download Export File', 'advanced-product-filters' ); ?></button>
		</div>

		<div class="apf-card">
			<h2><?php esc_html_e( 'Import', 'advanced-product-filters' ); ?></h2>
			<p><?php esc_html_e( 'Select a previously exported JSON file. Importing adds new Filter Sets; it never deletes existing ones.', 'advanced-product-filters' ); ?></p>
			<input type="file" id="apf-import-file" accept="application/json" />
			<button type="button" class="button button-primary" id="apf-import-btn"><?php esc_html_e( 'Import', 'advanced-product-filters' ); ?></button>
			<div id="apf-import-result" class="apf-notice" hidden></div>
		</div>
	</div>
</div>
