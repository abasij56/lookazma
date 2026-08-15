<?php
/**
 * Native About page — Timber entry (Page → Light about template).
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Timber\Timber' )
	|| ! class_exists( 'Hello_Elementor_Child_Light_About_Template' )
	|| ! class_exists( 'Hello_Elementor_Child_Light_Product_Template' )
) {
	status_header( 500 );
	wp_die( esc_html__( 'Timber یا About template در دسترس نیست.', 'hello-elementor-child' ) );
}

$context = array_merge(
	\Timber\Timber::context(),
	Hello_Elementor_Child_Light_Product_Template::get_chrome_context()
);

ob_start();
language_attributes();
$context['html_language_attributes'] = trim( ob_get_clean() );
$context['body_class']               = implode( ' ', get_body_class( 'lk-light-product lk-light-about lz-chrome' ) );

$light_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-light-product.css';
$context['light_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-light-product.css';
$context['light_css_ver'] = file_exists( $light_css ) ? (string) filemtime( $light_css ) : HELLO_ELEMENTOR_CHILD_VERSION;

$about_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/light-about.css';
$context['about_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/light-about.css';
$context['about_css_ver'] = file_exists( $about_css ) ? (string) filemtime( $about_css ) : HELLO_ELEMENTOR_CHILD_VERSION;

$context['about'] = Hello_Elementor_Child_Light_About_Template::get_page_content();

echo '<!-- LK-LIGHT-ABOUT-TEMPLATE-ACTIVE -->' . "\n";

\Timber\Timber::render( 'pages/light-about.twig', $context );
