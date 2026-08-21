<?php
/**
 * Native post category archive — Timber entry (e.g. /category/article/).
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Timber\Timber' )
	|| ! class_exists( 'Hello_Elementor_Child_Custom_Post_Category_Archive' )
	|| ! class_exists( 'Hello_Elementor_Child_Light_Product_Template' )
) {
	status_header( 500 );
	wp_die( esc_html__( 'Timber یا Category archive template در دسترس نیست.', 'hello-elementor-child' ) );
}

$context = array_merge(
	\Timber\Timber::context(),
	Hello_Elementor_Child_Light_Product_Template::get_chrome_context()
);

ob_start();
language_attributes();
$context['html_language_attributes'] = trim( ob_get_clean() );
$context['body_class']               = implode( ' ', get_body_class( 'lk-light-product lk-light-article lk-light-post-category lk-shop-cards-archive lz-chrome' ) );

$light_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-light-product.css';
$context['light_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-light-product.css';
$context['light_css_ver'] = file_exists( $light_css ) ? (string) filemtime( $light_css ) : HELLO_ELEMENTOR_CHILD_VERSION;

$card_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-product-shop-cards.css';
$context['card_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-product-shop-cards.css';
$context['card_css_ver'] = file_exists( $card_css ) ? (string) filemtime( $card_css ) : HELLO_ELEMENTOR_CHILD_VERSION;

$article_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/light-article.css';
$context['article_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/light-article.css';
$context['article_css_ver'] = file_exists( $article_css ) ? (string) filemtime( $article_css ) : HELLO_ELEMENTOR_CHILD_VERSION;

$context['article'] = Hello_Elementor_Child_Custom_Post_Category_Archive::get_page_content();

echo '<!-- LK-LIGHT-POST-CATEGORY-TEMPLATE-ACTIVE -->' . "\n";

\Timber\Timber::render( 'pages/light-article.twig', $context );
