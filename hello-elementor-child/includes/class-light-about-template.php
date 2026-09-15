<?php
/**
 * Light about template — native chrome; opt-in via Page → Template.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * About page with shared Lookazma header/footer.
 */
final class Hello_Elementor_Child_Light_About_Template {

	public const TEMPLATE_FILE = 'page-templates/light-about.php';

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
		add_action( 'acf/init', array( __CLASS__, 'register_acf_fields' ) );
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
		$templates[ self::TEMPLATE_FILE ] = __( 'Light about template', 'hello-elementor-child' );
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
		return self::TEMPLATE_FILE === $slug || 'light-about.php' === $slug;
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
		return self::page_uses_template( self::get_context_page_id() );
	}

	/**
	 * PHP entry path.
	 */
	public static function get_template_path(): string {
		return HELLO_ELEMENTOR_CHILD_PATH . 'page-light-about.php';
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

		$about_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/light-about.css';
		if ( file_exists( $about_css ) ) {
			wp_enqueue_style(
				'lk-light-about',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/css/light-about.css',
				array( 'lk-light-product', 'lk-lpt-breadcrumb' ),
				(string) filemtime( $about_css )
			);
		}

		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'wc-cart-fragments' );

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
		$classes[] = 'lk-light-about';
		$classes[] = 'lz-chrome';

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
		$title   = $page_id > 0 ? get_the_title( $page_id ) : __( 'درباره لوک آزما', 'hello-elementor-child' );
		if ( ! is_string( $title ) || '' === trim( $title ) ) {
			$title = __( 'درباره لوک آزما', 'hello-elementor-child' );
		}

		$intro_image = self::resolve_story_image( $page_id );
		$faq_image   = apply_filters(
			'lk_light_about_faq_image_url',
			content_url( 'uploads/2023/08/man-in-lab.webp' )
		);

		$content = array(
			'title'             => $title,
			'why_title'         => __( 'چرا لوک آزما؟', 'hello-elementor-child' ),
			'story_title'       => __( 'لوک آزما، تأمین هوشمند برای صنعتی پیشرو', 'hello-elementor-child' ),
			'story_text'        => __( 'لوک آزما با شبکه تامین تخصصی و همکاری با برندهای معتبر بین‌المللی، مواد شیمیایی آزمایشگاهی و صنعتی را با کیفیت قابل اطمینان در اختیار آزمایشگاه‌ها و صنایع قرار می‌دهد.', 'hello-elementor-child' ),
			'intro_image_url'   => $intro_image['url'],
			'intro_image_alt'   => $intro_image['alt'],
			'features'          => self::get_features(),
			'checklist'         => self::get_checklist(),
			'commitments_title' => __( 'تعهد ما، رضایت شماست', 'hello-elementor-child' ),
			'commitments'       => self::get_commitments(),
			'faq_title'         => __( 'پرسش‌های متداول', 'hello-elementor-child' ),
			'faq_subtitle'      => __( 'راهنمای جامع سوالات رایج شما در یک نگاه', 'hello-elementor-child' ),
			'faq_image_url'     => is_string( $faq_image ) ? $faq_image : '',
			'faq_image_alt'     => __( 'مواد شیمیایی آزمایشگاهی', 'hello-elementor-child' ),
			'faqs'              => self::get_faqs(),
			'breadcrumb_html'   => function_exists( 'hello_elementor_child_get_breadcrumb_html' )
				? hello_elementor_child_get_breadcrumb_html()
				: '',
		);

		return apply_filters( 'lk_light_about_page_content', $content, $page_id );
	}

	/**
	 * ACF fields on the About page (Light about template).
	 */
	public static function register_acf_fields(): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group(
			array(
				'key'                   => 'group_lk_about_page',
				'title'                 => __( 'تصویر صفحه درباره ما', 'hello-elementor-child' ),
				'fields'                => array(
					array(
						'key'           => 'field_lk_about_story_image',
						'label'         => __( 'تصویر بخش معرفی', 'hello-elementor-child' ),
						'name'          => 'about_story_image',
						'type'          => 'image',
						'return_format' => 'array',
						'preview_size'  => 'medium',
						'library'       => 'all',
					),
				),
				'location'              => array(
					array(
						array(
							'param'    => 'page_template',
							'operator' => '==',
							'value'    => self::TEMPLATE_FILE,
						),
					),
					array(
						array(
							'param'    => 'page_template',
							'operator' => '==',
							'value'    => 'light-about.php',
						),
					),
				),
				'menu_order'            => 0,
				'position'              => 'side',
				'style'                 => 'default',
				'label_placement'       => 'top',
				'instruction_placement' => 'label',
				'active'                => true,
			)
		);
	}

	/**
	 * Story image from ACF, then featured image, then default.
	 *
	 * @param int $page_id About page ID.
	 * @return array{url:string,alt:string}
	 */
	private static function resolve_story_image( int $page_id ): array {
		$default_url = content_url( 'uploads/2023/08/woman-lab2.webp' );
		$alt         = __( 'لوک آزما', 'hello-elementor-child' );
		$url         = '';

		if ( $page_id > 0 && function_exists( 'get_field' ) ) {
			$field = get_field( 'about_story_image', $page_id );
			if ( is_array( $field ) && ! empty( $field['url'] ) ) {
				$url = (string) $field['url'];
				if ( ! empty( $field['alt'] ) ) {
					$alt = (string) $field['alt'];
				}
			} elseif ( is_numeric( $field ) && (int) $field > 0 ) {
				$maybe = wp_get_attachment_image_url( (int) $field, 'large' );
				if ( is_string( $maybe ) && '' !== $maybe ) {
					$url = $maybe;
				}
				$meta_alt = (string) get_post_meta( (int) $field, '_wp_attachment_image_alt', true );
				if ( '' !== $meta_alt ) {
					$alt = $meta_alt;
				}
			}
		}

		if ( '' === $url && $page_id > 0 ) {
			$thumb_id = (int) get_post_thumbnail_id( $page_id );
			if ( $thumb_id > 0 ) {
				$maybe = wp_get_attachment_image_url( $thumb_id, 'large' );
				if ( is_string( $maybe ) && '' !== $maybe ) {
					$url = $maybe;
				}
			}
		}

		if ( '' === $url ) {
			$url = $default_url;
		}

		$filtered = apply_filters( 'lk_light_about_intro_image_url', $url, $page_id );

		return array(
			'url' => is_string( $filtered ) ? $filtered : $url,
			'alt' => $alt,
		);
	}

	/**
	 * Why Lookazma feature cards.
	 *
	 * @return array<int, array{icon:string,title:string,text:string}>
	 */
	private static function get_features(): array {
		$items = array(
			array(
				'icon'  => 'truck',
				'title' => __( 'ارسال سریع و مطمئن', 'hello-elementor-child' ),
				'text'  => __( 'پوشش ارسال به سراسر کشور', 'hello-elementor-child' ),
			),
			array(
				'icon'  => 'consult',
				'title' => __( 'مشاوره تخصصی', 'hello-elementor-child' ),
				'text'  => __( 'پشتیبانی قبل و بعد از خرید', 'hello-elementor-child' ),
			),
			array(
				'icon'  => 'price',
				'title' => __( 'قیمت رقابتی', 'hello-elementor-child' ),
				'text'  => __( 'شفافیت قیمت و استعلام سریع', 'hello-elementor-child' ),
			),
			array(
				'icon'  => 'quality',
				'title' => __( 'تضمین کیفیت', 'hello-elementor-child' ),
				'text'  => __( 'اصالت کالا و برگه آنالیز', 'hello-elementor-child' ),
			),
			array(
				'icon'  => 'flask',
				'title' => __( 'تنوع بالا', 'hello-elementor-child' ),
				'text'  => __( 'برندها و گریدهای تخصصی', 'hello-elementor-child' ),
			),
		);

		return apply_filters( 'lk_light_about_features', $items );
	}

	/**
	 * Story checklist.
	 *
	 * @return array<int, string>
	 */
	private static function get_checklist(): array {
		$items = array(
			__( 'تجربه همکاری با صنایع بزرگ و شرکت‌های پژوهشی', 'hello-elementor-child' ),
			__( 'تضمین کیفیت، اصالت و انطباق با استانداردهای جهانی', 'hello-elementor-child' ),
			__( 'پشتیبانی حرفه‌ای در انتخاب و مصرف مواد', 'hello-elementor-child' ),
			__( 'راه‌حل‌های تخصصی متناسب با نیاز صنایع مختلف', 'hello-elementor-child' ),
		);

		return apply_filters( 'lk_light_about_checklist', $items );
	}

	/**
	 * Commitments sidebar.
	 *
	 * @return array<int, array{icon:string,title:string,text:string}>
	 */
	private static function get_commitments(): array {
		$items = array(
			array(
				'icon'  => 'quality',
				'title' => __( 'کیفیت تضمین‌شده', 'hello-elementor-child' ),
				'text'  => __( 'تامین از منابع معتبر با برگه آنالیز', 'hello-elementor-child' ),
			),
			array(
				'icon'  => 'price',
				'title' => __( 'شفافیت قیمت', 'hello-elementor-child' ),
				'text'  => __( 'استعلام سریع بدون ابهام در هزینه', 'hello-elementor-child' ),
			),
			array(
				'icon'  => 'consult',
				'title' => __( 'پشتیبانی تخصصی', 'hello-elementor-child' ),
				'text'  => __( 'همراهی در انتخاب گرید و کاربرد مناسب', 'hello-elementor-child' ),
			),
			array(
				'icon'  => 'truck',
				'title' => __( 'ارسال سریع', 'hello-elementor-child' ),
				'text'  => __( 'تحویل مطمئن به سراسر کشور', 'hello-elementor-child' ),
			),
		);

		return apply_filters( 'lk_light_about_commitments', $items );
	}

	/**
	 * FAQ items from the live Elementor about page.
	 *
	 * @return array<int, array{question:string,answer:string}>
	 */
	private static function get_faqs(): array {
		$items = array(
			array(
				'question' => __( '1. ثبت سفارش مواد شیمیایی به صورت تلفنی', 'hello-elementor-child' ),
				'answer'   => __( 'بله، امکان ثبت سفارش بصورت تلفنی نیز وجود دارد و پس از مشاوره توسط پشتیبان علمی فروشگاه و راهنمایی ایشان می‌توانید درخواست خود را ثبت و پیش‌فاکتور دریافت کنید.', 'hello-elementor-child' ),
			),
			array(
				'question' => __( '2. شرایط مرجوعی کالا بعد از خرید مواد شیمیایی', 'hello-elementor-child' ),
				'answer'   => __( "جهت مرجوعی قبل از ارسال با واحد پشتیبانی شرکت حتما هماهنگ شود.\nکالا و بسته‌بندی کالا کاملاً سالم و بدون عیب باشد.\nجهت مرجوعی از شهرستان‌ها باید کالا به‌خوبی بسته‌بندی شود تا آسیب نبیند.\nهزینه حمل مرجوعی به عهده مشتری است مگر اینکه مشخص شود کالا از طرف شرکت به اشتباه فرستاده شده است.\nمواد شیمیایی با رعایت شرایط فوق حداکثر تا مدت زمان تعیین‌شده از سوی واحد پشتیبانی مورد قبول است.", 'hello-elementor-child' ),
			),
			array(
				'question' => __( '3. پرداخت قیمت خرید مواد شیمیایی', 'hello-elementor-child' ),
				'answer'   => __( 'برای پرداخت کالای خریداری شده، ما از روش پرداخت درگاه بانکی و در برخی موارد خاص از انتقال کارت به کارت پشتیبانی می‌کنیم.', 'hello-elementor-child' ),
			),
			array(
				'question' => __( '4. هزینه ارسال خرید مواد شیمیایی', 'hello-elementor-child' ),
				'answer'   => __( 'هزینه حمل و نقل و ارسال کالای خریداری‌شده بر عهده مشتری می‌باشد.', 'hello-elementor-child' ),
			),
			array(
				'question' => __( '5. خرید مواد شیمیایی به صورت اعتباری', 'hello-elementor-child' ),
				'answer'   => __( 'لطفاً برای اطلاع از شرایط خرید اعتباری با واحد فروش تماس حاصل فرمایید.', 'hello-elementor-child' ),
			),
		);

		return apply_filters( 'lk_light_about_faqs', $items );
	}
}
