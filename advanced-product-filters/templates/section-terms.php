<?php
/**
 * Template: generic taxonomy term list — covers Categories, Attributes
 * (Color/Image/Label swatches), Brand and Tags, in every supported input
 * type (checkbox, radio, buttons, color, image, label, dropdown, tree,
 * AJAX search).
 *
 * @package AdvancedProductFilters
 *
 * @var array<string, mixed> $section    Section configuration.
 * @var string                $taxonomy   Taxonomy slug.
 * @var string                $param      Query string parameter name.
 * @var string                $input_type Input type slug.
 * @var array<int, array{term: WP_Term, children: array}> $terms Term list (nested when tree).
 * @var array<int, int>      $counts     Term ID => product count.
 * @var string[]             $selected   Currently selected term slugs.
 * @var bool                 $show_count Whether to render product counts.
 */

defined( 'ABSPATH' ) || exit;

apf_section_open( $section );

if ( ! empty( $section['searchable'] ) && 'search' !== $input_type ) {
	?>
	<div class="apf-term-search">
		<input
			type="search"
			class="apf-term-search-input"
			placeholder="<?php esc_attr_e( 'Search…', 'advanced-product-filters' ); ?>"
			aria-label="<?php
				/* translators: %s: section label */
				printf( esc_attr__( 'Search %s', 'advanced-product-filters' ), esc_attr( $section['label'] ?? '' ) );
			?>"
		/>
	</div>
	<?php
}

if ( 'search' === $input_type ) {
	?>
	<div class="apf-ajax-search" data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>" data-param="<?php echo esc_attr( $param ); ?>">
		<input
			type="search"
			class="apf-ajax-search-input"
			placeholder="<?php esc_attr_e( 'Type to search…', 'advanced-product-filters' ); ?>"
		/>
		<div class="apf-ajax-search-results" hidden></div>
		<div class="apf-selected-pills" id="apf-selected-pills-<?php echo esc_attr( $taxonomy ); ?>">
			<?php foreach ( $selected as $slug ) :
				$term = get_term_by( 'slug', $slug, $taxonomy );
				if ( ! $term || is_wp_error( $term ) ) {
					continue;
				}
				?>
				<span class="apf-chip apf-chip-sm" data-param="<?php echo esc_attr( $param ); ?>" data-value="<?php echo esc_attr( $slug ); ?>">
					<?php echo esc_html( $term->name ); ?>
					<button type="button" class="apf-chip-remove" aria-label="<?php esc_attr_e( 'Remove', 'advanced-product-filters' ); ?>">×</button>
				</span>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
	apf_section_close();
	return;
}

/**
 * Renders one term row (used recursively for tree view children).
 *
 * @param WP_Term $term     Term to render.
 * @param array    $children Nested child term branches.
 * @param int      $depth    Current nesting depth, for indentation.
 * @return void
 */
$render_term = static function ( WP_Term $term, array $children, int $depth ) use ( &$render_term, $param, $input_type, $counts, $selected, $show_count, $taxonomy ) {
	$is_selected = in_array( $term->slug, $selected, true );
	$count       = $counts[ $term->term_id ] ?? null;
	$has_kids    = ! empty( $children );
	$field_type  = 'radio' === $input_type ? 'radio' : 'checkbox';
	?>
	<li class="apf-term-item apf-term-depth-<?php echo esc_attr( (string) $depth ); ?><?php echo $has_kids ? ' apf-has-children' : ''; ?>">
		<div class="apf-term-row">
			<?php if ( $has_kids ) : ?>
				<button type="button" class="apf-term-expand" aria-expanded="false" aria-label="<?php esc_attr_e( 'Toggle subcategories', 'advanced-product-filters' ); ?>">
					<svg width="8" height="8" viewBox="0 0 8 8" fill="none" aria-hidden="true"><path d="M2 1L6 4L2 7" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" /></svg>
				</button>
			<?php endif; ?>

			<?php if ( 'color' === $input_type || 'image' === $input_type ) : ?>
				<label class="apf-swatch apf-swatch-<?php echo esc_attr( $input_type ); ?><?php echo $is_selected ? ' apf-selected' : ''; ?>">
					<input type="<?php echo esc_attr( $field_type ); ?>" name="<?php echo esc_attr( $param ); ?>[]" value="<?php echo esc_attr( $term->slug ); ?>" <?php checked( $is_selected ); ?> />
					<?php if ( 'color' === $input_type ) : ?>
						<span class="apf-swatch-circle" style="--apf-swatch-color: <?php echo esc_attr( APF_Term_Swatches::get_color( $term->term_id ) ?: '#e2e2e2' ); ?>"></span>
					<?php else : ?>
						<?php $image = APF_Term_Swatches::get_image_url( $term->term_id ); ?>
						<span class="apf-swatch-image" <?php echo $image ? 'style="background-image:url(' . esc_url( $image ) . ')"' : ''; ?>></span>
					<?php endif; ?>
					<span class="apf-swatch-label"><?php echo esc_html( $term->name ); ?></span>
				</label>
			<?php elseif ( 'buttons' === $input_type || 'label' === $input_type ) : ?>
				<label class="apf-pill<?php echo $is_selected ? ' apf-selected' : ''; ?>">
					<input type="<?php echo esc_attr( $field_type ); ?>" name="<?php echo esc_attr( $param ); ?>[]" value="<?php echo esc_attr( $term->slug ); ?>" <?php checked( $is_selected ); ?> />
					<span><?php echo esc_html( $term->name ); ?></span>
				</label>
			<?php else : ?>
				<label class="apf-checkbox-label">
					<input type="<?php echo esc_attr( $field_type ); ?>" name="<?php echo esc_attr( $param ); ?>[]" value="<?php echo esc_attr( $term->slug ); ?>" <?php checked( $is_selected ); ?> />
					<span class="apf-checkbox-box" aria-hidden="true"></span>
					<span class="apf-term-name"><?php echo esc_html( $term->name ); ?></span>
					<?php if ( $show_count ) : ?>
						<span class="apf-term-count">(<?php echo esc_html( (string) ( $count ?? $term->count ) ); ?>)</span>
					<?php endif; ?>
				</label>
			<?php endif; ?>
		</div>

		<?php if ( $has_kids ) : ?>
			<ul class="apf-term-children" hidden>
				<?php foreach ( $children as $branch ) : ?>
					<?php $render_term( $branch['term'], $branch['children'], $depth + 1 ); ?>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</li>
	<?php
};
?>

<?php if ( 'dropdown' === $input_type ) : ?>
	<select class="apf-dropdown" name="<?php echo esc_attr( $param ); ?>">
		<option value=""><?php esc_html_e( 'All', 'advanced-product-filters' ); ?></option>
		<?php foreach ( $terms as $branch ) : ?>
			<?php $term = $branch['term']; ?>
			<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( in_array( $term->slug, $selected, true ) ); ?>>
				<?php echo esc_html( $term->name ); ?><?php echo $show_count ? ' (' . esc_html( (string) ( $counts[ $term->term_id ] ?? $term->count ) ) . ')' : ''; ?>
			</option>
		<?php endforeach; ?>
	</select>
<?php else : ?>
	<ul class="apf-term-list apf-term-list-<?php echo esc_attr( $input_type ); ?>">
		<?php foreach ( $terms as $branch ) : ?>
			<?php $render_term( $branch['term'], $branch['children'], 0 ); ?>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>

<?php
apf_section_close();
