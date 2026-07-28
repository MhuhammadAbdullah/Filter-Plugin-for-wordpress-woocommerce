<?php
/**
 * Plugin Name:       Advanced Product Filters
 * Plugin URI:        https://example.com/advanced-product-filters
 * Description:       Premium AJAX product filtering for WooCommerce — categories, price, swatches, attributes, brands, tags, ratings and more, with an Elementor & Gutenberg ready sidebar builder.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Advanced Product Filters Team
 * Author URI:        https://example.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       advanced-product-filters
 * Domain Path:       /languages
 * WC requires at least: 6.0
 * WC tested up to:   9.4
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

// -----------------------------------------------------------------------
// Core plugin constants.
// -----------------------------------------------------------------------
define( 'APF_VERSION', '1.0.0' );
define( 'APF_FILE', __FILE__ );
define( 'APF_PATH', plugin_dir_path( __FILE__ ) );
define( 'APF_URL', plugin_dir_url( __FILE__ ) );
define( 'APF_BASENAME', plugin_basename( __FILE__ ) );
define( 'APF_TEXT_DOMAIN', 'advanced-product-filters' );
define( 'APF_MIN_PHP', '8.0' );
define( 'APF_MIN_WC', '6.0' );

require_once APF_PATH . 'includes/classes/class-apf-autoloader.php';
APF_Autoloader::register();

register_activation_hook( __FILE__, array( 'APF_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'APF_Deactivator', 'deactivate' ) );

/**
 * Declares compatibility with the WooCommerce features that require an
 * explicit opt-in (High-Performance Order Storage / custom order tables,
 * and the Cart & Checkout blocks). This plugin never reads or writes
 * order data and never touches the cart/checkout templates — it only
 * queries products and taxonomies — so it is safe to declare full
 * compatibility with both. Without this, WooCommerce shows an "incompatible
 * plugin" admin notice purely because no compatibility was declared, not
 * because anything actually conflicts.
 *
 * @return void
 */
function apf_declare_woocommerce_compatibility(): void {
	if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		return;
	}

	\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', APF_FILE, true );
	\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', APF_FILE, true );
}
add_action( 'before_woocommerce_init', 'apf_declare_woocommerce_compatibility' );

/**
 * Boots the plugin once all plugins are loaded so we can safely detect
 * WooCommerce and other third-party dependencies.
 *
 * @return void
 */
function apf_bootstrap(): void {
	if ( ! APF_Compatibility::check() ) {
		return;
	}

	APF_Plugin::instance();
}
add_action( 'plugins_loaded', 'apf_bootstrap', 20 );
