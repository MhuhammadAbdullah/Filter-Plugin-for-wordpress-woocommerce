<?php
/**
 * Central registry of all available filter types.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Filters;

use MABCommerceFilters\Interfaces\FilterTypeInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps filter "type" strings (as stored in the filters table) to the
 * FilterTypeInterface implementation that renders and queries them.
 */
final class FilterTypeRegistry {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Registered filter type instances keyed by type name.
	 *
	 * @var array<string, FilterTypeInterface>
	 */
	private array $types = array();

	/**
	 * Private constructor, registers all built-in filter types.
	 */
	private function __construct() {
		$this->register( new CategoryFilterType() );

		$taxonomy = new TaxonomyFilterType();
		$this->register( $taxonomy );
		$this->alias( 'tag', $taxonomy );
		$this->alias( 'brand', $taxonomy );
		$this->alias( 'custom_taxonomy', $taxonomy );

		$this->register( new AttributeFilterType() );
		$this->register( new PriceFilterType() );
		$this->register( new RatingFilterType() );
		$this->register( new StockFilterType() );
		$this->register( new SaleFilterType() );
		$this->register( new SearchFilterType() );

		$meta = new MetaFilterType();
		$this->register( $meta );
		$this->alias( 'custom_field', $meta );
		$this->alias( 'boolean', $meta );
		$this->alias( 'date', $meta );
		$this->alias( 'number', $meta );
		$this->alias( 'text', $meta );

		/**
		 * Fires after all built-in filter types are registered, so third
		 * parties can register additional custom filter types.
		 *
		 * @param FilterTypeRegistry $registry The registry instance.
		 */
		do_action( 'mabcf_register_filter_types', $this );
	}

	/**
	 * Returns the shared registry instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers (or overrides) a filter type implementation.
	 *
	 * @param FilterTypeInterface $type Filter type implementation.
	 */
	public function register( FilterTypeInterface $type ): void {
		$this->types[ $type->get_type() ] = $type;
	}

	/**
	 * Registers an additional type key that resolves to an already
	 * constructed filter type instance (e.g. "tag" and "brand" both
	 * resolve to the generic TaxonomyFilterType).
	 *
	 * @param string              $alias Alias type name.
	 * @param FilterTypeInterface $type  Existing instance to reuse.
	 */
	public function alias( string $alias, FilterTypeInterface $type ): void {
		$this->types[ $alias ] = $type;
	}

	/**
	 * Retrieves a filter type by name, or null when unregistered.
	 *
	 * @param string $type Filter type machine name.
	 */
	public function get( string $type ): ?FilterTypeInterface {
		return $this->types[ $type ] ?? null;
	}

	/**
	 * Returns every registered filter type.
	 *
	 * @return array<string, FilterTypeInterface>
	 */
	public function all(): array {
		return $this->types;
	}

	/**
	 * Returns type => label pairs for use in admin UI selects.
	 *
	 * @return array<string, string>
	 */
	public function labels(): array {
		$labels = array();

		foreach ( $this->types as $key => $type ) {
			$labels[ $key ] = method_exists( $type, 'get_label' ) ? $type->get_label() : $key;
		}

		return $labels;
	}
}
