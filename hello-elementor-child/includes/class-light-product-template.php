<?php
/**
 * Light archive template — native listing for pages that select it
 * under Page → Template.
 *
 * Separate from single-product light and from the opt-in category light archive.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Native product listing content; Elementor Theme Builder header/footer.
 */
final class Hello_Elementor_Child_Light_Product_Template {

	public const OPTION_PER_PAGE = 'lk_light_product_per_page';

	public const OPTION_COLUMNS = 'lk_light_product_columns';

	public const TEMPLATE_FILE = 'page-templates/light-archive.php';

	public const DEFAULT_PER_PAGE = 12;

	public const DEFAULT_COLUMNS = 4;

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'after_setup_theme', array( __CLASS__, 'register_menus' ) );
		add_action( 'customize_register', array( __CLASS__, 'register_customizer' ) );
		add_filter( 'theme_page_templates', array( __CLASS__, 'register_page_template' ), 20, 4 );

		add_filter( 'timber/locations', array( __CLASS__, 'add_timber_locations' ) );

		add_action( 'template_redirect', array( __CLASS__, 'force_template' ), -1 );
		add_filter( 'template_include', array( __CLASS__, 'maybe_use_template' ), PHP_INT_MAX );
		add_filter( 'elementor/theme/need_override_location', array( __CLASS__, 'disable_elementor_locations' ), PHP_INT_MAX, 2 );
		add_filter( 'elementor/theme/get_location_templates', array( __CLASS__, 'remove_popup_templates' ), PHP_INT_MAX, 2 );
		add_filter( 'elementor/theme/get_location_templates/popup', array( __CLASS__, 'deny_popup_templates' ), PHP_INT_MAX );
		add_filter( 'elementor/frontend/builder_content_data', array( __CLASS__, 'empty_popup_builder_data' ), PHP_INT_MAX, 2 );
		add_action( 'wp_footer', array( __CLASS__, 'unhook_elementor_popups' ), 0 );
		add_filter( 'jet-theme-core/template-include', array( __CLASS__, 'disable_jet_theme_core_template' ), PHP_INT_MAX );
		add_filter( 'jet-woo-builder/custom-archive-template', array( __CLASS__, 'disable_jet_archive_template' ), PHP_INT_MAX );
		add_filter( 'jet-woo-builder/custom-shop-template', array( __CLASS__, 'disable_jet_archive_template' ), PHP_INT_MAX );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'dequeue_listing_assets' ), 100 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_filter( 'loop_shop_per_page', array( __CLASS__, 'filter_loop_per_page' ), 30 );
		add_filter( 'woocommerce_add_to_cart_fragments', array( __CLASS__, 'cart_count_fragment' ) );
	}

	/**
	 * Header / footer menu locations.
	 */
	public static function register_menus(): void {
		register_nav_menus(
			array(
				'lk_light_product'        => __( 'هدر Light archive template', 'hello-elementor-child' ),
				'lk_light_product_footer' => __( 'فوتر Light archive template', 'hello-elementor-child' ),
			)
		);
	}

	/**
	 * Customizer: cards per page + cards per row.
	 *
	 * @param WP_Customize_Manager $wp_customize Customizer.
	 */
	public static function register_customizer( $wp_customize ): void {
		if ( ! is_object( $wp_customize ) || ! method_exists( $wp_customize, 'add_section' ) ) {
			return;
		}

		$wp_customize->add_section(
			'lk_light_product',
			array(
				'title'       => __( 'Light archive template', 'hello-elementor-child' ),
				'description' => __( 'این تمپلیت را از تنظیمات برگه → ویژگی‌های صفحه → قالب انتخاب کنید. اینجا فقط تعداد کارت‌ها تنظیم می‌شود.', 'hello-elementor-child' ),
				'priority'    => 45,
			)
		);

		$wp_customize->add_setting(
			self::OPTION_PER_PAGE,
			array(
				'default'           => self::DEFAULT_PER_PAGE,
				'type'              => 'option',
				'sanitize_callback' => array( __CLASS__, 'sanitize_per_page' ),
			)
		);
		$wp_customize->add_control(
			self::OPTION_PER_PAGE,
			array(
				'label'       => __( 'تعداد کارت در هر صفحه', 'hello-elementor-child' ),
				'section'     => 'lk_light_product',
				'type'        => 'number',
				'input_attrs' => array(
					'min'  => 1,
					'max'  => 48,
					'step' => 1,
				),
			)
		);

		$wp_customize->add_setting(
			self::OPTION_COLUMNS,
			array(
				'default'           => self::DEFAULT_COLUMNS,
				'type'              => 'option',
				'sanitize_callback' => array( __CLASS__, 'sanitize_columns' ),
			)
		);
		$wp_customize->add_control(
			self::OPTION_COLUMNS,
			array(
				'label'       => __( 'تعداد کارت در هر ردیف', 'hello-elementor-child' ),
				'section'     => 'lk_light_product',
				'type'        => 'number',
				'input_attrs' => array(
					'min'  => 2,
					'max'  => 6,
					'step' => 1,
				),
			)
		);
	}

	/**
	 * Expose the page template in Page → Template (pages only).
	 *
	 * @param array<string, string> $templates Templates.
	 * @param mixed                 $theme     Theme object.
	 * @param mixed                 $post      Post.
	 * @param string                $post_type Post type.
	 * @return array<string, string>
	 */
	public static function register_page_template( $templates, $theme = null, $post = null, $post_type = '' ): array {
		if ( ! is_array( $templates ) ) {
			$templates = array();
		}
		if ( '' !== $post_type && 'page' !== $post_type ) {
			return $templates;
		}
		$templates[ self::TEMPLATE_FILE ] = __( 'Light archive template', 'hello-elementor-child' );
		return $templates;
	}

	/**
	 * Whether a page has this template assigned.
	 *
	 * @param int $page_id Page ID.
	 */
	public static function page_uses_template( int $page_id ): bool {
		if ( $page_id <= 0 ) {
			return false;
		}
		$slug = (string) get_page_template_slug( $page_id );
		return self::TEMPLATE_FILE === $slug || 'light-archive.php' === $slug;
	}

	/**
	 * Page ID that owns the current front-end request.
	 */
	public static function get_context_page_id(): int {
		if ( function_exists( 'is_shop' ) && is_shop() && function_exists( 'wc_get_page_id' ) ) {
			$id = (int) wc_get_page_id( 'shop' );
			if ( $id > 0 ) {
				return $id;
			}
		}

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
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_per_page( $value ): int {
		$n = absint( $value );
		if ( $n < 1 ) {
			$n = self::DEFAULT_PER_PAGE;
		}
		return min( 48, $n );
	}

	/**
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_columns( $value ): int {
		$n = absint( $value );
		if ( $n < 2 ) {
			$n = self::DEFAULT_COLUMNS;
		}
		return min( 6, $n );
	}

	/**
	 * Cards per page.
	 */
	public static function get_per_page(): int {
		return 12;
	}

	/**
	 * Cards per row (desktop).
	 */
	public static function get_columns(): int {
		return 4;
	}

	/**
	 * Add Timber views path.
	 *
	 * @param array<int, string> $locations Locations.
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
	 * Whether this template should own the current request.
	 */
	public static function is_enabled(): bool {
		if ( wp_doing_cron() ) {
			return false;
		}
		if ( is_admin() && ! wp_doing_ajax() ) {
			return false;
		}
		if ( wp_doing_ajax() ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		if ( function_exists( 'is_product' ) && is_product() ) {
			return false;
		}
		if ( class_exists( 'Hello_Elementor_Child_Custom_Category_Archive' )
			&& Hello_Elementor_Child_Custom_Category_Archive::is_enabled()
		) {
			return false;
		}

		return self::page_uses_template( self::get_context_page_id() );
	}

	/**
	 * PHP template path.
	 */
	public static function get_template_path(): string {
		return HELLO_ELEMENTOR_CHILD_PATH . 'woocommerce/archive-light-product.php';
	}

	/**
	 * Take over before Elementor / Jet archive templates.
	 */
	public static function force_template(): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		$path = self::get_template_path();
		if ( ! file_exists( $path ) ) {
			return;
		}

		status_header( 200 );
		include $path;
		exit;
	}

	/**
	 * Fallback template_include swap.
	 *
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
	 * Keep Elementor header/footer; block archive content override.
	 *
	 * @param bool   $need_override Whether override is needed.
	 * @param string $location      Location name.
	 */
	public static function disable_elementor_locations( bool $need_override, string $location ): bool {
		if ( ! self::is_enabled() ) {
			return $need_override;
		}
		if ( in_array( $location, array( 'header', 'footer', 'archive', 'product_archive', 'product-archive', 'single', 'popup' ), true ) ) {
			return false;
		}
		return $need_override;
	}

	/**
	 * Do not print Elementor Theme Builder popups on this template.
	 *
	 * @param mixed $templates Location templates.
	 * @param mixed $arg      Location slug or args array.
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
	 * Empty popup location documents.
	 *
	 * @param mixed $templates Templates.
	 * @return mixed
	 */
	public static function deny_popup_templates( $templates ) {
		return self::is_enabled() ? array() : $templates;
	}

	/**
	 * Strip popup builder JSON so Elementor cannot hydrate modal 17307.
	 *
	 * @param mixed $data    Builder data.
	 * @param mixed $post_id Document id.
	 * @return mixed
	 */
	public static function empty_popup_builder_data( $data, $post_id = 0 ) {
		if ( ! self::is_enabled() ) {
			return $data;
		}
		$post_id = (int) $post_id;
		if ( $post_id < 1 ) {
			return $data;
		}
		if ( 17307 === $post_id ) {
			return array();
		}
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance->documents ) ) {
			return $data;
		}
		$document = \Elementor\Plugin::$instance->documents->get( $post_id );
		if ( ! $document ) {
			return $data;
		}
		$name     = method_exists( $document, 'get_name' ) ? (string) $document->get_name() : '';
		$location = method_exists( $document, 'get_location' ) ? (string) $document->get_location() : '';
		if ( 'popup' === $name || 'popup' === $location ) {
			return array();
		}
		return $data;
	}

	/**
	 * Unhook Elementor Pro popup print on wp_footer.
	 */
	public static function unhook_elementor_popups(): void {
		if ( ! self::is_enabled() ) {
			return;
		}
		remove_all_actions( 'elementor/theme/before_do_popup' );
		remove_all_actions( 'elementor/theme/after_do_popup' );
		if ( class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
			$module = \ElementorPro\Modules\ThemeBuilder\Module::instance();
			if ( $module && method_exists( $module, 'get_locations_manager' ) ) {
				$manager = $module->get_locations_manager();
				if ( $manager ) {
					remove_action( 'wp_footer', array( $manager, 'do_location' ) );
					remove_action( 'wp_footer', array( $manager, 'print_locations' ) );
				}
			}
		}
	}

	/**
	 * Stop JetWooBuilder archive/shop templates.
	 *
	 * @param mixed $template Current template id/path.
	 * @return mixed
	 */
	public static function disable_jet_archive_template( $template ) {
		return self::is_enabled() ? false : $template;
	}

	/**
	 * Prevent JetThemeCore from swapping the PHP template.
	 *
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

		if ( class_exists( 'Hello_Elementor_Child_Archive_Product_Filter' ) ) {
			Hello_Elementor_Child_Archive_Product_Filter::enqueue_light_archive_assets();
		}

		$card_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-product-shop-cards.css';
		if ( file_exists( $card_css ) ) {
			wp_enqueue_style(
				'lk-archive-shop-cards',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-product-shop-cards.css',
				array( HELLO_ELEMENTOR_CHILD_VAZIRMATN_HANDLE, 'lk-archive-filters' ),
				(string) filemtime( $card_css )
			);
		}

		$light_deps = array( HELLO_ELEMENTOR_CHILD_VAZIRMATN_HANDLE, 'lk-archive-filters' );
		if ( wp_style_is( 'lk-archive-shop-cards', 'registered' ) || wp_style_is( 'lk-archive-shop-cards', 'enqueued' ) ) {
			$light_deps[] = 'lk-archive-shop-cards';
		}

		wp_enqueue_style(
			'lk-light-product',
			HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-light-product.css',
			$light_deps,
			HELLO_ELEMENTOR_CHILD_VERSION
		);

		wp_add_inline_style( 'lk-light-product', 'body.lk-light-product{--lk-lpt-cols:4;}' );

		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'wc-add-to-cart' );
		wp_enqueue_script( 'wc-cart-fragments' );
		wp_enqueue_script( 'woocommerce' );

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
	 * Drop Elementor/Jet chrome + listing assets (native header/footer).
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
			'jet-woo-builder-frontend-font',
			'jet-smart-filters',
			'jet-engine-frontend',
		);
		$script_handles = array(
			'elementor-frontend',
			'elementor-webpack-runtime',
			'elementor-frontend-modules',
			'elementor-pro-frontend',
			'webpack-pro',
			'pro-elements-handlers',
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
	 * Body classes.
	 *
	 * @param array<int, string> $classes Classes.
	 * @return array<int, string>
	 */
	public static function body_class( array $classes ): array {
		if ( self::is_enabled() ) {
			$classes[] = 'lk-light-product';
			$classes[] = 'lk-shop-cards-archive';
			$classes[] = 'lz-chrome';
		}
		return array_values( array_unique( $classes ) );
	}

	/**
	 * Woo loop per page when this template is active.
	 *
	 * @param int $per_page Current.
	 */
	public static function filter_loop_per_page( $per_page ): int {
		if ( ! self::is_enabled() ) {
			return (int) $per_page;
		}
		return self::get_per_page();
	}

	/**
	 * Update header cart count after AJAX add-to-cart.
	 *
	 * @param array<string, string> $fragments Fragments.
	 * @return array<string, string>
	 */
	public static function cart_count_fragment( array $fragments ): array {
		$snap = self::get_cart_snapshot();
		$fragments['span.lz-cart-btn__count'] = '<span class="lz-cart-btn__count cart-contents-count">' . esc_html( (string) $snap['count'] ) . '</span>';
		$fragments['span.lz-cart-btn__price'] = '<span class="lz-cart-btn__price">' . wp_kses_post( $snap['total'] ) . '</span>';
		return $fragments;
	}

	/**
	 * Cart count + formatted total.
	 *
	 * @return array{count:int,total:string}
	 */
	public static function get_cart_snapshot(): array {
		$count = 0;
		$total = '';
		if ( function_exists( 'WC' ) && WC()->cart ) {
			$count = (int) WC()->cart->get_cart_contents_count();
			$total = wp_strip_all_tags( (string) WC()->cart->get_cart_subtotal() );
		}
		return array(
			'count' => $count,
			'total' => $total,
		);
	}

	/**
	 * First assigned menu location from a preferred list.
	 *
	 * @param array<int, string> $candidates Location slugs.
	 */
	public static function resolve_menu_location( array $candidates ): string {
		$locations = get_nav_menu_locations();
		foreach ( $candidates as $loc ) {
			if ( ! empty( $locations[ $loc ] ) ) {
				return $loc;
			}
		}
		return (string) ( $candidates[0] ?? '' );
	}

	/**
	 * Render a nav menu to HTML.
	 *
	 * @param array<int, string> $candidates Locations.
	 * @param string             $class      UL class.
	 */
	public static function render_menu_html( array $candidates, string $class ): string {
		$location = self::resolve_menu_location( $candidates );
		if ( '' === $location ) {
			return '';
		}

		ob_start();
		wp_nav_menu(
			array(
				'theme_location' => $location,
				'container'      => false,
				'menu_class'     => $class,
				'fallback_cb'    => false,
				'depth'          => 2,
			)
		);
		return trim( (string) ob_get_clean() );
	}

	/**
	 * Header chrome data for Twig.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_chrome_context(): array {
		$logo_html = '';
		if ( has_custom_logo() ) {
			$logo_html = (string) get_custom_logo();
		}

		$cart     = self::get_cart_snapshot();
		$cart_url = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );

		$search_desktop = '';
		$search_mobile  = '';
		if ( class_exists( 'Hello_Elementor_Child_Lookazma_Search' ) ) {
			$search_desktop = Hello_Elementor_Child_Lookazma_Search::render_markup( '', 'lk-lpt-search-desktop' );
			$search_mobile  = Hello_Elementor_Child_Lookazma_Search::render_markup( '', 'lk-lpt-search-mobile' );
		}

		return array(
			'home_url'        => trailingslashit( home_url( '/' ) ),
			'site_name'       => get_bloginfo( 'name' ),
			'logo_html'       => $logo_html,
			'header_menu'     => self::render_menu_html( array( 'lk_light_product' ), 'lz-nav__list' ),
			'footer_menu'     => self::render_menu_html( array( 'lk_light_product_footer' ), 'lz-site-footer__links' ),
			'search_desktop'  => $search_desktop,
			'search_mobile'   => $search_mobile,
			'cart_url'        => $cart_url,
			'cart_count'      => $cart['count'],
			'cart_total'      => $cart['total'],
			'columns'         => self::get_columns(),
		);
	}
}
