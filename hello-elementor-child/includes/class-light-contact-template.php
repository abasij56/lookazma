<?php
/**
 * Light contact template — native chrome; opt-in via Page → Template.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contact page with shared Lookazma header/footer.
 */
final class Hello_Elementor_Child_Light_Contact_Template {

	public const TEMPLATE_FILE = 'page-templates/light-contact.php';

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
		$templates[ self::TEMPLATE_FILE ] = __( 'Light contact template', 'hello-elementor-child' );
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
		return self::TEMPLATE_FILE === $slug || 'light-contact.php' === $slug;
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
		return HELLO_ELEMENTOR_CHILD_PATH . 'page-light-contact.php';
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

		$contact_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/light-contact.css';
		if ( file_exists( $contact_css ) ) {
			wp_enqueue_style(
				'lk-light-contact',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/css/light-contact.css',
				array( 'lk-light-product', 'lk-lpt-breadcrumb' ),
				(string) filemtime( $contact_css )
			);
		}

		if ( function_exists( 'wpcf7_enqueue_scripts' ) ) {
			wpcf7_enqueue_scripts();
		}
		if ( function_exists( 'wpcf7_enqueue_styles' ) ) {
			wpcf7_enqueue_styles();
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
		$classes[] = 'lk-light-contact';
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
		$title   = $page_id > 0 ? get_the_title( $page_id ) : __( 'تماس با ما', 'hello-elementor-child' );
		if ( ! is_string( $title ) || '' === trim( $title ) ) {
			$title = __( 'تماس با ما', 'hello-elementor-child' );
		}

		$hero_image = apply_filters(
			'lk_light_contact_hero_image_url',
			content_url( 'uploads/2025/11/using-laptop-show-icon-address-600nw-2521386695.webp' )
		);

		$banner_image = apply_filters(
			'lk_light_contact_banner_image_url',
			content_url( 'uploads/2024/09/contact_us_5a6756504e-copy.webp' )
		);

		return array(
			'title'            => $title,
			'intro'            => __( 'برای ارتباط و کسب اطلاعات بیشتر با لوک آزما میتوانید از طریق راه های ارتباطی زیر و فرم تماس با ما در ارتباط باشید.', 'hello-elementor-child' ),
			'social_title'     => __( 'ما را در شبکه های اجتماعی دنبال کنید', 'hello-elementor-child' ),
			'banner_image_url' => is_string( $banner_image ) ? $banner_image : '',
			'hero_image_url'   => is_string( $hero_image ) ? $hero_image : '',
			'breadcrumb_html'  => function_exists( 'hello_elementor_child_get_breadcrumb_html' )
				? hello_elementor_child_get_breadcrumb_html()
				: '',
			'channels'         => self::get_channels(),
			'social'           => self::get_social_links(),
			'form_html'        => self::get_form_html( $page_id ),
		);
	}

	/**
	 * Contact channels (email, phones, addresses).
	 *
	 * @return array<int, array{key:string,label:string,value:string,href:string,type:string}>
	 */
	private static function get_channels(): array {
		$channels = array(
			array(
				'key'   => 'email',
				'label' => __( 'پشتیبانی', 'hello-elementor-child' ),
				'value' => 'info@lookazma.com',
				'href'  => 'mailto:info@lookazma.com',
				'type'  => 'email',
			),
			array(
				'key'   => 'phone',
				'label' => __( 'شماره تماس', 'hello-elementor-child' ),
				'value' => '021-82802125',
				'href'  => 'tel:+982182802125',
				'type'  => 'phone',
			),
			array(
				'key'   => 'mobile',
				'label' => __( 'شماره موبایل', 'hello-elementor-child' ),
				'value' => '09122114322',
				'href'  => 'tel:+989122114322',
				'type'  => 'phone',
			),
			array(
				'key'   => 'address',
				'label' => __( 'آدرس', 'hello-elementor-child' ),
				'value' => __( 'تهران، خیابان هویزه غربی پلاک 99 واحد 4', 'hello-elementor-child' ) . "\n" . __( 'البرز، کرج، مهرشهر، خیابان شهید ابوالمنصوری، پنجم غربی، پلاک 1', 'hello-elementor-child' ),
				'href'  => '',
				'type'  => 'address',
			),
		);

		return apply_filters( 'lk_light_contact_channels', $channels );
	}

	/**
	 * Social network links.
	 *
	 * @return array<int, array{key:string,label:string,url:string}>
	 */
	private static function get_social_links(): array {
		$links = array(
			array(
				'key'   => 'instagram',
				'label' => 'Instagram',
				'url'   => 'https://www.instagram.com/look.azma',
			),
			array(
				'key'   => 'linkedin',
				'label' => 'LinkedIn',
				'url'   => 'https://www.linkedin.com/company/lookazma',
			),
			array(
				'key'   => 'whatsapp',
				'label' => 'WhatsApp',
				'url'   => 'https://wa.me/989122114322',
			),
			array(
				'key'   => 'telegram',
				'label' => 'Telegram',
				'url'   => 'https://t.me/lookazma',
			),
			array(
				'key'   => 'x',
				'label' => 'X',
				'url'   => 'https://x.com/lookazma',
			),
			array(
				'key'   => 'facebook',
				'label' => 'Facebook',
				'url'   => 'https://www.facebook.com/lookazma',
			),
		);

		return apply_filters( 'lk_light_contact_social', $links );
	}

	/**
	 * Render contact form from page content shortcodes (CF7 / WPForms).
	 *
	 * @param int $page_id Page ID.
	 */
	private static function get_form_html( int $page_id ): string {
		$filtered = apply_filters( 'lk_light_contact_form_html', '', $page_id );
		if ( is_string( $filtered ) && '' !== trim( $filtered ) ) {
			return $filtered;
		}

		if ( $page_id <= 0 ) {
			return '';
		}

		$content = (string) get_post_field( 'post_content', $page_id );
		if ( '' === trim( $content ) ) {
			return '';
		}

		if ( preg_match_all( '/\[(contact-form-7|wpforms)[^\]]*\]/', $content, $matches ) && ! empty( $matches[0] ) ) {
			return implode( "\n", array_map( 'do_shortcode', $matches[0] ) );
		}

		if ( false !== stripos( $content, '[contact-form' ) || false !== stripos( $content, '[wpforms' ) ) {
			return do_shortcode( $content );
		}

		return '';
	}
}
