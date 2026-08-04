<?php
/**
 * WooCommerce single product – Timber entry.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Timber\Timber' ) || ! function_exists( 'wc_get_product' ) ) {
	wc_get_template( 'single-product.php' );
	return;
}

$context = \Timber\Timber::context();
$post_id = get_the_ID();
$product = wc_get_product( $post_id );

$context['post'] = \Timber\Timber::get_post( $post_id );

ob_start();
language_attributes();
$context['html_language_attributes'] = trim( ob_get_clean() );
$context['body_class']               = implode( ' ', get_body_class( 'lk-single-product' ) );

if ( $product ) {
	$gallery_ids = $product->get_gallery_image_ids();
	$images      = array();

	$main_id = $product->get_image_id();
	if ( $main_id ) {
		$images[] = array(
			'id'  => $main_id,
			'url' => wp_get_attachment_image_url( $main_id, 'large' ),
			'alt' => (string) get_post_meta( $main_id, '_wp_attachment_image_alt', true ),
		);
	}

	foreach ( $gallery_ids as $image_id ) {
		$images[] = array(
			'id'  => $image_id,
			'url' => wp_get_attachment_image_url( $image_id, 'large' ),
			'alt' => (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ),
		);
	}

	$attributes_list = array();
	foreach ( $product->get_attributes() as $attribute ) {
		$label = wc_attribute_label( $attribute->get_name() );

		if ( $attribute->is_taxonomy() ) {
			$terms = wc_get_product_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'names' ) );
			$value = is_wp_error( $terms ) ? '' : implode( ', ', $terms );
		} else {
			$value = implode( ', ', $attribute->get_options() );
		}

		if ( '' !== $value ) {
			$attributes_list[] = array(
				'label' => $label,
				'value' => $value,
			);
		}
	}

	$cas_no = function_exists( 'get_field' ) ? get_field( 'cas_no', $post_id ) : get_post_meta( $post_id, 'cas_no', true );
	$brand  = function_exists( 'get_field' ) ? get_field( 'آدرس_برند', $post_id ) : get_post_meta( $post_id, 'آدرس_برند', true );

	ob_start();
	woocommerce_template_single_add_to_cart();
	$add_to_cart_html = ob_get_clean();

	$context['product_data'] = array(
		'id'              => $product->get_id(),
		'name'            => $product->get_name(),
		'sku'             => $product->get_sku(),
		'price_html'      => $product->get_price_html(),
		'is_in_stock'     => $product->is_in_stock(),
		'is_purchasable'  => $product->is_purchasable(),
		'is_variable'     => $product->is_type( 'variable' ),
		'short_desc'      => $product->get_short_description(),
		'description'     => $product->get_description(),
		'cas_no'          => is_scalar( $cas_no ) ? (string) $cas_no : '',
		'brand'           => is_scalar( $brand ) ? (string) $brand : '',
		'images'          => $images,
		'attributes'      => $attributes_list,
		'permalink'       => get_permalink( $post_id ),
		'add_to_cart_html'=> $add_to_cart_html,
	);
}

wp_enqueue_style(
	'hello-elementor-child-single-product',
	HELLO_ELEMENTOR_CHILD_URI . 'assets/css/single-product.css',
	array(),
	HELLO_ELEMENTOR_CHILD_VERSION
);

if ( function_exists( 'wp_enqueue_script' ) ) {
	wp_enqueue_script( 'wc-add-to-cart' );
	wp_enqueue_script( 'woocommerce' );
	wp_enqueue_script( 'wc-single-product' );
	wp_enqueue_style( 'woocommerce-general' );
}

\Timber\Timber::render( 'woo/single-product.twig', $context );
