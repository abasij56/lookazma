<?php
/**
 * Template Name: Light cart template
 * Template Post Type: page
 *
 * Native WooCommerce Cart page without Elementor chrome.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$entry = HELLO_ELEMENTOR_CHILD_PATH . 'page-light-cart.php';
if ( file_exists( $entry ) ) {
	include $entry;
	return;
}

status_header( 500 );
wp_die( esc_html__( 'Light cart template is unavailable.', 'hello-elementor-child' ) );
