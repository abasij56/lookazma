<?php
/**
 * Hello Elementor Child theme functions.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HELLO_ELEMENTOR_CHILD_VERSION', '1.5.6' );
define( 'HELLO_ELEMENTOR_CHILD_PATH', get_stylesheet_directory() . '/' );
define( 'HELLO_ELEMENTOR_CHILD_URI', get_stylesheet_directory_uri() . '/' );

$hello_elementor_child_autoload = HELLO_ELEMENTOR_CHILD_PATH . 'vendor/autoload.php';
if ( file_exists( $hello_elementor_child_autoload ) ) {
	require_once $hello_elementor_child_autoload;
}

if ( class_exists( '\Timber\Timber' ) ) {
	\Timber\Timber::init();
}

require_once HELLO_ELEMENTOR_CHILD_PATH . 'includes/class-theme.php';

Hello_Elementor_Child_Theme::init();

/**
 * Enqueue child theme stylesheet.
 */
function hello_elementor_child_enqueue_styles() {
	if ( class_exists( 'Hello_Elementor_Child_Custom_Single_Product' )
		&& Hello_Elementor_Child_Custom_Single_Product::is_enabled()
	) {
		return;
	}

	wp_enqueue_style(
		'hello-elementor-child-style',
		get_stylesheet_directory_uri() . '/style.css',
		array( 'hello-elementor-theme-style' ),
		HELLO_ELEMENTOR_CHILD_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'hello_elementor_child_enqueue_styles', 20 );

/**
 * Category CTA button styles on classic / Elementor single products.
 * Light template already styles these via single-product-light.css (.lk-prose a).
 */
function hello_elementor_child_enqueue_product_cat_cta() {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}

	if ( class_exists( 'Hello_Elementor_Child_Custom_Single_Product' )
		&& Hello_Elementor_Child_Custom_Single_Product::is_enabled()
	) {
		return;
	}

	$css_file = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/product-cat-cta.css';
	if ( ! file_exists( $css_file ) ) {
		return;
	}

	wp_enqueue_style(
		'hello-elementor-child-product-cat-cta',
		HELLO_ELEMENTOR_CHILD_URI . 'assets/css/product-cat-cta.css',
		array(),
		(string) filemtime( $css_file )
	);
}
add_action( 'wp_enqueue_scripts', 'hello_elementor_child_enqueue_product_cat_cta', 25 );

/**
 * Stamp lk-cat-archive-btn on product-category links inside the product description
 * so Elementor nested tabs (and classic tabs) can style the CTA without touching breadcrumbs.
 *
 * @param string $description Product description HTML.
 * @return string
 */
function hello_elementor_child_mark_description_cat_cta( $description ) {
	if ( ! is_string( $description ) || '' === $description ) {
		return $description;
	}

	$marked = preg_replace_callback(
		'#<a\s([^>]*?)>#iu',
		static function ( array $m ): string {
			$attrs = $m[1];
			if ( ! preg_match( '#href=(["\'])(.*?)\1#iu', $attrs, $href_m ) ) {
				return $m[0];
			}
			$href = (string) $href_m[2];
			if ( false === stripos( $href, 'product-category' ) ) {
				return $m[0];
			}
			if ( preg_match( '#\bclass=(["\'])(.*?)\1#iu', $attrs, $class_m ) ) {
				$classes = preg_split( '/\s+/', trim( (string) $class_m[2] ) ) ?: array();
				if ( ! in_array( 'lk-cat-archive-btn', $classes, true ) ) {
					$classes[] = 'lk-cat-archive-btn';
				}
				$attrs = preg_replace(
					'#\bclass=(["\'])(.*?)\1#iu',
					'class="' . esc_attr( implode( ' ', $classes ) ) . '"',
					$attrs,
					1
				);
			} else {
				$attrs = 'class="lk-cat-archive-btn" ' . ltrim( $attrs );
			}
			return '<a ' . $attrs . '>';
		},
		$description
	);

	return is_string( $marked ) ? $marked : $description;
}
add_filter( 'woocommerce_product_get_description', 'hello_elementor_child_mark_description_cat_cta', 20 );
