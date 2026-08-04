<?php
/**
 * Main theme bootstrap.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Theme loader and feature registry.
 */
final class Hello_Elementor_Child_Theme {

	/**
	 * Boot the child theme.
	 */
	public static function init(): void {
		self::load_includes();
		self::init_features();
	}

	/**
	 * Require class files.
	 */
	private static function load_includes(): void {
		require_once HELLO_ELEMENTOR_CHILD_PATH . 'includes/attribute-price-editor/class-attribute-price-editor.php';
		require_once HELLO_ELEMENTOR_CHILD_PATH . 'includes/attribute-price-editor/class-renderer.php';
		require_once HELLO_ELEMENTOR_CHILD_PATH . 'includes/attribute-price-editor/class-ajax-handler.php';
		require_once HELLO_ELEMENTOR_CHILD_PATH . 'includes/class-custom-single-product.php';
		require_once HELLO_ELEMENTOR_CHILD_PATH . 'includes/class-custom-category-archive.php';
		require_once HELLO_ELEMENTOR_CHILD_PATH . 'includes/class-archive-product-filter.php';
		require_once HELLO_ELEMENTOR_CHILD_PATH . 'includes/class-product-search.php';
	}

	/**
	 * Initialize theme features.
	 */
	private static function init_features(): void {
		Hello_Elementor_Child_Attribute_Price_Editor::init();
		Hello_Elementor_Child_Custom_Single_Product::init();
		Hello_Elementor_Child_Custom_Category_Archive::init();
		Hello_Elementor_Child_Archive_Product_Filter::init();
		Hello_Elementor_Child_Product_Search::init();
	}
}
