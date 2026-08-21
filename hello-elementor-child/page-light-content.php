<?php
/**
 * Native content page — Timber entry (privacy / shipping).
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Timber\Timber' )
	|| ! class_exists( 'Hello_Elementor_Child_Light_Content_Template' )
	|| ! class_exists( 'Hello_Elementor_Child_Light_Product_Template' )
) {
	status_header( 500 );
	wp_die( esc_html__( 'Timber یا Content template در دسترس نیست.', 'hello-elementor-child' ) );
}

$context = array_merge(
	\Timber\Timber::context(),
	Hello_Elementor_Child_Light_Product_Template::get_chrome_context()
);

ob_start();
language_attributes();
$context['html_language_attributes'] = trim( ob_get_clean() );
$context['body_class']               = implode( ' ', get_body_class( 'lk-light-product lk-light-content lz-chrome' ) );

$light_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-light-product.css';
$context['light_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-light-product.css';
$context['light_css_ver'] = file_exists( $light_css ) ? (string) filemtime( $light_css ) : HELLO_ELEMENTOR_CHILD_VERSION;

$content_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/light-content.css';
$context['content_page_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/light-content.css';
$context['content_page_css_ver'] = file_exists( $content_css ) ? (string) filemtime( $content_css ) : HELLO_ELEMENTOR_CHILD_VERSION;

$slug = Hello_Elementor_Child_Light_Content_Template::get_page_slug();
if ( 'sending-goods' === $slug ) {
	$card_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-product-shop-cards.css';
	$context['card_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-product-shop-cards.css';
	$context['card_css_ver'] = file_exists( $card_css ) ? (string) filemtime( $card_css ) : HELLO_ELEMENTOR_CHILD_VERSION;
}

$context['content_page'] = Hello_Elementor_Child_Light_Content_Template::get_page_content();

echo '<!-- LK-LIGHT-CONTENT-TEMPLATE-ACTIVE -->' . "\n";

\Timber\Timber::render( 'pages/light-content.twig', $context );
