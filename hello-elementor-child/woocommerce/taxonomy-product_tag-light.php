<?php
/**
 * Light product tag (brand) archive – Timber entry.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Timber\Timber' ) || ! function_exists( 'is_product_tag' ) ) {
	status_header( 500 );
	wp_die( esc_html__( 'Timber یا WooCommerce در دسترس نیست.', 'hello-elementor-child' ) );
}

$term = get_queried_object();
if ( ! $term instanceof WP_Term || 'product_tag' !== $term->taxonomy ) {
	status_header( 404 );
	nocache_headers();
	include get_query_template( '404' );
	return;
}

$term_id = (int) $term->term_id;
$acf_id  = 'product_tag_' . $term_id;

/**
 * Scalar ACF/meta helper for term fields.
 *
 * @param string $acf_id ACF id (product_tag_{id}).
 * @param string $key    Field key.
 */
$lk_field = static function ( string $acf_id, string $key ): string {
	$value = function_exists( 'get_field' ) ? get_field( $key, $acf_id ) : '';
	if ( is_array( $value ) ) {
		if ( isset( $value['url'] ) ) {
			return (string) $value['url'];
		}
		if ( isset( $value['ID'] ) ) {
			$url = wp_get_attachment_url( (int) $value['ID'] );
			return $url ? (string) $url : '';
		}
		return '';
	}
	return is_scalar( $value ) ? trim( (string) $value ) : '';
};

$context = \Timber\Timber::context();
if ( class_exists( 'Hello_Elementor_Child_Light_Product_Template' ) ) {
	$context = array_merge( $context, Hello_Elementor_Child_Light_Product_Template::get_chrome_context() );
}

ob_start();
language_attributes();
$context['html_language_attributes'] = trim( ob_get_clean() );
$context['body_class']               = implode( ' ', get_body_class( 'lk-light-product lk-light-tag lk-shop-cards-archive lz-chrome' ) );

$css_file = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-light-product.css';
$context['light_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-light-product.css';
$context['light_css_ver'] = file_exists( $css_file ) ? (string) filemtime( $css_file ) : HELLO_ELEMENTOR_CHILD_VERSION;

$card_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-product-shop-cards.css';
$context['card_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-product-shop-cards.css';
$context['card_css_ver'] = file_exists( $card_css ) ? (string) filemtime( $card_css ) : HELLO_ELEMENTOR_CHILD_VERSION;

$tag_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-light-tag.css';
$context['tag_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-light-tag.css';
$context['tag_css_ver'] = file_exists( $tag_css ) ? (string) filemtime( $tag_css ) : HELLO_ELEMENTOR_CHILD_VERSION;

$image    = null;
$thumb_id = class_exists( 'Hello_Elementor_Child_Custom_Tag_Archive' )
	? Hello_Elementor_Child_Custom_Tag_Archive::resolve_thumbnail_id( $term_id )
	: (int) get_term_meta( $term_id, 'thumbnail_id', true );
$thumb_url = '';
if ( class_exists( 'Hello_Elementor_Child_Custom_Tag_Archive' ) ) {
	$thumb_url = Hello_Elementor_Child_Custom_Tag_Archive::resolve_image_url( $term_id, 'large' );
} elseif ( $thumb_id > 0 ) {
	$maybe = wp_get_attachment_image_url( $thumb_id, 'large' );
	$thumb_url = is_string( $maybe ) ? $maybe : '';
}
if ( '' !== $thumb_url ) {
	$image = array(
		'url' => $thumb_url,
		'alt' => $thumb_id > 0
			? (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true )
			: $term->name,
	);
	if ( '' === $image['alt'] ) {
		$image['alt'] = $term->name;
	}
}

$english_name = $lk_field( $acf_id, 'en-tag' );
if ( '' === $english_name ) {
	$english_name = $lk_field( $acf_id, 'english_name' );
}
if ( '' === $english_name ) {
	$english_name = $lk_field( $acf_id, 'en_name' );
}

$description = term_description( $term_id, 'product_tag' );
if ( ! is_string( $description ) ) {
	$description = '';
}
$description = trim( $description );

$breadcrumb_html = function_exists( 'hello_elementor_child_get_breadcrumb_html' )
	? hello_elementor_child_get_breadcrumb_html()
	: '';

$paged    = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
$per_page = class_exists( 'Hello_Elementor_Child_Custom_Tag_Archive' )
	? Hello_Elementor_Child_Custom_Tag_Archive::get_per_page()
	: 12;

$args = array(
	'post_type'           => 'product',
	'post_status'         => 'publish',
	'posts_per_page'      => $per_page,
	'paged'               => $paged,
	'ignore_sticky_posts' => true,
	'tax_query'           => array(
		array(
			'taxonomy'         => 'product_tag',
			'field'            => 'term_id',
			'terms'            => array( $term_id ),
			'include_children' => false,
		),
	),
);

if ( class_exists( 'Hello_Elementor_Child_Archive_Product_Filter' ) ) {
	$args = Hello_Elementor_Child_Archive_Product_Filter::apply_fragments_to_args( $args, null, true );
}

$query = new WP_Query( $args );

$products_html = '';
$pagination    = '';
$filters_html  = '';
if ( class_exists( 'Hello_Elementor_Child_Archive_Product_Filter' ) ) {
	$products_html = Hello_Elementor_Child_Archive_Product_Filter::render_products_grid_html( $query, 'shop' );
	$pagination    = Hello_Elementor_Child_Archive_Product_Filter::render_pagination_html( $query, $term_id, $paged, 'product_tag' );
	$filters_html  = Hello_Elementor_Child_Archive_Product_Filter::render_filters_html();
}

$context['tag_data'] = array(
	'id'              => $term_id,
	'name'            => $term->name,
	'english_name'    => $english_name,
	'description'     => $description,
	'image'           => $image,
	'count'           => (int) $query->found_posts,
	'breadcrumb_html' => $breadcrumb_html,
	'filters_html'    => $filters_html,
	'products_html'   => $products_html,
	'pagination_html' => $pagination,
);

if ( class_exists( 'Hello_Elementor_Child_Custom_Tag_Archive' ) ) {
	Hello_Elementor_Child_Custom_Tag_Archive::enqueue_light_assets();
}
if ( class_exists( 'Hello_Elementor_Child_Archive_Product_Filter' ) ) {
	Hello_Elementor_Child_Archive_Product_Filter::enqueue_light_archive_assets();
}

echo '<!-- LK-LIGHT-TAG-TEMPLATE-ACTIVE term_id=' . (int) $term_id . ' -->' . "\n";

\Timber\Timber::render( 'woo/archive-light-tag.twig', $context );
