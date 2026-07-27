<?php
/**
 * Light product category archive – Timber entry (opt-in via category checkbox).
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Timber\Timber' ) || ! function_exists( 'is_product_category' ) ) {
	status_header( 500 );
	wp_die( esc_html__( 'Timber یا WooCommerce در دسترس نیست.', 'hello-elementor-child' ) );
}

$term = get_queried_object();
if ( ! $term instanceof WP_Term || 'product_cat' !== $term->taxonomy ) {
	status_header( 404 );
	nocache_headers();
	include get_query_template( '404' );
	return;
}

$term_id = (int) $term->term_id;
$acf_id  = 'product_cat_' . $term_id;

/**
 * Scalar ACF/meta helper for term fields.
 *
 * @param string $acf_id ACF post ID style (product_cat_{id}).
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

$context         = \Timber\Timber::context();
$context['term'] = \Timber\Timber::get_term( $term_id );

ob_start();
language_attributes();
$context['html_language_attributes'] = trim( ob_get_clean() );
$context['body_class']               = implode( ' ', get_body_class( 'lk-archive-product-light' ) );

$css_file = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-product-light.css';
$context['light_css_inline'] = file_exists( $css_file ) ? (string) file_get_contents( $css_file ) : '';
$context['light_css_url']    = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-product-light.css';
$context['light_css_ver']    = file_exists( $css_file ) ? (string) filemtime( $css_file ) : HELLO_ELEMENTOR_CHILD_VERSION;

// Category image(s).
$images      = array();
$thumb_id    = (int) get_term_meta( $term_id, 'thumbnail_id', true );
if ( $thumb_id > 0 ) {
	$url = wp_get_attachment_image_url( $thumb_id, 'large' );
	if ( $url ) {
		$images[] = array(
			'url' => $url,
			'alt' => (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ),
		);
	}
}

$english_name = $lk_field( $acf_id, 'en-cat' );
$description  = term_description( $term_id, 'product_cat' );
if ( '' === trim( wp_strip_all_tags( (string) $description ) ) ) {
	$description = $lk_field( $acf_id, 'توضیحات' );
}

$spec_defs = array(
	array( 'key' => 'cas_no-cat', 'label' => 'CAS Number' ),
	array( 'key' => 'شکل_ظاهری_دسته', 'label' => 'شکل ظاهری' ),
	array( 'key' => 'مترادف', 'label' => 'مترادف' ),
	array( 'key' => 'فرمول_شیمیایی_دسته', 'label' => 'فرمول شیمیایی' ),
	array( 'key' => 'وزن_مولوکول', 'label' => 'وزن مولکولی' ),
	array( 'key' => 'نقطه_ذوب', 'label' => 'نقطه ذوب' ),
	array( 'key' => 'نقطه_جوش', 'label' => 'نقطه جوش' ),
	array( 'key' => 'نقطه_اشتعال', 'label' => 'نقطه اشتعال' ),
	array( 'key' => 'چگالی', 'label' => 'چگالی' ),
	array( 'key' => 'ویسکوزیته', 'label' => 'ویسکوزیته' ),
	array( 'key' => 'فشار_بخار', 'label' => 'فشار بخار' ),
	array( 'key' => 'حلالیت در آب', 'label' => 'حلالیت در آب' ),
	array( 'key' => 'حلالیت_دسته', 'label' => 'حلالیت' ),
);

$specs = array();
foreach ( $spec_defs as $def ) {
	$value = $lk_field( $acf_id, $def['key'] );
	if ( '' === $value ) {
		continue;
	}
	$specs[] = array(
		'label' => $def['label'],
		'value' => $value,
	);
}

ob_start();
woocommerce_breadcrumb();
$breadcrumb_html = ob_get_clean();

$paged   = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
$per_page = Hello_Elementor_Child_Custom_Category_Archive::get_per_page();

$args = array(
	'post_type'           => 'product',
	'post_status'         => 'publish',
	'posts_per_page'      => $per_page,
	'paged'               => $paged,
	'ignore_sticky_posts' => true,
	'tax_query'           => array(
		array(
			'taxonomy'         => 'product_cat',
			'field'            => 'term_id',
			'terms'            => array( $term_id ),
			'include_children' => true,
		),
	),
);

if ( class_exists( 'Hello_Elementor_Child_Archive_Product_Filter' ) ) {
	$args = Hello_Elementor_Child_Archive_Product_Filter::apply_fragments_to_args( $args, null, true );
}

$query = new WP_Query( $args );

$products_html = '';
$pagination    = '';
if ( class_exists( 'Hello_Elementor_Child_Archive_Product_Filter' ) ) {
	$products_html = Hello_Elementor_Child_Archive_Product_Filter::render_products_grid_html( $query );
	$pagination    = Hello_Elementor_Child_Archive_Product_Filter::render_pagination_html( $query, $term_id );
} else {
	ob_start();
	if ( $query->have_posts() ) {
		while ( $query->have_posts() ) {
			$query->the_post();
			$product = wc_get_product( get_the_ID() );
			if ( ! $product ) {
				continue;
			}
			echo '<article class="lk-loop-item"><h3>' . esc_html( $product->get_name() ) . '</h3></article>';
		}
		wp_reset_postdata();
	}
	$products_html = (string) ob_get_clean();
}

$filters_html = '';
if ( class_exists( 'Hello_Elementor_Child_Archive_Product_Filter' ) ) {
	$filters_html = Hello_Elementor_Child_Archive_Product_Filter::render_filters_html();
}

$context['category_data'] = array(
	'id'              => $term_id,
	'name'            => $term->name,
	'english_name'    => $english_name,
	'description'     => $description,
	'images'          => $images,
	'specs'           => $specs,
	'count'           => (int) $query->found_posts,
	'breadcrumb_html' => $breadcrumb_html,
	'filters_html'    => $filters_html,
	'products_html'   => $products_html,
	'pagination_html' => $pagination,
	'permalink'       => get_term_link( $term ),
);

echo '<!-- LK-LIGHT-CATEGORY-TEMPLATE-ACTIVE term_id=' . (int) $term_id . ' -->' . "\n";

\Timber\Timber::render( 'woo/archive-product-light.twig', $context );
