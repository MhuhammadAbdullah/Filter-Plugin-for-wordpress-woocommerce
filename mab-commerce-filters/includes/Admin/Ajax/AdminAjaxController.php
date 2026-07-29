<?php
/**
 * AJAX endpoints backing the admin builder, locations and utility actions.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Admin\Ajax;

use MABCommerceFilters\Filters\FilterTypeRegistry;
use MABCommerceFilters\Helpers\Helper;
use MABCommerceFilters\Repositories\FilterRepository;
use MABCommerceFilters\Repositories\FilterSetRepository;
use MABCommerceFilters\Repositories\LocationRepository;
use MABCommerceFilters\Repositories\LogRepository;
use MABCommerceFilters\Services\CacheService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every action here is admin-only (capability + nonce gated) and mutates
 * data through the repository layer, never with raw SQL.
 */
final class AdminAjaxController {

	/**
	 * Registers every admin AJAX action.
	 */
	public function register(): void {
		$actions = array(
			'mabcf_admin_save_filter_set'      => 'save_filter_set',
			'mabcf_admin_delete_filter_set'    => 'delete_filter_set',
			'mabcf_admin_duplicate_filter_set' => 'duplicate_filter_set',
			'mabcf_admin_toggle_filter_set'    => 'toggle_filter_set',
			'mabcf_admin_add_location'         => 'add_location',
			'mabcf_admin_delete_location'      => 'delete_location',
			'mabcf_admin_clear_cache'          => 'clear_cache',
			'mabcf_admin_clear_logs'           => 'clear_logs',
		);

		foreach ( $actions as $action => $method ) {
			add_action( 'wp_ajax_' . $action, array( $this, $method ) );
		}
	}

	/**
	 * Verifies the admin nonce and capability, halting the request if
	 * either check fails.
	 */
	private function guard(): void {
		check_ajax_referer( 'mabcf_admin', 'nonce' );

		if ( ! Helper::current_user_can_manage() ) {
			Helper::json_error( __( 'Permission denied.', 'mab-commerce-filters' ), 403 );
		}
	}

	/**
	 * Creates or updates a filter set together with its full filter list.
	 */
	public function save_filter_set(): void {
		$this->guard();

		$sets    = new FilterSetRepository();
		$filters = new FilterRepository();
		$registry = FilterTypeRegistry::instance();

		$set_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$name   = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );

		if ( '' === $name ) {
			Helper::json_error( __( 'A name is required.', 'mab-commerce-filters' ) );
			return;
		}

		$settings_payload = isset( $_POST['settings'] ) ? Helper::sanitize_recursive( wp_unslash( $_POST['settings'] ) ) : array();

		$payload = array(
			'name'     => $name,
			'layout'   => sanitize_key( wp_unslash( $_POST['layout'] ?? 'sidebar' ) ),
			'status'   => 'inactive' === sanitize_key( wp_unslash( $_POST['status'] ?? 'active' ) ) ? 'inactive' : 'active',
			'settings' => $settings_payload,
		);

		if ( $set_id ) {
			$existing = $sets->find( $set_id );

			if ( ! $existing ) {
				Helper::json_error( __( 'Filter set not found.', 'mab-commerce-filters' ), 404 );
				return;
			}

			$payload['slug'] = $existing['slug'];
			$sets->update( $set_id, $payload );
		} else {
			$payload['slug'] = $sets->generate_unique_slug( $name );
			$payload['priority'] = 10;
			$set_id = $sets->create( $payload );
		}

		if ( ! $set_id ) {
			Helper::json_error( __( 'Could not save filter set.', 'mab-commerce-filters' ) );
			return;
		}

		$submitted_filters = isset( $_POST['filters'] ) && is_array( $_POST['filters'] ) ? wp_unslash( $_POST['filters'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$existing_ids       = wp_list_pluck( $filters->get_for_set( $set_id ), 'id' );
		$kept_ids           = array();

		foreach ( $submitted_filters as $index => $filter ) {
			$type = sanitize_key( $filter['type'] ?? '' );

			if ( ! $registry->get( $type ) ) {
				continue;
			}

			$filter_payload = array(
				'filter_set_id' => $set_id,
				'type'          => $type,
				'label'         => sanitize_text_field( $filter['label'] ?? ucfirst( $type ) ),
				'source_key'    => sanitize_text_field( $filter['source_key'] ?? '' ),
				'display_style' => sanitize_key( $filter['display_style'] ?? 'list' ),
				'settings'      => Helper::sanitize_recursive( $filter['settings'] ?? array() ),
				'sort_order'    => (int) $index,
				'status'        => 'active',
			);

			$filter_id = absint( $filter['id'] ?? 0 );

			if ( $filter_id && in_array( $filter_id, $existing_ids, true ) ) {
				$filters->update( $filter_id, $filter_payload );
				$kept_ids[] = $filter_id;
			} else {
				$new_id = $filters->create( $filter_payload );
				if ( $new_id ) {
					$kept_ids[] = $new_id;
				}
			}
		}

		foreach ( array_diff( $existing_ids, $kept_ids ) as $removed_id ) {
			$filters->delete( (int) $removed_id );
		}

		( new CacheService() )->flush();

		Helper::json_success( array( 'id' => $set_id, 'edit_url' => admin_url( 'admin.php?page=mabcf-filter-builder&set=' . $set_id ) ) );
	}

	/**
	 * Deletes a filter set and everything that belongs to it.
	 */
	public function delete_filter_set(): void {
		$this->guard();

		$id = absint( $_POST['id'] ?? 0 );

		if ( ! $id ) {
			Helper::json_error( __( 'Invalid filter set.', 'mab-commerce-filters' ) );
			return;
		}

		( new FilterRepository() )->delete_for_set( $id );
		( new LocationRepository() )->delete_for_set( $id );
		( new FilterSetRepository() )->delete( $id );
		( new CacheService() )->flush();

		Helper::json_success();
	}

	/**
	 * Duplicates a filter set and all of its filters (not its locations).
	 */
	public function duplicate_filter_set(): void {
		$this->guard();

		$id = absint( $_POST['id'] ?? 0 );

		$sets    = new FilterSetRepository();
		$filters = new FilterRepository();

		$source = $sets->find( $id );

		if ( ! $source ) {
			Helper::json_error( __( 'Filter set not found.', 'mab-commerce-filters' ), 404 );
			return;
		}

		$new_name = $source['name'] . ' ' . __( '(Copy)', 'mab-commerce-filters' );

		$new_id = $sets->create(
			array(
				'name'        => $new_name,
				'slug'        => $sets->generate_unique_slug( $new_name ),
				'description' => $source['description'],
				'layout'      => $source['layout'],
				'status'      => 'inactive',
				'priority'    => $source['priority'],
				'settings'    => $source['settings'],
			)
		);

		if ( ! $new_id ) {
			Helper::json_error( __( 'Could not duplicate filter set.', 'mab-commerce-filters' ) );
			return;
		}

		foreach ( $filters->get_for_set( $id ) as $filter ) {
			unset( $filter['id'] );
			$filter['filter_set_id'] = $new_id;
			$filters->create( $filter );
		}

		Helper::json_success( array( 'id' => $new_id, 'edit_url' => admin_url( 'admin.php?page=mabcf-filter-builder&set=' . $new_id ) ) );
	}

	/**
	 * Toggles a filter set between active and inactive.
	 */
	public function toggle_filter_set(): void {
		$this->guard();

		$id     = absint( $_POST['id'] ?? 0 );
		$status = 'active' === sanitize_key( wp_unslash( $_POST['status'] ?? '' ) ) ? 'inactive' : 'active';

		( new FilterSetRepository() )->update( $id, array( 'status' => $status ) );
		( new CacheService() )->flush();

		Helper::json_success( array( 'status' => $status ) );
	}

	/**
	 * Adds a location assignment.
	 */
	public function add_location(): void {
		$this->guard();

		$filter_set_id = absint( $_POST['filter_set_id'] ?? 0 );
		$type          = sanitize_key( wp_unslash( $_POST['type'] ?? '' ) );
		$value         = sanitize_text_field( wp_unslash( $_POST['value'] ?? '' ) );

		if ( ! $filter_set_id || '' === $type ) {
			Helper::json_error( __( 'Filter set and type are required.', 'mab-commerce-filters' ) );
			return;
		}

		$id = ( new LocationRepository() )->create(
			array(
				'filter_set_id'  => $filter_set_id,
				'location_type'  => $type,
				'location_value' => $value,
			)
		);

		Helper::json_success( array( 'id' => $id ) );
	}

	/**
	 * Deletes a location assignment.
	 */
	public function delete_location(): void {
		$this->guard();

		$id = absint( $_POST['id'] ?? 0 );

		( new LocationRepository() )->delete( $id );

		Helper::json_success();
	}

	/**
	 * Flushes the plugin's cache.
	 */
	public function clear_cache(): void {
		$this->guard();

		( new CacheService() )->flush();

		Helper::json_success();
	}

	/**
	 * Truncates the log table.
	 */
	public function clear_logs(): void {
		$this->guard();

		( new LogRepository() )->truncate();

		Helper::json_success();
	}
}
