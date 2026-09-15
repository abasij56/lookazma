<?php
/**
 * Schema.org JSON-LD for brand archive and single brand pages.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Outputs structured data for brand SEO.
 */
final class Hello_Elementor_Child_Brand_Schema {

	private const PRODUCT_LIST_LIMIT = 12;

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'wp_head', array( __CLASS__, 'print_json_ld' ), 20 );
	}

	/**
	 * Print JSON-LD when on a brand page.
	 */
	public static function print_json_ld(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		$graph = null;

		if ( class_exists( 'Hello_Elementor_Child_Custom_Brand_Single' )
			&& Hello_Elementor_Child_Custom_Brand_Single::is_enabled()
		) {
			$brand = Hello_Elementor_Child_Custom_Brand_Single::get_brand_post();
			if ( $brand instanceof WP_Post ) {
				$graph = self::build_single_brand_graph( $brand );
			}
		} elseif ( class_exists( 'Hello_Elementor_Child_Light_Brands_Template' )
			&& Hello_Elementor_Child_Light_Brands_Template::is_enabled()
		) {
			$graph = self::build_brands_archive_graph();
		}

		if ( empty( $graph ) || ! is_array( $graph ) ) {
			return;
		}

		/**
		 * Filter brand schema @graph nodes before output.
		 *
		 * @param array<int, array<string, mixed>> $graph Schema nodes.
		 */
		$graph = apply_filters( 'lk_brand_schema_graph', $graph );

		if ( empty( $graph ) ) {
			return;
		}

		$payload = array(
			'@context' => 'https://schema.org',
			'@graph'   => array_values( $graph ),
		);

		echo '<script type="application/ld+json">';
		echo wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		echo '</script>' . "\n";
	}

	/**
	 * @param WP_Post $brand Brand post.
	 * @return array<int, array<string, mixed>>
	 */
	private static function build_single_brand_graph( WP_Post $brand ): array {
		$brand_id   = (int) $brand->ID;
		$brand_url = get_permalink( $brand );
		$brand_url = is_string( $brand_url ) ? $brand_url : '';
		$brand_url = user_trailingslashit( $brand_url );

		if ( '' === $brand_url ) {
			return array();
		}

		$site_id = trailingslashit( home_url( '/' ) ) . '#organization';
		$page_id = $brand_url . '#webpage';
		$entity_id = $brand_url . '#brand';

		$description = self::get_brand_description( $brand );
		$logo_url    = self::get_brand_logo_url( $brand_id );
		$english     = self::get_brand_english_name( $brand_id );
		$website     = self::get_brand_website_url( $brand_id );

		$graph = array();

		$site_node = self::get_site_organization_node( $site_id );
		if ( ! empty( $site_node ) ) {
			$graph[] = $site_node;
		}

		$brand_node = array(
			'@type'       => 'Brand',
			'@id'         => $entity_id,
			'name'        => $brand->post_title,
			'url'         => $brand_url,
			'description' => $description,
		);

		if ( '' !== $english && $english !== $brand->post_title ) {
			$brand_node['alternateName'] = $english;
		}

		if ( '' !== $logo_url ) {
			$brand_node['logo'] = array(
				'@type' => 'ImageObject',
				'url'   => $logo_url,
			);
			$brand_node['image'] = $logo_url;
		}

		$country = self::get_acf_string( $brand_id, 'brand_country' );
		if ( '' !== $country ) {
			$brand_node['foundingLocation'] = array(
				'@type' => 'Place',
				'name'  => $country,
			);
		}

		$same_as = array();
		if ( '' !== $website ) {
			$same_as[] = $website;
		}
		if ( ! empty( $same_as ) ) {
			$brand_node['sameAs'] = $same_as;
		}

		$graph[] = $brand_node;

		$graph[] = array(
			'@type'           => 'WebPage',
			'@id'             => $page_id,
			'url'             => $brand_url,
			'name'            => sprintf(
				/* translators: %s: brand name */
				__( 'محصولات برند %s', 'hello-elementor-child' ),
				$brand->post_title
			),
			'description'     => $description,
			'isPartOf'        => array( '@id' => $site_id ),
			'about'           => array( '@id' => $entity_id ),
			'inLanguage'      => self::get_site_language(),
			'breadcrumb'      => array( '@id' => $brand_url . '#breadcrumb' ),
			'mainEntity'      => array( '@id' => $entity_id ),
		);

		$crumbs = self::get_single_brand_breadcrumbs( $brand );
		$breadcrumb = self::build_breadcrumb_list_node( $brand_url . '#breadcrumb', $crumbs );
		if ( ! empty( $breadcrumb ) ) {
			$graph[] = $breadcrumb;
		}

		$products = self::get_brand_products( $brand, self::PRODUCT_LIST_LIMIT );
		if ( ! empty( $products ) ) {
			$list_id = $brand_url . '#product-list';
			$graph[] = self::build_product_list_node( $list_id, $products, $page_id );

			foreach ( $products as $product_post ) {
				if ( ! $product_post instanceof WP_Post ) {
					continue;
				}
				$product_node = self::build_product_node( $product_post, $brand );
				if ( ! empty( $product_node ) ) {
					$graph[] = $product_node;
				}
			}
		}

		$faqs = self::get_brand_faqs( $brand_id );
		if ( ! empty( $faqs ) ) {
			$graph[] = self::build_faq_page_node( $brand_url . '#faq', $faqs, $page_id );
		}

		return $graph;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function build_brands_archive_graph(): array {
		$page_id = class_exists( 'Hello_Elementor_Child_Light_Brands_Template' )
			? Hello_Elementor_Child_Light_Brands_Template::get_context_page_id()
			: 0;
		$page = $page_id > 0 ? get_post( $page_id ) : null;

		$title = __( 'شرکت ها', 'hello-elementor-child' );
		$url   = class_exists( 'Hello_Elementor_Child_Brand_Cpt' )
			? Hello_Elementor_Child_Brand_Cpt::get_archive_url()
			: home_url( '/brands/' );

		if ( $page instanceof WP_Post ) {
			if ( '' !== trim( $page->post_title ) ) {
				$title = $page->post_title;
			}
			$permalink = get_permalink( $page );
			if ( is_string( $permalink ) && '' !== $permalink ) {
				$url = $permalink;
			}
		}

		$url = user_trailingslashit( $url );

		$site_id      = trailingslashit( home_url( '/' ) ) . '#organization';
		$webpage_id   = $url . '#webpage';
		$list_id      = $url . '#brand-list';
		$description  = $page instanceof WP_Post ? trim( wp_strip_all_tags( (string) $page->post_excerpt ) ) : '';

		$graph = array();

		$site_node = self::get_site_organization_node( $site_id );
		if ( ! empty( $site_node ) ) {
			$graph[] = $site_node;
		}

		$graph[] = array(
			'@type'      => 'CollectionPage',
			'@id'        => $webpage_id,
			'url'        => $url,
			'name'       => $title,
			'description'=> $description,
			'isPartOf'   => array( '@id' => $site_id ),
			'inLanguage' => self::get_site_language(),
			'breadcrumb' => array( '@id' => $url . '#breadcrumb' ),
			'mainEntity' => array( '@id' => $list_id ),
		);

		$crumbs = self::get_brands_archive_breadcrumbs( $title, $url );
		$breadcrumb = self::build_breadcrumb_list_node( $url . '#breadcrumb', $crumbs );
		if ( ! empty( $breadcrumb ) ) {
			$graph[] = $breadcrumb;
		}

		$brands = get_posts(
			array(
				'post_type'      => Hello_Elementor_Child_Brand_Cpt::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);

		$list_items = array();
		$position   = 1;

		foreach ( $brands as $brand_id ) {
			$brand_id = (int) $brand_id;
			if ( $brand_id <= 0 ) {
				continue;
			}
			$brand_post = get_post( $brand_id );
			if ( ! $brand_post instanceof WP_Post ) {
				continue;
			}
			$brand_link = get_permalink( $brand_post );
			if ( ! is_string( $brand_link ) || '' === $brand_link ) {
				continue;
			}

			$item = array(
				'@type'    => 'ListItem',
				'position' => $position,
				'url'      => user_trailingslashit( $brand_link ),
				'name'     => $brand_post->post_title,
			);

			$logo = self::get_brand_logo_url( $brand_id );
			if ( '' !== $logo ) {
				$item['item'] = array(
					'@type' => 'Brand',
					'name'  => $brand_post->post_title,
					'url'   => user_trailingslashit( $brand_link ),
					'logo'  => $logo,
				);
			}

			$list_items[] = $item;
			++$position;
		}

		if ( ! empty( $list_items ) ) {
			$graph[] = array(
				'@type'           => 'ItemList',
				'@id'             => $list_id,
				'name'            => $title,
				'numberOfItems'   => count( $list_items ),
				'itemListElement' => $list_items,
			);
		}

		return $graph;
	}

	/**
	 * @param string $org_id Organization @id.
	 * @return array<string, mixed>
	 */
	private static function get_site_organization_node( string $org_id ): array {
		$node = array(
			'@type' => 'Organization',
			'@id'   => $org_id,
			'name'  => get_bloginfo( 'name' ),
			'url'   => trailingslashit( home_url( '/' ) ),
		);

		$logo_id = (int) get_theme_mod( 'custom_logo' );
		if ( $logo_id > 0 ) {
			$logo_url = wp_get_attachment_image_url( $logo_id, 'full' );
			if ( is_string( $logo_url ) && '' !== $logo_url ) {
				$node['logo'] = array(
					'@type' => 'ImageObject',
					'url'   => $logo_url,
				);
			}
		}

		return $node;
	}

	/**
	 * @param string                              $id     Node @id.
	 * @param array<int, array{0: string, 1: string}> $crumbs Label + URL pairs.
	 * @return array<string, mixed>
	 */
	private static function build_breadcrumb_list_node( string $id, array $crumbs ): array {
		if ( empty( $crumbs ) ) {
			return array();
		}

		$items    = array();
		$position = 1;

		foreach ( $crumbs as $crumb ) {
			if ( ! is_array( $crumb ) || empty( $crumb[0] ) ) {
				continue;
			}
			$label = (string) $crumb[0];
			$link  = isset( $crumb[1] ) ? trim( (string) $crumb[1] ) : '';

			$item = array(
				'@type'    => 'ListItem',
				'position' => $position,
				'name'     => $label,
			);

			if ( '' !== $link ) {
				$item['item'] = user_trailingslashit( $link );
			}

			$items[] = $item;
			++$position;
		}

		if ( empty( $items ) ) {
			return array();
		}

		return array(
			'@type'           => 'BreadcrumbList',
			'@id'             => $id,
			'itemListElement' => $items,
		);
	}

	/**
	 * @param WP_Post $brand Brand post.
	 * @return array<int, array{0: string, 1: string}>
	 */
	private static function get_single_brand_breadcrumbs( WP_Post $brand ): array {
		if ( function_exists( 'woocommerce_get_breadcrumb' ) ) {
			$crumbs = woocommerce_get_breadcrumb();
			if ( is_array( $crumbs ) && ! empty( $crumbs ) ) {
				return self::normalize_breadcrumb_crumbs( $crumbs, get_permalink( $brand ) );
			}
		}

		$home_label = __( 'خانه', 'hello-elementor-child' );
		$shop_label = function_exists( 'hello_elementor_child_get_shop_label' )
			? hello_elementor_child_get_shop_label()
			: __( 'فروشگاه', 'hello-elementor-child' );
		$shop_url = function_exists( 'hello_elementor_child_get_shop_url' )
			? hello_elementor_child_get_shop_url()
			: home_url( '/' );
		$brands_label = __( 'شرکت ها', 'hello-elementor-child' );
		$brands_url   = Hello_Elementor_Child_Brand_Cpt::get_archive_url();
		$brand_url    = get_permalink( $brand );
		$brand_url    = is_string( $brand_url ) ? user_trailingslashit( $brand_url ) : '';

		return array(
			array( $home_label, trailingslashit( home_url( '/' ) ) ),
			array( $shop_label, $shop_url ),
			array( $brands_label, $brands_url ),
			array(
				sprintf(
					/* translators: %s: brand name */
					__( 'محصولات %s', 'hello-elementor-child' ),
					$brand->post_title
				),
				$brand_url,
			),
		);
	}

	/**
	 * @param string $title Page title.
	 * @param string $url   Page URL.
	 * @return array<int, array{0: string, 1: string}>
	 */
	private static function get_brands_archive_breadcrumbs( string $title, string $url ): array {
		if ( function_exists( 'woocommerce_get_breadcrumb' ) ) {
			$crumbs = woocommerce_get_breadcrumb();
			if ( is_array( $crumbs ) && ! empty( $crumbs ) ) {
				return self::normalize_breadcrumb_crumbs( $crumbs, $url );
			}
		}

		$shop_label = function_exists( 'hello_elementor_child_get_shop_label' )
			? hello_elementor_child_get_shop_label()
			: __( 'فروشگاه', 'hello-elementor-child' );
		$shop_url = function_exists( 'hello_elementor_child_get_shop_url' )
			? hello_elementor_child_get_shop_url()
			: home_url( '/' );

		return array(
			array( __( 'خانه', 'hello-elementor-child' ), trailingslashit( home_url( '/' ) ) ),
			array( $shop_label, $shop_url ),
			array( $title, user_trailingslashit( $url ) ),
		);
	}

	/**
	 * Ensure the last breadcrumb has the current page URL.
	 *
	 * @param array<int, array{0?: string, 1?: string}> $crumbs     Crumbs.
	 * @param string|false|null                         $current_url Current URL.
	 * @return array<int, array{0: string, 1: string}>
	 */
	private static function normalize_breadcrumb_crumbs( array $crumbs, $current_url ): array {
		$normalized = array();
		$last_index = count( $crumbs ) - 1;

		foreach ( $crumbs as $index => $crumb ) {
			if ( ! is_array( $crumb ) || empty( $crumb[0] ) ) {
				continue;
			}
			$label = (string) $crumb[0];
			$url   = isset( $crumb[1] ) ? trim( (string) $crumb[1] ) : '';

			if ( $index === $last_index && ( '' === $url || '#' === $url ) && is_string( $current_url ) && '' !== $current_url ) {
				$url = user_trailingslashit( $current_url );
			}

			$normalized[] = array( $label, $url );
		}

		return $normalized;
	}

	/**
	 * @param string           $list_id   ItemList @id.
	 * @param array<int, WP_Post> $products Product posts.
	 * @param string           $page_id   WebPage @id.
	 * @return array<string, mixed>
	 */
	private static function build_product_list_node( string $list_id, array $products, string $page_id ): array {
		$items    = array();
		$position = 1;

		foreach ( $products as $product_post ) {
			if ( ! $product_post instanceof WP_Post ) {
				continue;
			}
			$permalink = get_permalink( $product_post );
			if ( ! is_string( $permalink ) || '' === $permalink ) {
				continue;
			}
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $position,
				'url'      => user_trailingslashit( $permalink ),
				'name'     => $product_post->post_title,
			);
			++$position;
		}

		return array(
			'@type'           => 'ItemList',
			'@id'             => $list_id,
			'name'            => __( 'محصولات برند', 'hello-elementor-child' ),
			'numberOfItems'   => count( $items ),
			'itemListElement' => $items,
			'isPartOf'        => array( '@id' => $page_id ),
		);
	}

	/**
	 * @param WP_Post $product_post Product post.
	 * @param WP_Post $brand        Brand post.
	 * @return array<string, mixed>
	 */
	private static function build_product_node( WP_Post $product_post, WP_Post $brand ): array {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return array();
		}

		$product = wc_get_product( $product_post->ID );
		if ( ! $product instanceof WC_Product ) {
			return array();
		}

		$permalink = get_permalink( $product_post );
		if ( ! is_string( $permalink ) || '' === $permalink ) {
			return array();
		}
		$permalink = user_trailingslashit( $permalink );

		$node = array(
			'@type' => 'Product',
			'@id'   => $permalink . '#product',
			'name'  => $product->get_name(),
			'url'   => $permalink,
			'brand' => array(
				'@type' => 'Brand',
				'name'  => $brand->post_title,
			),
		);

		$description = trim( wp_strip_all_tags( $product->get_short_description() ) );
		if ( '' === $description ) {
			$description = trim( wp_strip_all_tags( $product->get_description() ) );
		}
		if ( '' !== $description ) {
			$node['description'] = wp_trim_words( $description, 40, '…' );
		}

		$image_id = $product->get_image_id();
		if ( $image_id > 0 ) {
			$image_url = wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' );
			if ( is_string( $image_url ) && '' !== $image_url ) {
				$node['image'] = array( $image_url );
			}
		}

		$sku = $product->get_sku();
		if ( is_string( $sku ) && '' !== $sku ) {
			$node['sku'] = $sku;
		}

		$offer = self::build_product_offer( $product, $permalink );
		if ( ! empty( $offer ) ) {
			$node['offers'] = $offer;
		}

		return $node;
	}

	/**
	 * @param WC_Product $product   Product.
	 * @param string     $permalink Product URL.
	 * @return array<string, mixed>
	 */
	private static function build_product_offer( WC_Product $product, string $permalink ): array {
		$offer = array(
			'@type'         => 'Offer',
			'url'           => $permalink,
			'priceCurrency' => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'IRR',
			'availability'  => $product->is_in_stock()
				? 'https://schema.org/InStock'
				: 'https://schema.org/OutOfStock',
		);

		$price = $product->get_price();
		if ( is_numeric( $price ) && (float) $price > 0 ) {
			$offer['price'] = wc_format_decimal( $price, wc_get_price_decimals() );
		}

		return $offer;
	}

	/**
	 * @param string                           $faq_id  FAQ node id.
	 * @param array<int, array{question: string, answer: string}> $faqs    FAQ rows.
	 * @param string                           $page_id WebPage @id.
	 * @return array<string, mixed>
	 */
	private static function build_faq_page_node( string $faq_id, array $faqs, string $page_id ): array {
		$entities = array();

		foreach ( $faqs as $faq ) {
			$question = trim( (string) ( $faq['question'] ?? '' ) );
			$answer   = trim( (string) ( $faq['answer'] ?? '' ) );
			if ( '' === $question || '' === $answer ) {
				continue;
			}
			$entities[] = array(
				'@type'          => 'Question',
				'name'           => $question,
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $answer,
				),
			);
		}

		if ( empty( $entities ) ) {
			return array();
		}

		return array(
			'@type'      => 'FAQPage',
			'@id'        => $faq_id,
			'isPartOf'   => array( '@id' => $page_id ),
			'mainEntity' => $entities,
		);
	}

	/**
	 * @param WP_Post $brand Brand post.
	 * @param int     $limit Max products.
	 * @return array<int, WP_Post>
	 */
	private static function get_brand_products( WP_Post $brand, int $limit ): array {
		$args = array(
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'posts_per_page'      => max( 1, $limit ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'orderby'             => 'menu_order',
			'order'               => 'ASC',
		);

		if ( class_exists( 'Hello_Elementor_Child_Brand_Enhanced_Single' )
			&& Hello_Elementor_Child_Brand_Enhanced_Single::is_enabled_for( $brand )
		) {
			$args = Hello_Elementor_Child_Brand_Enhanced_Single::apply_product_scope( $args, (int) $brand->ID );
		} else {
			$term = Hello_Elementor_Child_Custom_Brand_Single::get_primary_product_tag( $brand );
			if ( $term instanceof WP_Term ) {
				$args['tax_query'] = array(
					array(
						'taxonomy'         => 'product_tag',
						'field'            => 'term_id',
						'terms'            => array( (int) $term->term_id ),
						'include_children' => false,
					),
				);
			} else {
				$args['post__in'] = array( 0 );
			}
		}

		$query = new WP_Query( $args );
		$posts = is_array( $query->posts ) ? $query->posts : array();
		wp_reset_postdata();

		return array_values(
			array_filter(
				$posts,
				static function ( $post ): bool {
					return $post instanceof WP_Post;
				}
			)
		);
	}

	/**
	 * @param int $brand_id Brand post ID.
	 * @return array<int, array{question: string, answer: string}>
	 */
	private static function get_brand_faqs( int $brand_id ): array {
		if ( ! function_exists( 'get_field' ) ) {
			return array();
		}

		$rows = get_field( 'brand_faq', $brand_id );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$faqs = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$question = trim( (string) ( $row['question'] ?? '' ) );
			$answer   = trim( (string) ( $row['answer'] ?? '' ) );
			if ( '' === $question || '' === $answer ) {
				continue;
			}
			$faqs[] = array(
				'question' => $question,
				'answer'   => $answer,
			);
		}

		return $faqs;
	}

	/**
	 * @param WP_Post $brand Brand post.
	 */
	private static function get_brand_description( WP_Post $brand ): string {
		$excerpt = trim( (string) $brand->post_excerpt );
		if ( '' !== $excerpt ) {
			return trim( wp_strip_all_tags( $excerpt ) );
		}

		$content = trim( wp_strip_all_tags( (string) $brand->post_content ) );
		if ( '' === $content ) {
			return '';
		}

		return wp_trim_words( $content, 40, '…' );
	}

	/**
	 * @param int $brand_id Brand post ID.
	 */
	private static function get_brand_logo_url( int $brand_id ): string {
		$thumb_id = (int) get_post_thumbnail_id( $brand_id );
		if ( $thumb_id <= 0 ) {
			return '';
		}
		$url = wp_get_attachment_image_url( $thumb_id, 'full' );
		return is_string( $url ) ? $url : '';
	}

	/**
	 * @param int $brand_id Brand post ID.
	 */
	private static function get_brand_english_name( int $brand_id ): string {
		foreach ( array( 'en-brand', 'english_name', 'en_name', 'en-tag' ) as $key ) {
			$val = self::get_acf_string( $brand_id, $key );
			if ( '' !== $val ) {
				return $val;
			}
		}
		return '';
	}

	/**
	 * @param int $brand_id Brand post ID.
	 */
	private static function get_brand_website_url( int $brand_id ): string {
		$website = self::get_acf_string( $brand_id, 'brand_website' );
		if ( '' === $website ) {
			return '';
		}
		if ( ! preg_match( '#^https?://#i', $website ) ) {
			$website = 'https://' . $website;
		}
		return esc_url_raw( $website );
	}

	/**
	 * @param int    $post_id Post ID.
	 * @param string $key     ACF/meta key.
	 */
	private static function get_acf_string( int $post_id, string $key ): string {
		if ( $post_id <= 0 ) {
			return '';
		}
		if ( function_exists( 'get_field' ) ) {
			$val = get_field( $key, $post_id );
			if ( is_scalar( $val ) && '' !== trim( (string) $val ) ) {
				return trim( (string) $val );
			}
		}
		$meta = get_post_meta( $post_id, $key, true );
		return is_scalar( $meta ) && '' !== trim( (string) $meta ) ? trim( (string) $meta ) : '';
	}

	/**
	 * Site language for schema.
	 */
	private static function get_site_language(): string {
		$locale = get_locale();
		if ( ! is_string( $locale ) || '' === $locale ) {
			return 'fa-IR';
		}
		return str_replace( '_', '-', $locale );
	}
}
