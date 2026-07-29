<?php
/**
 * Adds color/image/logo term-meta fields to taxonomy term edit screens.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Admin;

use MABCommerceFilters\Helpers\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce attribute taxonomies (`pa_*`) get a color-picker field for
 * the Color filter's swatches; every product taxonomy (attributes, tags,
 * and any brand-style taxonomy) gets an image and a logo upload field so
 * the Taxonomy filter's "Brand (Image)" / "Brand (Logo)" display styles
 * have real artwork to show, all stored as term meta and read back by
 * AttributeFilterType / TaxonomyFilterType.
 */
final class TermMetaFields {

	/**
	 * Registers hooks for every relevant taxonomy.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_for_taxonomies' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Attaches the add/edit form field hooks to every product taxonomy.
	 */
	public function register_for_taxonomies(): void {
		foreach ( get_object_taxonomies( 'product' ) as $taxonomy ) {
			add_action( "{$taxonomy}_add_form_fields", array( $this, 'add_form_fields' ) );
			add_action( "{$taxonomy}_edit_form_fields", array( $this, 'edit_form_fields' ), 10, 2 );
			add_action( "created_{$taxonomy}", array( $this, 'save' ) );
			add_action( "edited_{$taxonomy}", array( $this, 'save' ) );
		}
	}

	/**
	 * Enqueues the WP color picker on taxonomy edit screens.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue( string $hook ): void {
		if ( ! in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_media();
		wp_enqueue_script( 'mabcf-admin', Helper::asset_url( 'js/admin.js' ), array( 'wp-color-picker' ), MABCF_VERSION, true );
	}

	/**
	 * Renders the fields on the "Add new term" screen.
	 *
	 * @param string $taxonomy Current taxonomy.
	 */
	public function add_form_fields( string $taxonomy ): void {
		echo '<div class="form-field">';
		if ( str_starts_with( $taxonomy, 'pa_' ) ) {
			echo '<label for="mabcf_color">' . esc_html__( 'Swatch Color', 'mab-commerce-filters' ) . '</label>';
			echo '<input type="text" class="mabcf-color-field" name="mabcf_color" id="mabcf_color" value="">';
		}
		echo '<label for="mabcf_image">' . esc_html__( 'Swatch / Brand Image', 'mab-commerce-filters' ) . '</label>';
		echo '<input type="url" name="mabcf_image" id="mabcf_image" value="" class="mabcf-media-field">';
		echo '<button type="button" class="button mabcf-media-select" data-target="mabcf_image">' . esc_html__( 'Choose Image', 'mab-commerce-filters' ) . '</button>';
		echo '<label for="mabcf_logo">' . esc_html__( 'Brand Logo', 'mab-commerce-filters' ) . '</label>';
		echo '<input type="url" name="mabcf_logo" id="mabcf_logo" value="" class="mabcf-media-field">';
		echo '<button type="button" class="button mabcf-media-select" data-target="mabcf_logo">' . esc_html__( 'Choose Logo', 'mab-commerce-filters' ) . '</button>';
		echo '</div>';
	}

	/**
	 * Renders the fields on the "Edit term" screen.
	 *
	 * @param \WP_Term $term     Term being edited.
	 * @param string    $taxonomy Current taxonomy.
	 */
	public function edit_form_fields( \WP_Term $term, string $taxonomy ): void {
		$color = get_term_meta( $term->term_id, 'mabcf_color', true );
		$image = get_term_meta( $term->term_id, 'mabcf_image', true );
		$logo  = get_term_meta( $term->term_id, 'mabcf_logo', true );

		if ( str_starts_with( $taxonomy, 'pa_' ) ) {
			echo '<tr class="form-field"><th><label for="mabcf_color">' . esc_html__( 'Swatch Color', 'mab-commerce-filters' ) . '</label></th><td>';
			printf( '<input type="text" class="mabcf-color-field" name="mabcf_color" id="mabcf_color" value="%s"></td></tr>', esc_attr( $color ) );
		}

		echo '<tr class="form-field"><th><label for="mabcf_image">' . esc_html__( 'Swatch / Brand Image', 'mab-commerce-filters' ) . '</label></th><td>';
		printf( '<input type="url" name="mabcf_image" id="mabcf_image" value="%s" class="mabcf-media-field regular-text"> ', esc_url( $image ) );
		echo '<button type="button" class="button mabcf-media-select" data-target="mabcf_image">' . esc_html__( 'Choose Image', 'mab-commerce-filters' ) . '</button></td></tr>';

		echo '<tr class="form-field"><th><label for="mabcf_logo">' . esc_html__( 'Brand Logo', 'mab-commerce-filters' ) . '</label></th><td>';
		printf( '<input type="url" name="mabcf_logo" id="mabcf_logo" value="%s" class="mabcf-media-field regular-text"> ', esc_url( $logo ) );
		echo '<button type="button" class="button mabcf-media-select" data-target="mabcf_logo">' . esc_html__( 'Choose Logo', 'mab-commerce-filters' ) . '</button></td></tr>';
	}

	/**
	 * Persists the submitted term meta.
	 *
	 * @param int $term_id Saved term ID.
	 */
	public function save( int $term_id ): void {
		if ( ! Helper::current_user_can_manage() ) {
			return;
		}

		if ( isset( $_POST['mabcf_color'] ) ) {
			$color = sanitize_hex_color( wp_unslash( $_POST['mabcf_color'] ) );
			$color ? update_term_meta( $term_id, 'mabcf_color', $color ) : delete_term_meta( $term_id, 'mabcf_color' );
		}

		if ( isset( $_POST['mabcf_image'] ) ) {
			$image = esc_url_raw( wp_unslash( $_POST['mabcf_image'] ) );
			$image ? update_term_meta( $term_id, 'mabcf_image', $image ) : delete_term_meta( $term_id, 'mabcf_image' );
		}

		if ( isset( $_POST['mabcf_logo'] ) ) {
			$logo = esc_url_raw( wp_unslash( $_POST['mabcf_logo'] ) );
			$logo ? update_term_meta( $term_id, 'mabcf_logo', $logo ) : delete_term_meta( $term_id, 'mabcf_logo' );
		}
	}
}
