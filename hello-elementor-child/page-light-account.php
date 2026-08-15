<?php
/**
 * Native My Account page — Timber entry (Page → Light account template).
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Timber\Timber' )
	|| ! class_exists( 'Hello_Elementor_Child_Light_Account_Template' )
	|| ! class_exists( 'Hello_Elementor_Child_Light_Product_Template' )
) {
	status_header( 500 );
	wp_die( esc_html__( 'Timber یا Account template در دسترس نیست.', 'hello-elementor-child' ) );
}

$context = array_merge(
	\Timber\Timber::context(),
	Hello_Elementor_Child_Light_Product_Template::get_chrome_context()
);

ob_start();
language_attributes();
$context['html_language_attributes'] = trim( ob_get_clean() );
$context['body_class']               = implode( ' ', get_body_class( 'lk-light-product lk-light-account lz-chrome' ) );

$light_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-light-product.css';
$context['light_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-light-product.css';
$context['light_css_ver'] = file_exists( $light_css ) ? (string) filemtime( $light_css ) : HELLO_ELEMENTOR_CHILD_VERSION;

$account_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/light-account.css';
$context['account_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/light-account.css';
$context['account_css_ver'] = file_exists( $account_css ) ? (string) filemtime( $account_css ) : HELLO_ELEMENTOR_CHILD_VERSION;

$context['account'] = Hello_Elementor_Child_Light_Account_Template::get_page_content();

echo '<!-- LK-LIGHT-ACCOUNT-TEMPLATE-ACTIVE -->' . "\n";

\Timber\Timber::render( 'pages/light-account.twig', $context );
