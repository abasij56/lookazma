<?php
/**
 * Template Name: Light compare template
 * Template Post Type: page
 *
 * Product comparison page without Elementor chrome.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$entry = HELLO_ELEMENTOR_CHILD_PATH . 'page-light-compare.php';
if ( file_exists( $entry ) ) {
	include $entry;
	return;
}

status_header( 500 );
wp_die( esc_html__( 'Light compare template is unavailable.', 'hello-elementor-child' ) );
