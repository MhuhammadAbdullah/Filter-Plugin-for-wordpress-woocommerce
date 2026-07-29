<?php
/**
 * Tracks per-product view counts backing the "Most Viewed" sale filter.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Increments a `_mabcf_view_count` post meta counter once per visitor per
 * product per day (deduplicated via a short-lived cookie) so repeated
 * page loads from the same visitor don't inflate the count.
 */
final class ViewTracker {

	/**
	 * Registers the tracking hook.
	 */
	public function register(): void {
		add_action( 'woocommerce_before_single_product', array( $this, 'track' ) );
	}

	/**
	 * Records a view for the current single product, once per day per
	 * visitor.
	 */
	public function track(): void {
		if ( ! is_singular( 'product' ) || is_admin() ) {
			return;
		}

		$product_id = get_the_ID();

		if ( ! $product_id ) {
			return;
		}

		$cookie_name = 'mabcf_viewed_' . $product_id;

		if ( isset( $_COOKIE[ $cookie_name ] ) ) {
			return;
		}

		$count = (int) get_post_meta( $product_id, '_mabcf_view_count', true );
		update_post_meta( $product_id, '_mabcf_view_count', $count + 1 );

		if ( ! headers_sent() ) {
			setcookie( $cookie_name, '1', time() + DAY_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true );
		}
	}
}
