<?php
/**
 * Displays and clears the debug/AJAX/database log.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Admin\Pages;

use MABCommerceFilters\Repositories\LogRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only log viewer with a channel filter and a clear-all action.
 */
final class LogsPage extends AbstractAdminPage {

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'mabcf-logs';
	}

	/**
	 * {@inheritDoc}
	 */
	public function title(): string {
		return __( 'Logs', 'mab-commerce-filters' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function render_content(): void {
		$channel = isset( $_GET['channel'] ) ? sanitize_key( wp_unslash( $_GET['channel'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$logs    = ( new LogRepository() )->recent( $channel, 200 );

		echo '<div class="mabcf-panel">';
		echo '<div class="mabcf-panel__toolbar">';
		echo '<h2>' . esc_html__( 'Logs', 'mab-commerce-filters' ) . '</h2>';

		echo '<form method="get" style="display:inline-block;">';
		echo '<input type="hidden" name="page" value="mabcf-logs">';
		echo '<select name="channel" onchange="this.form.submit()">';
		foreach ( array( '' => __( 'All Channels', 'mab-commerce-filters' ), 'query' => __( 'Query', 'mab-commerce-filters' ), 'ajax' => __( 'AJAX', 'mab-commerce-filters' ), 'database' => __( 'Database', 'mab-commerce-filters' ) ) as $value => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $value ), selected( $channel, $value, false ), esc_html( $label ) );
		}
		echo '</select>';
		echo '</form>';

		printf(
			' <button type="button" class="button mabcf-clear-logs" data-nonce="%s">%s</button>',
			esc_attr( wp_create_nonce( 'mabcf_admin' ) ),
			esc_html__( 'Clear Logs', 'mab-commerce-filters' )
		);
		echo '</div>';

		if ( ! $logs ) {
			echo '<p>' . esc_html__( 'No log entries.', 'mab-commerce-filters' ) . '</p></div>';
			return;
		}

		echo '<table class="widefat striped"><thead><tr>
			<th>' . esc_html__( 'Time', 'mab-commerce-filters' ) . '</th>
			<th>' . esc_html__( 'Type', 'mab-commerce-filters' ) . '</th>
			<th>' . esc_html__( 'Channel', 'mab-commerce-filters' ) . '</th>
			<th>' . esc_html__( 'Message', 'mab-commerce-filters' ) . '</th>
			<th>' . esc_html__( 'Context', 'mab-commerce-filters' ) . '</th>
		</tr></thead><tbody>';

		foreach ( $logs as $log ) {
			printf(
				'<tr><td>%s</td><td><span class="mabcf-badge mabcf-badge--%s">%s</span></td><td>%s</td><td>%s</td><td><details><summary>%s</summary><pre>%s</pre></details></td></tr>',
				esc_html( $log['created_at'] ),
				esc_attr( 'error' === $log['log_type'] ? 'error' : ( 'warning' === $log['log_type'] ? 'warning' : 'muted' ) ),
				esc_html( $log['log_type'] ),
				esc_html( $log['channel'] ),
				esc_html( $log['message'] ),
				esc_html__( 'View', 'mab-commerce-filters' ),
				esc_html( wp_json_encode( $log['context'], JSON_PRETTY_PRINT ) )
			);
		}

		echo '</tbody></table></div>';
	}
}
