<?php
/**
 * Adds Color / Image swatch fields to attribute & brand taxonomy terms.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce does not natively store a colour or image against an
 * attribute term, so Color Swatches / Image Swatches sections would have
 * nothing to render without this. Adds a colour picker and a media
 * picker to the term add/edit screens for every `pa_*` attribute
 * taxonomy plus the detected brand taxonomy, persisted as term meta.
 */
final class APF_Term_Swatches {

	/**
	 * Term meta key storing the swatch hex colour.
	 *
	 * @var string
	 */
	public const META_COLOR = '_apf_swatch_color';

	/**
	 * Term meta key storing the swatch image attachment ID.
	 *
	 * @var string
	 */
	public const META_IMAGE = '_apf_swatch_image';

	/**
	 * Wires the term form hooks for every eligible taxonomy.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_hooks' ), 20 );
	}

	/**
	 * Registers the add/edit form hooks once taxonomies are available.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		foreach ( $this->eligible_taxonomies() as $taxonomy ) {
			add_action( "{$taxonomy}_add_form_fields", array( $this, 'render_add_fields' ) );
			add_action( "{$taxonomy}_edit_form_fields", array( $this, 'render_edit_fields' ), 10, 2 );
			add_action( "created_{$taxonomy}", array( $this, 'save_fields' ) );
			add_action( "edited_{$taxonomy}", array( $this, 'save_fields' ) );
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_term_assets' ) );
	}

	/**
	 * Taxonomies that get swatch fields: every WooCommerce attribute
	 * taxonomy plus the detected brand taxonomy.
	 *
	 * @return string[]
	 */
	private function eligible_taxonomies(): array {
		$taxonomies = array_keys( APF_Taxonomies::get_attribute_taxonomies() );
		$brand      = APF_Taxonomies::get_brand_taxonomy();

		if ( $brand ) {
			$taxonomies[] = $brand;
		}

		return $taxonomies;
	}

	/**
	 * Loads the colour picker + media uploader on term edit screens.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_term_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'term.php', 'edit-tags.php' ), true ) ) {
			return;
		}

		$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! in_array( $taxonomy, $this->eligible_taxonomies(), true ) ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_media();
		wp_enqueue_script( 'apf-term-swatches', APF_URL . 'assets/js/admin-term-swatches.js', array( 'wp-color-picker', 'jquery' ), APF_VERSION, true );
		wp_localize_script(
			'apf-term-swatches',
			'apfTermSwatchesI18n',
			array(
				'selectImage' => __( 'Select Swatch Image', 'advanced-product-filters' ),
				'useImage'    => __( 'Use Image', 'advanced-product-filters' ),
			)
		);
	}

	/**
	 * Renders the swatch fields on the "Add new term" screen.
	 *
	 * @return void
	 */
	public function render_add_fields(): void {
		?>
		<div class="form-field apf-term-swatch-field">
			<label for="apf-swatch-color"><?php esc_html_e( 'Swatch Color', 'advanced-product-filters' ); ?></label>
			<input type="text" id="apf-swatch-color" name="apf_swatch_color" class="apf-color-field" value="" data-default-color="#cccccc" />
			<p><?php esc_html_e( 'Used by Color Swatch sections. Leave empty to fall back to the label.', 'advanced-product-filters' ); ?></p>
		</div>
		<div class="form-field apf-term-swatch-field">
			<label for="apf-swatch-image"><?php esc_html_e( 'Swatch Image', 'advanced-product-filters' ); ?></label>
			<input type="hidden" id="apf-swatch-image" name="apf_swatch_image" value="" />
			<div class="apf-swatch-image-preview"></div>
			<button type="button" class="button apf-select-swatch-image"><?php esc_html_e( 'Select Image', 'advanced-product-filters' ); ?></button>
			<button type="button" class="button apf-remove-swatch-image" hidden><?php esc_html_e( 'Remove', 'advanced-product-filters' ); ?></button>
			<p><?php esc_html_e( 'Used by Image Swatch sections.', 'advanced-product-filters' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Renders the swatch fields on the "Edit term" screen.
	 *
	 * @param WP_Term $term     Term being edited.
	 * @param string  $taxonomy Current taxonomy slug.
	 * @return void
	 */
	public function render_edit_fields( WP_Term $term, string $taxonomy ): void {
		$color   = get_term_meta( $term->term_id, self::META_COLOR, true );
		$image_id = get_term_meta( $term->term_id, self::META_IMAGE, true );
		$image_url = $image_id ? wp_get_attachment_image_url( (int) $image_id, 'thumbnail' ) : '';
		?>
		<tr class="form-field apf-term-swatch-field">
			<th scope="row"><label for="apf-swatch-color"><?php esc_html_e( 'Swatch Color', 'advanced-product-filters' ); ?></label></th>
			<td>
				<input type="text" id="apf-swatch-color" name="apf_swatch_color" class="apf-color-field" value="<?php echo esc_attr( $color ); ?>" data-default-color="#cccccc" />
				<p class="description"><?php esc_html_e( 'Used by Color Swatch sections.', 'advanced-product-filters' ); ?></p>
			</td>
		</tr>
		<tr class="form-field apf-term-swatch-field">
			<th scope="row"><label for="apf-swatch-image"><?php esc_html_e( 'Swatch Image', 'advanced-product-filters' ); ?></label></th>
			<td>
				<input type="hidden" id="apf-swatch-image" name="apf_swatch_image" value="<?php echo esc_attr( $image_id ); ?>" />
				<div class="apf-swatch-image-preview">
					<?php if ( $image_url ) : ?>
						<img src="<?php echo esc_url( $image_url ); ?>" alt="" />
					<?php endif; ?>
				</div>
				<button type="button" class="button apf-select-swatch-image"><?php esc_html_e( 'Select Image', 'advanced-product-filters' ); ?></button>
				<button type="button" class="button apf-remove-swatch-image" <?php echo $image_id ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove', 'advanced-product-filters' ); ?></button>
				<p class="description"><?php esc_html_e( 'Used by Image Swatch sections.', 'advanced-product-filters' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Persists the swatch fields when a term is created or updated.
	 *
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public function save_fields( int $term_id ): void {
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		if ( isset( $_POST['apf_swatch_color'] ) ) {
			$color = sanitize_hex_color( wp_unslash( $_POST['apf_swatch_color'] ) );

			if ( $color ) {
				update_term_meta( $term_id, self::META_COLOR, $color );
			} else {
				delete_term_meta( $term_id, self::META_COLOR );
			}
		}

		if ( isset( $_POST['apf_swatch_image'] ) ) {
			$image_id = absint( $_POST['apf_swatch_image'] );

			if ( $image_id ) {
				update_term_meta( $term_id, self::META_IMAGE, $image_id );
			} else {
				delete_term_meta( $term_id, self::META_IMAGE );
			}
		}
	}

	/**
	 * Returns a term's swatch colour, if one has been set.
	 *
	 * @param int $term_id Term ID.
	 * @return string
	 */
	public static function get_color( int $term_id ): string {
		return (string) get_term_meta( $term_id, self::META_COLOR, true );
	}

	/**
	 * Returns a term's swatch image URL, if one has been set.
	 *
	 * @param int $term_id Term ID.
	 * @return string
	 */
	public static function get_image_url( int $term_id ): string {
		$image_id = (int) get_term_meta( $term_id, self::META_IMAGE, true );

		if ( ! $image_id ) {
			return '';
		}

		$url = wp_get_attachment_image_url( $image_id, 'thumbnail' );

		return $url ? $url : '';
	}
}
