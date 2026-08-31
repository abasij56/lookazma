<?php
/**
 * Light compare page template — product comparison; opt-in via Page → Template.
 *
 * Create a Page, set Template to "Light compare template" (any slug; `/compare/` if free).
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product comparison page with shared Lookazma header/footer.
 */
final class Hello_Elementor_Child_Light_Compare_Template {

	public const TEMPLATE_FILE = 'page-templates/light-compare.php';

	public const COMPARE_PATH = 'compare';

	public const QUERY_VAR = 'lk_compare';

	private const REWRITE_FLUSH_OPTION = 'hello_elementor_child_compare_rewrite_v2';

	/**
	 * When serving /compare/ directly, force context to the template page ID.
	 *
	 * @var int
	 */
	private static int $forced_page_id = 0;

	/**
	 * Cached flag: current HTTP request targets /compare/.
	 *
	 * @var bool|null
	 */
	private static ?bool $request_is_compare = null;

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'purge_compare_old_slug_meta' ), 1 );
		add_action( 'init', array( __CLASS__, 'register_redirect_guards' ), 2 );
		add_action( 'init', array( __CLASS__, 'register_rewrites' ), 5 );
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrite_rules' ), 999 );

		add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
		add_action( 'parse_query', array( __CLASS__, 'parse_compare_query' ), 1 );
		add_action( 'wp', array( __CLASS__, 'serve_compare_before_redirects' ), 0 );

		add_filter( 'theme_page_templates', array( __CLASS__, 'register_page_template' ), 20, 4 );
		add_filter( 'timber/locations', array( __CLASS__, 'add_timber_locations' ) );
		add_filter( 'woocommerce_get_breadcrumb', array( __CLASS__, 'filter_breadcrumb' ), 20 );
		add_filter( 'wp_robots', array( __CLASS__, 'filter_robots' ) );

		add_filter( 'redirect_canonical', array( __CLASS__, 'filter_redirect_canonical' ), 1, 2 );

		add_action( 'template_redirect', array( __CLASS__, 'hijack_compare_route' ), PHP_INT_MIN );
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
		$templates[ self::TEMPLATE_FILE ] = __( 'Light compare template', 'hello-elementor-child' );
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
		return self::TEMPLATE_FILE === $slug || 'light-compare.php' === $slug;
	}

	/**
	 * Current page ID for this request.
	 */
	public static function get_context_page_id(): int {
		if ( self::$forced_page_id > 0 ) {
			return self::$forced_page_id;
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
		return self::$forced_page_id > 0 || self::page_uses_template( self::get_context_page_id() );
	}

	/**
	 * PHP entry path.
	 */
	public static function get_template_path(): string {
		return HELLO_ELEMENTOR_CHILD_PATH . 'page-light-compare.php';
	}

	/**
	 * Published page that uses Light compare template (ignores slug).
	 */
	public static function get_compare_page(): ?WP_Post {
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
				'meta_query'             => array(
					'relation' => 'OR',
					array(
						'key'   => '_wp_page_template',
						'value' => self::TEMPLATE_FILE,
					),
					array(
						'key'   => '_wp_page_template',
						'value' => 'light-compare.php',
					),
				),
			)
		);

		if ( ! empty( $pages[0] ) && $pages[0] instanceof WP_Post ) {
			return $pages[0];
		}

		return null;
	}

	/**
	 * Dedicated rewrite — /compare/ never resolves via pagename (avoids old-slug → old-comp).
	 */
	public static function register_rewrites(): void {
		add_rewrite_tag( '%' . self::QUERY_VAR . '%', '([0-9]+)' );
		add_rewrite_rule(
			'^' . self::COMPARE_PATH . '/?$',
			'index.php?' . self::QUERY_VAR . '=1',
			'top'
		);
	}

	/**
	 * @param array<int, string> $vars Query vars.
	 * @return array<int, string>
	 */
	public static function register_query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Flush permalinks once after adding the compare rewrite rule.
	 */
	public static function maybe_flush_rewrite_rules(): void {
		if ( get_option( self::REWRITE_FLUSH_OPTION ) ) {
			return;
		}
		flush_rewrite_rules( false );
		update_option( self::REWRITE_FLUSH_OPTION, '1', true );
	}

	/**
	 * Main query: treat lk_compare=1 as the compare template page (not a 404).
	 *
	 * @param WP_Query $query Query.
	 */
	public static function parse_compare_query( WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( ! self::query_var_is_compare() && ! self::request_uri_is_compare() ) {
			return;
		}

		$page = self::get_compare_page();
		if ( ! $page instanceof WP_Post ) {
			return;
		}

		$query->set( 'page_id', (int) $page->ID );
		$query->set( 'pagename', '' );
		$query->set( 'name', '' );
		$query->set( self::QUERY_VAR, '1' );
		$query->is_page     = true;
		$query->is_singular = true;
		$query->is_home     = false;
		$query->is_404      = false;
	}

	/**
	 * Render before template_redirect (wp_old_slug_redirect runs at priority 10).
	 */
	public static function serve_compare_before_redirects(): void {
		if ( wp_doing_ajax() || is_admin() ) {
			return;
		}

		if ( ! self::should_serve_compare_route() ) {
			return;
		}

		$page = self::get_compare_page();
		if ( ! $page instanceof WP_Post ) {
			return;
		}

		self::render_compare_page( $page );
	}

	/**
	 * Whether this request should load the compare template at /compare/.
	 */
	private static function should_serve_compare_route(): bool {
		return self::query_var_is_compare() || self::request_uri_is_compare();
	}

	/**
	 * lk_compare query var present.
	 */
	private static function query_var_is_compare(): bool {
		$raw = get_query_var( self::QUERY_VAR, '' );
		return '1' === (string) $raw || 1 === (int) $raw;
	}

	/**
	 * Raw REQUEST_URI path is exactly /compare (works before main query runs).
	 */
	public static function request_uri_is_compare(): bool {
		if ( null !== self::$request_is_compare ) {
			return self::$request_is_compare;
		}

		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path = wp_parse_url( $uri, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			self::$request_is_compare = false;
			return false;
		}

		$path = trim( $path, '/' );
		$home = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( is_string( $home ) && '' !== trim( $home, '/' ) ) {
			$prefix = trim( $home, '/' ) . '/';
			if ( str_starts_with( $path, $prefix ) ) {
				$path = substr( $path, strlen( $prefix ) );
			}
		}

		self::$request_is_compare = ( self::COMPARE_PATH === $path );
		return self::$request_is_compare;
	}

	/**
	 * Delete WordPress "old slug" records that redirect /compare/ → another permalink.
	 */
	public static function purge_compare_old_slug_meta(): void {
		global $wpdb;

		if ( isset( $wpdb->postmeta ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s",
					'_wp_old_slug',
					self::COMPARE_PATH
				)
			);
		}

		$page = self::get_compare_page();
		if ( $page instanceof WP_Post ) {
			delete_post_meta( (int) $page->ID, '_wp_old_slug' );
		}
	}

	/**
	 * Block theme/plugin redirects when the visitor requested /compare/.
	 */
	public static function register_redirect_guards(): void {
		add_filter( 'old_slug_redirect_url', array( __CLASS__, 'block_redirects_for_compare_path' ) );
		add_filter( 'redirect_guess_404_permalink', array( __CLASS__, 'block_redirect_guess_404' ), 1, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'detach_compare_redirect_handlers' ), 0 );

		add_filter( 'pre_wp_redirect', array( __CLASS__, 'block_pre_wp_redirect_for_compare' ), 1, 2 );

		// Rank Math Redirections.
		add_filter( 'rank_math/frontend/redirect', array( __CLASS__, 'block_rank_math_redirect' ), 1, 2 );
		add_filter( 'rank_math/redirection/pre_search', array( __CLASS__, 'block_rank_math_pre_search' ), 1, 1 );

		// Yoast SEO Premium redirects.
		add_filter( 'wpseo_premium_redirect_url', array( __CLASS__, 'block_redirects_for_compare_path' ) );
		add_filter( 'wpseo_redirect_url', array( __CLASS__, 'block_redirects_for_compare_path' ) );

		// Redirection plugin (John Godley) — cancel matched redirects for /compare/.
		add_filter( 'redirection_redirect_matched', array( __CLASS__, 'block_redirection_matched' ), 1, 2 );
	}

	/**
	 * @param mixed $value Redirect target or truthy redirect flag.
	 * @return mixed
	 */
	public static function block_redirects_for_compare_path( $value ) {
		if ( self::request_uri_is_compare() || self::query_var_is_compare() ) {
			return false;
		}
		return $value;
	}

	/**
	 * WordPress 404 → guesses old permalink (compare → old-comp). Block it.
	 *
	 * @param string|false $redirect_url  Redirect target.
	 * @param string       $requested_url Requested URL.
	 * @return string|false
	 */
	public static function block_redirect_guess_404( $redirect_url, $requested_url ) {
		unset( $requested_url );

		if ( self::request_uri_is_compare() || self::query_var_is_compare() ) {
			return false;
		}

		return $redirect_url;
	}

	/**
	 * @param string|false $location Redirect URL.
	 * @param int          $status   HTTP status.
	 * @return string|false
	 */
	public static function block_pre_wp_redirect_for_compare( $location, $status ) {
		unset( $status );

		if ( self::request_uri_is_compare() || self::query_var_is_compare() ) {
			return false;
		}

		return $location;
	}

	/**
	 * @param mixed $redirect Rank Math redirect payload.
	 * @param mixed $url      Requested URL.
	 * @return mixed
	 */
	public static function block_rank_math_redirect( $redirect, $url = null ) {
		unset( $url );

		if ( self::request_uri_is_compare() || self::query_var_is_compare() ) {
			return false;
		}

		return $redirect;
	}

	/**
	 * @param mixed $pre_search Pre-search flag.
	 * @return mixed
	 */
	public static function block_rank_math_pre_search( $pre_search ) {
		if ( self::request_uri_is_compare() || self::query_var_is_compare() ) {
			return true;
		}

		return $pre_search;
	}

	/**
	 * @param bool  $matched Whether Redirection plugin matched a rule.
	 * @param mixed $redirect Redirect row.
	 */
	public static function block_redirection_matched( bool $matched, $redirect ): bool {
		unset( $redirect );

		if ( self::request_uri_is_compare() || self::query_var_is_compare() ) {
			return false;
		}

		return $matched;
	}

	/**
	 * Remove core handlers that send /compare/ to a renamed slug (e.g. old-comp).
	 */
	public static function detach_compare_redirect_handlers(): void {
		if ( ! self::request_uri_is_compare() && ! self::query_var_is_compare() ) {
			return;
		}

		remove_action( 'template_redirect', 'wp_old_slug_redirect' );
		remove_action( 'template_redirect', 'redirect_canonical' );
	}

	/**
	 * Public compare URL — always /compare/ (not the WP page slug like old-comp).
	 */
	public static function get_page_url(): string {
		$page = self::get_compare_page();
		$url  = home_url( user_trailingslashit( self::COMPARE_PATH ) );

		return (string) apply_filters( 'lk_compare_page_url', $url, $page instanceof WP_Post ? $page : null );
	}

	/**
	 * Fallback on template_redirect (primary serve is on `wp` priority 0).
	 */
	public static function hijack_compare_route(): void {
		self::serve_compare_before_redirects();
	}

	/**
	 * Stop WordPress / plugins from canonical-redirecting compare URLs away.
	 *
	 * @param string|false $redirect_url  Redirect target.
	 * @param string       $requested_url Requested URL.
	 * @return string|false
	 */
	public static function filter_redirect_canonical( $redirect_url, $requested_url ) {
		unset( $requested_url );

		if ( self::$forced_page_id > 0 || self::request_uri_is_compare() || self::query_var_is_compare() ) {
			return false;
		}

		if ( self::page_uses_template( (int) get_queried_object_id() ) ) {
			return false;
		}

		return $redirect_url;
	}

	/**
	 * Boot Timber entry for the compare template page.
	 *
	 * @param WP_Post $page Compare template page.
	 */
	private static function render_compare_page( WP_Post $page ): void {
		self::$forced_page_id = (int) $page->ID;
		self::prime_query_for_page( $page );
		self::unhook_elementor_chrome();

		status_header( 200 );

		$path = self::get_template_path();
		if ( ! file_exists( $path ) ) {
			return;
		}

		include $path;
		exit;
	}

	/**
	 * Align main query with the compare template page.
	 *
	 * @param WP_Post $page Page post.
	 */
	private static function prime_query_for_page( WP_Post $page ): void {
		global $wp_query, $post;

		$post = $page; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		if ( $wp_query instanceof WP_Query ) {
			$wp_query->is_page     = true;
			$wp_query->is_singular = true;
			$wp_query->is_home     = false;
			$wp_query->is_404      = false;
			$wp_query->queried_object    = $page;
			$wp_query->queried_object_id = (int) $page->ID;
			$wp_query->post              = $page;
			$wp_query->posts             = array( $page );
			$wp_query->post_count        = 1;
			$wp_query->found_posts       = 1;
			$wp_query->max_num_pages     = 1;
		}
	}

	/**
	 * Whether the current request targets the /compare/ path.
	 */
	public static function is_compare_slug_request(): bool {
		return self::request_uri_is_compare() || self::query_var_is_compare();
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_page_context(): array {
		$page_id = self::get_context_page_id();
		$page    = $page_id > 0 ? get_post( $page_id ) : null;

		$title = __( 'مقایسه محصولات', 'hello-elementor-child' );
		if ( $page instanceof WP_Post && '' !== trim( $page->post_title ) ) {
			$title = $page->post_title;
		}

		$breadcrumb_html = function_exists( 'hello_elementor_child_get_breadcrumb_html' )
			? hello_elementor_child_get_breadcrumb_html()
			: '';

		return array(
			'title'           => $title,
			'breadcrumb_html' => $breadcrumb_html,
			'shop_url'        => function_exists( 'hello_elementor_child_get_shop_url' )
				? hello_elementor_child_get_shop_url()
				: home_url( '/فروشگاه/' ),
		);
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

		$page  = get_post( self::get_context_page_id() );
		$title = ( $page instanceof WP_Post && '' !== $page->post_title )
			? $page->post_title
			: __( 'مقایسه محصولات', 'hello-elementor-child' );

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
	 * @param array<string, bool|string> $robots Robots directives.
	 * @return array<string, bool|string>
	 */
	public static function filter_robots( array $robots ): array {
		if ( ! self::is_enabled() ) {
			return $robots;
		}
		$robots['noindex']   = true;
		$robots['nofollow']  = true;
		return $robots;
	}

	/**
	 * Strip Elementor Theme Builder chrome.
	 */
	public static function unhook_elementor_chrome(): void {
		if ( ! self::is_enabled() && ! self::is_compare_slug_request() ) {
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

		if ( class_exists( 'Hello_Elementor_Child_Product_Compare' ) ) {
			Hello_Elementor_Child_Product_Compare::enqueue_assets( true );
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
		$classes[] = 'lk-light-compare';
		$classes[] = 'lz-chrome';
		return array_values( array_unique( $classes ) );
	}
}
