<?php
/**
 * Main plugin singleton — wires every subsystem together.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Boots every subsystem (post types, admin, frontend, AJAX, REST,
 * Elementor) and owns the cache-busting hooks that keep facet counts
 * fresh as products change.
 */
final class APF_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Query builder shared by the frontend `pre_get_posts` integration and
	 * the AJAX handlers.
	 *
	 * @var APF_Query_Builder
	 */
	public APF_Query_Builder $query_builder;

	/**
	 * Returns the singleton instance, booting the plugin on first access.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor — use `APF_Plugin::instance()`.
	 */
	private function __construct() {
		require_once APF_PATH . 'includes/frontend/functions-frontend.php';

		$this->load_textdomain();

		new APF_Post_Types();
		new APF_Term_Swatches();

		$this->query_builder = new APF_Query_Builder();

		new APF_Ajax( $this->query_builder );
		new APF_Rest_Controller( $this->query_builder );

		if ( is_admin() ) {
			new APF_Admin();
		} else {
			new APF_Frontend( $this->query_builder );
		}

		new APF_Shortcode();

		if ( did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin' ) ) {
			new APF_Elementor( $this->query_builder );
		} else {
			add_action( 'elementor/loaded', array( $this, 'load_elementor_integration' ) );
		}

		$this->hook_cache_invalidation();

		do_action( 'apf_loaded', $this );
	}

	/**
	 * Boots the Elementor integration once Elementor loads after us.
	 *
	 * @return void
	 */
	public function load_elementor_integration(): void {
		new APF_Elementor( $this->query_builder );
	}

	/**
	 * Loads the plugin's translation files.
	 *
	 * @return void
	 */
	private function load_textdomain(): void {
		load_plugin_textdomain( APF_TEXT_DOMAIN, false, dirname( APF_BASENAME ) . '/languages' );
	}

	/**
	 * Flushes cached facet counts whenever product data that affects them
	 * changes, so shoppers never see stale counts.
	 *
	 * @return void
	 */
	private function hook_cache_invalidation(): void {
		$invalidate = static function (): void {
			APF_Cache::flush();
		};

		add_action( 'save_post_product', $invalidate );
		add_action( 'deleted_post', $invalidate );
		add_action( 'woocommerce_update_product', $invalidate );
		add_action( 'woocommerce_new_product', $invalidate );
		add_action( 'woocommerce_product_set_stock_status', $invalidate );
		add_action( 'woocommerce_variation_set_stock_status', $invalidate );
		add_action( 'created_term', $invalidate );
		add_action( 'edited_term', $invalidate );
		add_action( 'delete_term', $invalidate );
	}
}
