<?php
/**
 * Native Brands page — Timber entry (Page → Light brands template).
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Timber\Timber' )
	|| ! class_exists( 'Hello_Elementor_Child_Light_Brands_Template' )
	|| ! class_exists( 'Hello_Elementor_Child_Brand_Cpt' )
) {
	status_header( 500 );
	wp_die( esc_html__( 'Timber یا تمپلیت برندها در دسترس نیست.', 'hello-elementor-child' ) );
}

$context = \Timber\Timber::context();
if ( class_exists( 'Hello_Elementor_Child_Light_Product_Template' ) ) {
	$context = array_merge( $context, Hello_Elementor_Child_Light_Product_Template::get_chrome_context() );
}

ob_start();
language_attributes();
$context['html_language_attributes'] = trim( ob_get_clean() );
$context['body_class']               = implode( ' ', get_body_class( 'lk-light-product lk-light-brands-archive lz-chrome' ) );

$css_file = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-light-product.css';
$context['light_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-light-product.css';
$context['light_css_ver'] = file_exists( $css_file ) ? (string) filemtime( $css_file ) : HELLO_ELEMENTOR_CHILD_VERSION;

$brands_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-light-brands.css';
$context['brands_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-light-brands.css';
$context['brands_css_ver'] = file_exists( $brands_css ) ? (string) filemtime( $brands_css ) : HELLO_ELEMENTOR_CHILD_VERSION;

$context['brands_archive'] = Hello_Elementor_Child_Light_Brands_Template::get_page_context();

Hello_Elementor_Child_Light_Brands_Template::enqueue_assets();

echo '<!-- LK-LIGHT-BRANDS-PAGE-TEMPLATE-ACTIVE -->' . "\n";

\Timber\Timber::render( 'woo/archive-brands.twig', $context );
