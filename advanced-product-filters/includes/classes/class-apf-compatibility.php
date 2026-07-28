<?php
/**
 * Environment compatibility checks.
 *
 * @package AdvancedProductFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Verifies the current environment satisfies the plugin's requirements
 * before any bootstrapping happens, surfacing friendly admin notices
 * instead of fatal errors when it does not.
 */
final class APF_Compatibility {

	/**
	 * Runs all compatibility checks.
	 *
	 * @return bool True when the environment is compatible.
	 */
	public static function check(): bool {
		$errors = array();

		if ( version_compare( PHP_VERSION, APF_MIN_PHP, '<' ) ) {
			/* translators: 1: required PHP version, 2: current PHP version */
			$errors[] = sprintf(
				__( 'Advanced Product Filters requires PHP %1$s or higher. You are running PHP %2$s.', 'advanced-product-filters' ),
				APF_MIN_PHP,
				PHP_VERSION
			);
		}

		if ( ! self::is_woocommerce_active() ) {
			$errors[] = __( 'Advanced Product Filters requires WooCommerce to be installed and active.', 'advanced-product-filters' );
		} elseif ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, APF_MIN_WC, '<' ) ) {
			/* translators: 1: required WooCommerce version, 2: current WooCommerce version */
			$errors[] = sprintf(
				__( 'Advanced Product Filters requires WooCommerce %1$s or higher. You are running WooCommerce %2$s.', 'advanced-product-filters' ),
				APF_MIN_WC,
				WC_VERSION
			);
		}

		if ( ! empty( $errors ) ) {
			self::$notices = $errors;
			add_action( 'admin_notices', array( __CLASS__, 'render_notices' ) );
			return false;
		}

		return true;
	}

	/**
	 * Collected human readable error messages.
	 *
	 * @var string[]
	 */
	private static array $notices = array();

	/**
	 * Determines whether WooCommerce is active on single sites and
	 * network-wide multisite installs alike.
	 *
	 * @return bool
	 */
	private static function is_woocommerce_active(): bool {
		if ( class_exists( 'WooCommerce' ) ) {
			return true;
		}

		$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}

		return in_array( 'woocommerce/woocommerce.php', $active_plugins, true );
	}

	/**
	 * Prints the collected compatibility notices in wp-admin.
	 *
	 * @return void
	 */
	public static function render_notices(): void {
		if ( empty( self::$notices ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Advanced Product Filters', 'advanced-product-filters' ) . ':</strong></p><ul style="list-style: disc; margin-left: 1.5em;">';

		foreach ( self::$notices as $notice ) {
			echo '<li>' . esc_html( $notice ) . '</li>';
		}

		echo '</ul></div>';
	}
}
