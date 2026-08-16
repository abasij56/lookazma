<?php
/**
 * Native Cart page — Timber entry (Page → Light cart template).
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Timber\Timber' )
	|| ! class_exists( 'Hello_Elementor_Child_Light_Cart_Template' )
	|| ! class_exists( 'Hello_Elementor_Child_Light_Product_Template' )
) {
	status_header( 500 );
	wp_die( esc_html__( 'Timber یا Cart template در دسترس نیست.', 'hello-elementor-child' ) );
}

$context = array_merge(
	\Timber\Timber::context(),
	Hello_Elementor_Child_Light_Product_Template::get_chrome_context()
);

ob_start();
language_attributes();
$context['html_language_attributes'] = trim( ob_get_clean() );
$context['body_class']               = implode( ' ', get_body_class( 'lk-light-product lk-light-cart lz-chrome' ) );

$light_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-light-product.css';
$context['light_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-light-product.css';
$context['light_css_ver'] = file_exists( $light_css ) ? (string) filemtime( $light_css ) : HELLO_ELEMENTOR_CHILD_VERSION;

$cart_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/light-cart.css';
$context['cart_page_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/light-cart.css';
$context['cart_page_css_ver'] = file_exists( $cart_css ) ? (string) filemtime( $cart_css ) : HELLO_ELEMENTOR_CHILD_VERSION;

$context['cart_page'] = Hello_Elementor_Child_Light_Cart_Template::get_page_content();

echo '<!-- LK-LIGHT-CART-TEMPLATE-ACTIVE -->' . "\n";

\Timber\Timber::render( 'pages/light-cart.twig', $context );
