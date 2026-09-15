<?php
/**
 * Enhanced brand CPT single – hero, stats, lk_brand products, content sections.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Timber\Timber' )
	|| ! class_exists( 'Hello_Elementor_Child_Custom_Brand_Single' )
	|| ! class_exists( 'Hello_Elementor_Child_Brand_Cpt' )
	|| ! class_exists( 'Hello_Elementor_Child_Brand_Enhanced_Single' )
) {
	status_header( 500 );
	wp_die( esc_html__( 'Timber یا تمپلیت برند در دسترس نیست.', 'hello-elementor-child' ) );
}

$brand = get_queried_object();
if ( ! $brand instanceof WP_Post || Hello_Elementor_Child_Brand_Cpt::POST_TYPE !== $brand->post_type ) {
	status_header( 404 );
	nocache_headers();
	include get_query_template( '404' );
	return;
}

$context = \Timber\Timber::context();
if ( class_exists( 'Hello_Elementor_Child_Light_Product_Template' ) ) {
	$context = array_merge( $context, Hello_Elementor_Child_Light_Product_Template::get_chrome_context() );
}

ob_start();
language_attributes();
$context['html_language_attributes'] = trim( ob_get_clean() );
$context['body_class']               = implode(
	' ',
	get_body_class( 'lk-light-product lk-light-brand lk-brand-enhanced lk-shop-cards-archive lz-chrome' )
);

$css_file = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-light-product.css';
$context['light_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-light-product.css';
$context['light_css_ver'] = file_exists( $css_file ) ? (string) filemtime( $css_file ) : HELLO_ELEMENTOR_CHILD_VERSION;

$card_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-product-shop-cards.css';
$context['card_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-product-shop-cards.css';
$context['card_css_ver'] = file_exists( $card_css ) ? (string) filemtime( $card_css ) : HELLO_ELEMENTOR_CHILD_VERSION;

$enhanced_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/single-brand-enhanced.css';
$context['brand_enhanced_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/single-brand-enhanced.css';
$context['brand_enhanced_css_ver'] = file_exists( $enhanced_css ) ? (string) filemtime( $enhanced_css ) : HELLO_ELEMENTOR_CHILD_VERSION;

$context['brand_data'] = Hello_Elementor_Child_Brand_Enhanced_Single::build_template_data( $brand );

Hello_Elementor_Child_Custom_Brand_Single::enqueue_light_assets();
Hello_Elementor_Child_Brand_Enhanced_Single::enqueue_assets();
if ( class_exists( 'Hello_Elementor_Child_Archive_Product_Filter' ) ) {
	Hello_Elementor_Child_Archive_Product_Filter::enqueue_light_archive_assets();
}

echo '<!-- LK-BRAND-ENHANCED-TEMPLATE brand_id=' . (int) $brand->ID . ' -->' . "\n";

\Timber\Timber::render( 'woo/single-brand-enhanced.twig', $context );
