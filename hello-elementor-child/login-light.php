<?php
/**
 * Light login entry. Digits form is embedded; the page chrome is the theme.
 *
 * @package Hello_Elementor_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Timber\Timber' )
	|| ! class_exists( 'Hello_Elementor_Child_Light_Login_Template' )
	|| ! class_exists( 'Hello_Elementor_Child_Light_Product_Template' )
) {
	wp_die( esc_html__( 'ورود در دسترس نیست.', 'hello-elementor-child' ) );
}

$context = array_merge(
	\Timber\Timber::context(),
	Hello_Elementor_Child_Light_Product_Template::get_chrome_context()
);

ob_start();
language_attributes();
$context['html_language_attributes'] = trim( ob_get_clean() );
$context['body_class']               = implode( ' ', get_body_class( 'lk-light-product lk-light-login lz-chrome' ) );

$light_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-light-product.css';
$context['light_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-light-product.css';
$context['light_css_ver'] = file_exists( $light_css ) ? (string) filemtime( $light_css ) : HELLO_ELEMENTOR_CHILD_VERSION;

$css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/light-login.css';
$context['login_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/light-login.css';
$context['login_css_ver'] = file_exists( $css ) ? (string) filemtime( $css ) : HELLO_ELEMENTOR_CHILD_VERSION;

Hello_Elementor_Child_Light_Login_Template::enqueue_assets();
$context['login'] = Hello_Elementor_Child_Light_Login_Template::get_context();

echo '<!-- LK-LIGHT-LOGIN-TEMPLATE-ACTIVE -->' . "\n";

\Timber\Timber::render( 'pages/light-login.twig', $context );
