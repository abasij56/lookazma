<?php
/**
 * Light single blog post – Timber entry.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Timber\Timber' )
	|| ! class_exists( 'Hello_Elementor_Child_Custom_Single_Post' )
	|| ! class_exists( 'Hello_Elementor_Child_Light_Product_Template' )
) {
	status_header( 500 );
	wp_die( esc_html__( 'Timber یا Single Post template در دسترس نیست.', 'hello-elementor-child' ) );
}

$post_id = Hello_Elementor_Child_Custom_Single_Post::get_current_post_id();
if ( $post_id <= 0 ) {
	status_header( 404 );
	nocache_headers();
	include get_query_template( '404' );
	return;
}

$context = array_merge(
	\Timber\Timber::context(),
	Hello_Elementor_Child_Light_Product_Template::get_chrome_context()
);

ob_start();
language_attributes();
$context['html_language_attributes'] = trim( ob_get_clean() );
$context['body_class']               = implode( ' ', get_body_class( 'lk-light-product lk-light-single-post lz-chrome' ) );

$light_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-light-product.css';
$context['light_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-light-product.css';
$context['light_css_ver'] = file_exists( $light_css ) ? (string) filemtime( $light_css ) : HELLO_ELEMENTOR_CHILD_VERSION;

$article_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/light-article.css';
$context['article_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/light-article.css';
$context['article_css_ver'] = file_exists( $article_css ) ? (string) filemtime( $article_css ) : HELLO_ELEMENTOR_CHILD_VERSION;

$post_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/light-single-post.css';
$context['single_post_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/light-single-post.css';
$context['single_post_css_ver'] = file_exists( $post_css ) ? (string) filemtime( $post_css ) : HELLO_ELEMENTOR_CHILD_VERSION;

$post_js = HELLO_ELEMENTOR_CHILD_PATH . 'assets/js/light-single-post.js';
$context['single_post_js_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/js/light-single-post.js';
$context['single_post_js_ver'] = file_exists( $post_js ) ? (string) filemtime( $post_js ) : HELLO_ELEMENTOR_CHILD_VERSION;

$context['single_post'] = Hello_Elementor_Child_Custom_Single_Post::get_post_context( $post_id );

Hello_Elementor_Child_Custom_Single_Post::enqueue_light_assets();

echo '<!-- LK-LIGHT-SINGLE-POST-TEMPLATE-ACTIVE post_id=' . (int) $post_id . ' -->' . "\n";

\Timber\Timber::render( 'pages/light-single-post.twig', $context );
