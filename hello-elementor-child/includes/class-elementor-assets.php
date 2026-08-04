<?php
/**
 * Disable Elementor frontend assets on Timber single product.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dequeues Elementor styles/scripts on product singular pages.
 */
final class Hello_Elementor_Child_Elementor_Assets {

	/**
	 * Hook into WordPress.
	 */
	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'dequeue_elementor_assets' ), 100 );
		add_action( 'wp_print_styles', array( __CLASS__, 'dequeue_elementor_assets' ), 100 );
		add_action( 'wp_print_scripts', array( __CLASS__, 'dequeue_elementor_assets' ), 100 );
	}

	/**
	 * Remove Elementor CSS/JS on single product pages only.
	 */
	public static function dequeue_elementor_assets(): void {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		$style_handles = array(
			'elementor-frontend',
			'elementor-frontend-legacy',
			'elementor-icons',
			'elementor-animations',
			'elementor-common',
			'elementor-pro',
			'elementor-pro-frontend',
			'e-motion-fx',
			'e-sticky',
			'font-awesome-5-all',
			'font-awesome-4-shim',
			'swatchbook-css',
		);

		$script_handles = array(
			'elementor-frontend',
			'elementor-frontend-modules',
			'elementor-webpack-runtime',
			'elementor-common',
			'elementor-web-cli',
			'elementor-pro-frontend',
			'elementor-pro-webpack-runtime',
			'elementor-pro-elements-handlers',
			'elementor-v2-editor-app-loader',
			'swatchbook-js',
		);

		foreach ( $style_handles as $handle ) {
			wp_dequeue_style( $handle );
			wp_deregister_style( $handle );
		}

		foreach ( $script_handles as $handle ) {
			wp_dequeue_script( $handle );
			wp_deregister_script( $handle );
		}

		// Catch dynamically registered Elementor handles.
		global $wp_styles, $wp_scripts;

		if ( $wp_styles instanceof WP_Styles ) {
			foreach ( (array) $wp_styles->queue as $handle ) {
				if ( self::is_elementor_handle( $handle ) ) {
					wp_dequeue_style( $handle );
					wp_deregister_style( $handle );
				}
			}
		}

		if ( $wp_scripts instanceof WP_Scripts ) {
			foreach ( (array) $wp_scripts->queue as $handle ) {
				if ( self::is_elementor_handle( $handle ) ) {
					wp_dequeue_script( $handle );
					wp_deregister_script( $handle );
				}
			}
		}
	}

	/**
	 * Whether a handle belongs to Elementor / Elementor Pro.
	 *
	 * @param string $handle Asset handle.
	 * @return bool
	 */
	private static function is_elementor_handle( string $handle ): bool {
		$handle = strtolower( $handle );

		return str_starts_with( $handle, 'elementor' )
			|| str_starts_with( $handle, 'e-' )
			|| false !== strpos( $handle, 'elementor' );
	}
}
