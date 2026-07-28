<?php
/**
 * Value object representing a single Filter Set.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wraps an `apf_filter_set` post together with its meta so the rest of the
 * plugin can work with a typed object instead of raw arrays scattered
 * throughout templates and AJAX handlers.
 */
final class APF_Filter_Set {

	/**
	 * Post ID backing this Filter Set. Zero for a not-yet-saved instance.
	 *
	 * @var int
	 */
	private int $id;

	/**
	 * Human readable name shown in wp-admin.
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * Whether the Filter Set is active (`publish`) or disabled (`draft`).
	 *
	 * @var bool
	 */
	private bool $enabled;

	/**
	 * Assignment priority; higher wins when several sets match a context.
	 *
	 * @var int
	 */
	private int $priority;

	/**
	 * Location assignment rules.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $locations;

	/**
	 * Ordered section configuration.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $sections;

	/**
	 * Per-Filter-Set behaviour overrides (falls back to global settings).
	 *
	 * @var array<string, mixed>
	 */
	private array $settings;

	/**
	 * Constructor.
	 *
	 * @param int                                $id        Post ID.
	 * @param string                              $name      Filter Set name.
	 * @param bool                                $enabled   Enabled state.
	 * @param int                                 $priority  Assignment priority.
	 * @param array<int, array<string, mixed>>    $locations Location rules.
	 * @param array<int, array<string, mixed>>    $sections  Section configuration.
	 * @param array<string, mixed>                $settings  Behaviour overrides.
	 */
	public function __construct( int $id, string $name, bool $enabled, int $priority, array $locations, array $sections, array $settings ) {
		$this->id        = $id;
		$this->name      = $name;
		$this->enabled   = $enabled;
		$this->priority  = $priority;
		$this->locations = $locations;
		$this->sections  = $sections;
		$this->settings  = $settings;
	}

	/**
	 * Builds an instance from a `WP_Post`.
	 *
	 * @param WP_Post $post Filter Set post.
	 * @return self
	 */
	public static function from_post( WP_Post $post ): self {
		$locations = get_post_meta( $post->ID, '_apf_locations', true );
		$sections  = get_post_meta( $post->ID, '_apf_sections', true );
		$settings  = get_post_meta( $post->ID, '_apf_settings', true );

		return new self(
			$post->ID,
			$post->post_title,
			'publish' === $post->post_status,
			(int) $post->menu_order,
			is_array( $locations ) ? $locations : array(),
			is_array( $sections ) ? $sections : self::default_sections(),
			is_array( $settings ) ? $settings : array()
		);
	}

	/**
	 * The factory default section list applied to newly created Filter Sets.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function default_sections(): array {
		return array(
			array(
				'id'      => 'active_filters',
				'type'    => 'active_filters',
				'label'   => __( 'Active Filters', 'advanced-product-filters' ),
				'enabled' => true,
			),
			array(
				'id'         => 'category',
				'type'       => 'category',
				'label'      => __( 'Categories', 'advanced-product-filters' ),
				'enabled'    => true,
				'collapsed'  => false,
				'input_type' => 'tree',
				'show_count' => true,
			),
			array(
				'id'         => 'price',
				'type'       => 'price',
				'label'      => __( 'Price', 'advanced-product-filters' ),
				'enabled'    => true,
				'collapsed'  => false,
				'input_type' => 'range_slider',
			),
		);
	}

	/**
	 * Post ID.
	 *
	 * @return int
	 */
	public function get_id(): int {
		return $this->id;
	}

	/**
	 * Filter Set name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Whether the Filter Set is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return $this->enabled;
	}

	/**
	 * Assignment priority.
	 *
	 * @return int
	 */
	public function get_priority(): int {
		return $this->priority;
	}

	/**
	 * Location assignment rules.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_locations(): array {
		return $this->locations;
	}

	/**
	 * Every configured section, including disabled ones.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_sections(): array {
		return $this->sections;
	}

	/**
	 * Only the enabled sections, in their configured display order.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_enabled_sections(): array {
		return array_values(
			array_filter(
				$this->sections,
				static fn( array $section ): bool => ! empty( $section['enabled'] )
			)
		);
	}

	/**
	 * A single behaviour setting, falling back to the global default.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public function get_setting( string $key ) {
		if ( array_key_exists( $key, $this->settings ) && '' !== $this->settings[ $key ] && null !== $this->settings[ $key ] ) {
			return $this->settings[ $key ];
		}

		return APF_Settings::get( $key );
	}

	/**
	 * Serialises the Filter Set for JSON responses and the admin builder.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'id'        => $this->id,
			'name'      => $this->name,
			'enabled'   => $this->enabled,
			'priority'  => $this->priority,
			'locations' => $this->locations,
			'sections'  => $this->sections,
			'settings'  => $this->settings,
		);
	}
}
