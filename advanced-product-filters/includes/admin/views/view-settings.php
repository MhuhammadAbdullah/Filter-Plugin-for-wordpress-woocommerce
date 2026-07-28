<?php
/**
 * View: global settings page.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

$settings = APF_Settings::all();
?>
<div class="wrap apf-wrap">
	<h1><?php esc_html_e( 'Advanced Product Filters — Settings', 'advanced-product-filters' ); ?></h1>

	<?php if ( isset( $_GET['apf-saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'advanced-product-filters' ); ?></p></div>
	<?php endif; ?>

	<form id="apf-settings-form" method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
		<input type="hidden" name="action" value="apf_save_settings" />
		<?php wp_nonce_field( 'apf_settings_save', 'apf_settings_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Instant AJAX Filtering', 'advanced-product-filters' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="instant_ajax" value="1" <?php checked( $settings['instant_ajax'] ); ?> />
						<?php esc_html_e( 'Update results as soon as a filter is selected.', 'advanced-product-filters' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Apply Button', 'advanced-product-filters' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="show_apply_button" value="1" <?php checked( $settings['show_apply_button'] ); ?> />
						<?php esc_html_e( 'Show an "Apply Filters" button (useful when Instant AJAX is off).', 'advanced-product-filters' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Clear Button', 'advanced-product-filters' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="show_clear_button" value="1" <?php checked( $settings['show_clear_button'] ); ?> />
						<?php esc_html_e( 'Show a "Reset All" button above the active filters.', 'advanced-product-filters' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Sync Filters To URL', 'advanced-product-filters' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="url_sync" value="1" <?php checked( $settings['url_sync'] ); ?> />
						<?php esc_html_e( 'Reflect active filters in the address bar so results are bookmarkable and the browser Back button works.', 'advanced-product-filters' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Sticky Sidebar', 'advanced-product-filters' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="sticky_sidebar" value="1" <?php checked( $settings['sticky_sidebar'] ); ?> />
						<?php esc_html_e( 'Keep the desktop sidebar in view while the page scrolls.', 'advanced-product-filters' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="apf-mobile-breakpoint"><?php esc_html_e( 'Mobile Offcanvas Breakpoint (px)', 'advanced-product-filters' ); ?></label></th>
				<td><input type="number" id="apf-mobile-breakpoint" name="mobile_breakpoint" min="320" max="1400" value="<?php echo esc_attr( (string) $settings['mobile_breakpoint'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Offcanvas Position', 'advanced-product-filters' ); ?></th>
				<td>
					<select name="offcanvas_position">
						<option value="left" <?php selected( $settings['offcanvas_position'], 'left' ); ?>><?php esc_html_e( 'Left', 'advanced-product-filters' ); ?></option>
						<option value="right" <?php selected( $settings['offcanvas_position'], 'right' ); ?>><?php esc_html_e( 'Right', 'advanced-product-filters' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Pagination Mode', 'advanced-product-filters' ); ?></th>
				<td>
					<select name="pagination_mode">
						<option value="pages" <?php selected( $settings['pagination_mode'], 'pages' ); ?>><?php esc_html_e( 'Classic Pagination', 'advanced-product-filters' ); ?></option>
						<option value="infinite" <?php selected( $settings['pagination_mode'], 'infinite' ); ?>><?php esc_html_e( 'Infinite Scroll', 'advanced-product-filters' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="apf-products-per-page"><?php esc_html_e( 'Products Per Page', 'advanced-product-filters' ); ?></label></th>
				<td><input type="number" id="apf-products-per-page" name="products_per_page" min="1" max="200" value="<?php echo esc_attr( (string) $settings['products_per_page'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="apf-new-arrival-days"><?php esc_html_e( '"New Arrival" Window (days)', 'advanced-product-filters' ); ?></label></th>
				<td><input type="number" id="apf-new-arrival-days" name="new_arrival_days" min="1" max="365" value="<?php echo esc_attr( (string) $settings['new_arrival_days'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Primary Color', 'advanced-product-filters' ); ?></th>
				<td><input type="text" class="apf-color-field" name="primary_color" value="<?php echo esc_attr( $settings['primary_color'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Accent Color', 'advanced-product-filters' ); ?></th>
				<td><input type="text" class="apf-color-field" name="accent_color" value="<?php echo esc_attr( $settings['accent_color'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable Caching', 'advanced-product-filters' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="enable_cache" value="1" <?php checked( $settings['enable_cache'] ); ?> />
						<?php esc_html_e( 'Cache facet counts and price ranges (recommended for large catalogs).', 'advanced-product-filters' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="apf-cache-ttl"><?php esc_html_e( 'Cache Lifetime (seconds)', 'advanced-product-filters' ); ?></label></th>
				<td><input type="number" id="apf-cache-ttl" name="cache_ttl" min="30" max="86400" value="<?php echo esc_attr( (string) $settings['cache_ttl'] ); ?>" /></td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'advanced-product-filters' ); ?></button>
			<button type="button" class="button apf-reset-plugin"><?php esc_html_e( 'Reset Plugin Settings', 'advanced-product-filters' ); ?></button>
		</p>
	</form>
</div>
