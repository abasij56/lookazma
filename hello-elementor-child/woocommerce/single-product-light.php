<?php
/**
 * Light single product – Timber entry (opt-in via product checkbox).
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
	$value = function_exists( 'get_field' ) ? get_field( $key, $post_id ) : get_post_meta( $post_id, $key, true );

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

	return is_scalar( $value ) ? (string) $value : '';
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

$context         = \Timber\Timber::context();
$context['post'] = \Timber\Timber::get_post( $post_id );

ob_start();
language_attributes();
$context['html_language_attributes'] = trim( ob_get_clean() );
$context['body_class']               = implode( ' ', get_body_class( 'lk-single-product-light' ) );

// Guarantee styles even if enqueue/CDN/cache strip the stylesheet link.
$css_file = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/single-product-light.css';
$context['light_css_inline'] = file_exists( $css_file ) ? (string) file_get_contents( $css_file ) : '';
$context['light_css_url']    = HELLO_ELEMENTOR_CHILD_URI . 'assets/css/single-product-light.css';
$context['light_css_ver']    = file_exists( $css_file ) ? (string) filemtime( $css_file ) : HELLO_ELEMENTOR_CHILD_VERSION;

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

$english_name = $lk_field( $post_id, 'english_name' );
if ( '' === $english_name ) {
	$english_name = $lk_field( $post_id, 'نام_انگلیسی' );
}

$datasheet = $lk_field( $post_id, 'datasheet' );
if ( '' === $datasheet ) {
	$datasheet = $lk_field( $post_id, 'برگه_مشخصات' );
}
if ( '' === $datasheet ) {
	$datasheet = $lk_field( $post_id, 'data_sheet' );
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

	$term_thumb = (int) get_term_meta( $first_tag->term_id, 'thumbnail_id', true );
	if ( $term_thumb ) {
		$url = wp_get_attachment_image_url( $term_thumb, 'thumbnail' );
		if ( $url ) {
			$brand_image = $url;
		}
	}
}

$brand_acf = $lk_field( $post_id, 'آدرس_برند' );
if ( '' !== $brand_acf ) {
	if ( $lk_is_url( $brand_acf ) ) {
		if ( '' === $brand_image ) {
			$brand_image = $brand_acf;
		}
	} elseif ( '' === $brand_name ) {
		$brand_name = $brand_acf;
	}
}

foreach ( array( 'brand_logo', 'لوگو_برند', 'brand_image' ) as $logo_key ) {
	$logo = $lk_field( $post_id, $logo_key );
	if ( '' !== $logo && $lk_is_url( $logo ) ) {
		$brand_image = $logo;
		break;
	}
}

if ( '' === $brand_name ) {
	$brand_name = $lk_attr( $attr_map, array( 'برند', 'Brand' ) );
}

// Highlight specs: 2-column card (grade, purity, country, packaging).
$highlight_defs = array(
	array(
		'label'   => 'گرید',
		'aliases' => array( 'گرید', 'Grade' ),
	),
	array(
		'label'   => 'درصد خلوص',
		'aliases' => array( 'درصد خلوص', 'خلوص', 'Purity' ),
	),
	array(
		'label'   => 'کشور تولید کننده',
		'aliases' => array( 'کشور تولید کننده', 'کشور سازنده', 'کشور', 'Country' ),
	),
	array(
		'label'   => 'بسته بندی',
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

ob_start();
woocommerce_breadcrumb();
$breadcrumb_html = ob_get_clean();

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

// —— Site chrome (header/footer) — Elementor is dequeued on this template ——
$logo_url = '';
$custom_logo_id = (int) get_theme_mod( 'custom_logo' );
if ( $custom_logo_id ) {
	$logo_url = (string) wp_get_attachment_image_url( $custom_logo_id, 'full' );
}
if ( '' === $logo_url ) {
	$logo_url = 'https://lookazma.com/wp-content/uploads/2023/06/lookazma-logo-1.svg';
}

$cart_count = 0;
$cart_total = '';
$cart_url   = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
if ( function_exists( 'WC' ) && WC()->cart ) {
	$cart_count = (int) WC()->cart->get_cart_contents_count();
	$cart_total = WC()->cart->get_cart_subtotal();
}

$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );

$context['site_chrome'] = array(
	'logo_url'        => $logo_url,
	'footer_logo_url' => 'https://lookazma.com/wp-content/uploads/2025/12/white-logo.svg',
	'cart_url'        => $cart_url,
	'cart_count'      => $cart_count,
	'cart_total'      => $cart_total,
	'nav'             => array(
		array(
			'label' => 'فروشگاه',
			'url'   => $shop_url,
		),
		array(
			'label' => 'درباره ما',
			'url'   => home_url( '/about-us/' ),
		),
		array(
			'label' => 'تماس با ما',
			'url'   => home_url( '/contact-us/' ),
		),
		array(
			'label' => 'مجله لوک آزما',
			'url'   => home_url( '/article/' ),
		),
		array(
			'label' => 'حساب کاربری',
			'url'   => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' ),
		),
	),
	'phone_office'    => '02182802125',
	'phone_mobile'    => '09122114322',
	'email'           => 'info@lookazma.com',
	'address'         => 'آدرس دفتر مرکزی: البرز، مهرشهر، بلوار شهرداری، فاز1، خیابان 216، پلاک 20',
	'hours'           => 'از شنبه تا پنجشنبه ۸ صبح تا ۱۷ پاسخگوی شما عزیزان هستیم.',
	'footer_about'    => 'لوک آزما به دنبال راهکاری برای حل مشکلات از دل صنعت مواد شیمایی متولد شده است تا تخصصی در زمینه مواد شیمیایی به شما کمک کند تا قیمت و اطلاعات مواد شیمیایی در دستان شما باشد.',
	'footer_links'    => array(
		array(
			'label' => 'همه محصولات',
			'url'   => $shop_url,
		),
		array(
			'label' => 'درباره لوک آزما',
			'url'   => home_url( '/about-us/' ),
		),
		array(
			'label' => 'ارتباط با ما',
			'url'   => home_url( '/contact-us/' ),
		),
		array(
			'label' => 'تماس با ما',
			'url'   => home_url( '/contact-us/' ),
		),
	),
	'instagram_url'   => 'https://www.instagram.com/look.azma',
	'enamad_url'      => 'https://trustseal.enamad.ir/?id=633258&Code=n5atzkEzysPoHgq5usIr8v3vRMhLZ2G4',
	'enamad_img'      => 'https://trustseal.enamad.ir/logo.aspx?id=633258&Code=n5atzkEzysPoHgq5usIr8v3vRMhLZ2G4',
	'designed_by'     => 'Designed by Alireza Ahmadi',
);

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
	'datasheet'        => $datasheet,
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
	'phone'            => '02182802125',
	'price_notice'     => 'به علت نوسانات قیمت لطفا قبل از اقدام به خرید از طریق تماس تلفنی استعلام قیمت بگیرید',
);

// Debug marker: if this appears in View Source, light PHP template ran.
echo '<!-- LK-LIGHT-TEMPLATE-ACTIVE product_id=' . (int) $post_id . ' -->' . "\n";

\Timber\Timber::render( 'woo/single-product-light.twig', $context );
