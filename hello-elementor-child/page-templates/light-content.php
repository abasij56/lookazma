<?php
/**
 * Template Name: Light content template
 * Template Post Type: page
 *
 * Static content pages (privacy, shipping, etc.) without Elementor chrome.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$entry = HELLO_ELEMENTOR_CHILD_PATH . 'page-light-content.php';
if ( file_exists( $entry ) ) {
	include $entry;
	return;
}

status_header( 500 );
wp_die( esc_html__( 'Light content template is unavailable.', 'hello-elementor-child' ) );
