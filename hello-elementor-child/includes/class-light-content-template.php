<?php
/**
 * Light content template — privacy & shipping pages (no toggles).
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static content pages with shared Lookazma header/footer.
 */
final class Hello_Elementor_Child_Light_Content_Template {

	public const TEMPLATE_FILE = 'page-templates/light-content.php';

	/**
	 * Page slugs that auto-use this template.
	 *
	 * @var array<int, string>
	 */
	private const AUTO_SLUGS = array( 'privacy', 'sending-goods' );

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_filter( 'theme_page_templates', array( __CLASS__, 'register_page_template' ), 20, 4 );
		add_filter( 'timber/locations', array( __CLASS__, 'add_timber_locations' ) );

		add_action( 'template_redirect', array( __CLASS__, 'unhook_elementor_chrome' ), -6 );
		add_action( 'template_redirect', array( __CLASS__, 'force_template' ), -1 );
		add_filter( 'template_include', array( __CLASS__, 'maybe_use_template' ), PHP_INT_MAX );
		add_filter( 'elementor/theme/need_override_location', array( __CLASS__, 'disable_elementor_locations' ), PHP_INT_MAX, 2 );
		add_filter( 'elementor/theme/get_location_templates', array( __CLASS__, 'remove_popup_templates' ), PHP_INT_MAX, 2 );
		add_filter( 'elementor/theme/get_location_templates/popup', array( __CLASS__, 'deny_popup_templates' ), PHP_INT_MAX );
		add_filter( 'elementor/frontend/builder_content_data', array( __CLASS__, 'empty_popup_builder_data' ), PHP_INT_MAX, 2 );
		add_action( 'wp_footer', array( __CLASS__, 'unhook_elementor_popups' ), 0 );
		add_filter( 'jet-theme-core/template-include', array( __CLASS__, 'disable_jet_theme_core_template' ), PHP_INT_MAX );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'dequeue_listing_assets' ), 100 );
		add_action( 'wp_print_styles', array( __CLASS__, 'dequeue_listing_assets' ), 100 );
		add_action( 'wp_print_scripts', array( __CLASS__, 'dequeue_listing_assets' ), 100 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
	}

	/**
	 * @param array<string, string> $templates Templates.
	 * @return array<string, string>
	 */
	public static function register_page_template( $templates, $theme = null, $post = null, $post_type = '' ): array {
		if ( ! is_array( $templates ) ) {
			$templates = array();
		}
		if ( '' !== $post_type && 'page' !== $post_type ) {
			return $templates;
		}
		$templates[ self::TEMPLATE_FILE ] = __( 'Light content template', 'hello-elementor-child' );
		return $templates;
	}

	/**
	 * @param array<int, string> $locations Timber paths.
	 * @return array<int, string>
	 */
	public static function add_timber_locations( array $locations ): array {
		$path = HELLO_ELEMENTOR_CHILD_PATH . 'views';
		if ( ! in_array( $path, $locations, true ) ) {
			array_unshift( $locations, $path );
		}
		return $locations;
	}

	/**
	 * @param int $page_id Page ID.
	 */
	public static function page_uses_template( int $page_id ): bool {
		if ( $page_id <= 0 ) {
			return false;
		}
		$slug = (string) get_page_template_slug( $page_id );
		return self::TEMPLATE_FILE === $slug || 'light-content.php' === $slug;
	}

	/**
	 * Current page ID for this request.
	 */
	public static function get_context_page_id(): int {
		$object = get_queried_object();
		if ( $object instanceof WP_Post && 'page' === $object->post_type ) {
			return (int) $object->ID;
		}
		if ( is_page() ) {
			return (int) get_queried_object_id();
		}
		return 0;
	}

	/**
	 * Page post_name when available.
	 */
	public static function get_page_slug(): string {
		$page_id = self::get_context_page_id();
		if ( $page_id <= 0 ) {
			return '';
		}
		$post = get_post( $page_id );
		return ( $post instanceof WP_Post ) ? (string) $post->post_name : '';
	}

	/**
	 * Whether this page is a target content page.
	 */
	public static function is_target_page( int $page_id ): bool {
		if ( $page_id <= 0 ) {
			return false;
		}
		if ( self::page_uses_template( $page_id ) ) {
			return true;
		}
		$post = get_post( $page_id );
		if ( ! ( $post instanceof WP_Post ) || 'page' !== $post->post_type ) {
			return false;
		}
		return in_array( (string) $post->post_name, self::AUTO_SLUGS, true );
	}

	/**
	 * Whether this template owns the current request.
	 */
	public static function is_enabled(): bool {
		if ( wp_doing_cron() || wp_doing_ajax() ) {
			return false;
		}
		if ( is_admin() && ! wp_doing_ajax() ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		return self::is_target_page( self::get_context_page_id() );
	}

	/**
	 * PHP entry path.
	 */
	public static function get_template_path(): string {
		return HELLO_ELEMENTOR_CHILD_PATH . 'page-light-content.php';
	}

	/**
	 * Hard takeover before Elementor page templates.
	 */
	public static function force_template(): void {
		if ( ! self::is_enabled() ) {
			return;
		}
		$path = self::get_template_path();
		if ( ! file_exists( $path ) ) {
			return;
		}
		self::unhook_elementor_chrome();
		status_header( 200 );
		include $path;
		exit;
	}

	/**
	 * @param string $template Current template.
	 */
	public static function maybe_use_template( string $template ): string {
		if ( ! self::is_enabled() ) {
			return $template;
		}
		$path = self::get_template_path();
		return file_exists( $path ) ? $path : $template;
	}

	/**
	 * @param bool   $need_override Whether override is needed.
	 * @param string $location      Location name.
	 */
	public static function disable_elementor_locations( bool $need_override, string $location ): bool {
		if ( ! self::is_enabled() ) {
			return $need_override;
		}
		if ( in_array( $location, array( 'header', 'footer', 'single', 'archive', 'popup' ), true ) ) {
			return false;
		}
		return $need_override;
	}

	/**
	 * @param mixed $templates Location templates.
	 * @param mixed $arg       Location slug or args.
	 * @return mixed
	 */
	public static function remove_popup_templates( $templates, $arg = null ) {
		if ( ! self::is_enabled() ) {
			return $templates;
		}
		$location = '';
		if ( is_string( $arg ) ) {
			$location = $arg;
		} elseif ( is_array( $arg ) && isset( $arg['location'] ) ) {
			$location = (string) $arg['location'];
		}
		if ( 'popup' === $location ) {
			return array();
		}
		return $templates;
	}

	/**
	 * @param mixed $templates Templates.
	 * @return mixed
	 */
	public static function deny_popup_templates( $templates ) {
		return self::is_enabled() ? array() : $templates;
	}

	/**
	 * @param mixed $data    Builder data.
	 * @param mixed $post_id Document id.
	 * @return mixed
	 */
	public static function empty_popup_builder_data( $data, $post_id = 0 ) {
		if ( ! self::is_enabled() ) {
			return $data;
		}
		if ( 17307 === (int) $post_id ) {
			return array();
		}
		return $data;
	}

	/**
	 * Unhook Elementor Theme Builder chrome.
	 */
	public static function unhook_elementor_chrome(): void {
		if ( ! self::is_enabled() ) {
			return;
		}
		foreach ( array(
			'elementor/theme/before_do_header',
			'elementor/theme/after_do_header',
			'elementor/theme/before_do_footer',
			'elementor/theme/after_do_footer',
			'elementor/theme/before_do_single',
			'elementor/theme/after_do_single',
			'elementor/theme/before_do_popup',
			'elementor/theme/after_do_popup',
		) as $hook ) {
			remove_all_actions( $hook );
		}
		if ( ! class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
			return;
		}
		$module = \ElementorPro\Modules\ThemeBuilder\Module::instance();
		if ( ! $module || ! method_exists( $module, 'get_locations_manager' ) ) {
			return;
		}
		$manager = $module->get_locations_manager();
		if ( ! $manager ) {
			return;
		}
		foreach ( array( 'wp_body_open', 'get_header', 'get_footer', 'wp_footer', 'wp_head' ) as $hook ) {
			remove_action( $hook, array( $manager, 'do_location' ) );
			remove_action( $hook, array( $manager, 'do_header' ) );
			remove_action( $hook, array( $manager, 'do_footer' ) );
			remove_action( $hook, array( $manager, 'print_locations' ) );
		}
	}

	/**
	 * Unhook Elementor Pro popup print on wp_footer.
	 */
	public static function unhook_elementor_popups(): void {
		self::unhook_elementor_chrome();
	}

	/**
	 * @param mixed $template Current template.
	 * @return mixed
	 */
	public static function disable_jet_theme_core_template( $template ) {
		if ( ! self::is_enabled() ) {
			return $template;
		}
		$path = self::get_template_path();
		return file_exists( $path ) ? $path : $template;
	}

	/**
	 * Front-end assets.
	 */
	public static function enqueue_assets(): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		wp_enqueue_style(
			'lk-light-product',
			HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-light-product.css',
			array( HELLO_ELEMENTOR_CHILD_VAZIRMATN_HANDLE ),
			file_exists( HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-light-product.css' )
				? (string) filemtime( HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-light-product.css' )
				: HELLO_ELEMENTOR_CHILD_VERSION
		);

		$content_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/light-content.css';
		if ( file_exists( $content_css ) ) {
			wp_enqueue_style(
				'lk-light-content',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/css/light-content.css',
				array( 'lk-light-product', 'lk-lpt-breadcrumb' ),
				(string) filemtime( $content_css )
			);
		}

		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'wc-cart-fragments' );

		if ( 'sending-goods' === self::get_page_slug() ) {
			$card_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-product-shop-cards.css';
			if ( file_exists( $card_css ) ) {
				wp_enqueue_style(
					'lk-archive-product-shop-cards',
					HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-product-shop-cards.css',
					array( 'lk-light-product' ),
					(string) filemtime( $card_css )
				);
			}
		}

		$chrome_js = HELLO_ELEMENTOR_CHILD_PATH . 'assets/js/light-archive-chrome.js';
		if ( file_exists( $chrome_js ) ) {
			wp_enqueue_script(
				'lk-light-archive-chrome',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/js/light-archive-chrome.js',
				array(),
				(string) filemtime( $chrome_js ),
				true
			);
		}
	}

	/**
	 * Drop Elementor/Jet chrome on this page.
	 */
	public static function dequeue_listing_assets(): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		$style_handles = array(
			'elementor-frontend',
			'elementor-icons',
			'elementor-animations',
			'e-animations',
			'hello-elementor-theme-style',
			'hello-elementor',
			'jet-woo-builder',
			'jet-smart-filters',
			'jet-engine-frontend',
		);
		$script_handles = array(
			'elementor-frontend',
			'elementor-pro-frontend',
			'elementor-pro',
			'jet-woo-builder',
			'jet-smart-filters',
			'jet-engine-frontend',
		);

		foreach ( $style_handles as $handle ) {
			wp_dequeue_style( $handle );
			wp_deregister_style( $handle );
		}
		foreach ( $script_handles as $handle ) {
			wp_dequeue_script( $handle );
			wp_deregister_script( $handle );
		}

		global $wp_styles, $wp_scripts;
		if ( $wp_styles instanceof WP_Styles ) {
			foreach ( (array) $wp_styles->queue as $handle ) {
				$h = strtolower( (string) $handle );
				if (
					false !== strpos( $h, 'elementor' )
					|| false !== strpos( $h, 'jet-woo' )
					|| false !== strpos( $h, 'jet-smart-filter' )
					|| false !== strpos( $h, 'menu-cart' )
				) {
					wp_dequeue_style( $handle );
				}
			}
		}
		if ( $wp_scripts instanceof WP_Scripts ) {
			foreach ( (array) $wp_scripts->queue as $handle ) {
				$h = strtolower( (string) $handle );
				if (
					false !== strpos( $h, 'elementor' )
					|| false !== strpos( $h, 'jet-woo' )
					|| false !== strpos( $h, 'jet-smart-filter' )
					|| false !== strpos( $h, 'menu-cart' )
				) {
					wp_dequeue_script( $handle );
				}
			}
		}
	}

	/**
	 * @param array<int, string> $classes Body classes.
	 * @return array<int, string>
	 */
	public static function body_class( array $classes ): array {
		if ( ! self::is_enabled() ) {
			return $classes;
		}

		$classes[] = 'lk-light-product';
		$classes[] = 'lk-light-content';
		$classes[] = 'lz-chrome';

		$slug = self::get_page_slug();
		if ( '' !== $slug ) {
			$classes[] = 'lk-light-content--' . sanitize_html_class( $slug );
		}

		$skip = array(
			'elementor-default'             => true,
			'elementor-template-full-width' => true,
			'elementor-page'                => true,
		);
		$classes = array_values(
			array_filter(
				$classes,
				static function ( $class ) use ( $skip ) {
					$class = (string) $class;
					if ( isset( $skip[ $class ] ) ) {
						return false;
					}
					return 0 !== strpos( $class, 'elementor-page-' );
				}
			)
		);

		return array_values( array_unique( $classes ) );
	}

	/**
	 * Page content for Twig.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_page_content(): array {
		$page_id = self::get_context_page_id();
		$slug    = self::get_page_slug();
		$title   = $page_id > 0 ? get_the_title( $page_id ) : '';
		if ( ! is_string( $title ) || '' === trim( $title ) ) {
			$title = self::get_default_title( $slug );
		}

		if ( 'sending-goods' === $slug ) {
			$content = array_merge(
				array(
					'slug'            => $slug,
					'layout'          => 'shipping',
					'title'           => $title,
					'breadcrumb_html' => function_exists( 'hello_elementor_child_get_breadcrumb_html' )
						? hello_elementor_child_get_breadcrumb_html()
						: '',
				),
				self::get_shipping_page_data()
			);

			return apply_filters( 'lk_light_content_page_content', $content, $page_id, $slug );
		}

		$content = array(
			'slug'            => $slug,
			'layout'          => 'privacy',
			'title'           => $title,
			'intro'           => self::get_intro( $slug ),
			'sections'        => self::get_sections( $slug ),
			'footer_note'     => self::get_footer_note( $slug ),
			'breadcrumb_html' => function_exists( 'hello_elementor_child_get_breadcrumb_html' )
				? hello_elementor_child_get_breadcrumb_html()
				: '',
		);

		return apply_filters( 'lk_light_content_page_content', $content, $page_id, $slug );
	}

	/**
	 * Shipping page data matching the Elementor layout.
	 *
	 * @return array<string, mixed>
	 */
	private static function get_shipping_page_data(): array {
		$base = 'https://lookazma.com/wp-content/uploads/2025/09/';

		return array(
			'hero_image'       => $base . '%D8%B1%D9%88%D8%B4%E2%80%8C%D9%87%D8%A7%DB%8C-%D8%A7%D8%B1%D8%B3%D8%A7%D9%84-%DA%A9%D8%A7%D9%84%D8%A7-%D8%AA%D9%88%D8%B3%D8%B7-%D9%84%D9%88%DA%A9-%D8%A2%D8%B2%D9%85%D8%A7-.webp',
			'cost'             => array(
				'title'      => __( 'هزینه ارسال', 'hello-elementor-child' ),
				'paragraphs' => array(
					__( 'هزینه ارسال با توجه به شیوه ی انتخابی ارسال توسط شما، متغیر و به صورت پس کرایه است.', 'hello-elementor-child' ),
					__( 'کلیه هزینه های ارسال بر عهده مشتری می باشد و در بخش ثبت سفارش نحوه ارسال را با توجه به مقدار بار تعیین می گردد. لازم به ذکر است که ارسال بار به شهرستان توسط باربری انجام می گیرد. فقط هزینه تهران تا باربری را دریافت می گردد و الباقی هزینه پس کرایه می شود.', 'hello-elementor-child' ),
				),
				'image'      => $base . '%D9%86%D8%AD%D9%88%D9%87-%D8%A7%D8%B1%D8%B3%D8%A7%D9%84-%D8%A8%D8%A7%D8%B1.webp',
				'image_alt'  => __( 'نحوه ارسال بار', 'hello-elementor-child' ),
			),
			'howto'            => array(
				'title'      => __( 'نحوه ارسال', 'hello-elementor-child' ),
				'paragraphs' => array(
					__( 'پس از افزودن محصولات مورد نظر به سبد خرید، لازم است با هماهنگی با واحد فروش درکوتاه ترین زمان به دست شما خواهد رسید. هزینه ارسال در تمامی گزینه ها به صورت پس کرایه است؛ بدین صورت که شما پس از دریافت محصول، هزینه ارسال را به متصدی پرداخت می نمایید.', 'hello-elementor-child' ),
				),
			),
			'methods'          => array(
				array(
					'title'     => __( 'ارسال با تیپاکس', 'hello-elementor-child' ),
					'text'      => __( 'پس از افزودن محصولات مورد نظر به سبد خرید، لازم است با هماهنگی با واحد فروش درکوتاه ترین زمان به دست شما خواهد رسید. هزینه ارسال در تمامی گزینه ها به صورت پس کرایه است؛ بدین صورت که شما پس از دریافت محصول، هزینه ارسال را به متصدی پرداخت می نمایید.', 'hello-elementor-child' ),
					'image'     => $base . '1.png',
					'image_alt' => __( 'ارسال با تیپاکس', 'hello-elementor-child' ),
				),
				array(
					'title'     => __( 'ارسال با پیک', 'hello-elementor-child' ),
					'text'      => __( 'این گزینه تنها برای مشتریان ساکن تهران امکان پذیر است؛ پس از ثبت سفارش، پس از تنها یک روز کاری بسته به دستتان خواهد رسید.', 'hello-elementor-child' ),
					'image'     => $base . '2.webp',
					'image_alt' => __( 'ارسال با پیک', 'hello-elementor-child' ),
				),
				array(
					'title'     => __( 'ارسال با باربری', 'hello-elementor-child' ),
					'text'      => __( 'این گزینه برای ارسال به شهرستان ها، مشروط بر اینکه باربری در مسیر مورد نظر سرویس دهی داشته باشد، امکان پذیر است. در این روش، مرسوله 2 الی 3 روز کاری پس از ثبت سفارش به دست شما خواهد رسید. هزینه ارسال در این روش نیز به صورت پس کرایه است.', 'hello-elementor-child' ),
					'image'     => $base . '%D8%A7%D8%B1%D8%B3%D8%A7%D9%84-%D8%A8%D8%A7-%D8%A8%D8%A7%D8%B1%D8%A8%D8%B1%DB%8C-.webp',
					'image_alt' => __( 'ارسال با باربری', 'hello-elementor-child' ),
				),
			),
			'details'          => array(
				'blocks'    => array(
					array(
						'title'      => __( 'نحوه ارسال در تهران', 'hello-elementor-child' ),
						'paragraphs' => array(
							__( 'معمولا تیپاکس به صرفه ترین روش برای ارسال های داخل شهر تهران است. البته اگر هزینه ارسال چندان اهمیتی ندارد و تمایل به دریافت ماده ی شیمیایی مورد نظر خود، در سریع ترین زمان ممکن هستید، می توانید از ارسال توسط پیک استفاده نمایید.', 'hello-elementor-child' ),
						),
					),
					array(
						'title'      => __( 'نحوه ارسال به شهرستان', 'hello-elementor-child' ),
						'paragraphs' => array(
							__( 'ارسال به شهرستان ها توسط تیپاکس و باربری انجام می شود و هزینه آن نیز به صورت پس کرایه است. معمولا بسته های ارسال شده توسط باربری، سریع تر به مقصد می رسند.', 'hello-elementor-child' ),
						),
					),
					array(
						'title'      => __( 'اطلاع از زمانبندی ارسال', 'hello-elementor-child' ),
						'paragraphs' => array(
							__( 'پس از ثبت سفارش و انتخاب شیوه ی ارسال، مدت زمان ارسال مرسوله برای سفارش های تهران، مدت زمان ارسال با توجه به هماهنگی قبلی حداقل یک و حداکثر 3 روز کاری پس از ثبت سفارش است.', 'hello-elementor-child' ),
						),
					),
					array(
						'title'      => __( 'پیگیری سفارشات', 'hello-elementor-child' ),
						'paragraphs' => array(
							__( 'برای پیگیری سفارش ها به 2 روش می توان عمل کرد:', 'hello-elementor-child' ),
							__( '1.مراجعه به پروفایل کاربری و ورود به قسمت “حساب کاربری من” بخش “سفارش ها”', 'hello-elementor-child' ),
							__( '2.تماس با قسمت فروش شرکت', 'hello-elementor-child' ),
							__( 'مرسولات شما پس از بوجود آمدن هماهنگی های لازم فقط برای یک بار به آدرس شما ارسال خواهند شد. چنانچه کاربر به هر دلیلی نتواند مرسوله خود را از پیک لوک آزما، ماموران پست و یا کارمندان کالارسان دریافت کند ملزم به پرداخت هزینه ارسال مجدد خواهند بود و فروشگاه اینترنتی لوک آزما تمام مسئولیت های مرتبط با این موضوع را از خود صلب می نماید', 'hello-elementor-child' ),
						),
					),
				),
				'image'     => $base . 'order-tracking.webp',
				'image_alt' => __( 'نحوه پیگیری سفارشات', 'hello-elementor-child' ),
				'after'     => array(
					array(
						'title'      => __( 'نحوه مرجوعی کالا', 'hello-elementor-child' ),
						'paragraphs' => array(
							__( 'در صورتیکه کیفیت محصول خریداری شده، مغایر با برگه آنالیز آن باشد می توانید محصول را به ما بازگردانید. برای این کار، از طریق بخش تماس با ما و همچنین تلفن های موجود در سایت می توانید درخواست خود را ثبت نمایید.', 'hello-elementor-child' ),
							__( 'ارسال به شهرستان ها توسط تیپاکس و باربری انجام می شود و هزینه آن نیز به صورت پس کرایه است. معمولا بسته های ارسال شده توسط باربری، سریع تر به مقصد می رسند.', 'hello-elementor-child' ),
						),
					),
					array(
						'title'      => __( 'هزینه ارسال', 'hello-elementor-child' ),
						'paragraphs' => array(
							__( 'هزینه ارسال با توجه به شیوه ی انتخابی ارسال توسط شما، متغیر و به صورت پس کرایه است.', 'hello-elementor-child' ),
						),
					),
					array(
						'title'      => __( 'اطلاع از زمانبندی ارسال', 'hello-elementor-child' ),
						'paragraphs' => array(
							__( 'پس از ثبت سفارش و انتخاب شیوه ی ارسال، مدت زمان ارسال مرسوله برای سفارش های تهران، مدت زمان ارسال با توجه به روش انتخابی حداقل یک و حداکثر 3 روز کاری پس از ثبت سفارش است.', 'hello-elementor-child' ),
						),
					),
				),
			),
			'bestsellers'      => array(
				'title'      => __( 'پرفروش ترین محصولات مواد شیمیایی', 'hello-elementor-child' ),
				'cards_html' => self::get_bestsellers_html(),
			),
		);
	}

	/**
	 * Best-selling product cards HTML.
	 */
	private static function get_bestsellers_html(): string {
		if ( ! function_exists( 'wc_get_products' )
			|| ! class_exists( 'Hello_Elementor_Child_Archive_Product_Filter' )
		) {
			return '';
		}

		$products = wc_get_products(
			array(
				'status'  => 'publish',
				'limit'   => 5,
				'orderby' => 'popularity',
				'order'   => 'DESC',
				'return'  => 'objects',
			)
		);

		if ( empty( $products ) ) {
			$products = wc_get_products(
				array(
					'status'  => 'publish',
					'limit'   => 5,
					'orderby' => 'date',
					'order'   => 'DESC',
					'return'  => 'objects',
				)
			);
		}

		$html = '';
		foreach ( (array) $products as $product ) {
			if ( $product instanceof WC_Product ) {
				$html .= Hello_Elementor_Child_Archive_Product_Filter::render_product_card_html( $product, 'shop' );
			}
		}

		return $html;
	}

	/**
	 * @param string $slug Page slug.
	 */
	private static function get_default_title( string $slug ): string {
		if ( 'privacy' === $slug ) {
			return __( 'حریم شخصی', 'hello-elementor-child' );
		}
		if ( 'sending-goods' === $slug ) {
			return __( 'روش‌های ارسال کالا توسط لوک آزما', 'hello-elementor-child' );
		}
		return __( 'صفحه', 'hello-elementor-child' );
	}

	/**
	 * @param string $slug Page slug.
	 */
	private static function get_intro( string $slug ): string {
		if ( 'privacy' === $slug ) {
			return __( 'در لوک آزما، ما متعهد به محافظت از حریم خصوصی و امنیت کاربران خود هستیم. این سیاست حفظ حریم خصوصی نحوه جمع‌آوری، استفاده و حفاظت از اطلاعات شخصی شما را هنگام بازدید از وب‌سایت ما شرح می‌دهد.', 'hello-elementor-child' );
		}
		return '';
	}

	/**
	 * @param string $slug Page slug.
	 */
	private static function get_footer_note( string $slug ): string {
		if ( 'privacy' === $slug ) {
			return __( 'این سیاست حفظ حریم خصوصی آخرین بار در ۱۴۰۳/۱/۳۰ به‌روزرسانی شده است.', 'hello-elementor-child' );
		}
		return '';
	}

	/**
	 * Open sections (no accordion/toggle).
	 *
	 * @param string $slug Page slug.
	 * @return array<int, array{heading:string,level:string,paragraphs:array<int,string>,items:array<int,string>}>
	 */
	private static function get_sections( string $slug ): array {
		if ( 'privacy' === $slug ) {
			return self::get_privacy_sections();
		}
		return array();
	}

	/**
	 * @return array<int, array{heading:string,level:string,paragraphs:array<int,string>,items:array<int,string>}>
	 */
	private static function get_privacy_sections(): array {
		return array(
			array(
				'heading'    => __( 'اطلاعاتی که ما جمع‌آوری می‌کنیم', 'hello-elementor-child' ),
				'level'      => 'h2',
				'paragraphs' => array(
					__( 'هنگامی که از وب‌سایت ما بازدید می‌کنید، ممکن است برخی از اطلاعات شناسایی شخصی، مانند نام، آدرس ایمیل، و هر اطلاعات دیگری که داوطلبانه در اختیار ما قرار می‌دهید، جمع‌آوری کنیم. همچنین ممکن است اطلاعات غیرشخصی مانند آدرس IP، نوع مرورگر و سیستم عامل شما را برای اهداف تحلیلی جمع‌آوری کنیم.', 'hello-elementor-child' ),
				),
				'items'      => array(),
			),
			array(
				'heading'    => __( 'استفاده از اطلاعات', 'hello-elementor-child' ),
				'level'      => 'h2',
				'paragraphs' => array(
					__( 'ما ممکن است از اطلاعاتی که جمع‌آوری می‌کنیم برای موارد زیر استفاده کنیم:', 'hello-elementor-child' ),
				),
				'items'      => array(
					__( 'ارائه و شخصی‌سازی خدمات به شما', 'hello-elementor-child' ),
					__( 'پاسخ به سوالات شما و ارائه پشتیبانی مشتری', 'hello-elementor-child' ),
					__( 'بهبود وب‌سایت و افزایش تجربه کاربری', 'hello-elementor-child' ),
					__( 'ارسال مطالب تبلیغاتی یا به‌روزرسانی‌های مربوط به محصولات و خدمات، با رضایت شما', 'hello-elementor-child' ),
					__( 'محافظت در برابر فعالیت‌های تقلبی یا غیرمجاز', 'hello-elementor-child' ),
				),
			),
			array(
				'heading'    => __( 'به اشتراک‌گذاری اطلاعات', 'hello-elementor-child' ),
				'level'      => 'h2',
				'paragraphs' => array(
					__( 'ما اطلاعات شخصی شما را به اشخاص ثالث نمی‌فروشیم، مبادله نمی‌کنیم یا اجاره نمی‌دهیم. با این حال، ممکن است اطلاعات شما را با ارائه‌دهندگان خدمات شخص ثالث قابل اعتمادی که به ما در راه‌اندازی وب‌سایت و انجام کسب‌وکار کمک می‌کنند، به اشتراک بگذاریم. این ارائه‌دهندگان موظف‌اند اطلاعات شما را محرمانه نگه دارند و از استفاده از آن برای هر هدف دیگری منع می‌شوند.', 'hello-elementor-child' ),
				),
				'items'      => array(),
			),
			array(
				'heading'    => __( 'امنیت داده‌ها', 'hello-elementor-child' ),
				'level'      => 'h2',
				'paragraphs' => array(
					__( 'ما اقدامات امنیتی مناسبی را برای محافظت از اطلاعات شخصی شما در برابر دسترسی، تغییر، افشا یا تخریب غیرمجاز اجرا می‌کنیم. با این حال، هیچ روشی برای انتقال از طریق اینترنت یا ذخیره‌سازی الکترونیکی ۱۰۰٪ ایمن نیست و ما نمی‌توانیم امنیت مطلق را تضمین کنیم.', 'hello-elementor-child' ),
				),
				'items'      => array(),
			),
			array(
				'heading'    => __( 'کوکی‌ها', 'hello-elementor-child' ),
				'level'      => 'h2',
				'paragraphs' => array(
					__( 'وب‌سایت ما ممکن است از کوکی‌ها برای بهبود تجربه مرور شما استفاده کند. این کوکی‌ها فایل‌های متنی کوچکی هستند که در دستگاه شما ذخیره می‌شوند و به ما در تجزیه و تحلیل ترافیک وب‌سایت و سفارشی‌سازی محتوا کمک می‌کنند. شما می‌توانید کوکی‌ها را در تنظیمات مرورگر خود غیرفعال کنید؛ البته این کار ممکن است بر عملکرد وب‌سایت تأثیر بگذارد.', 'hello-elementor-child' ),
				),
				'items'      => array(),
			),
			array(
				'heading'    => __( 'پیوندهای شخص ثالث', 'hello-elementor-child' ),
				'level'      => 'h2',
				'paragraphs' => array(
					__( 'وب‌سایت ما ممکن است حاوی پیوندهایی به وب‌سایت‌های شخص ثالث باشد. ما مسئولیتی در قبال شیوه‌های حفظ حریم خصوصی یا محتوای این وب‌سایت‌ها نداریم. توصیه می‌کنیم سیاست‌های حفظ حریم خصوصی سایت‌های شخص ثالثی را که بازدید می‌کنید مرور کنید.', 'hello-elementor-child' ),
				),
				'items'      => array(),
			),
			array(
				'heading'    => __( 'حریم خصوصی کودکان', 'hello-elementor-child' ),
				'level'      => 'h2',
				'paragraphs' => array(
					__( 'وب‌سایت ما برای کودکان زیر ۱۳ سال در نظر گرفته نشده است. ما آگاهانه اطلاعات شخصی کودکان را جمع‌آوری نمی‌کنیم. اگر فکر می‌کنید که ما سهواً اطلاعاتی از یک کودک جمع‌آوری کرده‌ایم، لطفاً فوراً با ما تماس بگیرید تا اقدامات لازم برای حذف اطلاعات انجام شود.', 'hello-elementor-child' ),
				),
				'items'      => array(),
			),
			array(
				'heading'    => __( 'تغییرات در این سیاست حفظ حریم خصوصی', 'hello-elementor-child' ),
				'level'      => 'h2',
				'paragraphs' => array(
					__( 'ما این حق را برای خود محفوظ می‌داریم که در هر زمان این سیاست حفظ حریم خصوصی را اصلاح یا به‌روزرسانی کنیم. هرگونه تغییر بلافاصله پس از انتشار نسخه به‌روزشده در وب‌سایت ما قابل اجرا خواهد بود.', 'hello-elementor-child' ),
				),
				'items'      => array(),
			),
			array(
				'heading'    => __( 'با ما تماس بگیرید', 'hello-elementor-child' ),
				'level'      => 'h2',
				'paragraphs' => array(
					__( 'اگر در مورد خط‌مشی رازداری ما سؤال یا نگرانی دارید، لطفاً با ما تماس بگیرید.', 'hello-elementor-child' ),
				),
				'items'      => array(),
			),
		);
	}
}
