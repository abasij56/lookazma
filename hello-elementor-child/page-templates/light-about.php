<?php
/**
 * Template Name: Light about template
 * Template Post Type: page
 *
 * Native about page without Elementor chrome.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$entry = HELLO_ELEMENTOR_CHILD_PATH . 'page-light-about.php';
if ( file_exists( $entry ) ) {
	include $entry;
	return;
}

status_header( 500 );
wp_die( esc_html__( 'Light about template is unavailable.', 'hello-elementor-child' ) );
