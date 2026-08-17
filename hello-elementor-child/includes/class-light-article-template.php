<?php
/**
 * Light article template — native chrome; opt-in via Page → Template.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Magazine / article listing with shared Lookazma header/footer.
 */
final class Hello_Elementor_Child_Light_Article_Template {

	public const TEMPLATE_FILE = 'page-templates/light-article.php';

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_filter( 'theme_page_templates', array( __CLASS__, 'register_page_template' ), 20, 4 );
		add_filter( 'timber/locations', array( __CLASS__, 'add_timber_locations' ) );

		add_action( 'pre_get_posts', array( __CLASS__, 'fix_paged_page_query' ), 1 );
		add_filter( 'redirect_canonical', array( __CLASS__, 'disable_canonical_on_paged' ), 10, 2 );

		add_action( 'template_redirect', array( __CLASS__, 'unhook_elementor_chrome' ), -6 );
		add_action( 'template_redirect', array( __CLASS__, 'force_template' ), -1 );
		add_filter( 'template_include', array( __CLASS__, 'maybe_use_template' ), PHP_INT_MAX );
		add_filter( 'elementor/theme/need_override_location', array( __CLASS__, 'disable_elementor_locations' ), PHP_INT_MAX, 2 );
		add_filter( 'elementor/theme/get_location_templates', array( __CLASS__, 'remove_popup_templates' ), PHP_INT_MAX, 2 );
		add_filter( 'elementor/theme/get_location_templates/popup', array( __CLASS__, 'deny_popup_templates' ), PHP_INT_MAX );
		add_filter( 'elementor/frontend/builder_content_data', array( __CLASS__, 'empty_popup_builder_data' ), PHP_INT_MAX, 2 );
		add_action( 'wp_footer', array( __CLASS__, 'unhook_elementor_popups' ), 0 );
		add_filter( 'jet-theme-core/template-include', array( __CLASS__, 'disable_jet_theme_core_template' ), PHP_INT_MAX );

		add_action( 'wp_ajax_lk_article_load_posts', array( __CLASS__, 'ajax_load_posts' ) );
		add_action( 'wp_ajax_nopriv_lk_article_load_posts', array( __CLASS__, 'ajax_load_posts' ) );

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
		$templates[ self::TEMPLATE_FILE ] = __( 'Light article template', 'hello-elementor-child' );
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
		return self::TEMPLATE_FILE === $slug || 'light-article.php' === $slug;
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
	 * Map /page/N/ on this static page to paged without 404.
	 *
	 * @param WP_Query $query Query.
	 */
	public static function fix_paged_page_query( WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$page_id = (int) $query->get( 'page_id' );
		if ( $page_id <= 0 && $query->get( 'pagename' ) ) {
			$page = get_page_by_path( (string) $query->get( 'pagename' ) );
			if ( $page instanceof WP_Post ) {
				$page_id = (int) $page->ID;
			}
		}
		if ( $page_id <= 0 || ! self::page_uses_template( $page_id ) ) {
			return;
		}

		$page_num = (int) $query->get( 'page' );
		$paged    = (int) $query->get( 'paged' );
		if ( $page_num > 1 && $paged < 2 ) {
			$query->set( 'paged', $page_num );
		}
		$query->is_404  = false;
		$query->is_page = true;
	}

	/**
	 * @param string|false $redirect Redirect URL.
	 * @param string       $requested Requested URL.
	 * @return string|false
	 */
	public static function disable_canonical_on_paged( $redirect, $requested = '' ) {
		unset( $requested );
		if ( self::is_enabled() && ( (int) get_query_var( 'paged' ) > 1 || (int) get_query_var( 'page' ) > 1 ) ) {
			return false;
		}
		return $redirect;
	}

	/**
	 * PHP entry path.
	 */
	public static function get_template_path(): string {
		return HELLO_ELEMENTOR_CHILD_PATH . 'page-light-article.php';
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

		$card_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-product-shop-cards.css';
		if ( file_exists( $card_css ) ) {
			wp_enqueue_style(
				'lk-shop-cards',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-product-shop-cards.css',
				array( 'lk-light-product' ),
				(string) filemtime( $card_css )
			);
		}

		$article_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/light-article.css';
		if ( file_exists( $article_css ) ) {
			wp_enqueue_style(
				'lk-light-article',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/css/light-article.css',
				array( 'lk-light-product', 'lk-lpt-breadcrumb' ),
				(string) filemtime( $article_css )
			);
		}

		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'wc-add-to-cart' );
		wp_enqueue_script( 'wc-cart-fragments' );

		if ( class_exists( 'Hello_Elementor_Child_Archive_Product_Filter' ) ) {
			Hello_Elementor_Child_Archive_Product_Filter::enqueue_light_archive_assets();
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

		$article_js = HELLO_ELEMENTOR_CHILD_PATH . 'assets/js/light-article.js';
		if ( file_exists( $article_js ) ) {
			wp_enqueue_script(
				'lk-light-article',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/js/light-article.js',
				array(),
				(string) filemtime( $article_js ),
				true
			);
			wp_localize_script(
				'lk-light-article',
				'lkArticle',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'action'  => 'lk_article_load_posts',
					'nonce'   => wp_create_nonce( 'lk_article_posts' ),
					'pageId'  => self::get_context_page_id(),
				)
			);
		}
	}

	/**
	 * Drop Elementor/Jet chrome.
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
		$classes[] = 'lk-light-article';
		$classes[] = 'lk-shop-cards-archive';
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
	 * Posts + pagination for a magazine page.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_listing_data( int $page_id, int $paged ): array {
		$paged = max( 1, $paged );
		$query = new WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 9,
				'paged'          => $paged,
			)
		);

		$posts = array();
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$thumb = get_the_post_thumbnail_url( $post, 'medium_large' );
			$posts[] = array(
				'id'    => (int) $post->ID,
				'url'   => get_permalink( $post ),
				'title' => get_the_title( $post ),
				'date'  => get_the_date( '', $post ),
				'image' => is_string( $thumb ) ? $thumb : '',
			);
		}

		$pagination = '';
		if ( (int) $query->max_num_pages > 1 && $page_id > 0 ) {
			$permalink = get_permalink( $page_id );
			$links     = paginate_links(
				array(
					'base'      => trailingslashit( (string) $permalink ) . '%_%',
					'format'    => 'page/%#%/',
					'current'   => $paged,
					'total'     => (int) $query->max_num_pages,
					'type'      => 'list',
					'mid_size'  => 2,
					'end_size'  => 1,
					'prev_text' => '&rarr;',
					'next_text' => '&larr;',
				)
			);
			$pagination = is_string( $links ) ? $links : '';
		}
		wp_reset_postdata();

		return array(
			'posts'           => $posts,
			'pagination_html' => $pagination,
			'more_label'      => __( 'ادامه مطلب', 'hello-elementor-child' ),
			'paged'           => $paged,
			'url'             => self::get_paged_url( $page_id, $paged ),
		);
	}

	/**
	 * Public URL for a listing page.
	 */
	public static function get_paged_url( int $page_id, int $paged ): string {
		$permalink = $page_id > 0 ? (string) get_permalink( $page_id ) : '';
		if ( '' === $permalink ) {
			return '';
		}
		if ( $paged < 2 ) {
			return $permalink;
		}
		return trailingslashit( $permalink ) . 'page/' . $paged . '/';
	}

	/**
	 * AJAX magazine pagination.
	 */
	public static function ajax_load_posts(): void {
		check_ajax_referer( 'lk_article_posts', 'nonce' );

		$page_id = isset( $_POST['page_id'] ) ? absint( wp_unslash( $_POST['page_id'] ) ) : 0;
		$paged   = isset( $_POST['paged'] ) ? absint( wp_unslash( $_POST['paged'] ) ) : 1;
		if ( $page_id <= 0 || ! self::page_uses_template( $page_id ) ) {
			wp_send_json_error( array( 'message' => 'invalid' ), 400 );
		}

		$listing = self::get_listing_data( $page_id, $paged );
		$html    = '';
		if ( class_exists( '\Timber\Timber' ) ) {
			$html = (string) \Timber\Timber::compile( 'pages/partials/light-article-cards.twig', array( 'article' => $listing ) );
		}

		wp_send_json_success(
			array(
				'html'        => $html,
				'pagination'  => $listing['pagination_html'],
				'url'         => $listing['url'],
				'paged'       => $listing['paged'],
			)
		);
	}

	/**
	 * Page content for Twig.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_page_content(): array {
		$page_id = self::get_context_page_id();
		$title   = $page_id > 0 ? get_the_title( $page_id ) : __( 'مجله لوک آزما', 'hello-elementor-child' );
		if ( ! is_string( $title ) || '' === trim( $title ) ) {
			$title = __( 'مجله لوک آزما', 'hello-elementor-child' );
		}

		$paged   = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
		$listing = self::get_listing_data( $page_id, $paged );

		return array_merge(
			$listing,
			array(
				'title'           => $title,
				'breadcrumb_html' => function_exists( 'hello_elementor_child_get_breadcrumb_html' )
					? hello_elementor_child_get_breadcrumb_html()
					: '',
				'carousel_html'   => self::get_product_cards_html(
					array(
						'orderby'        => 'rand',
						'posts_per_page' => 8,
					)
				),
				'latest_html'     => self::get_product_cards_html(
					array(
						'orderby'        => 'date',
						'order'          => 'DESC',
						'posts_per_page' => 2,
					)
				),
				'carousel_title'  => __( 'شگفت انگیزها', 'hello-elementor-child' ),
				'latest_title'    => __( 'آخرین محصولات', 'hello-elementor-child' ),
				'posts_title'     => __( 'آخرین مطالب', 'hello-elementor-child' ),
			)
		);
	}

	/**
	 * @param array<string, mixed> $args WP_Query args.
	 */
	private static function get_product_cards_html( array $args ): string {
		if ( ! function_exists( 'wc_get_product' ) || ! class_exists( 'Hello_Elementor_Child_Archive_Product_Filter' ) ) {
			return '';
		}

		$args  = array_merge(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 8,
			),
			$args
		);
		$query = new WP_Query( $args );
		$html  = '';
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$product = wc_get_product( $post->ID );
			if ( $product instanceof WC_Product ) {
				$html .= Hello_Elementor_Child_Archive_Product_Filter::render_product_card_html( $product, 'shop' );
			}
		}
		wp_reset_postdata();

		return $html;
	}
}
