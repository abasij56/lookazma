<?php
/**
 * Light brands page template — brand card grid; opt-in via Page → Template.
 *
 * Create a Page (e.g. slug `brands`), set Template to "Light brands template".
 * Brand singles stay at /brands/{slug}/.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Brands listing page with shared Lookazma header/footer.
 */
final class Hello_Elementor_Child_Light_Brands_Template {

	public const TEMPLATE_FILE = 'page-templates/light-brands.php';

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_filter( 'theme_page_templates', array( __CLASS__, 'register_page_template' ), 20, 4 );
		add_filter( 'timber/locations', array( __CLASS__, 'add_timber_locations' ) );

		add_filter( 'woocommerce_get_breadcrumb', array( __CLASS__, 'filter_breadcrumb' ), 20 );

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
		add_filter( 'body_class', array( __CLASS__, 'body_class' ), 99 );
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
		$templates[ self::TEMPLATE_FILE ] = __( 'Light brands template', 'hello-elementor-child' );
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
		return self::TEMPLATE_FILE === $slug || 'light-brands.php' === $slug;
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
		return HELLO_ELEMENTOR_CHILD_PATH . 'page-light-brands.php';
	}

	/**
	 * Published page that uses this template (for breadcrumbs / links).
	 */
	public static function get_brands_page(): ?WP_Post {
		$pages = get_posts(
			array(
				'post_type'              => 'page',
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_key'               => '_wp_page_template',
				'meta_value'             => self::TEMPLATE_FILE,
			)
		);

		if ( ! empty( $pages[0] ) && $pages[0] instanceof WP_Post ) {
			return $pages[0];
		}

		// Also accept short slug form some installs store.
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'meta_key'       => '_wp_page_template',
				'meta_value'     => 'light-brands.php',
			)
		);
		if ( ! empty( $pages[0] ) && $pages[0] instanceof WP_Post ) {
			return $pages[0];
		}

		$by_path = get_page_by_path( 'brands' );
		return ( $by_path instanceof WP_Post && 'publish' === $by_path->post_status ) ? $by_path : null;
	}

	/**
	 * Public brands listing URL.
	 */
	public static function get_page_url(): string {
		$page = self::get_brands_page();
		if ( $page instanceof WP_Post ) {
			$link = get_permalink( $page );
			if ( is_string( $link ) && '' !== $link ) {
				return $link;
			}
		}
		return home_url( '/brands/' );
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
	 * @param array<int, array{0?: string, 1?: string}> $crumbs Crumbs.
	 * @return array<int, array{0?: string, 1?: string}>
	 */
	public static function filter_breadcrumb( array $crumbs ): array {
		if ( ! self::is_enabled() ) {
			return $crumbs;
		}

		$page = get_post( self::get_context_page_id() );
		$title = ( $page instanceof WP_Post && '' !== $page->post_title )
			? $page->post_title
			: __( 'شرکت ها', 'hello-elementor-child' );

		$home_label = __( 'خانه', 'hello-elementor-child' );
		$home_url   = home_url( '/' );
		if ( isset( $crumbs[0] ) && is_array( $crumbs[0] ) ) {
			$home_label = isset( $crumbs[0][0] ) ? (string) $crumbs[0][0] : $home_label;
			$home_url   = isset( $crumbs[0][1] ) ? (string) $crumbs[0][1] : $home_url;
		}

		$shop_label = function_exists( 'hello_elementor_child_get_shop_label' )
			? hello_elementor_child_get_shop_label()
			: __( 'فروشگاه', 'hello-elementor-child' );
		$shop_url   = function_exists( 'hello_elementor_child_get_shop_url' )
			? hello_elementor_child_get_shop_url()
			: home_url( '/' );

		return array(
			array( $home_label, $home_url ),
			array( $shop_label, $shop_url ),
			array( $title, '' ),
		);
	}

	/**
	 * Strip Elementor Theme Builder chrome.
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
			'elementor/theme/before_do_archive',
			'elementor/theme/after_do_archive',
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
	 * @param bool   $need_override Whether override is needed.
	 * @param string $location      Location name.
	 */
	public static function disable_elementor_locations( bool $need_override, string $location ): bool {
		return self::is_enabled() ? false : $need_override;
	}

	/**
	 * @param array<int, mixed> $templates Templates.
	 * @param string            $location  Location.
	 * @return array<int, mixed>
	 */
	public static function remove_popup_templates( $templates, $location = '' ) {
		if ( ! self::is_enabled() ) {
			return $templates;
		}
		return ( 'popup' === $location ) ? array() : $templates;
	}

	/**
	 * @param array<int, mixed> $templates Popup templates.
	 * @return array<int, mixed>
	 */
	public static function deny_popup_templates( $templates ) {
		return self::is_enabled() ? array() : $templates;
	}

	/**
	 * @param array<int, mixed> $data    Builder data.
	 * @param int               $post_id Post ID.
	 * @return array<int, mixed>
	 */
	public static function empty_popup_builder_data( $data, $post_id = 0 ) {
		return self::is_enabled() ? array() : $data;
	}

	/**
	 * @param string $template Template path.
	 */
	public static function disable_jet_theme_core_template( $template ) {
		return self::is_enabled() ? '' : $template;
	}

	/**
	 * Unhook Elementor popup printer late.
	 */
	public static function unhook_elementor_popups(): void {
		if ( ! self::is_enabled() ) {
			return;
		}
		remove_all_actions( 'elementor/theme/before_do_popup' );
		remove_all_actions( 'elementor/theme/after_do_popup' );
	}

	/**
	 * Hero + cards context for Twig.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_page_context(): array {
		$page_id = self::get_context_page_id();
		$page    = $page_id > 0 ? get_post( $page_id ) : null;

		$title = __( 'شرکت ها', 'hello-elementor-child' );
		if ( $page instanceof WP_Post && '' !== trim( $page->post_title ) ) {
			$title = $page->post_title;
		}

		$hero_image = '';
		if ( $page instanceof WP_Post ) {
			$thumb_id = (int) get_post_thumbnail_id( $page );
			if ( $thumb_id > 0 ) {
				$maybe = wp_get_attachment_image_url( $thumb_id, 'full' );
				if ( is_string( $maybe ) && '' !== $maybe ) {
					$hero_image = $maybe;
				}
			}
		}
		if ( '' === $hero_image ) {
			$filtered = apply_filters(
				'lk_brands_archive_hero_image_url',
				'https://lookazma.com/wp-content/uploads/2025/12/banner-0223.webp'
			);
			$hero_image = is_string( $filtered ) ? $filtered : '';
		}

		$breadcrumb_html = function_exists( 'hello_elementor_child_get_breadcrumb_html' )
			? hello_elementor_child_get_breadcrumb_html()
			: '';

		return array(
			'title'           => $title,
			'hero_image_url'  => $hero_image,
			'breadcrumb_html' => $breadcrumb_html,
			'more_label'      => __( 'مشاهده شرکت', 'hello-elementor-child' ),
			'cards'           => self::get_brand_cards(),
		);
	}

	/**
	 * Card excerpt: first sentence of excerpt (or content) up to the first period.
	 *
	 * @param WP_Post $brand Brand post.
	 */
	public static function get_card_excerpt( WP_Post $brand ): string {
		$excerpt = trim( (string) $brand->post_excerpt );
		if ( '' === $excerpt ) {
			$excerpt = trim( wp_strip_all_tags( (string) $brand->post_content ) );
		} else {
			$excerpt = trim( wp_strip_all_tags( $excerpt ) );
		}

		$collapsed = preg_replace( '/\s+/u', ' ', $excerpt );
		$excerpt   = is_string( $collapsed ) ? trim( $collapsed ) : '';
		if ( '' === $excerpt ) {
			return '';
		}

		$dot_pos = function_exists( 'mb_strpos' )
			? mb_strpos( $excerpt, '.' )
			: strpos( $excerpt, '.' );

		if ( false !== $dot_pos && $dot_pos >= 0 ) {
			$sentence = function_exists( 'mb_substr' )
				? mb_substr( $excerpt, 0, (int) $dot_pos + 1 )
				: substr( $excerpt, 0, (int) $dot_pos + 1 );
			return trim( (string) $sentence );
		}

		return $excerpt;
	}

	/**
	 * Build card rows for the Twig grid.
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function get_brand_cards(): array {
		$cards = array();
		$posts = get_posts(
			array(
				'post_type'        => Hello_Elementor_Child_Brand_Cpt::POST_TYPE,
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => true,
			)
		);

		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$card = self::map_brand_card( $post );
			if ( null !== $card ) {
				$cards[] = $card;
			}
		}

		return $cards;
	}

	/**
	 * @param WP_Post $brand Brand post.
	 * @return array<string, string>|null
	 */
	private static function map_brand_card( WP_Post $brand ): ?array {
		$link = get_permalink( $brand );
		if ( ! is_string( $link ) || '' === $link ) {
			return null;
		}

		$image_url = '';
		$image_alt = $brand->post_title;
		$thumb_id  = (int) get_post_thumbnail_id( $brand->ID );
		if ( $thumb_id > 0 ) {
			$maybe = wp_get_attachment_image_url( $thumb_id, 'medium' );
			if ( is_string( $maybe ) && '' !== $maybe ) {
				$image_url = $maybe;
			}
			$meta_alt = (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true );
			if ( '' !== $meta_alt ) {
				$image_alt = $meta_alt;
			}
		}

		return array(
			'id'        => (string) $brand->ID,
			'title'     => $brand->post_title,
			'excerpt'   => self::get_card_excerpt( $brand ),
			'link'      => $link,
			'image_url' => $image_url,
			'image_alt' => $image_alt,
		);
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
			HELLO_ELEMENTOR_CHILD_VERSION
		);

		$brands_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-light-brands.css';
		if ( file_exists( $brands_css ) ) {
			wp_enqueue_style(
				'lk-light-brands',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-light-brands.css',
				array( 'lk-light-product' ),
				(string) filemtime( $brands_css )
			);
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
	 * Drop Elementor/Jet chrome + listing assets.
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
			'lk-archive-product-light',
			'lk-spl-css',
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
			'lk-spl-js',
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
		$classes[] = 'lk-light-brands-archive';
		$classes[] = 'lz-chrome';
		return array_values( array_unique( $classes ) );
	}
}
