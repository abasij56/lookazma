<?php
/**
 * Lookazma homepage Gutenberg blocks (Light homepage template only).
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dynamic blocks for the Light homepage body.
 */
final class Hello_Elementor_Child_Light_Homepage_Blocks {

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_editor_assets' ), 5 );
		add_action( 'init', array( __CLASS__, 'register_blocks' ), 10 );
		add_filter( 'block_categories_all', array( __CLASS__, 'register_block_category' ), 10, 2 );
		add_action( 'init', array( __CLASS__, 'register_patterns' ), 11 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
	}

	/**
	 * Product categories for the homepage card picker.
	 */
	public static function register_rest_routes(): void {
		register_rest_route(
			'lookazma/v1',
			'/product-cats',
			array(
				'methods'             => 'GET',
				'permission_callback' => static function () {
					return current_user_can( 'edit_pages' ) || current_user_can( 'edit_posts' );
				},
				'callback'            => static function () {
					$terms = get_terms(
						array(
							'taxonomy'   => 'product_cat',
							'hide_empty' => false,
							'number'     => 0,
							'orderby'    => 'name',
							'order'      => 'ASC',
						)
					);
					$items = array();
					if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
						return rest_ensure_response( $items );
					}
					foreach ( $terms as $term ) {
						if ( ! $term instanceof WP_Term ) {
							continue;
						}
						$items[] = array(
							'id'   => (int) $term->term_id,
							'name' => $term->name,
						);
					}
					return rest_ensure_response( $items );
				},
			)
		);

		register_rest_route(
			'lookazma/v1',
			'/products',
			array(
				'methods'             => 'GET',
				'permission_callback' => static function () {
					return current_user_can( 'edit_pages' ) || current_user_can( 'edit_posts' );
				},
				'callback'            => static function () {
					$ids = get_posts(
						array(
							'post_type'      => 'product',
							'post_status'    => 'publish',
							'posts_per_page' => -1,
							'orderby'        => 'title',
							'order'          => 'ASC',
							'fields'         => 'ids',
						)
					);
					$items = array();
					if ( ! is_array( $ids ) ) {
						return rest_ensure_response( $items );
					}
					foreach ( $ids as $id ) {
						$items[] = array(
							'id'   => (int) $id,
							'name' => get_the_title( (int) $id ),
						);
					}
					return rest_ensure_response( $items );
				},
			)
		);

		register_rest_route(
			'lookazma/v1',
			'/posts',
			array(
				'methods'             => 'GET',
				'permission_callback' => static function () {
					return current_user_can( 'edit_pages' ) || current_user_can( 'edit_posts' );
				},
				'callback'            => static function () {
					$ids = get_posts(
						array(
							'post_type'      => 'post',
							'post_status'    => 'publish',
							'posts_per_page' => -1,
							'orderby'        => 'title',
							'order'          => 'ASC',
							'fields'         => 'ids',
						)
					);
					$items = array();
					if ( ! is_array( $ids ) ) {
						return rest_ensure_response( $items );
					}
					foreach ( $ids as $id ) {
						$items[] = array(
							'id'   => (int) $id,
							'name' => get_the_title( (int) $id ),
						);
					}
					return rest_ensure_response( $items );
				},
			)
		);
	}

	/**
	 * Register editor script/style handles before register_block_type().
	 */
	public static function register_editor_assets(): void {
		$editor_js = HELLO_ELEMENTOR_CHILD_PATH . 'assets/js/light-homepage-blocks-editor.js';
		if ( file_exists( $editor_js ) ) {
			wp_register_script(
				'lk-light-homepage-blocks-editor',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/js/light-homepage-blocks-editor.js',
				array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-api-fetch' ),
				(string) filemtime( $editor_js ),
				true
			);
			$schemas = array();
			foreach ( self::block_definitions() as $slug => $args ) {
				$schemas[ 'lookazma/' . $slug ] = isset( $args['attributes'] ) && is_array( $args['attributes'] ) ? $args['attributes'] : array();
			}
			wp_add_inline_script(
				'lk-light-homepage-blocks-editor',
				'window.lookazmaHomepageBlockAttributes = ' . wp_json_encode( $schemas ) . ';',
				'before'
			);
		}

		$editor_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/light-homepage-editor.css';
		if ( file_exists( $editor_css ) ) {
			wp_register_style(
				'lk-light-homepage-editor',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/css/light-homepage-editor.css',
				array(),
				(string) filemtime( $editor_css )
			);
		}

		$front_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/light-homepage.css';
		if ( file_exists( $front_css ) ) {
			wp_register_style(
				'lk-light-homepage',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/css/light-homepage.css',
				array(),
				(string) filemtime( $front_css )
			);
		}
	}

	/**
	 * @return array<int, string>
	 */
	public static function get_block_names(): array {
		return array(
			'lookazma/home-hero-carousel',
			'lookazma/home-promise-bar',
			'lookazma/home-brand-strip',
			'lookazma/home-category-grid',
			'lookazma/home-cat-cards',
			'lookazma/home-product-grid',
			'lookazma/home-banner-row',
			'lookazma/home-article-grid',
			'lookazma/home-why',
			'lookazma/home-seo-box',
		);
	}

	/**
	 * Register block inserter category.
	 *
	 * @param array<int, array<string, string>> $categories Block categories.
	 * @param mixed                               $context    Editor context.
	 * @return array<int, array<string, string>>
	 */
	public static function register_block_category( array $categories, $context ): array {
		unset( $context );
		$categories[] = array(
			'slug'  => 'lookazma-homepage',
			'title' => __( 'لوک آزما — صفحه اصلی', 'hello-elementor-child' ),
		);
		return $categories;
	}

	/**
	 * Register dynamic blocks.
	 */
	/**
	 * Block settings shared by registration and the editor.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function block_definitions(): array {
		return array(
			'home-hero-carousel' => array(
				'title'           => __( 'اسلایدر هیرو', 'hello-elementor-child' ),
				'description'     => __( 'اسلایدر تمام‌صفحه قابل ویرایش برای صفحه اصلی.', 'hello-elementor-child' ),
				'icon'            => 'images-alt2',
				'attributes'      => array(
					'slides'     => array(
						'type'    => 'array',
						'default' => self::default_hero_slides(),
					),
					'autoplayMs' => array(
						'type'    => 'number',
						'default' => 5000,
					),
					'coverHero'  => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
				'render_callback' => array( __CLASS__, 'render_hero_carousel' ),
			),
			'home-promise-bar'   => array(
				'title'           => __( 'نوار مزایا', 'hello-elementor-child' ),
				'description'     => __( 'چهار کارت اعتماد و مزیت خرید.', 'hello-elementor-child' ),
				'icon'            => 'shield',
				'attributes'      => array(
					'items' => array(
						'type'    => 'array',
						'default' => self::default_promise_items(),
					),
				),
				'render_callback' => array( __CLASS__, 'render_promise_bar' ),
			),
			'home-brand-strip'   => array(
				'title'           => __( 'نوار برندها', 'hello-elementor-child' ),
				'description'     => __( 'لوگوی برندها با عنوان و دکمه.', 'hello-elementor-child' ),
				'icon'            => 'tag',
				'attributes'      => array(
					'title'       => array( 'type' => 'string', 'default' => 'برترین برندها' ),
					'description' => array( 'type' => 'string', 'default' => 'محصولات برترین تولید کنندگان مواد شیمیایی یکجا' ),
					'ctaText'     => array( 'type' => 'string', 'default' => 'مشاهده همه برندها' ),
					'ctaUrl'      => array( 'type' => 'string', 'default' => '/brands/' ),
					'autoplayMs'  => array(
						'type'    => 'number',
						'default' => 3000,
					),
					'brands'      => array(
						'type'    => 'array',
						'default' => self::default_brands(),
					),
				),
				'render_callback' => array( __CLASS__, 'render_brand_strip' ),
			),
			'home-category-grid' => array(
				'title'           => __( 'دسته‌بندی‌ها', 'hello-elementor-child' ),
				'description'     => __( 'کارت‌های میانبر دسته‌بندی محصولات.', 'hello-elementor-child' ),
				'icon'            => 'category',
				'attributes'      => array(
					'title'       => array( 'type' => 'string', 'default' => 'دسته‌بندی‌های پرجستجو' ),
					'description' => array( 'type' => 'string', 'default' => '' ),
					'categories'  => array(
						'type'    => 'array',
						'default' => self::default_categories(),
					),
				),
				'render_callback' => array( __CLASS__, 'render_category_grid' ),
			),
			'home-cat-cards'     => array(
				'title'           => __( 'کارت دسته‌ها', 'hello-elementor-child' ),
				'description'     => __( 'کارت سفید دسته با تصویر، نام فارسی، نام انگلیسی و CAS.', 'hello-elementor-child' ),
				'icon'            => 'category',
				'attributes'      => array(
					'limit'       => array(
						'type'    => 'number',
						'default' => 6,
					),
					'source'      => array(
						'type'    => 'string',
						'default' => 'latest',
					),
					'categoryIds' => array(
						'type'    => 'array',
						'default' => array(),
					),
				),
				'render_callback' => array( __CLASS__, 'render_cat_cards' ),
			),
			'home-product-grid'  => array(
				'title'           => __( 'گرید محصولات', 'hello-elementor-child' ),
				'description'     => __( 'نمایش محصولات ووکامرس در صفحه اصلی.', 'hello-elementor-child' ),
				'icon'            => 'products',
				'attributes'      => array(
					'title'       => array( 'type' => 'string', 'default' => 'محصولات مواد شیمیایی' ),
					'description' => array( 'type' => 'string', 'default' => '' ),
					'ctaText'     => array( 'type' => 'string', 'default' => 'فروشگاه لوک آزما' ),
					'ctaUrl'      => array( 'type' => 'string', 'default' => '/فروشگاه/' ),
					'productIds'  => array(
						'type'    => 'array',
						'default' => array(),
					),
					'categoryIds' => array(
						'type'    => 'array',
						'default' => array(),
					),
					'limit'       => array(
						'type'    => 'number',
						'default' => 5,
					),
					'source'      => array(
						'type'    => 'string',
						'default' => 'latest',
					),
				),
				'render_callback' => array( __CLASS__, 'render_product_grid' ),
			),
			'home-banner-row'    => array(
				'title'           => __( 'ردیف بنر', 'hello-elementor-child' ),
				'description'     => __( 'سه بنر تبلیغاتی با تصویر و لینک.', 'hello-elementor-child' ),
				'icon'            => 'format-image',
				'attributes'      => array(
					'banners' => array(
						'type'    => 'array',
						'default' => self::default_banners(),
					),
				),
				'render_callback' => array( __CLASS__, 'render_banner_row' ),
			),
			'home-article-grid'  => array(
				'title'           => __( 'گرید مقالات', 'hello-elementor-child' ),
				'description'     => __( 'آخرین نوشته‌ها و مقالات آموزشی.', 'hello-elementor-child' ),
				'icon'            => 'welcome-write-blog',
				'attributes'      => array(
					'title'       => array( 'type' => 'string', 'default' => 'مقالات آموزشی و علمی' ),
					'description' => array( 'type' => 'string', 'default' => '' ),
					'ctaText'     => array( 'type' => 'string', 'default' => 'مشاهده همه مقالات' ),
					'ctaUrl'      => array( 'type' => 'string', 'default' => '/article/' ),
					'limit'       => array(
						'type'    => 'number',
						'default' => 3,
					),
					'source'      => array(
						'type'    => 'string',
						'default' => 'latest',
					),
					'postIds'     => array(
						'type'    => 'array',
						'default' => array(),
					),
				),
				'render_callback' => array( __CLASS__, 'render_article_grid' ),
			),
			'home-why'           => array(
				'title'           => __( 'چرا لوک آزما؟', 'hello-elementor-child' ),
				'description'     => __( 'کارت‌های مزیت، معرفی و تعهدها.', 'hello-elementor-child' ),
				'icon'            => 'star-filled',
				'attributes'      => array(
					'image' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'render_callback' => array( __CLASS__, 'render_why' ),
			),
			'home-seo-box'       => array(
				'title'           => __( 'باکس متن اسکرول‌شو', 'hello-elementor-child' ),
				'description'     => __( 'باکس متن پایین صفحه با اسکرول داخلی (مثل المنتور).', 'hello-elementor-child' ),
				'icon'            => 'editor-justify',
				'attributes'      => array(
					'content' => array(
						'type'    => 'string',
						'default' => self::get_default_seo_html(),
					),
				),
				'render_callback' => array( __CLASS__, 'render_seo_box' ),
			),
		);
	}

	/**
	 * Register dynamic blocks.
	 */
	public static function register_blocks(): void {
		foreach ( self::block_definitions() as $slug => $args ) {
			register_block_type(
				'lookazma/' . $slug,
				array_merge(
					$args,
					array(
						'api_version'   => 2,
						'category'      => 'lookazma-homepage',
						'editor_script' => 'lk-light-homepage-blocks-editor',
						'editor_style'  => 'lk-light-homepage-editor',
						'style'         => 'lk-light-homepage',
						'supports'      => array(
							'html'     => false,
							'inserter' => true,
							'align'    => false,
						),
					)
				)
			);
		}
	}

	/**
	 * Register full homepage pattern.
	 */
	public static function register_patterns(): void {
		if ( ! function_exists( 'register_block_pattern' ) ) {
			return;
		}

		if ( function_exists( 'register_block_pattern_category' ) ) {
			register_block_pattern_category(
				'lookazma-homepage',
				array(
					'label' => __( 'لوک آزما — صفحه اصلی', 'hello-elementor-child' ),
				)
			);
		}

		$hero      = wp_json_encode( self::default_hero_slides() );
		$promises  = wp_json_encode( self::default_promise_items() );
		$brands    = wp_json_encode( self::default_brands() );
		$cats      = wp_json_encode( self::default_categories() );
		$banners   = wp_json_encode( self::default_banners() );

		$content = <<<HTML
<!-- wp:lookazma/home-hero-carousel {"slides":{$hero}} /-->

<!-- wp:lookazma/home-promise-bar {"items":{$promises}} /-->

<!-- wp:lookazma/home-brand-strip {"brands":{$brands}} /-->

<!-- wp:lookazma/home-category-grid {"categories":{$cats}} /-->

<!-- wp:lookazma/home-cat-cards {"limit":6} /-->

<!-- wp:lookazma/home-banner-row {"banners":{$banners}} /-->

<!-- wp:lookazma/home-product-grid {"title":"فروشگاه مواد شیمیایی","limit":5} /-->

<!-- wp:lookazma/home-article-grid {"limit":3} /-->

<!-- wp:lookazma/home-why /-->
HTML;

		register_block_pattern(
			'lookazma/homepage-full',
			array(
				'title'         => __( 'صفحه اصلی لوک آزما (کامل)', 'hello-elementor-child' ),
				'description'   => __( 'تمام بخش‌های پیش‌فرض صفحه اصلی Light.', 'hello-elementor-child' ),
				'categories'    => array( 'lookazma-homepage' ),
				'content'       => $content,
				'inserter'      => true,
				'viewportWidth' => 1400,
			)
		);
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	private static function default_hero_slides(): array {
		return array(
			array(
				'url' => 'https://lookazma.com/wp-content/uploads/2025/12/lookazma.webp',
				'alt' => 'لوک آزما',
			),
			array(
				'url' => 'https://lookazma.com/wp-content/uploads/2025/12/lookazma23.webp',
				'alt' => 'استعلام قیمت مواد شیمیایی',
			),
			array(
				'url' => 'https://lookazma.com/wp-content/uploads/2025/12/lookazma43.webp',
				'alt' => 'فروشگاه مواد شیمیایی',
			),
		);
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	private static function default_promise_items(): array {
		return array(
			array( 'title' => 'ارسال سریع', 'text' => 'پوشش ارسال به سراسر کشور', 'icon' => 'truck' ),
			array( 'title' => 'اصالت برند', 'text' => 'ارائه محصولات از تامین‌کنندگان معتبر', 'icon' => 'badge' ),
			array( 'title' => 'استعلام سریع', 'text' => 'مناسب خریدهای حساس به موجودی و قیمت', 'icon' => 'quote' ),
			array( 'title' => 'پشتیبانی تخصصی', 'text' => 'برای انتخاب گرید و کاربرد مناسب', 'icon' => 'support' ),
		);
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	private static function default_brands(): array {
		return array(
			array( 'image' => 'https://lookazma.com/wp-content/uploads/2023/10/download.jpg', 'alt' => 'دکتر مجللی', 'link' => '/brands/dr-mojallai/' ),
			array( 'image' => 'https://lookazma.com/wp-content/uploads/2023/10/noetrun-logo.webp', 'alt' => 'نوترون', 'link' => '/brands/neutron/' ),
			array( 'image' => 'https://lookazma.com/wp-content/uploads/2024/07/Merck_Logo.webp', 'alt' => 'مرک', 'link' => '/brands/merck/' ),
			array( 'image' => 'https://lookazma.com/wp-content/uploads/2023/12/Untitled.png', 'alt' => 'ویستاکم', 'link' => '/brands/vistachem/' ),
			array( 'image' => 'https://lookazma.com/wp-content/uploads/2024/07/Tat-chem-Logo-Exp.png', 'alt' => 'امرتات', 'link' => '/brands/ameretat/' ),
			array( 'image' => 'https://lookazma.com/wp-content/uploads/2025/11/armansina-logo-1.png-e1765191395192.webp', 'alt' => 'آرمان سینا', 'link' => '/brands/شرکت-آرمان-سینا/' ),
			array( 'image' => 'https://lookazma.com/wp-content/uploads/2025/12/logo.png', 'alt' => 'سینا', 'link' => '/brands/shahrabi/' ),
			array( 'image' => 'https://lookazma.com/wp-content/uploads/2026/05/قطران-شیمی-تجهیز-23506-e1783410412564.png', 'alt' => 'قطران شیمی', 'link' => '/brands/قطران-شیمی/' ),
		);
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	private static function default_categories(): array {
		return array(
			array( 'title' => 'حلال ها', 'subtitle' => 'Solvents', 'image' => 'https://lookazma.com/wp-content/uploads/2023/12/دوپروپانول-hplc-1-228x300.jpg.webp', 'link' => '/product-category/chemicals/solvents/' ),
			array( 'title' => 'نمک ها', 'subtitle' => 'Salts & Inorganics', 'image' => 'https://lookazma.com/wp-content/uploads/2023/12/بسته-بندی-کلی-شرکت-نوترون-300x300-1.jpg.webp', 'link' => '/product-category/chemicals/salts-and-inorganics/' ),
			array( 'title' => 'اسیدها', 'subtitle' => 'Acids & Bases', 'image' => 'https://lookazma.com/wp-content/uploads/2024/02/1-300x237.jpg.webp', 'link' => '/product-category/chemicals/acids-and-bases/' ),
			array( 'title' => 'مواد آلی', 'subtitle' => 'Organic Compounds', 'image' => 'https://lookazma.com/wp-content/uploads/2024/07/emsure_rangeemsure_range-ALL-300x210.jpg.webp', 'link' => '/product-category/chemicals/organic-compound/' ),
			array( 'title' => 'عناصر', 'subtitle' => 'Elements', 'image' => 'https://lookazma.com/wp-content/uploads/2024/02/1-300x237.jpg.webp', 'link' => '/product-category/chemicals/elements/' ),
			array( 'title' => 'محلول ها', 'subtitle' => 'Buffers & Standards', 'image' => 'https://lookazma.com/wp-content/uploads/2023/11/pH-buffers-pic-4-2-300x300.jpg.webp', 'link' => '/product-category/chemicals/buffers-and-standards/' ),
			array( 'title' => 'مواد اولیه', 'subtitle' => 'Raw Materials', 'image' => 'https://lookazma.com/wp-content/uploads/2025/11/sodium-chlorid_censored-1.jpg-201x300.webp', 'link' => '/product-category/chemicals/pharmaceutical-ingredients/' ),
		);
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	private static function default_banners(): array {
		return array(
			array(
				'image' => 'https://lookazma.com/wp-content/uploads/2025/12/banner-0223.webp',
				'label' => 'ویژه فروشگاه',
				'title' => 'سفارش مواد شیمیایی برای آزمایشگاه‌های تحقیقاتی',
				'text'  => 'ارائه مسیر ورود سریع به محصولات تخصصی.',
				'link'  => '/product-tag/dr-mojallai/',
			),
			array(
				'image' => 'https://lookazma.com/wp-content/uploads/2025/12/banner-013.webp',
				'label' => 'دسته‌بندی‌ها',
				'title' => 'مرتب‌سازی بهتر برای خرید بر اساس نوع ماده',
				'text'  => 'نمایش شفاف گروه‌ها برای تجربه‌ای حرفه‌ای.',
				'link'  => '/product-tag/merck/',
			),
			array(
				'image' => 'https://lookazma.com/wp-content/uploads/2025/12/banner-043.webp',
				'label' => 'مجله لوک آزما',
				'title' => 'ترکیب فروش و محتوای علمی',
				'text'  => 'مقالات آموزشی برای تصمیم‌گیری بهتر.',
				'link'  => '/product-tag/neutron/',
			),
		);
	}

	/**
	 * Map legacy /shop/ paths to the Persian shop slug /فروشگاه/.
	 *
	 * @param string $url URL or path.
	 */
	private static function prefer_shop_path( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		if ( '' === $path ) {
			$path = $url;
		}
		$path = untrailingslashit( rawurldecode( $path ) );
		$slug = ltrim( $path, '/' );
		if ( '/shop' === $path || 'shop' === $slug || '/فروشگاه' === $path || 'فروشگاه' === $slug ) {
			return '/فروشگاه/';
		}
		return $url;
	}

	/**
	 * Brand-strip CTA: prefer /brands/ (and remap old shop links).
	 *
	 * @param string $url Saved CTA URL.
	 */
	private static function prefer_brands_archive_path( string $url ): string {
		$url  = trim( $url );
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		if ( '' === $path ) {
			$path = $url;
		}
		$path = untrailingslashit( rawurldecode( $path ) );
		$slug = ltrim( $path, '/' );

		// Empty or legacy shop CTA → brands listing.
		if ( '' === $url
			|| '/shop' === $path
			|| 'shop' === $slug
			|| '/فروشگاه' === $path
			|| 'فروشگاه' === $slug
		) {
			if ( class_exists( 'Hello_Elementor_Child_Brand_Cpt' ) ) {
				return Hello_Elementor_Child_Brand_Cpt::get_archive_url();
			}
			return '/brands/';
		}

		return $url;
	}

	/**
	 * Merge saved brands with defaults so missing brand links are filled.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<int, array<string, string>>
	 */
	private static function resolve_brands( array $attributes ): array {
		$saved = isset( $attributes['brands'] ) && is_array( $attributes['brands'] ) ? $attributes['brands'] : array();
		if ( empty( $saved ) ) {
			return self::default_brands();
		}

		$defaults_by_alt = array();
		foreach ( self::default_brands() as $brand ) {
			if ( ! empty( $brand['alt'] ) ) {
				$defaults_by_alt[ (string) $brand['alt'] ] = $brand;
			}
		}

		$merged = array();
		foreach ( $saved as $brand ) {
			if ( ! is_array( $brand ) ) {
				continue;
			}
			$alt = isset( $brand['alt'] ) ? (string) $brand['alt'] : '';
			if ( '' !== $alt && isset( $defaults_by_alt[ $alt ] ) ) {
				$default = $defaults_by_alt[ $alt ];
				// Prefer coded defaults so link updates (e.g. sina → shahrabi) apply on saved blocks.
				if ( ! empty( $default['link'] ) ) {
					$brand['link'] = $default['link'];
				}
			}
			$merged[] = $brand;
		}

		return $merged;
	}

	/**
	 * Prefer default banner destination links for known homepage banners.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<int, array<string, string>>
	 */
	private static function resolve_banners( array $attributes ): array {
		$saved = isset( $attributes['banners'] ) && is_array( $attributes['banners'] ) ? $attributes['banners'] : array();
		if ( empty( $saved ) ) {
			return self::default_banners();
		}

		$defaults_by_image = array();
		foreach ( self::default_banners() as $banner ) {
			if ( ! empty( $banner['image'] ) ) {
				$defaults_by_image[ (string) $banner['image'] ] = $banner;
			}
		}

		$merged = array();
		foreach ( $saved as $banner ) {
			if ( ! is_array( $banner ) ) {
				continue;
			}
			$image = isset( $banner['image'] ) ? trim( (string) $banner['image'] ) : '';
			if ( '' !== $image && isset( $defaults_by_image[ $image ] ) ) {
				$banner['link'] = $defaults_by_image[ $image ]['link'];
			}
			$merged[] = $banner;
		}

		return $merged;
	}

	/**
	 * @param string $url URL or path.
	 */
	private static function normalize_url( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}
		if ( 0 === strpos( $url, 'http://' ) || 0 === strpos( $url, 'https://' ) ) {
			return esc_url( $url );
		}
		return esc_url( home_url( '/' . ltrim( $url, '/' ) ) );
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public static function render_hero_carousel( array $attributes ): string {
		$slides   = isset( $attributes['slides'] ) && is_array( $attributes['slides'] ) ? $attributes['slides'] : self::default_hero_slides();
		$autoplay = isset( $attributes['autoplayMs'] ) ? max( 2000, (int) $attributes['autoplayMs'] ) : 5000;
		$cover    = ! empty( $attributes['coverHero'] );
		$pieces   = array();

		foreach ( $slides as $slide ) {
			if ( ! is_array( $slide ) ) {
				continue;
			}
			$url = isset( $slide['url'] ) ? trim( (string) $slide['url'] ) : '';
			if ( '' === $url ) {
				continue;
			}
			$pieces[] = array(
				'html' => sprintf(
					'<div class="lk-hero-carousel__slide"><img src="%s" alt="%s" decoding="async"></div>',
					esc_url( $url ),
					esc_attr( isset( $slide['alt'] ) ? (string) $slide['alt'] : '' )
				),
			);
		}

		if ( $cover ) {
			foreach ( self::brand_cover_hero_slides() as $brand_html ) {
				$pieces[] = array( 'html' => $brand_html );
			}
		}

		if ( array() === $pieces ) {
			return '';
		}

		$slide_html = '';
		$dots       = '';
		foreach ( $pieces as $index => $piece ) {
			$active = 0 === $index ? ' is-active' : '';
			$html   = (string) $piece['html'];
			$html   = preg_replace( '/class="lk-hero-carousel__slide/', 'class="lk-hero-carousel__slide' . $active, $html, 1 );
			if ( 0 === $index ) {
				$html = str_replace( '<img ', '<img loading="eager" ', $html );
			} else {
				$html = str_replace( '<img ', '<img loading="lazy" ', $html );
			}
			$slide_html .= is_string( $html ) ? $html : '';
			$dots       .= sprintf(
				'<button type="button" class="lk-hero-carousel__dot%s" aria-label="%s" aria-selected="%s"></button>',
				0 === $index ? ' is-active' : '',
				esc_attr( sprintf( __( 'اسلاید %d', 'hello-elementor-child' ), $index + 1 ) ),
				0 === $index ? 'true' : 'false'
			);
		}

		$search = '';
		if ( class_exists( 'Hello_Elementor_Child_Lookazma_Search' ) ) {
			$search = Hello_Elementor_Child_Lookazma_Search::render_markup( '', 'lk-hero-search' );
		}

		$class = 'lk-hero-carousel lk-homepage-block';
		if ( $cover ) {
			$class .= ' lk-hero-carousel--cover';
		}

		return sprintf(
			'<section class="%s" aria-label="%s" data-lk-hero-carousel data-autoplay-ms="%d"><div class="lk-hero-carousel__viewport">%s</div><div class="lk-hero-carousel__dots" role="tablist" aria-label="%s">%s</div><div class="lk-hero-carousel__search">%s</div></section>',
			esc_attr( $class ),
			esc_attr__( 'اسلایدر اصلی', 'hello-elementor-child' ),
			(int) $autoplay,
			$slide_html,
			esc_attr__( 'انتخاب اسلاید', 'hello-elementor-child' ),
			$dots,
			$search
		);
	}

	/**
	 * Brand cover heroes for the homepage slider.
	 *
	 * @return array<int, string>
	 */
	private static function brand_cover_hero_slides(): array {
		if ( ! class_exists( 'Hello_Elementor_Child_Brand_Cpt' ) || ! class_exists( 'Hello_Elementor_Child_Brand_Enhanced_Single' ) ) {
			return array();
		}

		$brands = get_posts(
			array(
				'post_type'      => Hello_Elementor_Child_Brand_Cpt::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 24,
				'orderby'        => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => Hello_Elementor_Child_Brand_Enhanced_Single::META_KEY_HERO_COVER,
						'value' => 'yes',
					),
				),
			)
		);

		$slides = array();
		foreach ( $brands as $brand ) {
			if ( ! $brand instanceof WP_Post ) {
				continue;
			}
			$image = function_exists( 'get_field' ) ? get_field( 'brand_hero_image', $brand->ID ) : null;
			$url   = '';
			$alt   = $brand->post_title;
			if ( is_array( $image ) && ! empty( $image['url'] ) ) {
				$url = (string) $image['url'];
				if ( ! empty( $image['alt'] ) ) {
					$alt = (string) $image['alt'];
				}
			} elseif ( is_numeric( $image ) && (int) $image > 0 ) {
				$maybe = wp_get_attachment_image_url( (int) $image, 'large' );
				$url   = is_string( $maybe ) ? $maybe : '';
			}
			if ( '' === $url ) {
				continue;
			}

			$english = '';
			if ( function_exists( 'get_field' ) ) {
				foreach ( array( 'en-brand', 'english_name', 'en_name', 'en-tag' ) as $en_key ) {
					$val = get_field( $en_key, $brand->ID );
					if ( is_scalar( $val ) && '' !== trim( (string) $val ) ) {
						$english = trim( (string) $val );
						break;
					}
				}
			}

			$english_html = '' !== $english
				? '<span class="lk-hero-carousel__english">' . esc_html( $english ) . '</span>'
				: '';

			$slides[] = sprintf(
				'<div class="lk-hero-carousel__slide lk-hero-carousel__slide--brand"><img src="%s" alt="%s" decoding="async"><span class="lk-hero-carousel__caption"><span class="lk-hero-carousel__name">%s</span>%s</span></div>',
				esc_url( $url ),
				esc_attr( $alt ),
				esc_html( $brand->post_title ),
				$english_html
			);
		}

		return $slides;
	}

	/**
	 * Local Lucide SVG for a promise item (needed files only).
	 *
	 * @param array<string, mixed> $item  Promise row.
	 * @param int                  $index Zero-based index when no icon key is stored.
	 */
	private static function promise_icon_html( array $item, int $index ): string {
		$key = isset( $item['icon'] ) ? (string) $item['icon'] : '';
		$map = array(
			'truck'   => 'truck.svg',
			'badge'   => 'badge-check.svg',
			'quote'   => 'file-text.svg',
			'support' => 'headset.svg',
		);
		$file = $map[ $key ] ?? '';
		if ( '' === $file ) {
			$title_map = array(
				'ارسال سریع'       => 'truck.svg',
				'اصالت برند'       => 'badge-check.svg',
				'استعلام سریع'     => 'file-text.svg',
				'پشتیبانی تخصصی'   => 'headset.svg',
			);
			$title = isset( $item['title'] ) ? (string) $item['title'] : '';
			$file  = $title_map[ $title ] ?? '';
		}
		if ( '' === $file ) {
			$files = array( 'truck.svg', 'badge-check.svg', 'file-text.svg', 'headset.svg' );
			$file  = $files[ $index % count( $files ) ];
		}
		return self::icon_html( $file, 'lk-promise-icon', 20 );
	}

	/**
	 * Inline Lucide SVG from assets/icons/.
	 *
	 * @param string $file  Filename.
	 * @param string $class SVG class.
	 * @param int    $size  Pixel size.
	 */
	private static function icon_html( string $file, string $class = 'lk-homepage-icon', int $size = 20 ): string {
		$file = basename( $file );
		if ( ! preg_match( '/^[a-z0-9\-]+\.svg$/i', $file ) ) {
			return '';
		}
		$path = HELLO_ELEMENTOR_CHILD_PATH . 'assets/icons/' . $file;
		if ( ! is_readable( $path ) ) {
			return '';
		}
		$svg = (string) file_get_contents( $path );
		if ( '' === $svg ) {
			return '';
		}
		$size = max( 12, min( 48, $size ) );
		$svg  = preg_replace( '/\s(width|height)="[^"]*"/i', '', $svg, 2 );
		$svg  = preg_replace(
			'/<svg\b/i',
			sprintf(
				'<svg class="%1$s" width="%2$d" height="%2$d" aria-hidden="true" focusable="false"',
				esc_attr( $class ),
				$size
			),
			$svg,
			1
		);
		return is_string( $svg ) ? $svg : '';
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public static function render_promise_bar( array $attributes ): string {
		$items = isset( $attributes['items'] ) && is_array( $attributes['items'] ) ? $attributes['items'] : self::default_promise_items();
		$html  = '';

		$index = 0;
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$title = isset( $item['title'] ) ? (string) $item['title'] : '';
			$text  = isset( $item['text'] ) ? (string) $item['text'] : '';
			if ( '' === $title ) {
				continue;
			}
			$icon = self::promise_icon_html( $item, $index );
			++$index;
			$html .= sprintf(
				'<div class="lk-promise"><div class="lk-promise__icon" aria-hidden="true">%s</div><div><strong>%s</strong><span>%s</span></div></div>',
				$icon, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- local SVG markup.
				esc_html( $title ),
				esc_html( $text )
			);
		}

		if ( '' === $html ) {
			return '';
		}

		return sprintf(
			'<section class="lk-section lk-promise-bar lk-homepage-block container" aria-label="%s">%s</section>',
			esc_attr__( 'مزیت‌های خرید', 'hello-elementor-child' ),
			$html
		);
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public static function render_brand_strip( array $attributes ): string {
		$title       = isset( $attributes['title'] ) ? (string) $attributes['title'] : '';
		$description = isset( $attributes['description'] ) ? (string) $attributes['description'] : '';
		$cta_text    = isset( $attributes['ctaText'] ) ? (string) $attributes['ctaText'] : '';
		$cta_url     = isset( $attributes['ctaUrl'] ) ? self::normalize_url( self::prefer_brands_archive_path( (string) $attributes['ctaUrl'] ) ) : '';
		$brands      = self::resolve_brands( $attributes );
		$autoplay    = isset( $attributes['autoplayMs'] ) ? (int) $attributes['autoplayMs'] : 3000;
		if ( $autoplay < 1000 ) {
			$autoplay = 3000;
		}
		$brand_html  = '';

		foreach ( $brands as $brand ) {
			if ( ! is_array( $brand ) ) {
				continue;
			}
			$image = isset( $brand['image'] ) ? trim( (string) $brand['image'] ) : '';
			if ( '' === $image ) {
				continue;
			}
			$alt  = isset( $brand['alt'] ) ? (string) $brand['alt'] : '';
			$link = isset( $brand['link'] ) ? self::normalize_url( (string) $brand['link'] ) : '';
			$img  = sprintf( '<img src="%s" alt="%s" loading="lazy" decoding="async">', esc_url( $image ), esc_attr( $alt ) );
			$brand_html .= '' !== $link
				? sprintf( '<a class="lk-brand" href="%s">%s</a>', esc_url( $link ), $img )
				: sprintf( '<div class="lk-brand">%s</div>', $img );
		}

		$cta = ( '' !== $cta_text && '' !== $cta_url )
			? sprintf( '<a class="lk-btn lk-btn--secondary" href="%s">%s</a>', esc_url( $cta_url ), esc_html( $cta_text ) )
			: '';

		$prev_icon = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M14.5 6.5 9 12l5.5 5.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
		$next_icon = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9.5 6.5 15 12l-5.5 5.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
		$slider    = sprintf(
			'<div class="lk-brand-strip__bar" data-lk-brand-slider data-autoplay-ms="%6$d"><button type="button" class="lk-brand-strip__arrow" data-lk-brand-dir="-1" aria-label="%1$s">%2$s</button><div class="lk-brand-strip__viewport"><div class="lk-brand-strip__track">%3$s</div></div><button type="button" class="lk-brand-strip__arrow" data-lk-brand-dir="1" aria-label="%4$s">%5$s</button></div>',
			esc_attr__( 'برند قبلی', 'hello-elementor-child' ),
			$prev_icon,
			$brand_html,
			esc_attr__( 'برند بعدی', 'hello-elementor-child' ),
			$next_icon,
			$autoplay
		);

		return sprintf(
			'<section class="lk-section lk-glass-card lk-brand-strip lk-homepage-block container" aria-label="%s"><div class="lk-brand-strip__header"><div><h2>%s</h2>%s</div>%s</div>%s</section>',
			esc_attr__( 'برندها', 'hello-elementor-child' ),
			esc_html( $title ),
			'' !== $description ? sprintf( '<p>%s</p>', esc_html( $description ) ) : '',
			$cta,
			$slider
		);
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public static function render_category_grid( array $attributes ): string {
		$categories = self::resolve_categories( $attributes );
		$grid_html  = '';

		foreach ( $categories as $cat ) {
			if ( ! is_array( $cat ) ) {
				continue;
			}
			$cat_title = isset( $cat['title'] ) ? (string) $cat['title'] : '';
			$subtitle  = isset( $cat['subtitle'] ) ? (string) $cat['subtitle'] : '';
			$image     = isset( $cat['image'] ) ? trim( (string) $cat['image'] ) : '';
			$link      = isset( $cat['link'] ) ? self::normalize_url( (string) $cat['link'] ) : '';
			if ( '' === $cat_title || '' === $link ) {
				continue;
			}
			$style = '' !== $image ? sprintf( ' style="--lk-bg-image:url(\'%s\')"', esc_url( $image ) ) : '';
			$grid_html .= sprintf(
				'<a class="lk-category" href="%s"%s><div class="lk-category__body"><strong>%s</strong><span>%s</span></div></a>',
				esc_url( $link ),
				$style,
				esc_html( $cat_title ),
				esc_html( $subtitle )
			);
		}

		return sprintf(
			'<section class="lk-section lk-categories lk-homepage-block container" aria-label="%s"><div class="lk-categories__viewport"><div class="lk-categories__track">%s</div></div></section>',
			esc_attr__( 'دسته بندی ها', 'hello-elementor-child' ),
			$grid_html
		);
	}

	/**
	 * White category cards: image, Persian name, English name, CAS.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public static function render_cat_cards( array $attributes ): string {
		$limit  = isset( $attributes['limit'] ) ? max( 1, min( 12, (int) $attributes['limit'] ) ) : 6;
		$ids    = isset( $attributes['categoryIds'] ) && is_array( $attributes['categoryIds'] ) ? $attributes['categoryIds'] : array();
		$ids    = array_values( array_filter( array_map( 'intval', $ids ) ) );
		$source = isset( $attributes['source'] ) ? (string) $attributes['source'] : '';
		if ( '' === $source ) {
			$source = array() !== $ids ? 'picked' : 'latest';
		}
		$terms = self::cat_card_terms( $ids, $limit, $source );
		if ( array() === $terms ) {
			return '';
		}

		$cards = '';
		foreach ( $terms as $term ) {
			$cards .= self::render_cat_card( $term );
		}
		if ( '' === $cards ) {
			return '';
		}

		return sprintf(
			'<section class="lk-section lk-glass-card lk-cat-cards lk-homepage-block container" aria-label="%1$s"><div class="lk-section-head lk-cat-cards__head"><h2>%1$s</h2><p>%2$s</p>%3$s</div><div class="lk-cat-cards__grid">%4$s</div></section>',
			esc_html__( 'مواد شیمیایی پرجستجو', 'hello-elementor-child' ),
			esc_html__( 'محبوب‌ترین مواد شیمیایی در بین مشتریان لوک‌آزما', 'hello-elementor-child' ),
			self::cat_card_perks_html(),
			$cards
		);
	}

	/**
	 * @param array<int, int> $ids    Chosen term IDs. Used when source is picked.
	 * @param int             $limit  Maximum cards.
	 * @param string          $source latest, random, or picked.
	 * @return array<int, WP_Term>
	 */
	private static function cat_card_terms( array $ids, int $limit, string $source = 'latest' ): array {
		if ( 'random' !== $source && array() !== $ids ) {
			$terms = array();
			foreach ( $ids as $id ) {
				$term = get_term( $id, 'product_cat' );
				if ( $term instanceof WP_Term && ! is_wp_error( $term ) ) {
					$terms[] = $term;
				}
				if ( count( $terms ) >= $limit ) {
					break;
				}
			}
			if ( array() !== $terms ) {
				return $terms;
			}
		}

		$found = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'number'     => 60,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);
		if ( is_wp_error( $found ) || ! is_array( $found ) ) {
			return array();
		}

		$terms = array();
		foreach ( $found as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}
			$cas = self::term_text_field( (int) $term->term_id, 'cas_no-cat' );
			if ( '' === $cas || 0 === strcasecmp( $cas, 'N/A' ) ) {
				continue;
			}
			$terms[] = $term;
		}

		if ( 'random' === $source ) {
			shuffle( $terms );
		}

		return array_slice( $terms, 0, $limit );
	}

	/**
	 * Trust row under the category-card heading.
	 */
	private static function cat_card_perks_html(): string {
		$items = array(
			array( 'badge-check.svg', __( 'تأمین از شرکت‌های معتبر', 'hello-elementor-child' ) ),
			array( 'circle-dollar-sign.svg', __( 'قیمت رقابتی', 'hello-elementor-child' ) ),
			array( 'layers.svg', __( 'تنوع گرید و بسته‌بندی', 'hello-elementor-child' ) ),
			array( 'truck.svg', __( 'ارسال سریع به سراسر کشور', 'hello-elementor-child' ) ),
			array( 'headset.svg', __( 'مشاوره تخصصی رایگان', 'hello-elementor-child' ) ),
		);
		$html = '';
		foreach ( $items as $item ) {
			$html .= sprintf(
				'<li>%s<span>%s</span></li>',
				self::icon_html( $item[0], 'lk-cat-cards__perk-icon', 18 ),
				esc_html( $item[1] )
			);
		}
		return '<ul class="lk-cat-cards__perks">' . $html . '</ul>';
	}

	/**
	 * @param WP_Term $term Product category.
	 */
	private static function render_cat_card( WP_Term $term ): string {
		$term_id = (int) $term->term_id;
		$link    = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			return '';
		}

		$english = self::term_text_field( $term_id, 'en-cat' );
		$cas     = self::term_text_field( $term_id, 'cas_no-cat' );
		if ( 0 === strcasecmp( $cas, 'N/A' ) ) {
			$cas = '';
		}

		$thumb_id = (int) get_term_meta( $term_id, 'thumbnail_id', true );
		$image    = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : '';
		$alt      = $thumb_id ? (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) : '';
		if ( '' === $alt ) {
			$alt = $term->name;
		}
		$media = $image
			? sprintf( '<img src="%s" alt="%s" loading="lazy" decoding="async">', esc_url( $image ), esc_attr( $alt ) )
			: '';

		$en_html = '' !== $english
			? sprintf( '<span class="lk-cat-card__en">%s</span>', esc_html( $english ) )
			: '';
		$cas_html = '' !== $cas
			? sprintf(
				'<span class="lk-cat-card__cas"><span class="lk-cat-card__cas-label">%s</span> <span class="lk-cat-card__cas-value">%s</span></span>',
				esc_html__( 'CAS', 'hello-elementor-child' ),
				esc_html( $cas )
			)
			: '';

		return sprintf(
			'<a class="lk-cat-card" href="%s"><span class="lk-cat-card__media">%s</span><span class="lk-cat-card__body"><strong class="lk-cat-card__name">%s</strong>%s%s</span></a>',
			esc_url( $link ),
			$media,
			esc_html( $term->name ),
			$en_html,
			$cas_html
		);
	}

	/**
	 * @param int    $term_id Term ID.
	 * @param string $key     ACF field name.
	 */
	private static function term_text_field( int $term_id, string $key ): string {
		$acf_id = 'product_cat_' . $term_id;
		$value  = function_exists( 'get_field' ) ? get_field( $key, $acf_id ) : get_term_meta( $term_id, $key, true );
		if ( is_array( $value ) ) {
			return '';
		}
		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}

	/**
	 * Merge saved category cards with defaults so new items (e.g. مواد اولیه) appear on older pages.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<int, array<string, string>>
	 */
	private static function resolve_categories( array $attributes ): array {
		$saved = isset( $attributes['categories'] ) && is_array( $attributes['categories'] ) ? $attributes['categories'] : array();
		if ( empty( $saved ) ) {
			return self::default_categories();
		}

		$saved_by_title = array();
		foreach ( $saved as $cat ) {
			if ( is_array( $cat ) && ! empty( $cat['title'] ) ) {
				$saved_by_title[ (string) $cat['title'] ] = $cat;
			}
		}

		$defaults   = self::default_categories();
		$merged     = array();
		$seen_titles = array();

		foreach ( $defaults as $default_cat ) {
			$title = (string) $default_cat['title'];
			$seen_titles[ $title ] = true;
			$merged[]              = isset( $saved_by_title[ $title ] )
				? array_merge( $default_cat, $saved_by_title[ $title ] )
				: $default_cat;
		}

		foreach ( $saved as $cat ) {
			if ( ! is_array( $cat ) || empty( $cat['title'] ) ) {
				continue;
			}
			$title = (string) $cat['title'];
			if ( ! isset( $seen_titles[ $title ] ) ) {
				$merged[] = $cat;
			}
		}

		return $merged;
	}

	/**
	 * @param \WC_Product $product Product.
	 * @return string
	 */
	private static function render_product_card( WC_Product $product ): string {
		$permalink = get_permalink( $product->get_id() );
		$title     = $product->get_name();
		$image_id  = $product->get_image_id();
		$image     = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : wc_placeholder_img_src( 'medium' );
		$price     = $product->get_price_html();
		if ( '' === trim( wp_strip_all_tags( $price ) ) ) {
			$price = '<strong>' . esc_html__( 'استعلام', 'hello-elementor-child' ) . '</strong><span>' . esc_html__( 'قیمت متغیر', 'hello-elementor-child' ) . '</span>';
		} else {
			$price = '<strong>' . wp_kses_post( $price ) . '</strong>';
		}

		return sprintf(
			'<article class="lk-product-card"><div class="lk-product-card__photo"><a href="%1$s"><img src="%2$s" alt="%3$s" loading="lazy" decoding="async"></a></div><h3><a href="%1$s">%4$s</a></h3><div class="lk-product-card__footer"><div class="lk-price">%5$s</div><a class="lk-product-card__cta" href="%1$s">%6$s</a></div></article>',
			esc_url( $permalink ),
			esc_url( (string) $image ),
			esc_attr( $title ),
			esc_html( $title ),
			$price,
			esc_html__( 'مشاهده', 'hello-elementor-child' )
		);
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public static function render_product_grid( array $attributes ): string {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return '';
		}

		$title       = isset( $attributes['title'] ) ? (string) $attributes['title'] : '';
		$description = isset( $attributes['description'] ) ? (string) $attributes['description'] : '';
		$cta_text    = isset( $attributes['ctaText'] ) ? (string) $attributes['ctaText'] : '';
		$cta_url     = isset( $attributes['ctaUrl'] ) ? self::normalize_url( self::prefer_shop_path( (string) $attributes['ctaUrl'] ) ) : '';
		$limit       = isset( $attributes['limit'] ) ? max( 1, min( 12, (int) $attributes['limit'] ) ) : 5;
		$source      = isset( $attributes['source'] ) ? (string) $attributes['source'] : 'latest';
		if ( ! in_array( $source, array( 'latest', 'random', 'picked' ), true ) ) {
			$source = 'latest';
		}
		$product_ids = isset( $attributes['productIds'] ) && is_array( $attributes['productIds'] ) ? array_map( 'intval', $attributes['productIds'] ) : array();
		$product_ids = array_values( array_filter( $product_ids ) );

		$cards = '';
		if ( 'picked' === $source && ! empty( $product_ids ) ) {
			foreach ( array_slice( $product_ids, 0, $limit ) as $product_id ) {
				$product = wc_get_product( $product_id );
				if ( $product instanceof WC_Product ) {
					$cards .= Hello_Elementor_Child_Archive_Product_Filter::render_product_card_html( $product, 'shop' );
				}
			}
		} else {
			$query = new WP_Query(
				array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'posts_per_page' => $limit,
					'orderby'        => 'random' === $source ? 'rand' : 'date',
					'order'          => 'DESC',
				)
			);
			while ( $query->have_posts() ) {
				$query->the_post();
				$product = wc_get_product( get_the_ID() );
				if ( $product instanceof WC_Product ) {
					$cards .= Hello_Elementor_Child_Archive_Product_Filter::render_product_card_html( $product, 'shop' );
				}
			}
			wp_reset_postdata();
		}

		if ( '' === $cards ) {
			return '';
		}

		$cta = ( '' !== $cta_text && '' !== $cta_url )
			? sprintf( '<a class="lk-btn lk-btn--secondary" href="%s">%s</a>', esc_url( $cta_url ), esc_html( $cta_text ) )
			: '';

		return sprintf(
			'<section class="lk-section lk-glass-card lk-products-wrap lk-homepage-block container" aria-label="%s"><div class="lk-section-head"><div><h2>%s</h2>%s</div>%s</div><div class="lk-product-grid">%s</div></section>',
			esc_attr( $title ),
			esc_html( $title ),
			'' !== $description ? sprintf( '<p>%s</p>', esc_html( $description ) ) : '',
			$cta,
			$cards
		);
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public static function render_banner_row( array $attributes ): string {
		$banners = self::resolve_banners( $attributes );
		$html    = '';

		foreach ( $banners as $banner ) {
			if ( ! is_array( $banner ) ) {
				continue;
			}
			$image = isset( $banner['image'] ) ? trim( (string) $banner['image'] ) : '';
			$label = isset( $banner['label'] ) ? (string) $banner['label'] : '';
			$title = isset( $banner['title'] ) ? (string) $banner['title'] : '';
			$text  = isset( $banner['text'] ) ? (string) $banner['text'] : '';
			$link  = isset( $banner['link'] ) ? self::normalize_url( (string) $banner['link'] ) : '';
			if ( '' === $image || '' === $link ) {
				continue;
			}
			$style = sprintf( ' style="--lk-banner-image:url(\'%s\')"', esc_url( $image ) );
			$aria_label = '' !== $title ? $title : $label;
			$html      .= sprintf(
				'<a class="lk-banner" href="%s"%s aria-label="%s"></a>',
				esc_url( $link ),
				$style,
				esc_attr( $aria_label )
			);
		}

		if ( '' === $html ) {
			return '';
		}

		return sprintf(
			'<section class="lk-section lk-banners lk-homepage-block container" aria-label="%s">%s</section>',
			esc_attr__( 'بنرها', 'hello-elementor-child' ),
			$html
		);
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public static function render_article_grid( array $attributes ): string {
		$title       = isset( $attributes['title'] ) ? (string) $attributes['title'] : '';
		$description = isset( $attributes['description'] ) ? (string) $attributes['description'] : '';
		$cta_text    = isset( $attributes['ctaText'] ) ? (string) $attributes['ctaText'] : '';
		$cta_url     = isset( $attributes['ctaUrl'] ) ? self::normalize_url( (string) $attributes['ctaUrl'] ) : '';
		$limit       = isset( $attributes['limit'] ) ? max( 1, min( 10, (int) $attributes['limit'] ) ) : 3;
		$source      = isset( $attributes['source'] ) ? (string) $attributes['source'] : 'latest';
		if ( ! in_array( $source, array( 'latest', 'random', 'picked' ), true ) ) {
			$source = 'latest';
		}
		$post_ids = isset( $attributes['postIds'] ) && is_array( $attributes['postIds'] ) ? array_map( 'intval', $attributes['postIds'] ) : array();
		$post_ids = array_values( array_filter( $post_ids ) );

		if ( 'picked' === $source && ! empty( $post_ids ) ) {
			$ids = array_slice( $post_ids, 0, $limit );
		} else {
			$found = get_posts(
				array(
					'post_type'      => 'post',
					'post_status'    => 'publish',
					'posts_per_page' => $limit,
					'orderby'        => 'random' === $source ? 'rand' : 'date',
					'order'          => 'DESC',
					'fields'         => 'ids',
				)
			);
			$ids = is_array( $found ) ? array_map( 'intval', $found ) : array();
		}

		$cards = '';
		foreach ( $ids as $post_id ) {
			$cards .= self::render_homepage_article_card( $post_id );
		}

		if ( '' === $cards ) {
			return '';
		}

		$cta = ( '' !== $cta_text && '' !== $cta_url )
			? sprintf( '<a class="lk-btn lk-btn--secondary" href="%s">%s</a>', esc_url( $cta_url ), esc_html( $cta_text ) )
			: '';

		return sprintf(
			'<section class="lk-section lk-glass-card lk-articles lk-homepage-block container" aria-label="%s"><div class="lk-section-head"><div><h2>%s</h2>%s</div>%s</div><div class="lk-article-grid">%s</div></section>',
			esc_attr( $title ),
			esc_html( $title ),
			'' !== $description ? sprintf( '<p>%s</p>', esc_html( $description ) ) : '',
			$cta,
			$cards
		);
	}

	/**
	 * One homepage article card.
	 *
	 * @param int $post_id Post ID.
	 */
	private static function render_homepage_article_card( int $post_id ): string {
		$permalink = get_permalink( $post_id );
		if ( ! is_string( $permalink ) || '' === $permalink ) {
			return '';
		}
		$title_p = get_the_title( $post_id );
		$excerpt = wp_trim_words( get_the_excerpt( $post_id ), 22, '…' );
		$date    = get_the_date( '', $post_id );
		$image   = get_the_post_thumbnail_url( $post_id, 'medium_large' );
		if ( ! $image ) {
			$image = 'https://lookazma.com/wp-content/uploads/2026/02/p20260129145359f4fc5.webp';
		}
		$icon = class_exists( 'Hello_Elementor_Child_Light_Article_Template' )
			? Hello_Elementor_Child_Light_Article_Template::icon_html( 'calendar.svg', 'lk-article-card__date-icon', 14 )
			: '';

		return sprintf(
			'<article class="lk-article-card"><a class="lk-article-card__media" href="%1$s"><img src="%2$s" alt="%3$s" loading="lazy" width="600" height="173" decoding="async"></a><div class="lk-article-card__body"><p class="lk-article-card__date">%4$s<span>%5$s</span></p><h3 class="lk-article-card__title"><a href="%1$s">%3$s</a></h3><p class="lk-article-card__excerpt">%7$s</p><a class="lk-article-card__more" href="%1$s">%6$s</a></div></article>',
			esc_url( $permalink ),
			esc_url( $image ),
			esc_html( $title_p ),
			$icon,
			esc_html( (string) $date ),
			esc_html__( 'ادامه مطلب', 'hello-elementor-child' ),
			esc_html( $excerpt )
		);
	}

	/**
	 * Default SEO / about-copy HTML (Elementor widget 01a6705).
	 */
	public static function get_default_seo_html(): string {
		$path = HELLO_ELEMENTOR_CHILD_PATH . 'assets/html/homepage-seo-box.html';
		if ( ! file_exists( $path ) ) {
			return '';
		}
		$html = (string) file_get_contents( $path );
		return trim( $html );
	}

	/**
	 * Why Lookazma section. Only the story photo is editable.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public static function render_why( array $attributes ): string {
		$image = isset( $attributes['image'] ) ? trim( (string) $attributes['image'] ) : '';
		if ( '' === $image ) {
			$image = content_url( 'uploads/2023/08/woman-lab2.webp' );
		}

		$features = array(
			array( 'flask', __( 'تنوع بالا', 'hello-elementor-child' ), __( 'ارائه طیف گسترده‌ای از مواد شیمیایی برای تمامی نیازها', 'hello-elementor-child' ) ),
			array( 'quality', __( 'کیفیت تضمین‌شده', 'hello-elementor-child' ), __( 'تمامی محصولات همراه با برگه آنالیز (COA) ارائه می‌شوند', 'hello-elementor-child' ) ),
			array( 'price', __( 'قیمت‌های رقابتی', 'hello-elementor-child' ), __( 'بهترین قیمت‌ها با شفافیت کامل بدون هزینه پنهان', 'hello-elementor-child' ) ),
			array( 'consult', __( 'مشاوره تخصصی', 'hello-elementor-child' ), __( 'تیم کارشناسان مجرب در کنار شما در تمام مراحل خرید', 'hello-elementor-child' ) ),
			array( 'truck', __( 'ارسال سریع و مطمئن', 'hello-elementor-child' ), __( 'ارسال به سراسر کشور در کوتاه‌ترین زمان با بسته‌بندی ایمن', 'hello-elementor-child' ) ),
		);
		$checks = array(
			__( 'تجربه همکاری با صنایع بزرگ و شرکت‌های تحقیقاتی', 'hello-elementor-child' ),
			__( 'تضمین کیفیت، اصالت و تطابق با استانداردهای جهانی', 'hello-elementor-child' ),
			__( 'پشتیبانی حرفه‌ای در انتخاب و استفاده از مواد', 'hello-elementor-child' ),
			__( 'راهکارهای تخصصی متناسب با نیاز صنایع مختلف', 'hello-elementor-child' ),
		);
		$commitments = array(
			array( 'quality', __( 'کیفیت تضمین‌شده', 'hello-elementor-child' ), __( 'تمامی محصولات با گواهی‌نامه‌های معتبر ارائه می‌شوند.', 'hello-elementor-child' ) ),
			array( 'price', __( 'شفافیت در قیمت‌گذاری', 'hello-elementor-child' ), __( 'هیچ‌گونه هزینه پنهانی در خریدهای مشتریان وجود ندارد.', 'hello-elementor-child' ) ),
			array( 'consult', __( 'پشتیبانی تخصصی', 'hello-elementor-child' ), __( 'تیم پشتیبانی آماده پاسخگویی به تمامی سوالات شماست.', 'hello-elementor-child' ) ),
			array( 'truck', __( 'سرعت و دقت در ارسال', 'hello-elementor-child' ), __( 'تمامی سفارشات با نهایت دقت و در سریع‌ترین زمان ارسال می‌شوند.', 'hello-elementor-child' ) ),
		);

		$feature_html = '';
		foreach ( $features as $item ) {
			$feature_html .= sprintf(
				'<article class="lk-why__feature"><div class="lk-why__feature-icon" aria-hidden="true">%1$s</div><h3>%2$s</h3><p>%3$s</p></article>',
				self::why_icon( $item[0], 22 ),
				esc_html( $item[1] ),
				esc_html( $item[2] )
			);
		}

		$check_html = '';
		foreach ( $checks as $line ) {
			$check_html .= '<li>' . esc_html( $line ) . '</li>';
		}

		$commit_html = '';
		foreach ( $commitments as $item ) {
			$commit_html .= sprintf(
				'<li class="lk-why__commit-item"><div class="lk-why__commit-icon" aria-hidden="true">%1$s</div><div><h3>%2$s</h3><p>%3$s</p></div></li>',
				self::why_icon( $item[0], 20 ),
				esc_html( $item[1] ),
				esc_html( $item[2] )
			);
		}

		$arrow = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M14.5 6.5 9 12l5.5 5.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';

		return sprintf(
			'<section class="lk-section lk-why lk-homepage-block container" aria-label="%1$s"><h2 class="lk-why__title">%1$s</h2><div class="lk-why__features">%2$s</div><div class="lk-why__split"><article class="lk-why__story"><figure class="lk-why__media"><img src="%3$s" alt="%4$s" loading="lazy" decoding="async"></figure><div class="lk-why__copy"><h3>%5$s</h3><p>%6$s</p><ul class="lk-why__checks">%7$s</ul><a class="lk-why__more" href="%8$s"><span>%9$s</span>%10$s</a></div></article><aside class="lk-why__commit"><h3>%11$s</h3><ul>%12$s</ul></aside></div></section>',
			esc_html__( 'چرا لوک آزما؟', 'hello-elementor-child' ),
			$feature_html,
			esc_url( $image ),
			esc_attr__( 'ساختمان لوک آزما', 'hello-elementor-child' ),
			esc_html__( 'لوک آزما، تأمین هوشمند برای صنعتی پیشرو', 'hello-elementor-child' ),
			esc_html__( 'با شبکه گسترده تأمین و همکاری با برندهای معتبر بین‌المللی، هر نوع ماده شیمیایی موردنیاز شما را با بالاترین استانداردهای جهانی در کمترین زمان ممکن تأمین می‌کنیم.', 'hello-elementor-child' ),
			$check_html,
			esc_url( self::why_about_url() ),
			esc_html__( 'بیشتر درباره ما', 'hello-elementor-child' ),
			$arrow,
			esc_html__( 'تعهد ما، رضایت شماست', 'hello-elementor-child' ),
			$commit_html
		);
	}

	/**
	 * About page URL for the why-block button.
	 */
	private static function why_about_url(): string {
		$ids = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => '_wp_page_template',
						'value'   => array( 'page-templates/light-about.php', 'light-about.php' ),
						'compare' => 'IN',
					),
				),
			)
		);
		if ( is_array( $ids ) && isset( $ids[0] ) ) {
			$url = get_permalink( (int) $ids[0] );
			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}
		return home_url( '/about/' );
	}

	/**
	 * Lucide icon used by the why block.
	 */
	private static function why_icon( string $key, int $size ): string {
		$map  = array(
			'truck'   => 'truck.svg',
			'consult' => 'headset.svg',
			'price'   => 'circle-dollar-sign.svg',
			'quality' => 'shield-check.svg',
			'flask'   => 'flask-conical.svg',
		);
		$file = $map[ $key ] ?? 'shield-check.svg';
		if ( class_exists( 'Hello_Elementor_Child_Light_About_Template' ) ) {
			return Hello_Elementor_Child_Light_About_Template::icon_html( $file, 'lk-why__svg', $size );
		}
		return '';
	}

	/**
	 * Scrollable SEO text box at the bottom of the homepage.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public static function render_seo_box( array $attributes ): string {
		unset( $attributes );
		return '';
	}
}
