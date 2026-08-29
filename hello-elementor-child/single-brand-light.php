<?php
/**
 * Light brand CPT single – Timber entry (tag hero + category-style specs tabs).
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Timber\Timber' )
	|| ! class_exists( 'Hello_Elementor_Child_Custom_Brand_Single' )
	|| ! class_exists( 'Hello_Elementor_Child_Brand_Cpt' )
) {
	status_header( 500 );
	wp_die( esc_html__( 'Timber یا تمپلیت برند در دسترس نیست.', 'hello-elementor-child' ) );
}

$brand = get_queried_object();
if ( ! $brand instanceof WP_Post || Hello_Elementor_Child_Brand_Cpt::POST_TYPE !== $brand->post_type ) {
	status_header( 404 );
	nocache_headers();
	include get_query_template( '404' );
	return;
}

$brand_id = (int) $brand->ID;
$tag      = Hello_Elementor_Child_Custom_Brand_Single::get_primary_product_tag( $brand );
$term_id  = $tag instanceof WP_Term ? (int) $tag->term_id : 0;

$context = \Timber\Timber::context();
if ( class_exists( 'Hello_Elementor_Child_Light_Product_Template' ) ) {
	$context = array_merge( $context, Hello_Elementor_Child_Light_Product_Template::get_chrome_context() );
}

ob_start();
language_attributes();
$context['html_language_attributes'] = trim( ob_get_clean() );
$context['body_class']               = implode( ' ', get_body_class( 'lk-light-product lk-light-tag lk-light-brand lk-shop-cards-archive lz-chrome' ) );

$css_file = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-light-product.css';
$context['light_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-light-product.css';
$context['light_css_ver'] = file_exists( $css_file ) ? (string) filemtime( $css_file ) : HELLO_ELEMENTOR_CHILD_VERSION;

$card_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-product-shop-cards.css';
$context['card_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-product-shop-cards.css';
$context['card_css_ver'] = file_exists( $card_css ) ? (string) filemtime( $card_css ) : HELLO_ELEMENTOR_CHILD_VERSION;

$tag_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-light-tag.css';
$context['tag_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-light-tag.css';
$context['tag_css_ver'] = file_exists( $tag_css ) ? (string) filemtime( $tag_css ) : HELLO_ELEMENTOR_CHILD_VERSION;

$cat_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-light-category.css';
$context['cat_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-light-category.css';
$context['cat_css_ver'] = file_exists( $cat_css ) ? (string) filemtime( $cat_css ) : HELLO_ELEMENTOR_CHILD_VERSION;

$image     = null;
$thumb_id  = (int) get_post_thumbnail_id( $brand_id );
$thumb_url = '';
if ( $thumb_id > 0 ) {
	$maybe     = wp_get_attachment_image_url( $thumb_id, 'large' );
	$thumb_url = is_string( $maybe ) ? $maybe : '';
}
if ( '' === $thumb_url && $term_id > 0 && class_exists( 'Hello_Elementor_Child_Custom_Tag_Archive' ) ) {
	$thumb_url = Hello_Elementor_Child_Custom_Tag_Archive::resolve_image_url( $term_id, 'large' );
	if ( '' !== $thumb_url && $thumb_id <= 0 ) {
		$thumb_id = Hello_Elementor_Child_Custom_Tag_Archive::resolve_thumbnail_id( $term_id );
	}
}
if ( '' !== $thumb_url ) {
	$alt = $brand->post_title;
	if ( $thumb_id > 0 ) {
		$meta_alt = (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true );
		if ( '' !== $meta_alt ) {
			$alt = $meta_alt;
		}
	}
	$image = array(
		'url' => $thumb_url,
		'alt' => $alt,
	);
}

$english_name = '';
if ( function_exists( 'get_field' ) ) {
	foreach ( array( 'en-tag', 'english_name', 'en_name', 'en-brand' ) as $en_key ) {
		$val = get_field( $en_key, $brand_id );
		if ( is_scalar( $val ) && '' !== trim( (string) $val ) ) {
			$english_name = trim( (string) $val );
			break;
		}
	}
}
if ( '' === $english_name && $term_id > 0 && function_exists( 'get_field' ) ) {
	$acf_id = 'product_tag_' . $term_id;
	foreach ( array( 'en-tag', 'english_name', 'en_name' ) as $en_key ) {
		$val = get_field( $en_key, $acf_id );
		if ( is_scalar( $val ) && '' !== trim( (string) $val ) ) {
			$english_name = trim( (string) $val );
			break;
		}
	}
}

// Brand specs = editor content from the brand post (same TOC/tab pipeline as category).
$raw_specs = trim( (string) $brand->post_content );
if ( '' === $raw_specs ) {
	$raw_specs = trim( (string) $brand->post_excerpt );
}
if ( '' === $raw_specs && $term_id > 0 ) {
	$term_desc = term_description( $term_id, 'product_tag' );
	$raw_specs = is_string( $term_desc ) ? trim( $term_desc ) : '';
}

$specs_html = '';
if ( '' !== $raw_specs ) {
	// Preserve headings for TOC; run content filters for blocks/shortcodes.
	$specs_html = apply_filters( 'the_content', $raw_specs );
}

$parsed = array(
	'full_html' => '',
	'excerpt'   => '',
	'sections'  => array(),
	'has_specs' => false,
);
if ( class_exists( 'Hello_Elementor_Child_Custom_Category_Archive' ) && '' !== trim( wp_strip_all_tags( $specs_html ) ) ) {
	$parsed = Hello_Elementor_Child_Custom_Category_Archive::parse_description_sections( $specs_html );
} elseif ( '' !== trim( wp_strip_all_tags( $specs_html ) ) ) {
	$parsed['has_specs'] = true;
	$parsed['full_html'] = wp_kses_post( $specs_html );
	$text                = trim( wp_strip_all_tags( $specs_html ) );
	$text                = preg_replace( '/\s+/u', ' ', $text );
	if ( is_string( $text ) && '' !== $text ) {
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > 180 ) {
			$parsed['excerpt'] = mb_substr( $text, 0, 180 ) . '…';
		} elseif ( strlen( $text ) > 180 ) {
			$parsed['excerpt'] = substr( $text, 0, 180 ) . '…';
		} else {
			$parsed['excerpt'] = $text;
		}
	}
}

$excerpt = trim( (string) $brand->post_excerpt );
if ( '' === $excerpt ) {
	$excerpt = (string) ( $parsed['excerpt'] ?? '' );
} else {
	$excerpt = trim( wp_strip_all_tags( $excerpt ) );
	$collapsed = preg_replace( '/\s+/u', ' ', $excerpt );
	$excerpt   = is_string( $collapsed ) ? $collapsed : $excerpt;
}

$breadcrumb_html = function_exists( 'hello_elementor_child_get_breadcrumb_html' )
	? hello_elementor_child_get_breadcrumb_html()
	: '';

$paged    = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
$per_page = Hello_Elementor_Child_Custom_Brand_Single::get_per_page();

$args = array(
	'post_type'           => 'product',
	'post_status'         => 'publish',
	'posts_per_page'      => $per_page,
	'paged'               => $paged,
	'ignore_sticky_posts' => true,
);

if ( $term_id > 0 ) {
	$args['tax_query'] = array(
		array(
			'taxonomy'         => 'product_tag',
			'field'            => 'term_id',
			'terms'            => array( $term_id ),
			'include_children' => false,
		),
	);
} else {
	$args['post__in'] = array( 0 );
}

if ( class_exists( 'Hello_Elementor_Child_Archive_Product_Filter' ) ) {
	$args = Hello_Elementor_Child_Archive_Product_Filter::apply_fragments_to_args( $args, null, true );
}

$query = new WP_Query( $args );

$products_html = '';
$pagination    = '';
$filters_html  = '';
if ( class_exists( 'Hello_Elementor_Child_Archive_Product_Filter' ) ) {
	$products_html = Hello_Elementor_Child_Archive_Product_Filter::render_products_grid_html( $query, 'shop' );
	$pagination    = Hello_Elementor_Child_Archive_Product_Filter::render_pagination_html(
		$query,
		$term_id,
		$paged,
		$term_id > 0 ? 'product_tag' : ''
	);
	$filters_html = Hello_Elementor_Child_Archive_Product_Filter::render_filters_html();
}

$context['brand_data'] = array(
	'id'              => $brand_id,
	'name'            => $brand->post_title,
	'english_name'    => $english_name,
	'excerpt'         => $excerpt,
	'image'           => $image,
	'full_html'       => (string) ( $parsed['full_html'] ?? '' ),
	'sections'        => is_array( $parsed['sections'] ?? null ) ? $parsed['sections'] : array(),
	'has_specs'       => ! empty( $parsed['has_specs'] ),
	'count'           => (int) $query->found_posts,
	'breadcrumb_html' => $breadcrumb_html,
	'filters_html'    => $filters_html,
	'products_html'   => $products_html,
	'pagination_html' => $pagination,
);

Hello_Elementor_Child_Custom_Brand_Single::enqueue_light_assets();
if ( class_exists( 'Hello_Elementor_Child_Archive_Product_Filter' ) ) {
	Hello_Elementor_Child_Archive_Product_Filter::enqueue_light_archive_assets();
}

echo '<!-- LK-LIGHT-BRAND-TEMPLATE-ACTIVE brand_id=' . (int) $brand_id . ' term_id=' . (int) $term_id . ' -->' . "\n";

\Timber\Timber::render( 'woo/archive-light-brand.twig', $context );
