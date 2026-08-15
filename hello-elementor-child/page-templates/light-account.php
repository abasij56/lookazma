<?php
/**
 * Template Name: Light account template
 * Template Post Type: page
 *
 * Native WooCommerce My Account page without Elementor chrome.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$entry = HELLO_ELEMENTOR_CHILD_PATH . 'page-light-account.php';
if ( file_exists( $entry ) ) {
	include $entry;
	return;
}

status_header( 500 );
wp_die( esc_html__( 'Light account template is unavailable.', 'hello-elementor-child' ) );
