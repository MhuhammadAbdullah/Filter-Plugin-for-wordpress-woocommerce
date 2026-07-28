<?php
/**
 * View: Filter Sets list page.
 *
 * @package AdvancedProductFilters
 *
 * @var APF_Filter_Set[] $filter_sets Every stored Filter Set.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap apf-wrap">
	<div class="apf-page-header">
		<h1><?php esc_html_e( 'Filter Sets', 'advanced-product-filters' ); ?></h1>
		<a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=apf-filter-set-edit' ) ); ?>">
			<?php esc_html_e( 'Add New', 'advanced-product-filters' ); ?>
		</a>
	</div>

	<p class="description">
		<?php esc_html_e( 'Create unlimited Filter Sets and assign each one to the shop page, specific categories, tags, brands, custom taxonomies, pages or Elementor templates. Drag rows to change which Filter Set wins when several match the same page.', 'advanced-product-filters' ); ?>
	</p>

	<?php if ( empty( $filter_sets ) ) : ?>
		<div class="apf-empty-state">
			<p><?php esc_html_e( 'No Filter Sets yet. Create your first one to start filtering your shop.', 'advanced-product-filters' ); ?></p>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=apf-filter-set-edit' ) ); ?>">
				<?php esc_html_e( 'Add New Filter Set', 'advanced-product-filters' ); ?>
			</a>
		</div>
	<?php else : ?>
		<table class="widefat apf-filter-sets-table" id="apf-filter-sets-table">
			<thead>
				<tr>
					<th class="apf-col-handle"></th>
					<th><?php esc_html_e( 'Name', 'advanced-product-filters' ); ?></th>
					<th><?php esc_html_e( 'Assigned To', 'advanced-product-filters' ); ?></th>
					<th><?php esc_html_e( 'Sections', 'advanced-product-filters' ); ?></th>
					<th><?php esc_html_e( 'Status', 'advanced-product-filters' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'advanced-product-filters' ); ?></th>
				</tr>
			</thead>
			<tbody id="apf-filter-sets-body">
				<?php foreach ( $filter_sets as $filter_set ) : ?>
					<tr data-id="<?php echo esc_attr( (string) $filter_set->get_id() ); ?>">
						<td class="apf-col-handle"><span class="apf-drag-handle dashicons dashicons-menu" aria-hidden="true"></span></td>
						<td>
							<strong>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=apf-filter-set-edit&id=' . $filter_set->get_id() ) ); ?>">
									<?php echo esc_html( $filter_set->get_name() ?: __( '(no name)', 'advanced-product-filters' ) ); ?>
								</a>
							</strong>
						</td>
						<td><?php echo esc_html( apf_describe_locations( $filter_set->get_locations() ) ); ?></td>
						<td><?php echo esc_html( (string) count( $filter_set->get_enabled_sections() ) ) . ' ' . esc_html__( 'enabled', 'advanced-product-filters' ); ?></td>
						<td>
							<label class="apf-switch">
								<input type="checkbox" class="apf-toggle-enabled" data-id="<?php echo esc_attr( (string) $filter_set->get_id() ); ?>" <?php checked( $filter_set->is_enabled() ); ?> />
								<span class="apf-switch-track"></span>
							</label>
						</td>
						<td class="apf-col-actions">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=apf-filter-set-edit&id=' . $filter_set->get_id() ) ); ?>"><?php esc_html_e( 'Edit', 'advanced-product-filters' ); ?></a>
							|
							<a href="#" class="apf-duplicate" data-id="<?php echo esc_attr( (string) $filter_set->get_id() ); ?>"><?php esc_html_e( 'Duplicate', 'advanced-product-filters' ); ?></a>
							|
							<a href="#" class="apf-delete" data-id="<?php echo esc_attr( (string) $filter_set->get_id() ); ?>"><?php esc_html_e( 'Delete', 'advanced-product-filters' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
