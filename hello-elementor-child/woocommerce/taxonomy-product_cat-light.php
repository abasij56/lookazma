<?php
/**
 * Light category template – Timber entry (all product categories).
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

$context = \Timber\Timber::context();
if ( class_exists( 'Hello_Elementor_Child_Light_Product_Template' ) ) {
	$context = array_merge( $context, Hello_Elementor_Child_Light_Product_Template::get_chrome_context() );
}

ob_start();
language_attributes();
$context['html_language_attributes'] = trim( ob_get_clean() );
$context['body_class']               = implode( ' ', get_body_class( 'lk-light-product lk-light-category lk-shop-cards-archive lz-chrome' ) );

$css_file = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-light-product.css';
$context['light_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-light-product.css';
$context['light_css_ver'] = file_exists( $css_file ) ? (string) filemtime( $css_file ) : HELLO_ELEMENTOR_CHILD_VERSION;

$card_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-product-shop-cards.css';
$context['card_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-product-shop-cards.css';
$context['card_css_ver'] = file_exists( $card_css ) ? (string) filemtime( $card_css ) : HELLO_ELEMENTOR_CHILD_VERSION;

$cat_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-light-category.css';
$context['cat_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-light-category.css';
$context['cat_css_ver'] = file_exists( $cat_css ) ? (string) filemtime( $cat_css ) : HELLO_ELEMENTOR_CHILD_VERSION;

$images   = array();
$thumb_id = (int) get_term_meta( $term_id, 'thumbnail_id', true );
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
$description  = Hello_Elementor_Child_Custom_Category_Archive::resolve_category_description_html( $term_id, $acf_id );
$parsed       = Hello_Elementor_Child_Custom_Category_Archive::parse_description_sections( (string) $description );

$spec_defs = array(
	array( 'key' => 'cas_no-cat', 'label' => 'CAS Number', 'icon' => 'cas' ),
	array( 'key' => 'شکل_ظاهری_دسته', 'label' => 'شکل ظاهری', 'icon' => 'appearance' ),
	array( 'key' => 'حلالیت در آب', 'label' => 'حلالیت در آب', 'icon' => 'water' ),
	array( 'key' => 'حلالیت_دسته', 'label' => 'حلالیت', 'icon' => 'solubility' ),
	array( 'key' => 'مترادف', 'label' => 'مترادف', 'icon' => 'synonym' ),
	array( 'key' => 'فرمول_شیمیایی_دسته', 'label' => 'فرمول شیمیایی', 'icon' => 'formula' ),
	array( 'key' => 'وزن_مولوکول', 'label' => 'وزن مولکولی', 'icon' => 'weight' ),
	array( 'key' => 'نقطه_ذوب', 'label' => 'نقطه ذوب', 'icon' => 'melt' ),
	array( 'key' => 'نقطه_جوش', 'label' => 'نقطه جوش', 'icon' => 'boil' ),
	array( 'key' => 'نقطه_اشتعال', 'label' => 'نقطه اشتعال', 'icon' => 'flash' ),
	array( 'key' => 'چگالی', 'label' => 'چگالی', 'icon' => 'density' ),
	array( 'key' => 'ویسکوزیته', 'label' => 'ویسکوزیته', 'icon' => 'viscosity' ),
	array( 'key' => 'فشار_بخار', 'label' => 'فشار بخار', 'icon' => 'vapor' ),
);

$specs      = array();
$wide_items = array();
foreach ( $spec_defs as $def ) {
	$value = $lk_field( $acf_id, $def['key'] );
	if ( '' === $value || 0 === strcasecmp( $value, 'N/A' ) ) {
		continue;
	}
	$item = array(
		'label' => $def['label'],
		'value' => $value,
		'icon'  => $def['icon'],
		'wide'  => in_array( $def['label'], array( 'مترادف', 'حلالیت' ), true ),
	);
	if ( $item['wide'] ) {
		$wide_items[] = $item;
		continue;
	}
	$specs[] = $item;
}
foreach ( $wide_items as $wide_item ) {
	$specs[] = $wide_item;
}

$msds_url  = '';
$msds_keys = array( 'msds', 'MSDS', 'msds-cat', 'msds_cat', 'msds_file', 'فایل_msds', 'برگه_ایمنی', 'sds', 'safety_data_sheet' );
foreach ( $msds_keys as $msds_key ) {
	$candidate = $lk_field( $acf_id, $msds_key );
	if ( '' !== $candidate && preg_match( '#^https?://#i', $candidate ) ) {
		$msds_url = $candidate;
		break;
	}
	if ( '' !== $candidate && is_numeric( $candidate ) ) {
		$att = wp_get_attachment_url( (int) $candidate );
		if ( $att ) {
			$msds_url = $att;
			break;
		}
	}
	$meta = get_term_meta( $term_id, $msds_key, true );
	if ( is_numeric( $meta ) ) {
		$att = wp_get_attachment_url( (int) $meta );
		if ( $att ) {
			$msds_url = $att;
			break;
		}
	}
	if ( is_string( $meta ) && preg_match( '#^https?://#i', $meta ) ) {
		$msds_url = $meta;
		break;
	}
}

$breadcrumb_html = function_exists( 'hello_elementor_child_get_breadcrumb_html' )
	? hello_elementor_child_get_breadcrumb_html()
	: '';

$paged    = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
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
$filters_html  = '';
if ( class_exists( 'Hello_Elementor_Child_Archive_Product_Filter' ) ) {
	$products_html = Hello_Elementor_Child_Archive_Product_Filter::render_products_grid_html( $query, 'shop' );
	$pagination    = Hello_Elementor_Child_Archive_Product_Filter::render_pagination_html( $query, $term_id, $paged, 'product_cat' );
	$filters_html  = Hello_Elementor_Child_Archive_Product_Filter::render_filters_html();
}

$context['category_data'] = array(
	'id'              => $term_id,
	'name'            => $term->name,
	'english_name'    => $english_name,
	'msds_url'        => $msds_url,
	'excerpt'         => (string) ( $parsed['excerpt'] ?? '' ),
	'images'          => $images,
	'specs'           => $specs,
	'h1_label'        => (string) ( $parsed['h1_label'] ?? '' ),
	'full_html'       => (string) ( $parsed['full_html'] ?? '' ),
	'sections'        => is_array( $parsed['sections'] ?? null ) ? $parsed['sections'] : array(),
	'has_specs'       => ! empty( $parsed['has_specs'] ) || array() !== $specs,
	'count'           => (int) $query->found_posts,
	'breadcrumb_html' => $breadcrumb_html,
	'filters_html'    => $filters_html,
	'products_html'   => $products_html,
	'pagination_html' => $pagination,
	'permalink'       => get_term_link( $term ),
	'columns'         => 4,
);

echo '<!-- LK-LIGHT-CATEGORY-TEMPLATE-ACTIVE term_id=' . (int) $term_id . ' -->' . "\n";

\Timber\Timber::render( 'woo/archive-light-category.twig', $context );
