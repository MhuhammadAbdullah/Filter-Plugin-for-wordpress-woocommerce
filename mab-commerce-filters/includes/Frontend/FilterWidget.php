<?php
/**
 * Classic widget-area version of the filter sidebar (non-Elementor themes).
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Frontend;

use MABCommerceFilters\Repositories\FilterSetRepository;
use MABCommerceFilters\Services\LocationResolver;
use MABCommerceFilters\Services\UrlSyncService;
use MABCommerceFilters\Repositories\FilterRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lets store owners drag "MAB Commerce Filters" into any classic widget
 * area (e.g. the shop sidebar), choosing which filter set to display or
 * leaving it on "Auto" to use the current page's assigned set.
 */
final class FilterWidget extends \WP_Widget {

	/**
	 * Registers the widget with WordPress.
	 */
	public function __construct() {
		parent::__construct(
			'mabcf_filter_widget',
			__( 'MAB Commerce Filters', 'mab-commerce-filters' ),
			array( 'description' => __( 'Displays an AJAX WooCommerce product filter sidebar.', 'mab-commerce-filters' ) )
		);
	}

	/**
	 * Outputs the widget content.
	 *
	 * @param array<string, mixed> $args     Widget area args.
	 * @param array<string, mixed> $instance Saved widget instance settings.
	 */
	public function widget( $args, $instance ): void {
		$resolver       = new LocationResolver();
		$filter_set_id  = (int) ( $instance['filter_set_id'] ?? 0 );
		$set            = $filter_set_id ? ( new FilterSetRepository() )->find( $filter_set_id ) : $resolver->resolve_current();

		if ( ! $set || 'active' !== $set['status'] ) {
			return;
		}

		$filters   = ( new FilterRepository() )->get_for_set( (int) $set['id'] );
		$url_sync  = new UrlSyncService();
		$selection = $url_sync->from_request( $filters, wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$context   = $resolver->current_context();
		$base_args = $resolver->base_args_for_context( $context );

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . esc_html( $instance['title'] ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo ( new Renderer() )->render( $set, $base_args, $selection, $context ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Outputs the widget admin form.
	 *
	 * @param array<string, mixed> $instance Saved widget instance settings.
	 */
	public function form( $instance ): void {
		$title         = $instance['title'] ?? __( 'Filter Products', 'mab-commerce-filters' );
		$filter_set_id = (int) ( $instance['filter_set_id'] ?? 0 );
		$sets          = ( new FilterSetRepository() )->get_all();
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'mab-commerce-filters' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'filter_set_id' ) ); ?>"><?php esc_html_e( 'Filter Set:', 'mab-commerce-filters' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'filter_set_id' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'filter_set_id' ) ); ?>">
				<option value="0" <?php selected( 0, $filter_set_id ); ?>><?php esc_html_e( 'Auto (based on page location)', 'mab-commerce-filters' ); ?></option>
				<?php foreach ( $sets as $set ) : ?>
					<option value="<?php echo (int) $set['id']; ?>" <?php selected( (int) $set['id'], $filter_set_id ); ?>><?php echo esc_html( $set['name'] ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	/**
	 * Sanitises and saves the widget instance settings.
	 *
	 * @param array<string, mixed> $new_instance New settings.
	 * @param array<string, mixed> $old_instance Previous settings.
	 * @return array<string, mixed>
	 */
	public function update( $new_instance, $old_instance ): array {
		return array(
			'title'         => sanitize_text_field( $new_instance['title'] ?? '' ),
			'filter_set_id' => absint( $new_instance['filter_set_id'] ?? 0 ),
		);
	}
}
