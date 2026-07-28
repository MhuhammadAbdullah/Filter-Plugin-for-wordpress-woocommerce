<?php
/**
 * Repository for reading and writing Filter Sets.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * All persistence for Filter Sets goes through this repository so the
 * `apf_filter_set` storage details never leak into admin, frontend or
 * AJAX code.
 */
final class APF_Filter_Sets {

	/**
	 * Returns every Filter Set, including disabled ones, ordered by
	 * priority (`menu_order`) descending.
	 *
	 * @return APF_Filter_Set[]
	 */
	public static function get_all(): array {
		$posts = get_posts(
			array(
				'post_type'      => APF_Post_Types::FILTER_SET,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'orderby'        => 'menu_order',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		return array_map( array( 'APF_Filter_Set', 'from_post' ), $posts );
	}

	/**
	 * Fetches a single Filter Set by ID.
	 *
	 * @param int $id Post ID.
	 * @return APF_Filter_Set|null
	 */
	public static function get( int $id ): ?APF_Filter_Set {
		$post = get_post( $id );

		if ( ! $post instanceof WP_Post || APF_Post_Types::FILTER_SET !== $post->post_type ) {
			return null;
		}

		return APF_Filter_Set::from_post( $post );
	}

	/**
	 * Creates or updates a Filter Set from sanitised request data.
	 *
	 * @param array<string, mixed> $data {
	 *     @type int    $id        Existing post ID, 0 to create a new set.
	 *     @type string $name      Filter Set name.
	 *     @type bool   $enabled   Enabled state.
	 *     @type int    $priority  Assignment priority.
	 *     @type array  $locations Location rules.
	 *     @type array  $sections  Section configuration.
	 *     @type array  $settings  Behaviour overrides.
	 * }
	 * @return int The Filter Set post ID.
	 */
	public static function save( array $data ): int {
		$id = isset( $data['id'] ) ? absint( $data['id'] ) : 0;

		$postarr = array(
			'ID'         => $id,
			'post_type'  => APF_Post_Types::FILTER_SET,
			'post_title' => sanitize_text_field( $data['name'] ?? __( 'Untitled Filter Set', 'advanced-product-filters' ) ),
			'post_status' => empty( $data['enabled'] ) ? 'draft' : 'publish',
			'menu_order' => isset( $data['priority'] ) ? absint( $data['priority'] ) : 10,
		);

		$id = wp_insert_post( $postarr, true );

		if ( is_wp_error( $id ) ) {
			return 0;
		}

		update_post_meta( $id, '_apf_locations', self::sanitise_locations( $data['locations'] ?? array() ) );
		update_post_meta( $id, '_apf_sections', self::sanitise_sections( $data['sections'] ?? array() ) );
		update_post_meta( $id, '_apf_settings', self::sanitise_settings( $data['settings'] ?? array() ) );

		APF_Cache::flush();

		return $id;
	}

	/**
	 * Duplicates an existing Filter Set, appending "(Copy)" to its name.
	 *
	 * @param int $id Post ID to duplicate.
	 * @return int New post ID, or 0 on failure.
	 */
	public static function duplicate( int $id ): int {
		$original = self::get( $id );

		if ( ! $original ) {
			return 0;
		}

		$data           = $original->to_array();
		$data['id']     = 0;
		/* translators: %s: original Filter Set name */
		$data['name']   = sprintf( __( '%s (Copy)', 'advanced-product-filters' ), $original->get_name() );
		$data['enabled'] = false;

		return self::save( $data );
	}

	/**
	 * Permanently deletes a Filter Set.
	 *
	 * @param int $id Post ID.
	 * @return bool
	 */
	public static function delete( int $id ): bool {
		$deleted = wp_delete_post( $id, true );

		if ( $deleted ) {
			APF_Cache::flush();
		}

		return (bool) $deleted;
	}

	/**
	 * Persists the drag-and-drop order submitted from the Filter Sets list.
	 *
	 * @param int[] $ordered_ids Post IDs from highest to lowest priority.
	 * @return void
	 */
	public static function reorder( array $ordered_ids ): void {
		$total = count( $ordered_ids );

		foreach ( $ordered_ids as $index => $id ) {
			wp_update_post(
				array(
					'ID'         => absint( $id ),
					'menu_order' => $total - $index,
				)
			);
		}

		APF_Cache::flush();
	}

	/**
	 * Resolves the single best-matching, enabled Filter Set for the
	 * current front-end context (highest priority wins, ties broken by
	 * rule specificity).
	 *
	 * @return APF_Filter_Set|null
	 */
	public static function get_for_current_context(): ?APF_Filter_Set {
		$preview = self::get_preview_override();

		if ( null !== $preview ) {
			return $preview;
		}

		$context = APF_Context::current();
		$best    = null;
		$best_score = -1;

		foreach ( self::get_all() as $filter_set ) {
			if ( ! $filter_set->is_enabled() ) {
				continue;
			}

			$specificity = APF_Context::match_score( $filter_set->get_locations(), $context );

			if ( -1 === $specificity ) {
				continue;
			}

			$score = ( $filter_set->get_priority() * 100 ) + $specificity;

			if ( $score > $best_score ) {
				$best_score = $score;
				$best       = $filter_set;
			}
		}

		return $best;
	}

	/**
	 * Lets a logged-in admin preview a specific Filter Set — including
	 * disabled or unsaved-context ones — from the builder's live preview
	 * iframe, bypassing normal location matching entirely.
	 *
	 * @return APF_Filter_Set|null
	 */
	private static function get_preview_override(): ?APF_Filter_Set {
		if ( empty( $_GET['apf_preview_id'] ) || empty( $_GET['apf_preview_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return null;
		}

		$id = absint( $_GET['apf_preview_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! current_user_can( APF_Admin::CAPABILITY ) ) {
			return null;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['apf_preview_nonce'] ) ), 'apf_preview_' . $id ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return null;
		}

		return self::get( $id );
	}

	/**
	 * Sanitises the location rules array submitted by the builder UI.
	 *
	 * @param mixed $locations Raw locations.
	 * @return array<int, array<string, mixed>>
	 */
	private static function sanitise_locations( $locations ): array {
		if ( ! is_array( $locations ) ) {
			return array();
		}

		$clean = array();

		foreach ( $locations as $location ) {
			if ( empty( $location['type'] ) ) {
				continue;
			}

			$entry = array(
				'type' => sanitize_key( $location['type'] ),
			);

			if ( ! empty( $location['taxonomy'] ) ) {
				$entry['taxonomy'] = sanitize_key( $location['taxonomy'] );
			}

			if ( ! empty( $location['ids'] ) && is_array( $location['ids'] ) ) {
				$entry['ids'] = array_map( 'absint', $location['ids'] );
			}

			$clean[] = $entry;
		}

		return $clean;
	}

	/**
	 * Sanitises the ordered section configuration submitted by the builder UI.
	 *
	 * @param mixed $sections Raw sections.
	 * @return array<int, array<string, mixed>>
	 */
	private static function sanitise_sections( $sections ): array {
		if ( ! is_array( $sections ) ) {
			return array();
		}

		$clean = array();

		foreach ( $sections as $section ) {
			if ( empty( $section['type'] ) || ! APF_Section_Types::exists( sanitize_key( $section['type'] ) ) ) {
				continue;
			}

			$entry = array(
				'id'      => sanitize_key( $section['id'] ?? $section['type'] ),
				'type'    => sanitize_key( $section['type'] ),
				'label'   => sanitize_text_field( $section['label'] ?? '' ),
				'enabled' => ! empty( $section['enabled'] ),
			);

			foreach ( array( 'collapsed', 'show_count', 'searchable', 'multi_select' ) as $bool_key ) {
				if ( isset( $section[ $bool_key ] ) ) {
					$entry[ $bool_key ] = ! empty( $section[ $bool_key ] );
				}
			}

			if ( ! empty( $section['input_type'] ) ) {
				$entry['input_type'] = sanitize_key( $section['input_type'] );
			}

			if ( ! empty( $section['taxonomy'] ) ) {
				$entry['taxonomy'] = sanitize_key( $section['taxonomy'] );
			}

			if ( isset( $section['term_limit'] ) ) {
				$entry['term_limit'] = absint( $section['term_limit'] );
			}

			$clean[] = $entry;
		}

		return $clean;
	}

	/**
	 * Sanitises the per-Filter-Set behaviour overrides.
	 *
	 * @param mixed $settings Raw settings.
	 * @return array<string, mixed>
	 */
	private static function sanitise_settings( $settings ): array {
		if ( ! is_array( $settings ) ) {
			return array();
		}

		$clean = array();

		foreach ( array( 'instant_ajax', 'show_apply_button', 'show_clear_button', 'url_sync', 'sticky_sidebar' ) as $bool_key ) {
			if ( isset( $settings[ $bool_key ] ) && '' !== $settings[ $bool_key ] ) {
				$clean[ $bool_key ] = ! empty( $settings[ $bool_key ] );
			}
		}

		if ( isset( $settings['products_per_page'] ) && '' !== $settings['products_per_page'] ) {
			$clean['products_per_page'] = absint( $settings['products_per_page'] );
		}

		return $clean;
	}
}
