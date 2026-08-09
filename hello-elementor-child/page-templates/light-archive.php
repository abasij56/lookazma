<?php
/**
 * Template Name: Light archive template
 * Template Post Type: page
 *
 * Native product listing (shop-style) without Elementor chrome.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$archive = HELLO_ELEMENTOR_CHILD_PATH . 'woocommerce/archive-light-product.php';
if ( file_exists( $archive ) ) {
	include $archive;
	return;
}

status_header( 500 );
wp_die( esc_html__( 'Light archive template is unavailable.', 'hello-elementor-child' ) );
