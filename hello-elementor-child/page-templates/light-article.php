<?php
/**
 * Template Name: Light article template
 * Template Post Type: page
 *
 * Native magazine / article listing without Elementor chrome.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$base  = defined( 'HELLO_ELEMENTOR_CHILD_PATH' ) ? HELLO_ELEMENTOR_CHILD_PATH : trailingslashit( dirname( __DIR__ ) );
$entry = $base . 'page-light-article.php';
if ( file_exists( $entry ) ) {
	include $entry;
	return;
}

status_header( 500 );
wp_die( esc_html__( 'Light article template is unavailable.', 'hello-elementor-child' ) );
