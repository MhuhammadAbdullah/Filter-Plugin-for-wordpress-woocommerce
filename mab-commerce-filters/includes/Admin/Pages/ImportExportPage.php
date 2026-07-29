<?php
/**
 * Export/import filter sets and plugin settings as JSON.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Admin\Pages;

use MABCommerceFilters\Helpers\Helper;
use MABCommerceFilters\Repositories\FilterRepository;
use MABCommerceFilters\Repositories\FilterSetRepository;
use MABCommerceFilters\Repositories\LocationRepository;
use MABCommerceFilters\Repositories\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Streams a JSON export of every filter set (with its filters and
 * locations) or of plugin settings, and accepts the same JSON shape back
 * in on import.
 */
final class ImportExportPage extends AbstractAdminPage {

	/**
	 * Registers the admin-post handlers used for file download/upload.
	 */
	public function register(): void {
		add_action( 'admin_post_mabcf_export_filters', array( $this, 'handle_export_filters' ) );
		add_action( 'admin_post_mabcf_export_settings', array( $this, 'handle_export_settings' ) );
		add_action( 'admin_post_mabcf_import', array( $this, 'handle_import' ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'mabcf-import-export';
	}

	/**
	 * {@inheritDoc}
	 */
	public function title(): string {
		return __( 'Import/Export', 'mab-commerce-filters' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function render_content(): void {
		if ( isset( $_GET['mabcf_imported'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->notice(
				'error' === $_GET['mabcf_imported'] // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					? __( 'Import failed: invalid file.', 'mab-commerce-filters' )
					: __( 'Import completed successfully.', 'mab-commerce-filters' ),
				'error' === $_GET['mabcf_imported'] ? 'error' : 'success' // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			);
		}

		echo '<div class="mabcf-panel">';
		echo '<h2>' . esc_html__( 'Export', 'mab-commerce-filters' ) . '</h2>';
		echo '<p>' . esc_html__( 'Download every filter set (with its filters and location assignments) as a JSON file you can import on another site.', 'mab-commerce-filters' ) . '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:8px;">';
		wp_nonce_field( 'mabcf_export_filters' );
		echo '<input type="hidden" name="action" value="mabcf_export_filters">';
		submit_button( __( 'Export Filter Sets (JSON)', 'mab-commerce-filters' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;">';
		wp_nonce_field( 'mabcf_export_settings' );
		echo '<input type="hidden" name="action" value="mabcf_export_settings">';
		submit_button( __( 'Export Settings (JSON)', 'mab-commerce-filters' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<hr><h2>' . esc_html__( 'Import', 'mab-commerce-filters' ) . '</h2>';
		echo '<p>' . esc_html__( 'Upload a JSON file previously exported from this plugin. Filter sets with a matching slug will be replaced.', 'mab-commerce-filters' ) . '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data">';
		wp_nonce_field( 'mabcf_import' );
		echo '<input type="hidden" name="action" value="mabcf_import">';
		echo '<input type="file" name="mabcf_import_file" accept="application/json" required>';
		submit_button( __( 'Import', 'mab-commerce-filters' ), 'primary', 'submit', false );
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Streams a JSON export of every filter set with its filters/locations.
	 */
	public function handle_export_filters(): void {
		check_admin_referer( 'mabcf_export_filters' );

		if ( ! Helper::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'mab-commerce-filters' ) );
		}

		$sets      = new FilterSetRepository();
		$filters   = new FilterRepository();
		$locations = new LocationRepository();

		$export = array(
			'plugin'  => 'mab-commerce-filters',
			'version' => MABCF_VERSION,
			'exported_at' => gmdate( 'c' ),
			'filter_sets' => array(),
		);

		foreach ( $sets->get_all() as $set ) {
			$export['filter_sets'][] = array(
				'set'       => $set,
				'filters'   => $filters->get_for_set( (int) $set['id'] ),
				'locations' => $locations->get_for_set( (int) $set['id'] ),
			);
		}

		$this->stream_json( $export, 'mab-commerce-filters-export-' . gmdate( 'Y-m-d' ) . '.json' );
	}

	/**
	 * Streams a JSON export of plugin settings.
	 */
	public function handle_export_settings(): void {
		check_admin_referer( 'mabcf_export_settings' );

		if ( ! Helper::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'mab-commerce-filters' ) );
		}

		$export = array(
			'plugin'      => 'mab-commerce-filters',
			'version'     => MABCF_VERSION,
			'exported_at' => gmdate( 'c' ),
			'settings'    => ( new SettingsRepository() )->all(),
		);

		$this->stream_json( $export, 'mab-commerce-filters-settings-' . gmdate( 'Y-m-d' ) . '.json' );
	}

	/**
	 * Handles the JSON import upload for filter sets and/or settings.
	 */
	public function handle_import(): void {
		check_admin_referer( 'mabcf_import' );

		if ( ! Helper::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'mab-commerce-filters' ) );
		}

		$redirect = admin_url( 'admin.php?page=mabcf-import-export' );

		if ( empty( $_FILES['mabcf_import_file']['tmp_name'] ) || UPLOAD_ERR_OK !== $_FILES['mabcf_import_file']['error'] ) {
			wp_safe_redirect( add_query_arg( 'mabcf_imported', 'error', $redirect ) );
			exit;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_get_contents
		$contents = file_get_contents( $_FILES['mabcf_import_file']['tmp_name'] );
		$payload  = json_decode( (string) $contents, true );

		if ( ! is_array( $payload ) || 'mab-commerce-filters' !== ( $payload['plugin'] ?? '' ) ) {
			wp_safe_redirect( add_query_arg( 'mabcf_imported', 'error', $redirect ) );
			exit;
		}

		if ( isset( $payload['filter_sets'] ) && is_array( $payload['filter_sets'] ) ) {
			$this->import_filter_sets( $payload['filter_sets'] );
		}

		if ( isset( $payload['settings'] ) && is_array( $payload['settings'] ) ) {
			( new SettingsRepository() )->set_many( $payload['settings'] );
		}

		wp_safe_redirect( add_query_arg( 'mabcf_imported', 'success', $redirect ) );
		exit;
	}

	/**
	 * Imports (creates or replaces by slug) each exported filter set.
	 *
	 * @param array<int, array<string, mixed>> $entries Exported filter-set entries.
	 */
	private function import_filter_sets( array $entries ): void {
		$sets      = new FilterSetRepository();
		$filters   = new FilterRepository();
		$locations = new LocationRepository();

		foreach ( $entries as $entry ) {
			$set_data = $entry['set'] ?? null;

			if ( ! is_array( $set_data ) || empty( $set_data['slug'] ) ) {
				continue;
			}

			$existing = $sets->find_by_slug( sanitize_title( $set_data['slug'] ) );

			$payload = array(
				'name'        => sanitize_text_field( $set_data['name'] ?? 'Imported Set' ),
				'slug'        => sanitize_title( $set_data['slug'] ),
				'description' => sanitize_textarea_field( $set_data['description'] ?? '' ),
				'layout'      => sanitize_key( $set_data['layout'] ?? 'sidebar' ),
				'status'      => 'active' === ( $set_data['status'] ?? 'active' ) ? 'active' : 'inactive',
				'priority'    => absint( $set_data['priority'] ?? 10 ),
				'settings'    => is_array( $set_data['settings'] ?? null ) ? $set_data['settings'] : array(),
			);

			if ( $existing ) {
				$set_id = (int) $existing['id'];
				$sets->update( $set_id, $payload );
				$filters->delete_for_set( $set_id );
				$locations->delete_for_set( $set_id );
			} else {
				$set_id = $sets->create( $payload );
			}

			if ( ! $set_id ) {
				continue;
			}

			foreach ( (array) ( $entry['filters'] ?? array() ) as $filter ) {
				$filters->create(
					array(
						'filter_set_id' => $set_id,
						'type'          => sanitize_key( $filter['type'] ?? 'category' ),
						'label'         => sanitize_text_field( $filter['label'] ?? '' ),
						'source_key'    => sanitize_text_field( $filter['source_key'] ?? '' ),
						'display_style' => sanitize_key( $filter['display_style'] ?? 'list' ),
						'settings'      => is_array( $filter['settings'] ?? null ) ? $filter['settings'] : array(),
						'sort_order'    => absint( $filter['sort_order'] ?? 0 ),
						'status'        => 'active',
					)
				);
			}

			foreach ( (array) ( $entry['locations'] ?? array() ) as $location ) {
				$locations->create(
					array(
						'filter_set_id'  => $set_id,
						'location_type'  => sanitize_key( $location['location_type'] ?? 'global' ),
						'location_value' => sanitize_text_field( $location['location_value'] ?? '' ),
					)
				);
			}
		}
	}

	/**
	 * Sends an array as a downloadable JSON file and terminates the request.
	 *
	 * @param array<string, mixed> $data     Data to encode.
	 * @param string                $filename Suggested download filename.
	 */
	private function stream_json( array $data, string $filename ): void {
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}
}
