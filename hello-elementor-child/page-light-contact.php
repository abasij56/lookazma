<?php
/**
 * Native Contact page — Timber entry (Page → Light contact template).
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Timber\Timber' )
	|| ! class_exists( 'Hello_Elementor_Child_Light_Contact_Template' )
	|| ! class_exists( 'Hello_Elementor_Child_Light_Product_Template' )
) {
	status_header( 500 );
	wp_die( esc_html__( 'Timber یا Contact template در دسترس نیست.', 'hello-elementor-child' ) );
}

$context = array_merge(
	\Timber\Timber::context(),
	Hello_Elementor_Child_Light_Product_Template::get_chrome_context()
);

ob_start();
language_attributes();
$context['html_language_attributes'] = trim( ob_get_clean() );
$context['body_class']               = implode( ' ', get_body_class( 'lk-light-product lk-light-contact lz-chrome' ) );

$light_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-light-product.css';
$context['light_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-light-product.css';
$context['light_css_ver'] = file_exists( $light_css ) ? (string) filemtime( $light_css ) : HELLO_ELEMENTOR_CHILD_VERSION;

$contact_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/light-contact.css';
$context['contact_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/light-contact.css';
$context['contact_css_ver'] = file_exists( $contact_css ) ? (string) filemtime( $contact_css ) : HELLO_ELEMENTOR_CHILD_VERSION;

$context['contact'] = Hello_Elementor_Child_Light_Contact_Template::get_page_content();

echo '<!-- LK-LIGHT-CONTACT-TEMPLATE-ACTIVE -->' . "\n";

\Timber\Timber::render( 'pages/light-contact.twig', $context );
