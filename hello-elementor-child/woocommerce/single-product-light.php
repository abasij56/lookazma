<?php
/**
 * Light single product – Timber entry (global default; opt out via product checkbox).
 *
 * Layout based on ai/static-hml/full-with-chrome.html
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Timber\Timber' ) || ! function_exists( 'wc_get_product' ) ) {
	status_header( 500 );
	wp_die( esc_html__( 'Timber یا WooCommerce در دسترس نیست.', 'hello-elementor-child' ) );
}

$post_id = (int) get_the_ID();
$product = wc_get_product( $post_id );

if ( ! $product ) {
	status_header( 404 );
	nocache_headers();
	include get_query_template( '404' );
	return;
}

/**
 * Scalar ACF/meta helper (supports file/image arrays).
 *
 * @param int    $post_id Product ID.
 * @param string $key     Field key.
 * @return string
 */
$lk_field = static function ( int $post_id, string $key ): string {
	$value = function_exists( 'get_field' ) ? get_field( $key, $post_id ) : null;
	if ( null === $value || false === $value || '' === $value ) {
		$value = get_post_meta( $post_id, $key, true );
	}

	if ( is_array( $value ) ) {
		if ( isset( $value['url'] ) && '' !== (string) $value['url'] ) {
			return (string) $value['url'];
		}
		$id = 0;
		if ( isset( $value['ID'] ) ) {
			$id = (int) $value['ID'];
		} elseif ( isset( $value['id'] ) ) {
			$id = (int) $value['id'];
		}
		if ( $id > 0 ) {
			$url = wp_get_attachment_url( $id );
			return $url ? (string) $url : '';
		}
		return '';
	}

	if ( is_numeric( $value ) && (int) $value > 0 ) {
		$url = wp_get_attachment_url( (int) $value );
		if ( $url ) {
			return (string) $url;
		}
	}

	return is_scalar( $value ) ? trim( (string) $value ) : '';
};

/**
 * Whether a string looks like a media URL.
 *
 * @param string $value Candidate value.
 */
$lk_is_url = static function ( string $value ): bool {
	return (bool) preg_match( '#^(https?:)?//#i', $value ) || false !== strpos( $value, '/wp-content/' );
};

/**
 * Find attribute value by one of several label aliases.
 *
 * @param array<string, string> $attr_map Attribute map.
 * @param array<int, string>    $aliases  Label aliases.
 */
$lk_attr = static function ( array $attr_map, array $aliases ): string {
	foreach ( $aliases as $alias ) {
		if ( isset( $attr_map[ $alias ] ) && '' !== $attr_map[ $alias ] ) {
			return $attr_map[ $alias ];
		}
	}

	foreach ( $attr_map as $label => $value ) {
		foreach ( $aliases as $alias ) {
			if ( false !== mb_stripos( (string) $label, $alias ) && '' !== $value ) {
				return $value;
			}
		}
	}

	return '';
};

/**
 * Resolve ACF/meta file values to a URL.
 *
 * @param mixed $value Raw field value.
 */
$lk_media_url = static function ( $value ) use ( $lk_is_url ): string {
	if ( null === $value || false === $value || '' === $value ) {
		return '';
	}

	if ( is_array( $value ) ) {
		if ( isset( $value['url'] ) && '' !== (string) $value['url'] ) {
			return (string) $value['url'];
		}

		$id = 0;
		if ( isset( $value['ID'] ) ) {
			$id = (int) $value['ID'];
		} elseif ( isset( $value['id'] ) ) {
			$id = (int) $value['id'];
		}

		if ( $id > 0 ) {
			$url = wp_get_attachment_url( $id );
			return $url ? (string) $url : '';
		}

		return '';
	}

	if ( is_numeric( $value ) && (int) $value > 0 ) {
		$url = wp_get_attachment_url( (int) $value );
		return $url ? (string) $url : '';
	}

	if ( ! is_scalar( $value ) ) {
		return '';
	}

	$candidate = trim( (string) $value );
	if ( '' === $candidate ) {
		return '';
	}

	if ( $lk_is_url( $candidate ) ) {
		return $candidate;
	}

	if ( str_starts_with( $candidate, '/' ) ) {
		return home_url( $candidate );
	}

	return '';
};

/**
 * Whether an ACF field is the technical datasheet («برگه مشخصات فنی»).
 *
 * @param string $name  Field name.
 * @param string $label Field label.
 * @param string $type  Field type.
 */
$lk_is_datasheet_field = static function ( string $name, string $label, string $type ): bool {
	$label = trim( $label );
	$name  = trim( $name );

	if ( in_array( $label, array( 'COA', 'coa', 'برگه مشخصات فنی', 'دانلود برگه مشخصات فنی', 'برگه مشخصات' ), true ) ) {
		return true;
	}

	if ( '' !== $label && false !== mb_stripos( $label, 'مشخصات فنی' ) ) {
		if ( false !== mb_stripos( $label, 'ایمنی' ) || false !== mb_stripos( $label, 'msds' ) ) {
			return false;
		}
		return true;
	}

	if ( '' !== $label && false !== mb_stripos( $label, 'برگه مشخصات' ) ) {
		return true;
	}

	if ( '' !== $label && preg_match( '/\bcoa\b/i', $label ) ) {
		return true;
	}

	$known_names = array(
		'coa',
		'COA',
		'coa_file',
		'datasheet',
		'data_sheet',
		'tds',
		'tds_file',
		'pdf',
		'technical_datasheet',
		'datasheet_file',
		'برگه_مشخصات',
		'برگه_مشخصات_فنی',
		'برگه مشخصات',
		'برگه مشخصات فنی',
		'دانلود_برگه_مشخصات_فنی',
		'دانلود برگه مشخصات فنی',
		'فایل_مشخصات',
	);

	if ( in_array( $name, $known_names, true ) ) {
		return true;
	}

	if ( preg_match( '/^(coa|coa_file)$/i', $name ) || preg_match( '/datasheet|data_sheet|tds/i', $name ) ) {
		return true;
	}

	if ( preg_match( '/برگه.*مشخصات/u', $name ) ) {
		return true;
	}

	return in_array( $type, array( 'file', 'url', 'image', 'link' ), true )
		&& '' !== $label
		&& false !== mb_stripos( $label, 'برگه' );
};

/**
 * Find product technical datasheet URL (empty when not set).
 *
 * @param int $post_id Product ID.
 */
$lk_resolve_datasheet = static function ( int $post_id ) use ( $lk_field, $lk_media_url, $lk_is_datasheet_field, $lk_is_url ): string {
	foreach ( array(
		'coa',
		'COA',
		'coa_file',
		'برگه مشخصات فنی',
		'برگه_مشخصات_فنی',
		'برگه مشخصات',
		'برگه_مشخصات',
		'دانلود برگه مشخصات فنی',
		'دانلود_برگه_مشخصات_فنی',
		'datasheet',
		'data_sheet',
		'tds',
		'tds_file',
		'pdf',
		'technical_datasheet',
		'فایل_مشخصات',
		'datasheet_file',
	) as $sheet_key ) {
		$raw = function_exists( 'get_field' ) ? get_field( $sheet_key, $post_id, false ) : get_post_meta( $post_id, $sheet_key, true );
		if ( null === $raw || false === $raw || '' === $raw ) {
			$raw = get_post_meta( $post_id, $sheet_key, true );
		}

		$url = $lk_media_url( $raw );
		if ( '' !== $url && $lk_is_url( $url ) ) {
			return esc_url_raw( $url );
		}
	}

	if ( function_exists( 'acf_get_field_objects' ) ) {
		$fields = acf_get_field_objects( $post_id, false );
		if ( is_array( $fields ) ) {
			foreach ( $fields as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}

				$name  = isset( $field['name'] ) ? (string) $field['name'] : '';
				$label = isset( $field['label'] ) ? (string) $field['label'] : '';
				$type  = isset( $field['type'] ) ? (string) $field['type'] : '';

				if ( ! $lk_is_datasheet_field( $name, $label, $type ) ) {
					continue;
				}

				$url = $lk_media_url( $field['value'] ?? null );
				if ( '' !== $url && $lk_is_url( $url ) ) {
					return esc_url_raw( $url );
				}
			}
		}
	}

	foreach ( array_keys( (array) get_post_meta( $post_id ) ) as $meta_key ) {
		$meta_key = (string) $meta_key;
		if ( str_starts_with( $meta_key, '_' ) ) {
			continue;
		}

		if (
			! preg_match( '/^(coa|coa_file)$/i', $meta_key )
			&& ! preg_match( '/datasheet|data_sheet|tds|برگه/u', $meta_key )
		) {
			continue;
		}

		$url = $lk_media_url( get_post_meta( $post_id, $meta_key, true ) );
		if ( '' !== $url && $lk_is_url( $url ) ) {
			return esc_url_raw( $url );
		}
	}

	return '';
};

$context = array_merge(
	\Timber\Timber::context(),
	class_exists( 'Hello_Elementor_Child_Light_Product_Template' )
		? Hello_Elementor_Child_Light_Product_Template::get_chrome_context()
		: array()
);
$context['post'] = \Timber\Timber::get_post( $post_id );

ob_start();
language_attributes();
$context['html_language_attributes'] = trim( ob_get_clean() );
$context['body_class']               = implode( ' ', get_body_class( 'lk-single-product-light lk-light-product lz-chrome' ) );

$chrome_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-light-product.css';
$context['light_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-light-product.css';
$context['light_css_ver'] = file_exists( $chrome_css ) ? (string) filemtime( $chrome_css ) : HELLO_ELEMENTOR_CHILD_VERSION;

$spl_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/single-product-light.css';
$context['spl_css_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/single-product-light.css';
$context['spl_css_ver'] = file_exists( $spl_css ) ? (string) filemtime( $spl_css ) : HELLO_ELEMENTOR_CHILD_VERSION;

$spl_js = HELLO_ELEMENTOR_CHILD_PATH . 'assets/js/single-product-light.js';
$context['spl_js_url'] = HELLO_ELEMENTOR_CHILD_URI . 'assets/js/single-product-light.js';
$context['spl_js_ver'] = file_exists( $spl_js ) ? (string) filemtime( $spl_js ) : HELLO_ELEMENTOR_CHILD_VERSION;

// —— Single product image (no gallery) ——
$image = null;
$main_id = $product->get_image_id();
if ( $main_id ) {
	$image = array(
		'id'  => $main_id,
		'url' => wp_get_attachment_image_url( $main_id, 'large' ),
		'alt' => (string) get_post_meta( $main_id, '_wp_attachment_image_alt', true ),
	);
}

// —— Attributes ——
$attributes_list = array();
$attr_map        = array();
foreach ( $product->get_attributes() as $attribute ) {
	$label = wc_attribute_label( $attribute->get_name() );

	if ( $attribute->is_taxonomy() ) {
		$terms = wc_get_product_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'names' ) );
		$value = is_wp_error( $terms ) ? '' : implode( ', ', $terms );
	} else {
		$value = implode( ', ', array_map( 'strval', $attribute->get_options() ) );
	}

	if ( '' === $value ) {
		continue;
	}

	$row               = array(
		'label' => $label,
		'value' => $value,
	);
	$attributes_list[] = $row;
	$attr_map[ $label ] = $value;
}

$cas_no = $lk_field( $post_id, 'cas_no' );
if ( '' === $cas_no ) {
	$cas_no = $lk_attr( $attr_map, array( 'CAS', 'Cas No', 'CAS Number' ) );
}

$english_name = '';
foreach ( array( 'en-name', 'en_name', 'english_name', 'نام_انگلیسی', 'english' ) as $en_key ) {
	$english_name = $lk_field( $post_id, $en_key );
	if ( '' !== $english_name && ! $lk_is_url( $english_name ) ) {
		break;
	}
	$english_name = '';
}
if ( '' === $english_name ) {
	$english_name = $lk_attr( $attr_map, array( 'نام انگلیسی', 'English Name', 'English', 'EN Name' ) );
}

$datasheet_pack = class_exists( 'Hello_Elementor_Child_Light_Datasheet' )
	? Hello_Elementor_Child_Light_Datasheet::get_product_datasheet( $post_id )
	: array(
		'url'           => '',
		'view_url'      => '',
		'download_url'  => '',
		'mime'          => '',
		'attachment_id' => 0,
	);
$datasheet = isset( $datasheet_pack['url'] ) ? (string) $datasheet_pack['url'] : '';
if ( '' === $datasheet && isset( $datasheet_pack['view_url'] ) ) {
	$datasheet = (string) $datasheet_pack['view_url'];
}

// Brand: tag first, then attribute / ACF.
$brand_name  = '';
$brand_link  = '';
$brand_image = '';

$tags = get_the_terms( $post_id, 'product_tag' );
if ( $tags && ! is_wp_error( $tags ) ) {
	$first_tag  = $tags[0];
	$brand_name = $first_tag->name;
	$brand_link = get_term_link( $first_tag );
	if ( is_wp_error( $brand_link ) ) {
		$brand_link = '';
	}

	$term_thumb = 0;
	if ( class_exists( 'Hello_Elementor_Child_Custom_Tag_Archive' ) ) {
		$term_thumb  = Hello_Elementor_Child_Custom_Tag_Archive::resolve_thumbnail_id( (int) $first_tag->term_id );
		$brand_image = Hello_Elementor_Child_Custom_Tag_Archive::resolve_image_url( (int) $first_tag->term_id, 'thumbnail' );
	} else {
		$term_thumb = (int) get_term_meta( $first_tag->term_id, 'thumbnail_id', true );
		if ( $term_thumb ) {
			$url = wp_get_attachment_image_url( $term_thumb, 'thumbnail' );
			if ( $url ) {
				$brand_image = $url;
			}
		}
	}
}

$brand_acf = $lk_field( $post_id, 'آدرس_برند' );
if ( '' !== $brand_acf ) {
	if ( ! $lk_is_url( $brand_acf ) && '' === $brand_name ) {
		$brand_name = $brand_acf;
	}
}

// Product-level company logo (ACF «لوگو شرکت») — same keys as shop cards.
foreach ( array( 'لوگو_شرکت', 'لوگو شرکت', 'brand_logo', 'لوگو_برند', 'brand_image' ) as $logo_key ) {
	$logo = $lk_field( $post_id, $logo_key );
	if ( '' !== $logo && $lk_is_url( $logo ) ) {
		$brand_image = $logo;
		break;
	}
}

if ( '' === $brand_image && '' !== $brand_acf && $lk_is_url( $brand_acf ) ) {
	$brand_image = $brand_acf;
}

if ( '' === $brand_name ) {
	$brand_name = $lk_attr( $attr_map, array( 'برند', 'Brand' ) );
}

// Highlight specs: 2-column card (grade, purity, country, packaging).
$highlight_defs = array(
	array(
		'label'   => 'گرید',
		'icon'    => 'grade',
		'aliases' => array( 'گرید', 'Grade' ),
	),
	array(
		'label'   => 'درصد خلوص',
		'icon'    => 'purity',
		'aliases' => array( 'درصد خلوص', 'خلوص', 'Purity' ),
	),
	array(
		'label'   => 'کشور تولید کننده',
		'icon'    => 'country',
		'aliases' => array( 'کشور تولید کننده', 'کشور سازنده', 'کشور', 'Country' ),
	),
	array(
		'label'   => 'بسته بندی',
		'icon'    => 'packaging',
		'aliases' => array( 'بسته بندی', 'بسته‌بندی', 'Packaging', 'Pack size' ),
	),
);

$highlights = array();
foreach ( $highlight_defs as $def ) {
	$value = $lk_attr( $attr_map, $def['aliases'] );
	if ( '' === $value ) {
		continue;
	}
	$highlights[] = array(
		'label' => $def['label'],
		'value' => $value,
		'icon'  => $def['icon'],
	);
}

// Full attributes table extras.
$table_attrs = $attributes_list;
if ( '' !== $brand_name ) {
	$has_brand = false;
	foreach ( $table_attrs as $row ) {
		if ( 'برند' === $row['label'] ) {
			$has_brand = true;
			break;
		}
	}
	if ( ! $has_brand ) {
		$table_attrs[] = array(
			'label' => 'برند',
			'value' => $brand_name,
		);
	}
}
if ( '' !== $cas_no ) {
	$table_attrs[] = array(
		'label' => 'CAS Number',
		'value' => $cas_no,
	);
}
if ( $product->get_sku() ) {
	$table_attrs[] = array(
		'label' => 'SKU',
		'value' => $product->get_sku(),
	);
}

$breadcrumb_html = function_exists( 'hello_elementor_child_get_breadcrumb_html' )
	? hello_elementor_child_get_breadcrumb_html()
	: '';

ob_start();
woocommerce_template_single_add_to_cart();
$add_to_cart_html = ob_get_clean();

$reviews_html = '';
if ( comments_open( $post_id ) || get_comments_number( $post_id ) ) {
	global $post, $withcomments;
	$post         = get_post( $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	$withcomments = true;
	setup_postdata( $post );
	ob_start();
	comments_template();
	$reviews_html = ob_get_clean();
	wp_reset_postdata();
}

$related_items = array();
if ( function_exists( 'wc_get_related_products' ) ) {
	$related_ids = wc_get_related_products( $post_id, 4 );
	foreach ( $related_ids as $related_id ) {
		$related = wc_get_product( $related_id );
		if ( ! $related ) {
			continue;
		}
		$thumb_id          = $related->get_image_id();
		$related_items[] = array(
			'id'         => $related->get_id(),
			'name'       => $related->get_name(),
			'permalink'  => get_permalink( $related->get_id() ),
			'price_html' => $related->get_price_html(),
			'image'      => $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : '',
		);
	}
}

$context['product_data'] = array(
	'id'               => $product->get_id(),
	'name'             => $product->get_name(),
	'english_name'     => $english_name,
	'sku'              => $product->get_sku(),
	'price_html'       => $product->get_price_html(),
	'is_in_stock'      => $product->is_in_stock(),
	'is_purchasable'   => $product->is_purchasable(),
	'is_variable'      => $product->is_type( 'variable' ),
	'description'      => $product->get_description(),
	'cas_no'           => $cas_no,
	'brand_name'       => $brand_name,
	'brand_link'       => $brand_link,
	'brand_image'      => $brand_image,
	'datasheet'           => $datasheet,
	'datasheet_view_url'  => isset( $datasheet_pack['view_url'] ) ? (string) $datasheet_pack['view_url'] : '',
	'datasheet_download_url' => isset( $datasheet_pack['download_url'] ) ? (string) $datasheet_pack['download_url'] : '',
	'datasheet_mime'      => isset( $datasheet_pack['mime'] ) ? (string) $datasheet_pack['mime'] : '',
	'image'            => $image,
	'attributes'       => $table_attrs,
	'highlights'       => $highlights,
	'permalink'        => get_permalink( $post_id ),
	'add_to_cart_html' => $add_to_cart_html,
	'breadcrumb_html'  => $breadcrumb_html,
	'reviews_html'     => $reviews_html,
	'related'          => $related_items,
	'shipping_url'     => home_url( '/sending-goods/' ),
	'whatsapp_url'     => 'https://wa.me/989122114322',
	'price_notice'     => 'به علت نوسانات قیمت لطفا قبل از اقدام به خرید از طریق تماس تلفنی استعلام قیمت بگیرید',
);

// Debug marker: if this appears in View Source, light PHP template ran.
echo '<!-- LK-LIGHT-TEMPLATE-ACTIVE product_id=' . (int) $post_id . ' -->' . "\n";

// Ensure assets are queued even when this template is forced before the normal enqueue pass.
if ( class_exists( 'Hello_Elementor_Child_Custom_Single_Product' ) ) {
	Hello_Elementor_Child_Custom_Single_Product::enqueue_light_assets();
}

\Timber\Timber::render( 'woo/single-product-light.twig', $context );
