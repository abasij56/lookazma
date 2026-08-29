<?php
/**
 * Template Name: Light brands template
 * Template Post Type: page
 *
 * Brands listing page (hero + brand cards) without Elementor chrome.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$entry = HELLO_ELEMENTOR_CHILD_PATH . 'page-light-brands.php';
if ( file_exists( $entry ) ) {
	include $entry;
	return;
}

status_header( 500 );
wp_die( esc_html__( 'Light brands template is unavailable.', 'hello-elementor-child' ) );
