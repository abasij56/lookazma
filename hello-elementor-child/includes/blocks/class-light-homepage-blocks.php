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
				array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
				(string) filemtime( $editor_js ),
				true
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
			'lookazma/home-product-grid',
			'lookazma/home-banner-row',
			'lookazma/home-article-grid',
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
	public static function register_blocks(): void {
		$blocks = array(
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
					'ctaUrl'      => array( 'type' => 'string', 'default' => '/فروشگاه/' ),
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
					'limit'       => array(
						'type'    => 'number',
						'default' => 5,
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
				),
				'render_callback' => array( __CLASS__, 'render_article_grid' ),
			),
			'home-seo-box'       => array(
				'title'           => __( 'باکس متن اسکرول‌شو', 'hello-elementor-child' ),
				'description'     => __( 'باکس متن پایین صفحه با اسکرول داخلی (مثل المنتور).', 'hello-elementor-child' ),
				'icon'            => 'editor-justify',
				'attributes'      => array(
					'content' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'render_callback' => array( __CLASS__, 'render_seo_box' ),
			),
		);

		foreach ( $blocks as $slug => $args ) {
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

<!-- wp:lookazma/home-product-grid {"title":"محصولات مواد شیمیایی","limit":5} /-->

<!-- wp:lookazma/home-banner-row {"banners":{$banners}} /-->

<!-- wp:lookazma/home-product-grid {"title":"فروشگاه مواد شیمیایی","limit":5} /-->

<!-- wp:lookazma/home-article-grid {"limit":3} /-->

<!-- wp:lookazma/home-seo-box /-->
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
			array( 'title' => 'ارسال سریع', 'text' => 'پوشش ارسال به سراسر کشور' ),
			array( 'title' => 'اصالت برند', 'text' => 'ارائه محصولات از تامین‌کنندگان معتبر' ),
			array( 'title' => 'استعلام سریع', 'text' => 'مناسب خریدهای حساس به موجودی و قیمت' ),
			array( 'title' => 'پشتیبانی تخصصی', 'text' => 'برای انتخاب گرید و کاربرد مناسب' ),
		);
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	private static function default_brands(): array {
		return array(
			array( 'image' => 'https://lookazma.com/wp-content/uploads/2023/10/download.jpg', 'alt' => 'دکتر مجللی', 'link' => '/product-tag/dr-mojallai/' ),
			array( 'image' => 'https://lookazma.com/wp-content/uploads/2023/10/noetrun-logo.webp', 'alt' => 'نوترون', 'link' => '/product-tag/neutron/' ),
			array( 'image' => 'https://lookazma.com/wp-content/uploads/2024/07/Merck_Logo.webp', 'alt' => 'مرک', 'link' => '/product-tag/merck/' ),
			array( 'image' => 'https://lookazma.com/wp-content/uploads/2023/12/Untitled.png', 'alt' => 'ویستاکم', 'link' => '/product-tag/vistachem/' ),
			array( 'image' => 'https://lookazma.com/wp-content/uploads/2024/07/Tat-chem-Logo-Exp.png', 'alt' => 'امرتات', 'link' => '/product-tag/ameretat/' ),
			array( 'image' => 'https://lookazma.com/wp-content/uploads/2025/11/armansina-logo-1.png-e1765191395192.webp', 'alt' => 'آرمان سینا', 'link' => '/product-tag/شرکت-آرمان-سینا/' ),
			array( 'image' => 'https://lookazma.com/wp-content/uploads/2025/12/logo.png', 'alt' => 'سینا', 'link' => '/product-tag/sina/' ),
			array( 'image' => 'https://lookazma.com/wp-content/uploads/2026/05/قطران-شیمی-تجهیز-23506-e1783410412564.png', 'alt' => 'قطران شیمی', 'link' => '/product-tag/قطران-شیمی/' ),
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
	 * Map legacy /shop/ paths to the Persian shop slug.
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
		$path = untrailingslashit( $path );
		if ( '/shop' === $path || 'shop' === ltrim( $path, '/' ) ) {
			return '/فروشگاه/';
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
				$link    = isset( $brand['link'] ) ? trim( (string) $brand['link'] ) : '';
				if ( '' === $link && ! empty( $default['link'] ) ) {
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
		$slides     = isset( $attributes['slides'] ) && is_array( $attributes['slides'] ) ? $attributes['slides'] : self::default_hero_slides();
		$autoplay   = isset( $attributes['autoplayMs'] ) ? max( 2000, (int) $attributes['autoplayMs'] ) : 5000;
		$slide_html = '';

		foreach ( $slides as $index => $slide ) {
			if ( ! is_array( $slide ) ) {
				continue;
			}
			$url = isset( $slide['url'] ) ? trim( (string) $slide['url'] ) : '';
			if ( '' === $url ) {
				continue;
			}
			$alt    = isset( $slide['alt'] ) ? (string) $slide['alt'] : '';
			$active = 0 === (int) $index ? ' is-active' : '';
			$slide_html .= sprintf(
				'<div class="lk-hero-carousel__slide%s"><img src="%s" alt="%s" loading="%s" decoding="async"></div>',
				esc_attr( $active ),
				esc_url( $url ),
				esc_attr( $alt ),
				0 === (int) $index ? 'eager' : 'lazy'
			);
		}

		if ( '' === $slide_html ) {
			return '';
		}

		$dots = '';
		for ( $i = 0; $i < count( $slides ); $i++ ) {
			$dots .= sprintf(
				'<button type="button" class="lk-hero-carousel__dot%s" aria-label="%s" aria-selected="%s"></button>',
				0 === $i ? ' is-active' : '',
				esc_attr( sprintf( __( 'اسلاید %d', 'hello-elementor-child' ), $i + 1 ) ),
				0 === $i ? 'true' : 'false'
			);
		}

		return sprintf(
			'<section class="lk-hero-carousel lk-homepage-block" aria-label="%s" data-lk-hero-carousel data-autoplay-ms="%d"><div class="lk-hero-carousel__viewport">%s</div><div class="lk-hero-carousel__dots" role="tablist" aria-label="%s">%s</div></section>',
			esc_attr__( 'اسلایدر اصلی', 'hello-elementor-child' ),
			(int) $autoplay,
			$slide_html,
			esc_attr__( 'انتخاب اسلاید', 'hello-elementor-child' ),
			$dots
		);
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public static function render_promise_bar( array $attributes ): string {
		$items = isset( $attributes['items'] ) && is_array( $attributes['items'] ) ? $attributes['items'] : self::default_promise_items();
		$html  = '';

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$title = isset( $item['title'] ) ? (string) $item['title'] : '';
			$text  = isset( $item['text'] ) ? (string) $item['text'] : '';
			if ( '' === $title ) {
				continue;
			}
			$html .= sprintf(
				'<div class="lk-promise"><div class="lk-promise__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3l8 3v6c0 5-3.4 8.4-8 9-4.6-.6-8-4-8-9V6l8-3z"></path></svg></div><div><strong>%s</strong><span>%s</span></div></div>',
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
		$cta_url     = isset( $attributes['ctaUrl'] ) ? self::normalize_url( self::prefer_shop_path( (string) $attributes['ctaUrl'] ) ) : '';
		$brands      = self::resolve_brands( $attributes );
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

		return sprintf(
			'<section class="lk-section lk-glass-card lk-brand-strip lk-homepage-block container" aria-label="%s"><div class="lk-brand-strip__header"><div><h2>%s</h2>%s</div>%s</div><div class="lk-brand-strip__track">%s</div></section>',
			esc_attr__( 'برندها', 'hello-elementor-child' ),
			esc_html( $title ),
			'' !== $description ? sprintf( '<p>%s</p>', esc_html( $description ) ) : '',
			$cta,
			$brand_html
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
		$product_ids = isset( $attributes['productIds'] ) && is_array( $attributes['productIds'] ) ? array_map( 'intval', $attributes['productIds'] ) : array();
		$product_ids = array_values( array_filter( $product_ids ) );

		$cards = '';
		if ( ! empty( $product_ids ) ) {
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
					'orderby'        => 'date',
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
		$limit       = isset( $attributes['limit'] ) ? max( 1, min( 3, (int) $attributes['limit'] ) ) : 3;

		$query = new WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$cards = '';
		while ( $query->have_posts() ) {
			$query->the_post();
			$post_id   = get_the_ID();
			$permalink = get_permalink( $post_id );
			$title_p   = get_the_title();
			$excerpt   = wp_trim_words( get_the_excerpt(), 18, '…' );
			$date      = get_the_date();
			$image     = get_the_post_thumbnail_url( $post_id, 'medium_large' );
			if ( ! $image ) {
				$image = 'https://lookazma.com/wp-content/uploads/2026/02/p20260129145359f4fc5.webp';
			}
			$cards .= sprintf(
				'<article class="lk-article-card"><div class="lk-article-card__media"><a href="%1$s"><img src="%2$s" alt="%3$s" loading="lazy" decoding="async"></a></div><div class="lk-article-card__body"><div class="lk-article-card__meta"><span>%4$s</span></div><h3><a href="%1$s">%3$s</a></h3><p>%5$s</p><a class="lk-article-card__link" href="%1$s">%6$s</a></div></article>',
				esc_url( $permalink ),
				esc_url( $image ),
				esc_html( $title_p ),
				esc_html( $date ),
				esc_html( $excerpt ),
				esc_html__( 'ادامه مطلب', 'hello-elementor-child' )
			);
		}
		wp_reset_postdata();

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
	 * Scrollable SEO text box at the bottom of the homepage.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public static function render_seo_box( array $attributes ): string {
		$content = isset( $attributes['content'] ) ? trim( (string) $attributes['content'] ) : '';
		if ( '' === $content ) {
			$content = self::get_default_seo_html();
		}
		if ( '' === $content ) {
			return '';
		}

		return sprintf(
			'<section class="lk-section lk-seo-box-section lk-homepage-block container" aria-label="%1$s"><div class="lk-seo-box">%2$s</div></section>',
			esc_attr__( 'درباره لوک آزما', 'hello-elementor-child' ),
			wp_kses_post( $content )
		);
	}
}
