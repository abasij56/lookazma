<?php
/**
 * Native Checkout page — Timber entry (Page → Light checkout template).
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Timber\Timber' )
	|| ! class_exists( 'Hello_Elementor_Child_Light_Checkout_Template' )
	|| ! class_exists( 'Hello_Elementor_Child_Light_Product_Template' )
) {
	status_header( 500 );
	wp_die( esc_html__( 'Timber یا Checkout template در دسترس نیست.', 'hello-elementor-child' ) );
}

$context = array_merge(
	\Timber\Timber::context(),
	Hello_Elementor_Child_Light_Product_Template::get_chrome_context()
);

ob_start();
language_attributes();
$context['html_language_attributes'] = trim( ob_get_clean() );
$context['body_class']               = implode(
	' ',
	Hello_Elementor_Child_Light_Checkout_Template::sanitize_body_classes(
		get_body_class(
			'lk-light-product lk-light-checkout lz-chrome'
			. ( Hello_Elementor_Child_Light_Checkout_Template::is_thankyou() ? ' lk-light-thankyou woocommerce-order-received' : '' )
		)
	)
);

$light_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-light-product.css';
$context['light_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-light-product.css';
$context['light_css_ver'] = file_exists( $light_css ) ? (string) filemtime( $light_css ) : HELLO_ELEMENTOR_CHILD_VERSION;

$checkout_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/light-checkout.css';
$context['checkout_page_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/light-checkout.css';
$context['checkout_page_css_ver'] = file_exists( $checkout_css ) ? (string) filemtime( $checkout_css ) : HELLO_ELEMENTOR_CHILD_VERSION;

$context['checkout_page'] = Hello_Elementor_Child_Light_Checkout_Template::get_page_content();

echo '<!-- LK-LIGHT-CHECKOUT-TEMPLATE-ACTIVE'
	. ( Hello_Elementor_Child_Light_Checkout_Template::is_thankyou() ? ' thankyou' : '' )
	. ' -->' . "\n";

\Timber\Timber::render( 'pages/light-checkout.twig', $context );
