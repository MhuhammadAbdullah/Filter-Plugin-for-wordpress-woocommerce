<?php
/**
 * Admin bootstrap — menu registration and asset loading.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers every wp-admin page the plugin exposes and loads the admin
 * builder's CSS/JS only on those pages.
 */
final class APF_Admin {

	/**
	 * Capability required to manage the plugin.
	 *
	 * @var string
	 */
	public const CAPABILITY = 'manage_woocommerce';

	/**
	 * Page hook suffixes registered by `add_menu_page()` / `add_submenu_page()`,
	 * used to scope asset loading.
	 *
	 * @var string[]
	 */
	private array $page_hooks = array();

	/**
	 * Editor (builder) page renderer.
	 *
	 * @var APF_Admin_Editor
	 */
	private APF_Admin_Editor $editor;

	/**
	 * Wires the admin hooks.
	 */
	public function __construct() {
		require_once APF_PATH . 'includes/admin/functions-admin.php';

		$this->editor = new APF_Admin_Editor();

		new APF_Admin_Ajax();

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . APF_BASENAME, array( $this, 'add_settings_link' ) );
	}

	/**
	 * Registers the plugin's top-level menu and submenus.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		$this->page_hooks[] = add_menu_page(
			__( 'Advanced Product Filters', 'advanced-product-filters' ),
			__( 'Product Filters', 'advanced-product-filters' ),
			self::CAPABILITY,
			'apf-filter-sets',
			array( $this->editor, 'render_list_page' ),
			'dashicons-filter',
			56
		);

		$this->page_hooks[] = add_submenu_page(
			'apf-filter-sets',
			__( 'Filter Sets', 'advanced-product-filters' ),
			__( 'Filter Sets', 'advanced-product-filters' ),
			self::CAPABILITY,
			'apf-filter-sets',
			array( $this->editor, 'render_list_page' )
		);

		$this->page_hooks[] = add_submenu_page(
			'apf-filter-sets',
			__( 'Add New Filter Set', 'advanced-product-filters' ),
			__( 'Add New', 'advanced-product-filters' ),
			self::CAPABILITY,
			'apf-filter-set-edit',
			array( $this->editor, 'render_editor_page' )
		);

		$this->page_hooks[] = add_submenu_page(
			'apf-filter-sets',
			__( 'Settings', 'advanced-product-filters' ),
			__( 'Settings', 'advanced-product-filters' ),
			self::CAPABILITY,
			'apf-settings',
			array( $this, 'render_settings_page' )
		);

		$this->page_hooks[] = add_submenu_page(
			'apf-filter-sets',
			__( 'Import / Export', 'advanced-product-filters' ),
			__( 'Import / Export', 'advanced-product-filters' ),
			self::CAPABILITY,
			'apf-import-export',
			array( $this, 'render_import_export_page' )
		);
	}

	/**
	 * Enqueues the admin builder assets only on the plugin's own pages.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, $this->page_hooks, true ) ) {
			return;
		}

		wp_enqueue_style( 'apf-admin', APF_URL . 'assets/css/admin.css', array(), APF_VERSION );
		wp_enqueue_script( 'apf-admin', APF_URL . 'assets/js/admin-builder.js', array(), APF_VERSION, true );

		wp_localize_script(
			'apf-admin',
			'APF_ADMIN',
			array(
				'restUrl'         => esc_url_raw( rest_url( 'apf/v1' ) ),
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'apf_admin_nonce' ),
				'sectionTypes'    => APF_Section_Types::all(),
				'inputTypes'      => APF_Filter_Input_Types::all(),
				'taxonomies'      => APF_Taxonomies::get_all_filterable_taxonomies(),
				'attributeTaxonomies' => APF_Taxonomies::get_attribute_taxonomies(),
				'customTaxonomies'    => APF_Taxonomies::get_custom_taxonomies(),
				'brandTaxonomy'   => APF_Taxonomies::get_brand_taxonomy(),
				'listUrl'         => admin_url( 'admin.php?page=apf-filter-sets' ),
				'i18n'            => array(
					'confirmDelete'   => __( 'Delete this Filter Set? This cannot be undone.', 'advanced-product-filters' ),
					'confirmReset'    => __( 'Reset ALL Advanced Product Filters settings and delete every Filter Set? This cannot be undone.', 'advanced-product-filters' ),
					'saved'           => __( 'Filter Set saved.', 'advanced-product-filters' ),
					'saveError'       => __( 'Could not save the Filter Set. Please try again.', 'advanced-product-filters' ),
					'addSection'      => __( 'Add Section', 'advanced-product-filters' ),
					'untitled'        => __( 'Untitled Filter Set', 'advanced-product-filters' ),
				),
			)
		);
	}

	/**
	 * Renders the global settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		require APF_PATH . 'includes/admin/views/view-settings.php';
	}

	/**
	 * Renders the import/export page.
	 *
	 * @return void
	 */
	public function render_import_export_page(): void {
		require APF_PATH . 'includes/admin/views/view-import-export.php';
	}

	/**
	 * Adds a "Settings" shortcut to the plugin's row on the Plugins screen.
	 *
	 * @param string[] $links Existing action links.
	 * @return string[]
	 */
	public function add_settings_link( array $links ): array {
		$links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=apf-settings' ) ),
			esc_html__( 'Settings', 'advanced-product-filters' )
		);

		return $links;
	}
}
