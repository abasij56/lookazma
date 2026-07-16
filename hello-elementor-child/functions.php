<?php
/**
 * Hello Elementor Child theme functions.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HELLO_ELEMENTOR_CHILD_VERSION', '1.0.0' );
define( 'HELLO_ELEMENTOR_CHILD_PATH', get_stylesheet_directory() . '/' );
define( 'HELLO_ELEMENTOR_CHILD_URI', get_stylesheet_directory_uri() . '/' );

require_once HELLO_ELEMENTOR_CHILD_PATH . 'includes/class-theme.php';

Hello_Elementor_Child_Theme::init();

/**
 * Enqueue child theme stylesheet.
 */
function hello_elementor_child_enqueue_styles() {
	wp_enqueue_style(
		'hello-elementor-child-style',
		get_stylesheet_directory_uri() . '/style.css',
		array( 'hello-elementor-theme-style' ),
		HELLO_ELEMENTOR_CHILD_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'hello_elementor_child_enqueue_styles', 20 );
