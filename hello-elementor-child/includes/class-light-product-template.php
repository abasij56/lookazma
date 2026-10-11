<?php
/**
 * Light archive template — native listing for pages that select it
 * under Page → Template, and for all product search results.
 *
 * Separate from single-product light and from the category light archive.
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

		add_filter( 'wp_nav_menu_objects', array( __CLASS__, 'mark_chemicals_nav_items' ), 20, 2 );
		add_filter( 'wp_nav_menu_objects', array( __CLASS__, 'mark_brands_nav_items' ), 21, 2 );
		add_filter( 'nav_menu_css_class', array( __CLASS__, 'chemicals_nav_item_classes' ), 20, 2 );
		add_filter( 'nav_menu_css_class', array( __CLASS__, 'brands_nav_item_classes' ), 21, 2 );
		add_filter( 'nav_menu_link_attributes', array( __CLASS__, 'chemicals_nav_link_attributes' ), 20, 2 );
		add_filter( 'nav_menu_link_attributes', array( __CLASS__, 'brands_nav_link_attributes' ), 21, 2 );
		add_filter( 'nav_menu_item_title', array( __CLASS__, 'chemicals_nav_item_title' ), 20, 2 );
		add_filter( 'nav_menu_item_title', array( __CLASS__, 'brands_nav_item_title' ), 21, 2 );

		add_filter( 'timber/locations', array( __CLASS__, 'add_timber_locations' ) );

		add_action( 'template_redirect', array( __CLASS__, 'unhook_elementor_chrome' ), -5 );
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
		add_action( 'wp_print_styles', array( __CLASS__, 'dequeue_listing_assets' ), 100 );
		add_action( 'wp_print_scripts', array( __CLASS__, 'dequeue_listing_assets' ), 100 );
		add_action( 'wp_head', array( __CLASS__, 'print_critical_chrome_css' ), 3 );
		add_filter( 'style_loader_tag', array( __CLASS__, 'mark_chrome_styles_no_async' ), 20, 2 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_filter( 'loop_shop_per_page', array( __CLASS__, 'filter_loop_per_page' ), 30 );
		add_filter( 'woocommerce_add_to_cart_fragments', array( __CLASS__, 'cart_count_fragment' ) );
	}

	/**
	 * Whether any native light chrome template is rendering.
	 */
	public static function uses_native_chrome(): bool {
		if ( self::is_enabled() ) {
			return true;
		}

		if ( class_exists( 'Hello_Elementor_Child_Product_Compare' )
			&& method_exists( 'Hello_Elementor_Child_Product_Compare', 'is_light_front' )
		) {
			return Hello_Elementor_Child_Product_Compare::is_light_front();
		}

		return false;
	}

	/**
	 * Critical header CSS in <head> so LiteSpeed async CSS cannot FOUC the chrome
	 * (giant icons, dual search, open mobile nav) on cold first paint.
	 */
	public static function print_critical_chrome_css(): void {
		if ( is_admin() || wp_doing_ajax() || ! self::uses_native_chrome() ) {
			return;
		}

		echo '<style id="lk-light-chrome-critical">'
			. 'html:has(body.lk-light-product){overflow-x:clip;transform:none!important}'
			. 'body.lk-light-product{margin:0;overflow-x:clip;transform:none!important;padding-top:var(--lz-header-h,8rem)}'
			. 'body.lk-light-product>.lz-site-header,'
			. 'body.lk-light-product.search .lz-site-header,'
			. 'body.lk-light-product.search-results .lz-site-header,'
			. '.lz-site-header{position:fixed!important;top:0!important;left:0!important;right:0!important;z-index:10050!important;width:100%!important;max-width:none!important;margin:0!important;background:#fff;border-bottom:1px solid #e6eef4;transform:none!important}'
			. '.lz-site-header__top{display:grid;grid-template-columns:auto 1fr auto;gap:1rem;align-items:center;min-height:64px;padding:.55rem 4%;background:#fff}'
			. '.lz-site-header__logo img,.lz-site-header__logo .custom-logo{display:block;height:46px!important;width:auto!important;max-width:240px!important}'
			. '.lz-search--desktop{display:none;width:100%;max-width:620px;margin-inline:auto}'
			. '.lz-search-mobile{display:block;padding:0 4% .75rem;background:#fff}'
			. '.lz-header-actions{display:inline-flex;align-items:center;gap:.45rem}'
			. '.lz-account-btn{display:inline-flex;align-items:center;justify-content:center;gap:.45rem;min-height:2.75rem;padding:.4rem .7rem;border:1px solid #d4e0ea;border-radius:12px;background:#fff;color:#001f3f;font-weight:800;font-size:.8rem;line-height:1;text-decoration:none;white-space:nowrap}'
			. '.lz-account-btn__icon{display:inline-grid;place-items:center;width:18px!important;height:18px!important;flex:0 0 18px;overflow:hidden}'
			. '.lz-account-btn__icon svg{width:18px!important;height:18px!important;max-width:18px!important;max-height:18px!important}'
			. '.lz-cart-btn{display:inline-flex;align-items:center;gap:.35rem;padding:.4rem .55rem;border:1px solid #d4e0ea;border-radius:12px;background:#fff;color:#001f3f;text-decoration:none}'
			. '.lz-cart-btn__icon{position:relative;display:inline-grid;place-items:center;width:22px!important;height:22px!important;overflow:visible}'
			. '.lz-cart-btn__icon svg{width:22px!important;height:22px!important;max-width:22px!important;max-height:22px!important}'
			. '.lz-cart-btn__count{position:absolute;top:-4px;left:-4px;width:16px;height:16px;min-width:0;padding:0;border-radius:50%;background:#0f4c81;color:#fff;font-size:.62rem;font-weight:800;line-height:1;display:grid;place-items:center;box-sizing:border-box}'
			. '.lz-cart-btn__price{display:none}'
			. 'body.lk-light-product .lz-menu-toggle{display:none!important;align-items:center;justify-content:center;width:2.75rem;height:2.75rem;padding:0;border:1px solid #d4e0ea;border-radius:12px;background:#fff;color:#001f3f}'
			. 'body.lk-light-product .lz-menu-toggle__icon{display:block;width:22px!important;height:22px!important;max-width:22px!important;max-height:22px!important}'
			. 'body.lk-light-product .lz-menu-toggle__icon--close{display:none}'
			. '.lz-nav-backdrop{display:none}'
			. '.lz-site-header__bottom{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.75rem;padding:.55rem 4% .8rem;border-top:1px solid #e6eef4;background:#fff}'
			. '.lz-nav,.lz-nav__list{display:flex;flex-wrap:wrap;align-items:center;gap:.35rem 1.1rem;list-style:none;margin:0;padding:0}'
			. '.lz-phones{display:none}'
			. '@media (min-width:900px){'
			. '.lz-site-header__logo img,.lz-site-header__logo .custom-logo{height:56px!important;max-width:290px!important}'
			. '.lz-search--desktop{display:block}'
			. '.lz-search-mobile{display:none}'
			. '.lz-cart-btn__price{display:inline}'
			. '.lz-phones{display:flex}'
			. '}'
			. '@media (max-width:899px){'
			. '.lz-site-header__top{grid-template-columns:minmax(0,1fr) auto}'
			. '.lz-search--desktop{display:none!important}'
			. 'body.lk-light-product .lz-menu-toggle{display:inline-flex!important}'
			. '.lz-cart-btn__price{display:none!important}'
			. '.lz-site-header__bottom{display:none!important}'
			. 'body.lz-nav-open .lz-site-header__bottom{display:flex!important}'
			. '}'
			. '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Keep chrome CSS render-blocking under LiteSpeed "Load CSS Asynchronously".
	 *
	 * @param string $html   Link tag HTML.
	 * @param string $handle Style handle.
	 */
	public static function mark_chrome_styles_no_async( string $html, string $handle ): string {
		if ( ! self::uses_native_chrome() ) {
			return $html;
		}

		$protected = array(
			HELLO_ELEMENTOR_CHILD_VAZIRMATN_HANDLE,
			'lk-light-product',
			'lk-light-homepage',
			'lk-lookazma-search',
			'lk-shop-cards',
			'lk-archive-shop-cards',
			'lk-single-product-light',
			'lk-light-compare',
		);

		if ( ! in_array( $handle, $protected, true ) ) {
			return $html;
		}

		if ( false !== strpos( $html, 'data-no-async' ) ) {
			return $html;
		}

		return str_replace( '<link ', '<link data-no-async="1" data-no-optimize="1" data-no-ucss="1" ', $html );
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

		if ( class_exists( 'Hello_Elementor_Child_Custom_Tag_Archive' )
			&& Hello_Elementor_Child_Custom_Tag_Archive::is_enabled()
		) {
			return false;
		}

		if ( class_exists( 'Hello_Elementor_Child_Custom_Post_Category_Archive' )
			&& Hello_Elementor_Child_Custom_Post_Category_Archive::is_enabled()
		) {
			return false;
		}

		if ( class_exists( 'Hello_Elementor_Child_Light_Contact_Template' )
			&& Hello_Elementor_Child_Light_Contact_Template::is_enabled()
		) {
			return false;
		}

		if ( class_exists( 'Hello_Elementor_Child_Light_About_Template' )
			&& Hello_Elementor_Child_Light_About_Template::is_enabled()
		) {
			return false;
		}

		if ( class_exists( 'Hello_Elementor_Child_Light_Content_Template' )
			&& Hello_Elementor_Child_Light_Content_Template::is_enabled()
		) {
			return false;
		}

		if ( class_exists( 'Hello_Elementor_Child_Light_Account_Template' )
			&& Hello_Elementor_Child_Light_Account_Template::is_enabled()
		) {
			return false;
		}

		if ( class_exists( 'Hello_Elementor_Child_Light_Cart_Template' )
			&& Hello_Elementor_Child_Light_Cart_Template::is_enabled()
		) {
			return false;
		}

		if ( class_exists( 'Hello_Elementor_Child_Light_Checkout_Template' )
			&& Hello_Elementor_Child_Light_Checkout_Template::is_enabled()
		) {
			return false;
		}

		if ( class_exists( 'Hello_Elementor_Child_Light_Article_Template' )
			&& Hello_Elementor_Child_Light_Article_Template::is_enabled()
		) {
			return false;
		}

		if ( class_exists( 'Hello_Elementor_Child_Custom_Single_Post' )
			&& Hello_Elementor_Child_Custom_Single_Post::is_enabled()
		) {
			return false;
		}

		if ( class_exists( 'Hello_Elementor_Child_Custom_Brand_Single' )
			&& Hello_Elementor_Child_Custom_Brand_Single::is_brand_request()
		) {
			return false;
		}

		if ( class_exists( 'Hello_Elementor_Child_Light_Homepage_Template' )
			&& Hello_Elementor_Child_Light_Homepage_Template::is_enabled()
		) {
			return false;
		}

		if ( class_exists( 'Hello_Elementor_Child_Light_Compare_Template' )
			&& Hello_Elementor_Child_Light_Compare_Template::is_enabled()
		) {
			return false;
		}

		if ( class_exists( 'Hello_Elementor_Child_Light_404_Template' )
			&& Hello_Elementor_Child_Light_404_Template::is_enabled()
		) {
			return false;
		}

		if ( class_exists( 'Hello_Elementor_Child_Light_Login_Template' )
			&& Hello_Elementor_Child_Light_Login_Template::is_enabled()
		) {
			return false;
		}

		if ( class_exists( 'Hello_Elementor_Child_Archive_Product_Filter' )
			&& Hello_Elementor_Child_Archive_Product_Filter::is_product_search_context()
		) {
			return true;
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

		self::unhook_elementor_chrome();
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
		if ( in_array( $location, array( 'header', 'footer', 'archive', 'product_archive', 'product-archive', 'search', 'search-results', 'single', 'popup' ), true ) ) {
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
	 * Unhook Elementor Theme Builder chrome so native header stays a body child.
	 * Search Results templates otherwise wrap wp_body_open and break position:fixed.
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
			'elementor/theme/before_do_archive',
			'elementor/theme/after_do_archive',
			'elementor/theme/before_do_search',
			'elementor/theme/after_do_search',
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

		if ( class_exists( 'Hello_Elementor_Child_Product_Compare' ) ) {
			Hello_Elementor_Child_Product_Compare::enqueue_assets( false );
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
		if ( ! self::is_enabled() ) {
			return $classes;
		}

		$classes[] = 'lk-light-product';
		$classes[] = 'lk-shop-cards-archive';
		$classes[] = 'lz-chrome';
		if ( class_exists( 'Hello_Elementor_Child_Archive_Product_Filter' )
			&& Hello_Elementor_Child_Archive_Product_Filter::is_product_search_context()
		) {
			$classes[] = 'lk-light-search';
		}

		$skip = array(
			'elementor-default'              => true,
			'elementor-template-full-width'  => true,
			'elementor-page'                 => true,
			'e-lazyload'                     => true,
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
	 * Whether a menu item is the chemicals category root.
	 *
	 * @param object $item Menu item.
	 */
	public static function is_chemicals_root_item( $item ): bool {
		if ( ! is_object( $item ) ) {
			return false;
		}
		if ( 'product_cat' === ( $item->object ?? '' ) ) {
			$term = get_term( (int) ( $item->object_id ?? 0 ), 'product_cat' );
			if ( $term && ! is_wp_error( $term ) && 'chemicals' === $term->slug ) {
				return true;
			}
		}
		$url = (string) ( $item->url ?? '' );
		if ( '' === $url ) {
			return false;
		}
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$path = untrailingslashit( $path );
		return (bool) preg_match( '#/product-category/chemicals$#', $path );
	}

	/**
	 * Mark chemicals parent + children for scoped mega-submenu styling.
	 *
	 * @param array<int, object> $items Menu items.
	 * @param object|array       $args  Menu args.
	 * @return array<int, object>
	 */
	public static function mark_chemicals_nav_items( $items, $args ) {
		if ( ! is_array( $items ) || array() === $items ) {
			return $items;
		}

		$root_ids = array();
		foreach ( $items as $item ) {
			if ( self::is_chemicals_root_item( $item ) ) {
				$root_ids[] = (int) $item->ID;
				$classes    = is_array( $item->classes ) ? $item->classes : array();
				$classes[]  = 'lz-nav__chemicals';
				$item->classes = array_values( array_unique( $classes ) );
			}
		}

		if ( array() === $root_ids ) {
			return $items;
		}

		foreach ( $items as $item ) {
			$parent = (int) ( $item->menu_item_parent ?? 0 );
			if ( in_array( $parent, $root_ids, true ) ) {
				$classes       = is_array( $item->classes ) ? $item->classes : array();
				$classes[]     = 'lz-nav__chemicals-child';
				$item->classes = array_values( array_unique( $classes ) );
			}
		}

		return $items;
	}

	/**
	 * Extra class pass (keeps chemicals markers if another filter rebuilt classes).
	 *
	 * @param array<int, string> $classes Classes.
	 * @param object             $item    Menu item.
	 * @return array<int, string>
	 */
	public static function chemicals_nav_item_classes( $classes, $item ) {
		$classes = is_array( $classes ) ? $classes : array();
		if ( self::is_chemicals_root_item( $item ) ) {
			$classes[] = 'lz-nav__chemicals';
		}
		return array_values( array_unique( $classes ) );
	}

	/**
	 * Make chemicals parent unclickable (hover opens submenu).
	 *
	 * @param array<string, string> $atts Link attributes.
	 * @param object                $item Menu item.
	 * @return array<string, string>
	 */
	public static function chemicals_nav_link_attributes( $atts, $item ) {
		$atts    = is_array( $atts ) ? $atts : array();
		$classes = is_object( $item ) && is_array( $item->classes ) ? $item->classes : array();
		if ( ! in_array( 'lz-nav__chemicals', $classes, true ) && ! self::is_chemicals_root_item( $item ) ) {
			return $atts;
		}

		$atts['href']          = '#';
		$atts['role']          = 'button';
		$atts['aria-haspopup'] = 'true';
		$atts['aria-expanded'] = 'false';
		$atts['tabindex']      = '0';
		$existing              = isset( $atts['class'] ) ? (string) $atts['class'] : '';
		$atts['class']         = trim( $existing . ' lz-nav__chemicals-toggle' );
		return $atts;
	}

	/**
	 * Append category thumbnail for chemicals submenu children only.
	 *
	 * @param string $title Item title.
	 * @param object $item  Menu item.
	 */
	public static function chemicals_nav_item_title( $title, $item ): string {
		$title   = (string) $title;
		$classes = is_object( $item ) && is_array( $item->classes ) ? $item->classes : array();
		if ( ! in_array( 'lz-nav__chemicals-child', $classes, true ) ) {
			return $title;
		}
		if ( ! is_object( $item ) || 'product_cat' !== ( $item->object ?? '' ) ) {
			return $title;
		}

		$term_id = (int) ( $item->object_id ?? 0 );
		$thumb   = '';
		if ( $term_id > 0 ) {
			$thumb_id = (int) get_term_meta( $term_id, 'thumbnail_id', true );
			if ( $thumb_id > 0 ) {
				$src = wp_get_attachment_image_url( $thumb_id, 'woocommerce_gallery_thumbnail' );
				if ( ! is_string( $src ) || '' === $src ) {
					$src = wp_get_attachment_image_url( $thumb_id, 'thumbnail' );
				}
				if ( is_string( $src ) && '' !== $src ) {
					$thumb = sprintf(
						'<img class="lz-nav__cat-thumb" src="%s" alt="" width="40" height="40" loading="lazy" decoding="async">',
						esc_url( $src )
					);
				}
			}
		}
		if ( '' === $thumb ) {
			$thumb = '<span class="lz-nav__cat-thumb lz-nav__cat-thumb--empty" aria-hidden="true"></span>';
		}

		return $thumb . '<span class="lz-nav__cat-label">' . esc_html( wp_strip_all_tags( $title ) ) . '</span>';
	}

	/**
	 * Whether a menu item is the brands / شرکت ها root.
	 *
	 * @param object $item Menu item.
	 */
	public static function is_brands_root_item( $item ): bool {
		if ( ! is_object( $item ) ) {
			return false;
		}
		if ( 'page' === ( $item->object ?? '' ) ) {
			$post = get_post( (int) ( $item->object_id ?? 0 ) );
			if ( $post instanceof WP_Post && 'brands' === $post->post_name ) {
				return true;
			}
		}
		$title = isset( $item->title ) ? wp_strip_all_tags( (string) $item->title ) : '';
		if ( 'شرکت ها' === $title || 'شرکت‌ها' === $title ) {
			return true;
		}
		$url = (string) ( $item->url ?? '' );
		if ( '' === $url ) {
			return false;
		}
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$path = untrailingslashit( $path );
		return (bool) preg_match( '#/brands$#', $path );
	}

	/**
	 * Mark brands parent + children for scoped mega-submenu styling.
	 *
	 * @param array<int, object> $items Menu items.
	 * @param object|array       $args  Menu args.
	 * @return array<int, object>
	 */
	public static function mark_brands_nav_items( $items, $args ) {
		if ( ! is_array( $items ) || array() === $items ) {
			return $items;
		}

		$root_ids = array();
		foreach ( $items as $item ) {
			if ( self::is_brands_root_item( $item ) ) {
				$root_ids[]    = (int) $item->ID;
				$classes       = is_array( $item->classes ) ? $item->classes : array();
				$classes[]     = 'lz-nav__brands';
				$item->classes = array_values( array_unique( $classes ) );
			}
		}

		if ( array() === $root_ids ) {
			return $items;
		}

		foreach ( $items as $item ) {
			$parent = (int) ( $item->menu_item_parent ?? 0 );
			if ( in_array( $parent, $root_ids, true ) ) {
				$classes       = is_array( $item->classes ) ? $item->classes : array();
				$classes[]     = 'lz-nav__brands-child';
				$item->classes = array_values( array_unique( $classes ) );
			}
		}

		return $items;
	}

	/**
	 * Extra class pass for brands root.
	 *
	 * @param array<int, string> $classes Classes.
	 * @param object             $item    Menu item.
	 * @return array<int, string>
	 */
	public static function brands_nav_item_classes( $classes, $item ) {
		$classes = is_array( $classes ) ? $classes : array();
		if ( self::is_brands_root_item( $item ) ) {
			$classes[] = 'lz-nav__brands';
		}
		return array_values( array_unique( $classes ) );
	}

	/**
	 * Brands parent: keep mega-menu class, link to /brands/.
	 *
	 * @param array<string, string> $atts Link attributes.
	 * @param object                $item Menu item.
	 * @return array<string, string>
	 */
	public static function brands_nav_link_attributes( $atts, $item ) {
		$atts    = is_array( $atts ) ? $atts : array();
		$classes = is_object( $item ) && is_array( $item->classes ) ? $item->classes : array();
		if ( ! in_array( 'lz-nav__brands', $classes, true ) && ! self::is_brands_root_item( $item ) ) {
			return $atts;
		}

		$atts['href']          = home_url( '/brands/' );
		$atts['aria-haspopup'] = 'true';
		unset( $atts['role'], $atts['tabindex'] );
		$existing      = isset( $atts['class'] ) ? (string) $atts['class'] : '';
		$atts['class'] = trim( $existing . ' lz-nav__brands-toggle' );
		return $atts;
	}

	/**
	 * Brand featured image for شرکت ها submenu children only.
	 *
	 * @param string $title Item title.
	 * @param object $item  Menu item.
	 */
	public static function brands_nav_item_title( $title, $item ): string {
		$title   = (string) $title;
		$classes = is_object( $item ) && is_array( $item->classes ) ? $item->classes : array();
		if ( ! in_array( 'lz-nav__brands-child', $classes, true ) ) {
			return $title;
		}
		if ( ! is_object( $item ) || 'brand' !== ( $item->object ?? '' ) ) {
			return $title;
		}

		$post_id = (int) ( $item->object_id ?? 0 );
		$thumb   = '';
		if ( $post_id > 0 ) {
			$thumb_id = (int) get_post_thumbnail_id( $post_id );
			if ( $thumb_id > 0 ) {
				$src = wp_get_attachment_image_url( $thumb_id, 'thumbnail' );
				if ( ! is_string( $src ) || '' === $src ) {
					$src = wp_get_attachment_image_url( $thumb_id, 'medium' );
				}
				if ( is_string( $src ) && '' !== $src ) {
					$thumb = sprintf(
						'<img class="lz-nav__cat-thumb" src="%s" alt="" width="40" height="40" loading="lazy" decoding="async">',
						esc_url( $src )
					);
				}
			}
		}
		if ( '' === $thumb ) {
			$thumb = '<span class="lz-nav__cat-thumb lz-nav__cat-thumb--empty" aria-hidden="true"></span>';
		}

		return $thumb . '<span class="lz-nav__cat-label">' . esc_html( wp_strip_all_tags( $title ) ) . '</span>';
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

		$account_url = function_exists( 'wc_get_page_permalink' ) ? (string) wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' );
		if ( '' === $account_url ) {
			$account_url = home_url( '/my-account/' );
		}

		$redirect_to = home_url( '/' );
		if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
			$redirect_to = home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		}

		$login_url = add_query_arg(
			array(
				'login'       => 'true',
				'page'        => '1',
				'redirect_to' => $redirect_to,
			),
			home_url( '/' )
		);

		$account_display_name = '';
		$logout_url           = function_exists( 'wc_logout_url' ) ? wc_logout_url() : wp_logout_url( home_url( '/' ) );
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			if ( $user instanceof WP_User ) {
				$account_display_name = (string) $user->display_name;
				if ( '' === trim( $account_display_name ) ) {
					$account_display_name = (string) $user->user_login;
				}
			}
		}

		$search_desktop = '';
		$search_mobile  = '';
		if ( class_exists( 'Hello_Elementor_Child_Lookazma_Search' ) ) {
			$search_desktop = Hello_Elementor_Child_Lookazma_Search::render_markup( '', 'lk-lpt-search-desktop' );
			$search_mobile  = Hello_Elementor_Child_Lookazma_Search::render_markup( '', 'lk-lpt-search-mobile' );
		}

		$compare_url = class_exists( 'Hello_Elementor_Child_Light_Compare_Template' )
			? Hello_Elementor_Child_Light_Compare_Template::get_page_url()
			: home_url( '/compare/' );

		return array(
			'home_url'        => trailingslashit( home_url( '/' ) ),
			'site_name'       => get_bloginfo( 'name' ),
			'logo_html'       => $logo_html,
			'header_menu'     => self::render_menu_html( array( 'lk_light_product' ), 'lz-nav__list' ),
			'footer_menu'     => self::render_menu_html( array( 'lk_light_product_footer' ), 'lz-site-footer__links' ),
			'search_desktop'  => $search_desktop,
			'search_mobile'   => $search_mobile,
			'compare_url'       => $compare_url,
			'compare_chrome_html' => class_exists( 'Hello_Elementor_Child_Product_Compare' )
				? Hello_Elementor_Child_Product_Compare::get_tray_html()
				: '',
			'compare_css_url'      => class_exists( 'Hello_Elementor_Child_Product_Compare' )
				&& file_exists( HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/light-compare.css' )
				? HELLO_ELEMENTOR_CHILD_URI . 'assets/css/light-compare.css'
				: '',
			'compare_css_ver'      => file_exists( HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/light-compare.css' )
				? (string) filemtime( HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/light-compare.css' )
				: HELLO_ELEMENTOR_CHILD_VERSION,
			'cart_url'          => $cart_url,
			'cart_count'        => $cart['count'],
			'cart_total'        => $cart['total'],
			'account_logged_in'    => is_user_logged_in(),
			'account_url'          => $account_url,
			'login_url'            => $login_url,
			'account_display_name' => $account_display_name,
			'logout_url'           => $logout_url,
			'columns'           => self::get_columns(),
		);
	}
}
