<?php
/**
 * Elementor widget: a single standalone filter control (Category, Price,
 * Color, Size, Brand, Tag, Rating, Stock or Search), picked from any
 * configured filter set.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Elementor\Widgets;

use Elementor\Controls_Manager;
use MABCommerceFilters\Filters\FilterTypeRegistry;
use MABCommerceFilters\Repositories\FilterRepository;
use MABCommerceFilters\Repositories\FilterSetRepository;
use MABCommerceFilters\Services\LocationResolver;
use MABCommerceFilters\Services\UrlSyncService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One class powers nine distinct Elementor widget registrations (see
 * ElementorIntegration::register_widgets()); the `$mode` passed to the
 * constructor decides which already-configured filters are eligible and
 * which get_name()/get_title()/get_icon() this instance reports.
 */
final class SingleFilterWidget extends AbstractMabcfWidget {

	/**
	 * Widget mode: category|price|color|size|brand|tag|rating|stock|search.
	 *
	 * @var string
	 */
	private string $mode;

	/**
	 * Elementor widget name (unique machine name).
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * Elementor widget title.
	 *
	 * @var string
	 */
	private string $widget_title;

	/**
	 * Elementor widget icon (eicon-* class).
	 *
	 * @var string
	 */
	private string $icon;

	/**
	 * Constructor.
	 *
	 * @param string $mode  Widget mode.
	 * @param string $name  Elementor widget name.
	 * @param string $title Elementor widget title.
	 * @param string $icon  Elementor icon class.
	 * @param array<string, mixed>  $data Elementor element data (framework-passed).
	 * @param array<string, mixed>|null $args Elementor element args (framework-passed).
	 */
	public function __construct( string $mode = 'category', string $name = 'mabcf-category-filter', string $title = '', string $icon = 'eicon-filter', $data = array(), $args = null ) {
		parent::__construct( $data, $args );

		$this->mode         = $mode;
		$this->name         = $name;
		$this->widget_title = $title ?: __( 'Filter', 'mab-commerce-filters' );
		$this->icon         = $icon;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return $this->widget_title;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon(): string {
		return $this->icon;
	}

	/**
	 * Maps the widget mode to the underlying stored filter `type`, and
	 * optionally a `display_style` narrowing it further.
	 *
	 * @return array{type: string, style: string}
	 */
	private function target(): array {
		$map = array(
			'category' => array( 'type' => 'category', 'style' => '' ),
			'price'    => array( 'type' => 'price', 'style' => '' ),
			'color'    => array( 'type' => 'attribute', 'style' => 'color' ),
			'size'     => array( 'type' => 'attribute', 'style' => 'pill' ),
			'brand'    => array( 'type' => 'taxonomy', 'style' => '' ),
			'tag'      => array( 'type' => 'taxonomy', 'style' => '' ),
			'rating'   => array( 'type' => 'rating', 'style' => '' ),
			'stock'    => array( 'type' => 'stock', 'style' => '' ),
			'search'   => array( 'type' => 'search', 'style' => '' ),
		);

		return $map[ $this->mode ] ?? array( 'type' => 'category', 'style' => '' );
	}

	/**
	 * Lists eligible filters (id => "Set — Label") for this widget's mode.
	 *
	 * @return array<string, string>
	 */
	private function eligible_filters(): array {
		$target  = $this->target();
		$sets    = wp_list_pluck( ( new FilterSetRepository() )->get_all(), 'name', 'id' );
		$filters = new FilterRepository();
		$options = array();

		foreach ( array_keys( $sets ) as $set_id ) {
			foreach ( $filters->get_for_set( (int) $set_id ) as $filter ) {
				if ( $filter['type'] !== $target['type'] ) {
					continue;
				}

				if ( $target['style'] && ( $filter['display_style'] ?? '' ) !== $target['style'] ) {
					continue;
				}

				$options[ (string) $filter['id'] ] = $sets[ $set_id ] . ' — ' . $filter['label'];
			}
		}

		return $options ?: array( '' => __( 'No matching filter configured yet.', 'mab-commerce-filters' ) );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'section_content',
			array( 'label' => $this->widget_title )
		);

		$this->add_control(
			'filter_id',
			array(
				'label'   => __( 'Filter', 'mab-commerce-filters' ),
				'type'    => Controls_Manager::SELECT,
				'options' => $this->eligible_filters(),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * {@inheritDoc}
	 */
	protected function render(): void {
		$this->ensure_assets();

		$settings  = $this->get_settings_for_display();
		$filter_id = absint( $settings['filter_id'] ?? 0 );

		if ( ! $filter_id ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<p>' . esc_html__( 'Select a filter to display, or configure one in the Filter Builder first.', 'mab-commerce-filters' ) . '</p>';
			}
			return;
		}

		$filter = ( new FilterRepository() )->find( $filter_id );

		if ( ! $filter ) {
			return;
		}

		$type = FilterTypeRegistry::instance()->get( $filter['type'] );

		if ( ! $type ) {
			return;
		}

		$resolver  = new LocationResolver();
		$context   = $resolver->current_context();
		$base_args = $resolver->base_args_for_context( $context );

		$selection = ( new UrlSyncService() )->from_request( array( $filter ), wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$selected  = $selection[ $filter_id ] ?? array();

		printf( '<div class="mabcf mabcf--standalone-filter" data-filter-set-id="%d">', (int) $filter['filter_set_id'] );
		echo $type->render( $filter, $base_args, is_array( $selected ) ? $selected : array( 'value' => $selected ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
	}
}
