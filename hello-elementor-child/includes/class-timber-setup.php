<?php
/**
 * Timber setup for the child theme.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers Twig locations and WooCommerce single-product rendering.
 */
final class Hello_Elementor_Child_Timber {

	/**
	 * Hook into WordPress / Timber.
	 */
	public static function init(): void {
		if ( ! class_exists( '\Timber\Timber' ) ) {
			return;
		}

		add_filter( 'timber/locations', array( __CLASS__, 'add_locations' ) );
		add_filter( 'template_include', array( __CLASS__, 'single_product_template' ), 9999 );
		add_filter( 'elementor/theme/need_override_location', array( __CLASS__, 'disable_elementor_single_override' ), 999, 2 );
	}

	/**
	 * Twig view directories.
	 *
	 * @param array<int|string, string|array<int, string>> $locations Existing locations.
	 * @return array<int|string, string|array<int, string>>
	 */
	public static function add_locations( $locations ) {
		$locations[] = HELLO_ELEMENTOR_CHILD_PATH . 'views';
		return $locations;
	}

	/**
	 * Force Timber template for WooCommerce single product.
	 *
	 * @param string $template Current template path.
	 * @return string
	 */
	public static function single_product_template( string $template ): string {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return $template;
		}

		$custom = HELLO_ELEMENTOR_CHILD_PATH . 'woocommerce/single-product.php';
		if ( file_exists( $custom ) ) {
			return $custom;
		}

		return $template;
	}

	/**
	 * Prevent Elementor Theme Builder from overriding single product.
	 *
	 * @param bool   $need_override Whether Elementor wants override.
	 * @param string $location      Location name.
	 * @return bool
	 */
	public static function disable_elementor_single_override( bool $need_override, string $location ): bool {
		if ( function_exists( 'is_product' ) && is_product() && 'single' === $location ) {
			return false;
		}

		return $need_override;
	}
}
