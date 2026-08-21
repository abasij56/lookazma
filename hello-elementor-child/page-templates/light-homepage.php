<?php
/**
 * Template Name: Light homepage template
 * Template Post Type: page
 *
 * Native homepage with Gutenberg body blocks.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$entry = HELLO_ELEMENTOR_CHILD_PATH . 'page-light-homepage.php';
if ( file_exists( $entry ) ) {
	include $entry;
	return;
}

status_header( 500 );
wp_die( esc_html__( 'Light homepage template is unavailable.', 'hello-elementor-child' ) );
