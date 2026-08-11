<?php
/**
 * Search results — same native listing as Light archive template.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$path = get_stylesheet_directory() . '/woocommerce/archive-light-product.php';
if ( file_exists( $path ) ) {
	include $path;
	return;
}

status_header( 500 );
wp_die( esc_html__( 'Light archive template در دسترس نیست.', 'hello-elementor-child' ) );
