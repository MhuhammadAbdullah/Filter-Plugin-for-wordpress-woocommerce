<?php
/**
 * Conditionally loads front-end CSS/JS only where a filter can appear.
 *
 * @package MABCommerceFilters
 */

namespace MABCommerceFilters\Frontend;

use MABCommerceFilters\Helpers\Helper;
use MABCommerceFilters\Repositories\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers `mabcf-frontend` style/script and enqueues them on shop,
 * product taxonomy archives, and any page/post containing the plugin's
 * shortcode. Elementor widgets enqueue the same handles on demand.
 */
final class AssetLoader {

	/**
	 * Registers WordPress hooks.
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ), 20 );
	}

	/**
	 * Registers (without enqueuing) the shared handles.
	 */
	public function register_assets(): void {
		wp_register_style( 'mabcf-frontend', Helper::asset_url( 'css/frontend.css' ), array(), MABCF_VERSION );
		wp_register_script( 'mabcf-frontend', Helper::asset_url( 'js/frontend.js' ), array(), MABCF_VERSION, true );

		$settings = new SettingsRepository();

		wp_localize_script(
			'mabcf-frontend',
			'mabcfSettings',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'mabcf_public' ),
				'i18n'    => array(
					'seeMore'  => __( 'See More', 'mab-commerce-filters' ),
					'seeLess'  => __( 'See Less', 'mab-commerce-filters' ),
					'loading'  => __( 'Loading…', 'mab-commerce-filters' ),
				),
				'primaryColor' => (string) $settings->get( 'primary_color', '#111111' ),
			)
		);
	}

	/**
	 * Enqueues the assets when the current request can render a filter.
	 */
	public function maybe_enqueue(): void {
		if ( $this->should_load() ) {
			wp_enqueue_style( 'mabcf-frontend' );
			wp_enqueue_script( 'mabcf-frontend' );
		}
	}

	/**
	 * Determines whether the current request may render a filter widget.
	 */
	private function should_load(): bool {
		if ( function_exists( 'is_shop' ) && is_shop() ) {
			return true;
		}

		if ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) {
			return true;
		}

		$post = get_post();

		if ( $post && has_shortcode( (string) $post->post_content, 'mabcf_filters' ) ) {
			return true;
		}

		if ( $post && did_action( 'elementor/loaded' ) && 'builder' === get_post_meta( $post->ID, '_elementor_edit_mode', true ) ) {
			return true;
		}

		/**
		 * Filters whether the front-end assets should be enqueued for the
		 * current request, so integrators can force-load them for custom
		 * templates that render a filter widget programmatically.
		 *
		 * @param bool $should_load Whether to enqueue.
		 */
		return (bool) apply_filters( 'mabcf_should_load_assets', false );
	}
}
