<?php
/**
 * Hello Elementor Child theme functions.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HELLO_ELEMENTOR_CHILD_VERSION', '1.5.7' );
define( 'HELLO_ELEMENTOR_CHILD_PATH', get_stylesheet_directory() . '/' );
define( 'HELLO_ELEMENTOR_CHILD_URI', get_stylesheet_directory_uri() . '/' );

/** Style handle for local Vazirmatn @font-face. */
define( 'HELLO_ELEMENTOR_CHILD_VAZIRMATN_HANDLE', 'hello-elementor-child-vazirmatn' );

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
 * Register + enqueue local Vazirmatn and site-wide typography override.
 * Runs on every front-end request (including light templates).
 */
function hello_elementor_child_enqueue_vazirmatn(): void {
	$font_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/vazirmatn.css';

	if ( ! file_exists( $font_css ) ) {
		return;
	}

	wp_enqueue_style(
		HELLO_ELEMENTOR_CHILD_VAZIRMATN_HANDLE,
		HELLO_ELEMENTOR_CHILD_URI . 'assets/css/vazirmatn.css',
		array(),
		(string) filemtime( $font_css )
	);
}
add_action( 'wp_enqueue_scripts', 'hello_elementor_child_enqueue_vazirmatn', 5 );

/**
 * Site typography override late so it beats Elementor Kit / widget CSS.
 */
function hello_elementor_child_enqueue_site_typography(): void {
	$type_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/site-typography.css';
	if ( ! file_exists( $type_css ) ) {
		return;
	}

	wp_enqueue_style(
		'hello-elementor-child-site-typography',
		HELLO_ELEMENTOR_CHILD_URI . 'assets/css/site-typography.css',
		array( HELLO_ELEMENTOR_CHILD_VAZIRMATN_HANDLE ),
		(string) filemtime( $type_css )
	);
}
add_action( 'wp_enqueue_scripts', 'hello_elementor_child_enqueue_site_typography', 999 );

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
		array( 'hello-elementor-theme-style', HELLO_ELEMENTOR_CHILD_VAZIRMATN_HANDLE ),
		HELLO_ELEMENTOR_CHILD_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'hello_elementor_child_enqueue_styles', 20 );

/**
 * Category CTA + intro typography on single product pages.
 */
function hello_elementor_child_enqueue_product_cat_cta() {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}

	$intro_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/product-intro-typography.css';
	if ( file_exists( $intro_css ) ) {
		wp_enqueue_style(
			'hello-elementor-child-product-intro-typography',
			HELLO_ELEMENTOR_CHILD_URI . 'assets/css/product-intro-typography.css',
			array( HELLO_ELEMENTOR_CHILD_VAZIRMATN_HANDLE ),
			(string) filemtime( $intro_css )
		);
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
		array( 'hello-elementor-child-product-intro-typography' ),
		(string) filemtime( $css_file )
	);
}
add_action( 'wp_enqueue_scripts', 'hello_elementor_child_enqueue_product_cat_cta', 1001 );

/**
 * Stamp lk-cat-archive-btn on product-category links inside the product description
 * and normalize visible label to «دسته بندی [category name]».
 *
 * @param string $description Product description HTML.
 * @return string
 */
function hello_elementor_child_mark_description_cat_cta( $description ) {
	if ( ! is_string( $description ) || '' === $description ) {
		return $description;
	}

	$marked = preg_replace_callback(
		'#<a\s([^>]*?)>(.*?)</a>#isu',
		static function ( array $m ): string {
			$attrs = $m[1];
			$inner = $m[2];
			if ( ! preg_match( '#href=(["\'])(.*?)\1#iu', $attrs, $href_m ) ) {
				return $m[0];
			}
			$href = html_entity_decode( (string) $href_m[2], ENT_QUOTES, 'UTF-8' );
			if ( false === stripos( $href, 'product-category' )
				&& ! preg_match( '#^https?://(?:www\.)?lookazma\.com/?$#iu', $href )
			) {
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

			$label = hello_elementor_child_resolve_cat_cta_label( $href, $inner );
			return '<a ' . $attrs . '>' . esc_html( $label ) . '</a>';
		},
		$description
	);

	return is_string( $marked ) ? $marked : $description;
}
add_filter( 'woocommerce_product_get_description', 'hello_elementor_child_mark_description_cat_cta', 20 );

/**
 * Build «دسته بندی …» label; fix URL / ellipsis / bare names as link text.
 *
 * @param string $href  Link URL.
 * @param string $inner Current anchor inner HTML.
 * @return string
 */
function hello_elementor_child_resolve_cat_cta_label( string $href, string $inner ): string {
	$text = trim(
		html_entity_decode(
			wp_strip_all_tags( $inner ),
			ENT_QUOTES,
			'UTF-8'
		)
	);

	$prefix = 'دسته بندی ';
	$looks_ok = ( 0 === strpos( $text, $prefix ) )
		&& strlen( $text ) > strlen( $prefix )
		&& false === stripos( $text, 'http' )
		&& '...' !== $text
		&& '…' !== $text;

	if ( $looks_ok ) {
		return $text;
	}

	$name = hello_elementor_child_category_name_from_url( $href );
	if ( '' === $name ) {
		$name = 'لوک آزما';
	}

	return $prefix . $name;
}

/**
 * Resolve product_cat term name from a category archive URL.
 *
 * @param string $url Category URL.
 * @return string
 */
function hello_elementor_child_category_name_from_url( string $url ): string {
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	if ( '' === $path ) {
		return '';
	}

	if ( ! preg_match( '#/product-category/(.+?)/?$#iu', $path, $m ) ) {
		return '';
	}

	$segments = array_values(
		array_filter(
			explode( '/', trim( (string) $m[1], '/' ) )
		)
	);
	if ( array() === $segments ) {
		return '';
	}

	$slug = rawurldecode( (string) end( $segments ) );
	$term = get_term_by( 'slug', $slug, 'product_cat' );
	if ( $term instanceof WP_Term && ! is_wp_error( $term ) ) {
		return (string) $term->name;
	}

	return '';
}
