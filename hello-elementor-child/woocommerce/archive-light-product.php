<?php
/**
 * Light archive template – product listing for pages that select it,
 * and for site search results.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Timber\Timber' ) || ! class_exists( 'Hello_Elementor_Child_Light_Product_Template' ) ) {
	status_header( 500 );
	wp_die( esc_html__( 'Timber یا Light archive template در دسترس نیست.', 'hello-elementor-child' ) );
}

$context = array_merge(
	\Timber\Timber::context(),
	Hello_Elementor_Child_Light_Product_Template::get_chrome_context()
);

ob_start();
language_attributes();
$context['html_language_attributes'] = trim( ob_get_clean() );
$context['body_class']               = implode( ' ', get_body_class( 'lk-light-product lk-shop-cards-archive lz-chrome' ) );

$css_file = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-light-product.css';
$context['light_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-light-product.css';
$context['light_css_ver'] = file_exists( $css_file ) ? (string) filemtime( $css_file ) : HELLO_ELEMENTOR_CHILD_VERSION;

$card_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-product-shop-cards.css';
$context['card_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-product-shop-cards.css';
$context['card_css_ver'] = file_exists( $card_css ) ? (string) filemtime( $card_css ) : HELLO_ELEMENTOR_CHILD_VERSION;

$lk_field = static function ( string $acf_id, string $key ): string {
	$value = function_exists( 'get_field' ) ? get_field( $key, $acf_id ) : '';
	if ( is_array( $value ) ) {
		if ( isset( $value['url'] ) ) {
			return (string) $value['url'];
		}
		return '';
	}
	return is_scalar( $value ) ? trim( (string) $value ) : '';
};

$kind      = 'shop';
$title     = __( 'فروشگاه', 'hello-elementor-child' );
$english   = '';
$description = '';
$term_id   = 0;
$taxonomy  = '';
$search    = '';

if ( class_exists( 'Hello_Elementor_Child_Archive_Product_Filter' )
	&& Hello_Elementor_Child_Archive_Product_Filter::is_product_search_context()
) {
	$kind   = 'search';
	$search = trim( (string) get_search_query( false ) );
	$title  = '' !== $search
		? sprintf(
			/* translators: %s search query */
			__( 'نتایج جستجو برای «%s»', 'hello-elementor-child' ),
			$search
		)
		: __( 'جستجوی محصولات', 'hello-elementor-child' );
} elseif ( function_exists( 'is_product_category' ) && is_product_category() ) {
	$term = get_queried_object();
	if ( $term instanceof WP_Term ) {
		$kind        = 'category';
		$term_id     = (int) $term->term_id;
		$taxonomy    = 'product_cat';
		$title       = $term->name;
		$acf_id      = 'product_cat_' . $term_id;
		$english     = $lk_field( $acf_id, 'en-cat' );
		$description = term_description( $term_id, 'product_cat' );
	}
} elseif ( function_exists( 'is_product_tag' ) && is_product_tag() ) {
	$term = get_queried_object();
	if ( $term instanceof WP_Term ) {
		$kind     = 'tag';
		$term_id  = (int) $term->term_id;
		$taxonomy = 'product_tag';
		$title    = $term->name;
	}
} else {
	$page_id = 0;
	if ( function_exists( 'is_shop' ) && is_shop() && function_exists( 'wc_get_page_id' ) ) {
		$page_id = (int) wc_get_page_id( 'shop' );
		$kind    = 'shop';
	} elseif ( is_page() ) {
		$page_id = (int) get_queried_object_id();
		$kind    = 'page';
	}
	if ( $page_id > 0 ) {
		$page_title = get_the_title( $page_id );
		if ( is_string( $page_title ) && '' !== trim( $page_title ) ) {
			$title = $page_title;
		}
	}
}

$breadcrumb_html = function_exists( 'hello_elementor_child_get_breadcrumb_html' )
	? hello_elementor_child_get_breadcrumb_html()
	: '';

$paged    = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
$per_page = Hello_Elementor_Child_Light_Product_Template::get_per_page();

$args = array(
	'post_type'           => 'product',
	'post_status'         => 'publish',
	'posts_per_page'      => $per_page,
	'paged'               => $paged,
	'ignore_sticky_posts' => true,
);

if ( '' !== $search ) {
	$args['s'] = $search;
}

if ( $term_id > 0 && '' !== $taxonomy ) {
	$args['tax_query'] = array(
		array(
			'taxonomy'         => $taxonomy,
			'field'            => 'term_id',
			'terms'            => array( $term_id ),
			'include_children' => ( 'product_cat' === $taxonomy ),
		),
	);
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
	$pagination    = Hello_Elementor_Child_Archive_Product_Filter::render_pagination_html( $query, $term_id, $paged, $taxonomy, $search );
	$filters_html  = Hello_Elementor_Child_Archive_Product_Filter::render_filters_html();
}

$context['archive'] = array(
	'kind'            => $kind,
	'title'           => $title,
	'english_name'    => $english,
	'description'     => $description,
	'count'           => (int) $query->found_posts,
	'breadcrumb_html' => $breadcrumb_html,
	'filters_html'    => $filters_html,
	'products_html'   => $products_html,
	'pagination_html' => $pagination,
	'columns'         => Hello_Elementor_Child_Light_Product_Template::get_columns(),
);

if ( class_exists( 'Hello_Elementor_Child_Archive_Product_Filter' ) ) {
	Hello_Elementor_Child_Archive_Product_Filter::enqueue_light_archive_assets();
}

echo '<!-- LK-LIGHT-PRODUCT-TEMPLATE-ACTIVE kind=' . esc_html( $kind ) . ' -->' . "\n";

\Timber\Timber::render( 'woo/archive-light-product.twig', $context );
