<?php
/**
 * Light 404 — Timber entry.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Timber\Timber' )
	|| ! class_exists( 'Hello_Elementor_Child_Light_404_Template' )
	|| ! class_exists( 'Hello_Elementor_Child_Light_Product_Template' )
) {
	status_header( 500 );
	wp_die( esc_html__( 'Timber یا 404 template در دسترس نیست.', 'hello-elementor-child' ) );
}

status_header( 404 );
nocache_headers();

$context = array_merge(
	\Timber\Timber::context(),
	Hello_Elementor_Child_Light_Product_Template::get_chrome_context()
);

ob_start();
language_attributes();
$context['html_language_attributes'] = trim( ob_get_clean() );
$context['body_class']               = implode( ' ', get_body_class( 'lk-light-product lk-light-404 error404 lz-chrome' ) );

$light_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-light-product.css';
$context['light_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-light-product.css';
$context['light_css_ver'] = file_exists( $light_css ) ? (string) filemtime( $light_css ) : HELLO_ELEMENTOR_CHILD_VERSION;

$css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/light-404.css';
$context['error404_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/light-404.css';
$context['error404_css_ver'] = file_exists( $css ) ? (string) filemtime( $css ) : HELLO_ELEMENTOR_CHILD_VERSION;

$context['error404'] = Hello_Elementor_Child_Light_404_Template::get_page_content();

Hello_Elementor_Child_Light_404_Template::enqueue_assets();

echo '<!-- LK-LIGHT-404-TEMPLATE-ACTIVE -->' . "\n";

\Timber\Timber::render( 'pages/light-404.twig', $context );
