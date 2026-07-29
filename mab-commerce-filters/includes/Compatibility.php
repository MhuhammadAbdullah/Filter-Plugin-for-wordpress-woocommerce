<?php
/**
 * Declares WooCommerce feature compatibility (HPOS, Cart & Checkout Blocks).
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Must be wired up on `before_woocommerce_init` — the only hook
 * WooCommerce's `FeaturesUtil::declare_compatibility()` accepts — which
 * fires during the `plugins_loaded` sequence, potentially before this
 * plugin's own `plugins_loaded` bootstrap runs. `register()` is
 * therefore called directly from the main plugin file, right after the
 * autoloader, instead of from `Plugin::boot()`.
 *
 * The plugin only ever reads/writes WooCommerce *products* (via
 * `WP_Query`, `WC_Product`, taxonomies and product meta) and never
 * touches order data or renders classic cart/checkout markup, so it is
 * safe to declare full compatibility with both High-Performance Order
 * Storage and the Cart & Checkout blocks.
 */
final class Compatibility {

	/**
	 * Registers the `before_woocommerce_init` listener.
	 */
	public static function register(): void {
		add_action( 'before_woocommerce_init', array( self::class, 'declare_feature_compatibility' ) );
	}

	/**
	 * Declares compatibility with every WooCommerce feature this plugin
	 * has been verified against. No-ops gracefully on WooCommerce
	 * versions that predate `FeaturesUtil` (< 6.4), preserving backward
	 * compatibility with `MABCF_MIN_WC`.
	 */
	public static function declare_feature_compatibility(): void {
		if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			return;
		}

		// High-Performance Order Storage ("Custom order tables" / HPOS).
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			MABCF_FILE,
			true
		);

		// Cart & Checkout Blocks (Store API based cart/checkout).
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'cart_checkout_blocks',
			MABCF_FILE,
			true
		);
	}
}
