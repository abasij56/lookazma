<?php
/**
 * Light brand CPT single — visual clone of product tag (brand) archive.
 *
 * New template (does not include taxonomy-product_tag-light.php).
 * Products come from product_tag(s) assigned to the brand post.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Forces brand singles onto the Timber light template.
 */
final class Hello_Elementor_Child_Custom_Brand_Single {

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_filter( 'timber/locations', array( __CLASS__, 'add_timber_locations' ) );

		add_filter( 'woocommerce_get_breadcrumb', array( __CLASS__, 'filter_breadcrumb' ), 20 );

		// Native hierarchy + early force (beat Elementor/Jet like product singles).
		add_filter( 'single_template', array( __CLASS__, 'filter_single_template' ), PHP_INT_MAX );
		add_filter( 'single_template_hierarchy', array( __CLASS__, 'filter_single_template_hierarchy' ), PHP_INT_MAX );
		add_action( 'template_redirect', array( __CLASS__, 'unhook_elementor_chrome' ), -6 );
		add_action( 'template_redirect', array( __CLASS__, 'force_light_template' ), -1 );
		add_filter( 'template_include', array( __CLASS__, 'maybe_use_custom_template' ), PHP_INT_MAX );
		add_filter( 'elementor/theme/need_override_location', array( __CLASS__, 'disable_elementor_locations' ), PHP_INT_MAX, 2 );
		add_filter( 'elementor/theme/get_location_templates', array( __CLASS__, 'remove_popup_templates' ), PHP_INT_MAX, 2 );
		add_filter( 'elementor/theme/get_location_templates/popup', array( __CLASS__, 'deny_popup_templates' ), PHP_INT_MAX );
		add_filter( 'elementor/frontend/builder_content_data', array( __CLASS__, 'empty_popup_builder_data' ), PHP_INT_MAX, 2 );
		add_action( 'wp_footer', array( __CLASS__, 'unhook_elementor_popups' ), 0 );
		add_filter( 'jet-theme-core/template-include', array( __CLASS__, 'disable_jet_theme_core_template' ), PHP_INT_MAX );
		add_filter( 'jet-woo-builder/custom-archive-template', array( __CLASS__, 'disable_jet_archive_template' ), PHP_INT_MAX );
		add_filter( 'jet-woo-builder/custom-shop-template', array( __CLASS__, 'disable_jet_archive_template' ), PHP_INT_MAX );
		add_filter( 'jet-woo-builder/custom-single-template', array( __CLASS__, 'disable_jet_archive_template' ), PHP_INT_MAX );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_light_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'dequeue_listing_assets' ), 100 );
		add_action( 'wp_print_styles', array( __CLASS__, 'dequeue_listing_assets' ), 100 );
		add_action( 'wp_print_scripts', array( __CLASS__, 'dequeue_listing_assets' ), 100 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ), 99 );
	}

	/**
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
	 * Whether the light brand single is active.
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
		if ( ! is_singular( Hello_Elementor_Child_Brand_Cpt::POST_TYPE ) ) {
			return false;
		}
		$post = get_queried_object();
		return $post instanceof WP_Post && Hello_Elementor_Child_Brand_Cpt::POST_TYPE === $post->post_type;
	}

	/**
	 * Brand post for the current request.
	 */
	public static function get_brand_post(): ?WP_Post {
		if ( ! self::is_enabled() ) {
			return null;
		}
		$post = get_queried_object();
		return $post instanceof WP_Post ? $post : null;
	}

	/**
	 * Primary product_tag used to list products on this brand page.
	 *
	 * Prefers a tag with the same slug as the brand post, then first assigned tag,
	 * then a tag looked up by brand slug (even if not yet assigned on the post).
	 *
	 * @param WP_Post|null $brand Brand post.
	 */
	public static function get_primary_product_tag( ?WP_Post $brand = null ): ?WP_Term {
		if ( ! $brand instanceof WP_Post ) {
			$brand = self::get_brand_post();
		}
		if ( ! $brand instanceof WP_Post ) {
			return null;
		}

		$terms = get_the_terms( $brand->ID, 'product_tag' );
		if ( is_array( $terms ) && ! empty( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( $term instanceof WP_Term && $term->slug === $brand->post_name ) {
					return $term;
				}
			}
			$first = $terms[0];
			return $first instanceof WP_Term ? $first : null;
		}

		$by_slug = get_term_by( 'slug', $brand->post_name, 'product_tag' );
		return $by_slug instanceof WP_Term ? $by_slug : null;
	}

	/**
	 * Products per page (match tag archive).
	 */
	public static function get_per_page(): int {
		return 12;
	}

	/**
	 * Light template PHP path (prefer enhanced when enabled, then light entry).
	 */
	public static function get_light_template_path(): string {
		$brand = self::get_brand_post();
		if ( $brand instanceof WP_Post
			&& class_exists( 'Hello_Elementor_Child_Brand_Enhanced_Single' )
			&& Hello_Elementor_Child_Brand_Enhanced_Single::is_enabled_for( $brand )
		) {
			$enhanced = HELLO_ELEMENTOR_CHILD_PATH . 'single-brand-enhanced.php';
			if ( file_exists( $enhanced ) ) {
				return $enhanced;
			}
		}

		$light = HELLO_ELEMENTOR_CHILD_PATH . 'single-brand-light.php';
		if ( file_exists( $light ) ) {
			return $light;
		}
		$native = HELLO_ELEMENTOR_CHILD_PATH . 'single-brand.php';
		return file_exists( $native ) ? $native : $light;
	}

	/**
	 * Whether the current brand uses lk_brand product meta (enhanced template).
	 */
	public static function uses_lk_brand_products(): bool {
		return class_exists( 'Hello_Elementor_Child_Brand_Enhanced_Single' )
			&& Hello_Elementor_Child_Brand_Enhanced_Single::is_active();
	}

	/**
	 * Force WP hierarchy to prefer our brand templates.
	 *
	 * @param array<int, string> $templates Hierarchy.
	 * @return array<int, string>
	 */
	public static function filter_single_template_hierarchy( array $templates ): array {
		if ( ! self::is_brand_request() ) {
			return $templates;
		}
		return array_values(
			array_unique(
				array_merge(
					array( 'single-brand-enhanced.php', 'single-brand-light.php', 'single-brand.php' ),
					$templates
				)
			)
		);
	}

	/**
	 * @param string $template Resolved single template.
	 */
	public static function filter_single_template( string $template ): string {
		if ( ! self::is_brand_request() ) {
			return $template;
		}
		$custom = self::get_light_template_path();
		return file_exists( $custom ) ? $custom : $template;
	}

	/**
	 * Brand singular without relying only on is_singular() timing.
	 */
	public static function is_brand_request(): bool {
		if ( wp_doing_cron() || ( is_admin() && ! wp_doing_ajax() ) ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		if ( self::is_enabled() ) {
			return true;
		}

		$post = get_queried_object();
		if ( $post instanceof WP_Post && Hello_Elementor_Child_Brand_Cpt::POST_TYPE === $post->post_type ) {
			return true;
		}

		global $wp_query;
		if ( $wp_query instanceof WP_Query ) {
			$qv_type = $wp_query->get( 'post_type' );
			if ( Hello_Elementor_Child_Brand_Cpt::POST_TYPE === $qv_type && ( $wp_query->is_single || $wp_query->get( 'name' ) || $wp_query->get( 'p' ) ) ) {
				return true;
			}
			if ( $wp_query->get( 'lk_brand' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<int, array{0?: string, 1?: string}> $crumbs Crumbs.
	 * @return array<int, array{0?: string, 1?: string}>
	 */
	public static function filter_breadcrumb( array $crumbs ): array {
		if ( ! self::is_enabled() ) {
			return $crumbs;
		}

		$brand = self::get_brand_post();
		if ( ! $brand instanceof WP_Post ) {
			return $crumbs;
		}

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

		$brands_label = __( 'شرکت ها', 'hello-elementor-child' );
		$brands_url   = Hello_Elementor_Child_Brand_Cpt::get_archive_url();

		return array(
			array( $home_label, $home_url ),
			array( $shop_label, $shop_url ),
			array( $brands_label, $brands_url ),
			array(
				sprintf(
					/* translators: %s: brand name */
					__( 'محصولات %s', 'hello-elementor-child' ),
					$brand->post_title
				),
				'',
			),
		);
	}

	/**
	 * Strip Elementor Theme Builder chrome (same approach as light product singles).
	 */
	public static function unhook_elementor_chrome(): void {
		if ( ! self::is_brand_request() ) {
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
	 * Hard takeover before Elementor/Jet templates.
	 */
	public static function force_light_template(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		if ( ! self::is_brand_request() ) {
			return;
		}

		$custom = self::get_light_template_path();
		if ( ! file_exists( $custom ) ) {
			return;
		}

		self::unhook_elementor_chrome();
		status_header( 200 );
		include $custom;
		exit;
	}

	/**
	 * @param string $template Current template.
	 */
	public static function maybe_use_custom_template( string $template ): string {
		if ( ! self::is_brand_request() ) {
			return $template;
		}
		$custom = self::get_light_template_path();
		return file_exists( $custom ) ? $custom : $template;
	}

	/**
	 * @param bool   $need_override Whether override is needed.
	 * @param string $location      Location name.
	 */
	public static function disable_elementor_locations( bool $need_override, string $location ): bool {
		if ( ! self::is_brand_request() ) {
			return $need_override;
		}
		if ( in_array( $location, array( 'header', 'footer', 'single', 'archive', 'product_archive', 'product-archive', 'popup' ), true ) ) {
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
		if ( ! self::is_brand_request() ) {
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
		return self::is_brand_request() ? array() : $templates;
	}

	/**
	 * @param mixed $data    Builder data.
	 * @param mixed $post_id Document id.
	 * @return mixed
	 */
	public static function empty_popup_builder_data( $data, $post_id = 0 ) {
		return self::is_brand_request() ? array() : $data;
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
		if ( ! self::is_brand_request() ) {
			return $template;
		}
		$custom = self::get_light_template_path();
		return file_exists( $custom ) ? $custom : $template;
	}

	/**
	 * @param mixed $template_id Jet template ID.
	 * @return mixed
	 */
	public static function disable_jet_archive_template( $template_id ) {
		return self::is_brand_request() ? 0 : $template_id;
	}

	/**
	 * Front-end assets (reuse tag/shop light CSS).
	 */
	public static function enqueue_light_assets(): void {
		if ( ! self::is_brand_request() ) {
			return;
		}

		if ( class_exists( 'Hello_Elementor_Child_Archive_Product_Filter' ) ) {
			Hello_Elementor_Child_Archive_Product_Filter::enqueue_assets();
		}

		$card_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-product-shop-cards.css';
		if ( file_exists( $card_css ) ) {
			wp_enqueue_style(
				'lk-archive-shop-cards',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-product-shop-cards.css',
				array( HELLO_ELEMENTOR_CHILD_VAZIRMATN_HANDLE ),
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

		$tag_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-light-tag.css';
		if ( file_exists( $tag_css ) ) {
			wp_enqueue_style(
				'lk-light-tag',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-light-tag.css',
				array( 'lk-light-product' ),
				(string) filemtime( $tag_css )
			);
		}

		$cat_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-light-category.css';
		if ( file_exists( $cat_css ) ) {
			wp_enqueue_style(
				'lk-light-category',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-light-category.css',
				array( 'lk-light-tag' ),
				(string) filemtime( $cat_css )
			);
		}

		wp_add_inline_style( 'lk-light-product', 'body.lk-light-brand{--lk-lpt-cols:4;}' );

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

		$tabs_js = HELLO_ELEMENTOR_CHILD_PATH . 'assets/js/light-category-tabs.js';
		if ( file_exists( $tabs_js ) ) {
			wp_enqueue_script(
				'lk-light-category-tabs',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/js/light-category-tabs.js',
				array(),
				(string) filemtime( $tabs_js ),
				true
			);
		}
	}

	/**
	 * Drop Elementor/Jet chrome + listing assets.
	 */
	public static function dequeue_listing_assets(): void {
		if ( ! self::is_brand_request() ) {
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
					|| false !== strpos( $h, 'menu-cart' )
				) {
					wp_dequeue_script( $handle );
				}
			}
		}
	}

	/**
	 * @param array<int, string> $classes Classes.
	 * @return array<int, string>
	 */
	public static function body_class( array $classes ): array {
		if ( ! self::is_brand_request() ) {
			return $classes;
		}

		$classes[] = 'lk-light-product';
		$classes[] = 'lk-light-tag';
		$classes[] = 'lk-light-brand';
		if ( self::uses_lk_brand_products() ) {
			$classes[] = 'lk-brand-enhanced';
		}
		$classes[] = 'lk-shop-cards-archive';
		$classes[] = 'lz-chrome';

		$skip = array(
			'lk-single-product-light'       => true,
			'elementor-default'             => true,
			'elementor-template-full-width' => true,
			'elementor-page'                => true,
			'hello-elementor-default'       => true,
			'brand-template-default'        => true,
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
}
