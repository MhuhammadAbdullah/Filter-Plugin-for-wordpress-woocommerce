<?php
/**
 * Drag & drop filter builder for a single filter set.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Admin\Pages;

use MABCommerceFilters\Filters\FilterTypeRegistry;
use MABCommerceFilters\Repositories\FilterRepository;
use MABCommerceFilters\Repositories\FilterSetRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the palette of available filter types, an editable canvas of
 * the filter set's current filters (reorderable via native HTML5 drag &
 * drop, wired up in assets/js/admin.js), and the filter set's own
 * behaviour settings. The whole canvas is serialised client-side and
 * saved in one request via `mabcf_admin_save_filter_set`.
 */
final class FilterBuilderPage extends AbstractAdminPage {

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'mabcf-filter-builder';
	}

	/**
	 * {@inheritDoc}
	 */
	public function title(): string {
		return __( 'Filter Builder', 'mab-commerce-filters' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function render_content(): void {
		$set_id = isset( $_GET['set'] ) ? absint( $_GET['set'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$sets    = new FilterSetRepository();
		$filters = new FilterRepository();

		$set     = $set_id ? $sets->find( $set_id ) : null;
		$rows    = $set ? $filters->get_for_set( $set_id ) : array();
		$is_new  = ! $set;

		$set = $set ?: array(
			'id'          => 0,
			'name'        => '',
			'slug'        => '',
			'description' => '',
			'layout'      => 'sidebar',
			'status'      => 'active',
			'priority'    => 10,
			'settings'    => array(
				'ajax'            => true,
				'instant'         => true,
				'change_url'      => true,
				'apply_button'    => false,
				'collapsible'     => true,
				'columns'         => 4,
				'show_sorting'    => true,
				'infinite_scroll' => false,
			),
		);

		$settings = is_array( $set['settings'] ) ? $set['settings'] : array();

		echo '<div class="mabcf-panel mabcf-builder" data-set-id="' . (int) $set['id'] . '">';
		echo '<input type="hidden" id="mabcf-builder-nonce" value="' . esc_attr( wp_create_nonce( 'mabcf_admin' ) ) . '">';

		echo '<div class="mabcf-builder__top">';
		printf( '<h2>%s</h2>', $is_new ? esc_html__( 'New Filter Set', 'mab-commerce-filters' ) : esc_html__( 'Edit Filter Set', 'mab-commerce-filters' ) );

		echo '<div class="mabcf-field-row">';
		$this->text_field( 'name', __( 'Name', 'mab-commerce-filters' ), $set['name'] );
		$this->select_field(
			'layout',
			__( 'Layout', 'mab-commerce-filters' ),
			$set['layout'],
			array(
				'sidebar'    => __( 'Sidebar', 'mab-commerce-filters' ),
				'horizontal' => __( 'Horizontal Bar', 'mab-commerce-filters' ),
				'offcanvas'  => __( 'Offcanvas / Mobile Drawer', 'mab-commerce-filters' ),
			)
		);
		$this->select_field(
			'status',
			__( 'Status', 'mab-commerce-filters' ),
			$set['status'],
			array( 'active' => __( 'Active', 'mab-commerce-filters' ), 'inactive' => __( 'Inactive', 'mab-commerce-filters' ) )
		);
		echo '</div>';

		echo '<div class="mabcf-field-row">';
		$this->checkbox_field( 'settings_ajax', __( 'AJAX (no page reload)', 'mab-commerce-filters' ), ! empty( $settings['ajax'] ) );
		$this->checkbox_field( 'settings_instant', __( 'Instant filtering', 'mab-commerce-filters' ), ! empty( $settings['instant'] ) );
		$this->checkbox_field( 'settings_change_url', __( 'Update browser URL', 'mab-commerce-filters' ), ! empty( $settings['change_url'] ) );
		$this->checkbox_field( 'settings_apply_button', __( 'Show Apply button', 'mab-commerce-filters' ), ! empty( $settings['apply_button'] ) );
		$this->checkbox_field( 'settings_collapsible', __( 'Collapsible accordion sections', 'mab-commerce-filters' ), ! empty( $settings['collapsible'] ) );
		$this->checkbox_field( 'settings_show_sorting', __( 'Show sorting dropdown', 'mab-commerce-filters' ), ! empty( $settings['show_sorting'] ) );
		$this->checkbox_field( 'settings_infinite_scroll', __( 'Infinite scroll (instead of pagination)', 'mab-commerce-filters' ), ! empty( $settings['infinite_scroll'] ) );
		$this->text_field( 'settings_columns', __( 'Grid Columns', 'mab-commerce-filters' ), (string) ( $settings['columns'] ?? 4 ), 'number' );
		echo '</div>';
		echo '</div>';

		echo '<div class="mabcf-builder__body">';
		$this->render_palette();
		$this->render_canvas( $rows );
		echo '</div>';

		echo '<div class="mabcf-builder__footer">';
		printf( '<button type="button" class="button button-primary button-hero mabcf-save-set">%s</button>', esc_html__( 'Save Filter Set', 'mab-commerce-filters' ) );
		printf( '<span class="mabcf-save-status" aria-live="polite"></span>' );
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Renders the left-hand palette of draggable filter type blocks.
	 */
	private function render_palette(): void {
		$blocks = array(
			'category'  => __( 'Category', 'mab-commerce-filters' ),
			'taxonomy'  => __( 'Taxonomy (Tags/Brand/Custom)', 'mab-commerce-filters' ),
			'attribute' => __( 'Attribute (Color/Size/Swatch)', 'mab-commerce-filters' ),
			'price'     => __( 'Price Slider', 'mab-commerce-filters' ),
			'rating'    => __( 'Rating', 'mab-commerce-filters' ),
			'stock'     => __( 'Stock Status', 'mab-commerce-filters' ),
			'sale'      => __( 'Sale / Featured / New', 'mab-commerce-filters' ),
			'search'    => __( 'Search Box', 'mab-commerce-filters' ),
			'meta'      => __( 'Custom Field', 'mab-commerce-filters' ),
		);

		echo '<div class="mabcf-builder__palette">';
		echo '<h3>' . esc_html__( 'Filter Types', 'mab-commerce-filters' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'Drag a filter type into the canvas to add it.', 'mab-commerce-filters' ) . '</p>';
		foreach ( $blocks as $type => $label ) {
			printf(
				'<div class="mabcf-palette-item" draggable="true" data-type="%s">%s</div>',
				esc_attr( $type ),
				esc_html( $label )
			);
		}
		echo '</div>';
	}

	/**
	 * Renders the canvas of currently configured filters.
	 *
	 * @param array<int, array<string, mixed>> $rows Existing filter rows.
	 */
	private function render_canvas( array $rows ): void {
		echo '<div class="mabcf-builder__canvas-wrap">';
		echo '<h3>' . esc_html__( 'Active Filters', 'mab-commerce-filters' ) . '</h3>';
		echo '<div class="mabcf-builder__canvas" id="mabcf-canvas">';

		foreach ( $rows as $row ) {
			$this->render_canvas_item( $row );
		}

		echo '</div>';
		echo '<p class="mabcf-builder__empty-hint description">' . esc_html__( 'Drop filter types here, or drag existing filters to reorder them.', 'mab-commerce-filters' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Renders a single canvas item (one configured filter).
	 *
	 * @param array<string, mixed> $row Filter row.
	 */
	private function render_canvas_item( array $row ): void {
		$settings = is_array( $row['settings'] ) ? $row['settings'] : array();
		$labels   = FilterTypeRegistry::instance()->labels();

		printf(
			'<div class="mabcf-builder-item" draggable="true" data-id="%1$d" data-type="%2$s">
				<div class="mabcf-builder-item__head">
					<span class="mabcf-builder-item__drag" aria-hidden="true">::</span>
					<strong class="mabcf-builder-item__type">%3$s</strong>
					<input type="text" class="mabcf-field-label" value="%4$s" placeholder="%5$s">
					<button type="button" class="button-link mabcf-item-toggle">%6$s</button>
					<button type="button" class="button-link-delete mabcf-item-remove">%7$s</button>
				</div>
				<div class="mabcf-builder-item__body">
					<div class="mabcf-field-row">
						<label>%8$s
							<input type="text" class="mabcf-field-source" value="%9$s" placeholder="%10$s">
						</label>
						<label>%11$s
							<select class="mabcf-field-display">%12$s</select>
						</label>
					</div>
					<div class="mabcf-field-row">
						<label><input type="checkbox" class="mabcf-field-show-count" %13$s> %14$s</label>
						<label><input type="checkbox" class="mabcf-field-collapsible" %15$s> %16$s</label>
						<label><input type="checkbox" class="mabcf-field-searchable" %17$s> %18$s</label>
					</div>
				</div>
			</div>',
			(int) $row['id'],
			esc_attr( $row['type'] ),
			esc_html( $labels[ $row['type'] ] ?? $row['type'] ),
			esc_attr( $row['label'] ),
			esc_attr__( 'Filter label', 'mab-commerce-filters' ),
			esc_html__( 'Edit', 'mab-commerce-filters' ),
			esc_html__( 'Remove', 'mab-commerce-filters' ),
			esc_html__( 'Source (taxonomy/attribute/meta key)', 'mab-commerce-filters' ),
			esc_attr( $row['source_key'] ?? '' ),
			esc_attr__( 'e.g. pa_color, product_tag, _brand', 'mab-commerce-filters' ),
			esc_html__( 'Display Style', 'mab-commerce-filters' ),
			$this->display_style_options( $row['type'], $row['display_style'] ?? '' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			checked( ! empty( $settings['show_count'] ), true, false ),
			esc_html__( 'Show product count', 'mab-commerce-filters' ),
			checked( ! empty( $settings['collapsible'] ), true, false ),
			esc_html__( 'Collapsible', 'mab-commerce-filters' ),
			checked( ! empty( $settings['searchable'] ), true, false ),
			esc_html__( 'Searchable list', 'mab-commerce-filters' )
		);
	}

	/**
	 * Builds the `<option>` list for a filter type's display styles.
	 *
	 * @param string $type    Filter type.
	 * @param string $current Currently selected display style.
	 */
	private function display_style_options( string $type, string $current ): string {
		$styles_by_type = array(
			'category'  => array( 'list' => __( 'List', 'mab-commerce-filters' ) ),
			'taxonomy'  => array( 'list' => __( 'List', 'mab-commerce-filters' ), 'pill' => __( 'Pills', 'mab-commerce-filters' ), 'brand-image' => __( 'Brand (Image)', 'mab-commerce-filters' ), 'brand-logo' => __( 'Brand (Logo)', 'mab-commerce-filters' ) ),
			'attribute' => array( 'color' => __( 'Color Swatch', 'mab-commerce-filters' ), 'image' => __( 'Image Swatch', 'mab-commerce-filters' ), 'pill' => __( 'Size Pill', 'mab-commerce-filters' ), 'list' => __( 'List', 'mab-commerce-filters' ) ),
			'price'     => array( 'slider' => __( 'Dual Slider', 'mab-commerce-filters' ) ),
			'meta'      => array( 'text' => __( 'Text', 'mab-commerce-filters' ), 'number' => __( 'Number Range', 'mab-commerce-filters' ), 'boolean' => __( 'Toggle', 'mab-commerce-filters' ), 'date' => __( 'Date Range', 'mab-commerce-filters' ) ),
		);

		$styles = $styles_by_type[ $type ] ?? array( 'list' => __( 'List', 'mab-commerce-filters' ) );
		$html   = '';

		foreach ( $styles as $value => $label ) {
			$html .= sprintf( '<option value="%s"%s>%s</option>', esc_attr( $value ), selected( $current, $value, false ), esc_html( $label ) );
		}

		return $html;
	}

	/**
	 * Renders a labelled text input.
	 *
	 * @param string $id    Field id/name suffix.
	 * @param string $label Field label.
	 * @param string $value Current value.
	 * @param string $type  Input type.
	 */
	private function text_field( string $id, string $label, string $value, string $type = 'text' ): void {
		printf(
			'<label class="mabcf-field">%s<input type="%s" id="mabcf-field-%s" value="%s"></label>',
			esc_html( $label ),
			esc_attr( $type ),
			esc_attr( $id ),
			esc_attr( $value )
		);
	}

	/**
	 * Renders a labelled select input.
	 *
	 * @param string                $id      Field id/name suffix.
	 * @param string                $label   Field label.
	 * @param string                $value   Current value.
	 * @param array<string, string> $options Value => label options.
	 */
	private function select_field( string $id, string $label, string $value, array $options ): void {
		echo '<label class="mabcf-field">' . esc_html( $label );
		printf( '<select id="mabcf-field-%s">', esc_attr( $id ) );
		foreach ( $options as $option_value => $option_label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $option_value ), selected( $value, $option_value, false ), esc_html( $option_label ) );
		}
		echo '</select></label>';
	}

	/**
	 * Renders a labelled checkbox input.
	 *
	 * @param string $id      Field id/name suffix.
	 * @param string $label   Field label.
	 * @param bool   $checked Whether it is currently checked.
	 */
	private function checkbox_field( string $id, string $label, bool $checked ): void {
		printf(
			'<label class="mabcf-field mabcf-field--checkbox"><input type="checkbox" id="mabcf-field-%s" %s> %s</label>',
			esc_attr( $id ),
			checked( $checked, true, false ),
			esc_html( $label )
		);
	}
}
