<?php
/**
 * Plugin Name:       MAB Commerce Filters
 * Plugin URI:        https://mabcommercefilters.com
 * Description:       Premium WooCommerce AJAX Product Filters for Elementor. Category, price, color, size, brand, tag, rating, stock and custom attribute filters with a drag & drop filter builder.
 * Version:           1.0.2
 * Requires at least: 6.0
 * Requires PHP:      8.2
 * Requires Plugins:  woocommerce
 * Author:            MAB Commerce
 * Author URI:        https://mabcommercefilters.com
 * License:            GPL v2 or later
 * License URI:        https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:        mab-commerce-filters
 * Domain Path:        /languages
 * WC requires at least: 7.0
 * WC tested up to:      10.9
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MABCF_VERSION', '1.0.2' );
define( 'MABCF_FILE', __FILE__ );
define( 'MABCF_PATH', plugin_dir_path( __FILE__ ) );
define( 'MABCF_URL', plugin_dir_url( __FILE__ ) );
define( 'MABCF_BASENAME', plugin_basename( __FILE__ ) );
define( 'MABCF_DB_VERSION', '1.0.0' );
define( 'MABCF_MIN_PHP', '8.2' );
define( 'MABCF_MIN_WP', '6.0' );
define( 'MABCF_MIN_WC', '7.0' );

require_once MABCF_PATH . 'includes/Autoloader.php';
Autoloader::register();

/*
 * Registered unconditionally, immediately on file load — NOT deferred to
 * this plugin's own `plugins_loaded` bootstrap below. WooCommerce fires
 * `before_woocommerce_init` (the only hook FeaturesUtil accepts) from
 * within its own `plugins_loaded` callback, so waiting for our
 * `mabcf_bootstrap()` (priority 20) risks running after WooCommerce has
 * already checked compatibility. This is a no-op when WooCommerce is not
 * active, since the action simply never fires.
 */
Compatibility::register();

register_activation_hook( __FILE__, array( '\\MABCommerceFilters\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\MABCommerceFilters\\Deactivator', 'deactivate' ) );

/**
 * Boots the plugin once all plugins are loaded so WooCommerce/Elementor
 * dependency checks and text-domain loading happen at the right hook.
 */
function mabcf_bootstrap(): void {
	Plugin::instance()->boot();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\mabcf_bootstrap', 20 );
