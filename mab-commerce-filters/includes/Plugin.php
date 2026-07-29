<?php
/**
 * Main plugin bootstrap: dependency checks and service wiring.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters;

use MABCommerceFilters\Admin\AdminMenu;
use MABCommerceFilters\Admin\Ajax\AdminAjaxController;
use MABCommerceFilters\Admin\Pages\ImportExportPage;
use MABCommerceFilters\Admin\TermMetaFields;
use MABCommerceFilters\Ajax\FilterAjaxController;
use MABCommerceFilters\Database\Migrator;
use MABCommerceFilters\Elementor\ElementorIntegration;
use MABCommerceFilters\Frontend\AssetLoader;
use MABCommerceFilters\Frontend\FilterWidget;
use MABCommerceFilters\Frontend\MainQueryIntegration;
use MABCommerceFilters\Frontend\ShortcodeHandler;
use MABCommerceFilters\Repositories\LogRepository;
use MABCommerceFilters\Rest\RestController;
use MABCommerceFilters\Services\ViewTracker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Singleton responsible for verifying dependencies and instantiating
 * every controller/service the plugin needs, once, on `plugins_loaded`.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Whether boot() has already run.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Returns the shared plugin instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor (singleton).
	 */
	private function __construct() {}

	/**
	 * Boots the plugin: checks dependencies, loads translations, upgrades
	 * the database if needed, and wires every controller/service.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		load_plugin_textdomain( 'mab-commerce-filters', false, dirname( MABCF_BASENAME ) . '/languages' );

		if ( ! $this->dependencies_met() ) {
			add_action( 'admin_notices', array( $this, 'render_dependency_notice' ) );
			return;
		}

		$this->maybe_upgrade();
		$this->register_frontend();
		$this->register_ajax();
		$this->register_rest();

		if ( is_admin() ) {
			$this->register_admin();
		}

		if ( did_action( 'elementor/loaded' ) || defined( 'ELEMENTOR_VERSION' ) ) {
			( new ElementorIntegration() )->register();
		} else {
			add_action( 'elementor/loaded', array( $this, 'register_elementor_late' ) );
		}

		add_action( 'widgets_init', array( $this, 'register_widgets' ) );

		( new ViewTracker() )->register();

		/**
		 * Fires once the plugin has finished booting all of its services,
		 * so third parties can register their own filter types, hooks,
		 * or integrations at a known-safe point.
		 */
		do_action( 'mabcf_loaded' );
	}

	/**
	 * Registers Elementor integration when Elementor loads after this
	 * plugin (covers plugin-load-order edge cases).
	 */
	public function register_elementor_late(): void {
		( new ElementorIntegration() )->register();
	}

	/**
	 * Registers the classic WP_Widget for non-Elementor themes.
	 */
	public function register_widgets(): void {
		register_widget( FilterWidget::class );
	}

	/**
	 * Verifies WooCommerce is active and PHP/WP minimums are met.
	 */
	private function dependencies_met(): bool {
		if ( version_compare( PHP_VERSION, MABCF_MIN_PHP, '<' ) ) {
			return false;
		}

		if ( ! class_exists( '\\WooCommerce' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Renders an admin notice explaining why the plugin did not load.
	 */
	public function render_dependency_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$message = ! class_exists( '\\WooCommerce' )
			? __( 'MAB Commerce Filters requires WooCommerce to be installed and active.', 'mab-commerce-filters' )
			/* translators: %s: required PHP version. */
			: sprintf( __( 'MAB Commerce Filters requires PHP %s or higher.', 'mab-commerce-filters' ), MABCF_MIN_PHP );

		printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $message ) );
	}

	/**
	 * Runs pending DB migrations when the stored schema version is stale.
	 */
	private function maybe_upgrade(): void {
		if ( get_option( 'mabcf_db_version' ) !== MABCF_DB_VERSION ) {
			( new Migrator() )->migrate();
		}
	}

	/**
	 * Wires front-end rendering services.
	 */
	private function register_frontend(): void {
		( new AssetLoader() )->register();
		( new ShortcodeHandler() )->register();
		( new MainQueryIntegration() )->register();
	}

	/**
	 * Wires the public AJAX controller.
	 */
	private function register_ajax(): void {
		( new FilterAjaxController() )->register();
	}

	/**
	 * Wires the REST API controller.
	 */
	private function register_rest(): void {
		( new RestController() )->register();
	}

	/**
	 * Wires admin-only controllers and screens.
	 */
	private function register_admin(): void {
		( new AdminMenu() )->register();
		( new AdminAjaxController() )->register();
		( new ImportExportPage() )->register();
		( new TermMetaFields() )->register();
	}
}
