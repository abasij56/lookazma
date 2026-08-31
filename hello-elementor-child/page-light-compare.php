<?php
/**
 * Native Compare page — Timber entry (Page → Light compare template).
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Timber\Timber' )
	|| ! class_exists( 'Hello_Elementor_Child_Light_Compare_Template' )
) {
	status_header( 500 );
	wp_die( esc_html__( 'Timber یا تمپلیت مقایسه در دسترس نیست.', 'hello-elementor-child' ) );
}

$context = \Timber\Timber::context();
if ( class_exists( 'Hello_Elementor_Child_Light_Product_Template' ) ) {
	$context = array_merge( $context, Hello_Elementor_Child_Light_Product_Template::get_chrome_context() );
}

ob_start();
language_attributes();
$context['html_language_attributes'] = trim( ob_get_clean() );
$context['body_class']               = implode( ' ', get_body_class( 'lk-light-product lk-light-compare lz-chrome' ) );

$css_file = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-light-product.css';
$context['light_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-light-product.css';
$context['light_css_ver'] = file_exists( $css_file ) ? (string) filemtime( $css_file ) : HELLO_ELEMENTOR_CHILD_VERSION;

$compare_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/light-compare.css';
$context['compare_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/light-compare.css';
$context['compare_css_ver'] = file_exists( $compare_css ) ? (string) filemtime( $compare_css ) : HELLO_ELEMENTOR_CHILD_VERSION;

$context['compare'] = Hello_Elementor_Child_Light_Compare_Template::get_page_context();

Hello_Elementor_Child_Light_Compare_Template::enqueue_assets();

echo '<!-- LK-LIGHT-COMPARE-PAGE-TEMPLATE-ACTIVE -->' . "\n";

\Timber\Timber::render( 'pages/light-compare.twig', $context );
