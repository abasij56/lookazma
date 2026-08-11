<?php
/**
 * Template Name: Light contact template
 * Template Post Type: page
 *
 * Native contact page without Elementor chrome.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$entry = HELLO_ELEMENTOR_CHILD_PATH . 'page-light-contact.php';
if ( file_exists( $entry ) ) {
	include $entry;
	return;
}

status_header( 500 );
wp_die( esc_html__( 'Light contact template is unavailable.', 'hello-elementor-child' ) );
