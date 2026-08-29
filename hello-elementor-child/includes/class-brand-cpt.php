<?php
/**
 * Brand custom post type (CPT).
 *
 * Archive listing is a WP Page with "Light brands template".
 * Singles at /brands/{slug}/.
 * Uses WooCommerce product_cat + product_tag (no dedicated brand taxonomy).
 * Product tag archives 301 to matching brand posts.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and maintains the brand CPT.
 */
final class Hello_Elementor_Child_Brand_Cpt {

	public const POST_TYPE = 'brand';

	public const REWRITE_SLUG = 'brands';

	/** Bump to force rewrite flush after CPT/rewrite changes. */
	private const REWRITE_FLUSH_OPTION = 'hello_elementor_child_brand_cpt_flush_v4';

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		// Late so we override any other "brand" CPT (CPT UI, brands plugins, etc.).
		add_action( 'init', array( __CLASS__, 'register_post_type' ), 99 );
		add_action( 'init', array( __CLASS__, 'attach_woo_taxonomies' ), 100 );
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ), 101 );
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrite_rules' ), 999 );

		add_action( 'pre_get_posts', array( __CLASS__, 'keep_product_tax_archives_on_products' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'ensure_brand_singular_query' ), 1 );

		// Rescue pretty URLs / mistaken 404s before the light 404 template exits.
		add_action( 'template_redirect', array( __CLASS__, 'redirect_legacy_brand_urls' ), -25 );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_cpt_archive_to_page' ), -24 );
		add_action( 'template_redirect', array( __CLASS__, 'rescue_brand_404' ), -20 );
		// Tag→brand 301 before light tag archive takes over (priority 1).
		add_action( 'template_redirect', array( __CLASS__, 'redirect_tags_to_brand' ), 0 );
	}

	/**
	 * Public brands listing URL (WP Page with Light brands template).
	 */
	public static function get_archive_url(): string {
		if ( class_exists( 'Hello_Elementor_Child_Light_Brands_Template' ) ) {
			return Hello_Elementor_Child_Light_Brands_Template::get_page_url();
		}
		return home_url( '/' . self::REWRITE_SLUG . '/' );
	}

	/**
	 * If old CPT archive still resolves, send visitors to the brands Page.
	 */
	public static function redirect_cpt_archive_to_page(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		if ( ! is_post_type_archive( self::POST_TYPE ) ) {
			return;
		}

		wp_safe_redirect( self::get_archive_url(), 301 );
		exit;
	}

	/**
	 * 301 legacy /brand/{slug}/ → /brands/{slug}/ (and /brand/ → /brands/).
	 */
	public static function redirect_legacy_brand_urls(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		$path = self::request_path();
		if ( '' === $path ) {
			return;
		}

		// Exact /brand/ → archive.
		if ( 'brand' === $path ) {
			wp_safe_redirect( self::get_archive_url(), 301 );
			exit;
		}

		if ( ! preg_match( '#^brand/([^/]+)(?:/page/([0-9]+))?/?$#', $path, $matches ) ) {
			return;
		}

		$slug  = sanitize_title( rawurldecode( $matches[1] ) );
		$paged = isset( $matches[2] ) ? max( 1, (int) $matches[2] ) : 1;
		if ( '' === $slug ) {
			return;
		}

		$target = home_url( user_trailingslashit( self::REWRITE_SLUG . '/' . $slug ) );
		if ( $paged > 1 ) {
			$target = trailingslashit( $target ) . user_trailingslashit( 'page/' . $paged, 'paged' );
		}

		wp_safe_redirect( $target, 301 );
		exit;
	}

	/**
	 * 301 every product_tag archive to /brands/{slug}/ when a published brand exists.
	 */
	public static function redirect_tags_to_brand(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		if ( ! function_exists( 'is_product_tag' ) || ! is_product_tag() ) {
			return;
		}

		$term = get_queried_object();
		if ( ! $term instanceof WP_Term || 'product_tag' !== $term->taxonomy ) {
			return;
		}

		$slug = sanitize_title( urldecode( (string) $term->slug ) );
		if ( '' === $slug ) {
			return;
		}

		$brand = self::get_published_brand_by_slug( $slug );
		if ( ! $brand instanceof WP_Post ) {
			return;
		}

		$target = get_permalink( $brand );
		if ( ! is_string( $target ) || '' === $target ) {
			return;
		}

		// Preserve pagination: /product-tag/{slug}/page/2/ → /brands/{slug}/page/2/.
		$paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
		if ( $paged > 1 ) {
			$target = trailingslashit( $target ) . user_trailingslashit( 'page/' . $paged, 'paged' );
		}

		wp_safe_redirect( $target, 301 );
		exit;
	}

	/**
	 * Find a published brand post by slug.
	 *
	 * @param string $slug Post name / tag slug.
	 */
	public static function get_published_brand_by_slug( string $slug ): ?WP_Post {
		$slug = sanitize_title( $slug );
		if ( '' === $slug ) {
			return null;
		}

		$by_path = get_page_by_path( $slug, OBJECT, self::POST_TYPE );
		if ( $by_path instanceof WP_Post && 'publish' === $by_path->post_status ) {
			return $by_path;
		}

		$posts = get_posts(
			array(
				'name'             => $slug,
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'publish',
				'posts_per_page'   => 1,
				'suppress_filters' => true,
			)
		);

		return ( ! empty( $posts[0] ) && $posts[0] instanceof WP_Post ) ? $posts[0] : null;
	}

	/**
	 * Register CPT brand (always — do not skip if another plugin already registered it).
	 */
	public static function register_post_type(): void {
		$labels = array(
			'name'                  => 'برندها',
			'singular_name'         => 'برند',
			'menu_name'             => 'برندها',
			'name_admin_bar'        => 'برند',
			'add_new'               => 'افزودن برند',
			'add_new_item'          => 'افزودن برند جدید',
			'new_item'              => 'برند جدید',
			'edit_item'             => 'ویرایش برند',
			'view_item'             => 'مشاهده برند',
			'all_items'             => 'همه برندها',
			'search_items'          => 'جستجوی برندها',
			'not_found'             => 'برندی یافت نشد.',
			'not_found_in_trash'    => 'برندی در زباله‌دان نیست.',
			'featured_image'        => 'لوگوی برند',
			'set_featured_image'    => 'تنظیم لوگوی برند',
			'remove_featured_image' => 'حذف لوگوی برند',
			'use_featured_image'    => 'استفاده به‌عنوان لوگوی برند',
			'archives'              => 'آرشیو برندها',
			'insert_into_item'      => 'درج در برند',
			'uploaded_to_this_item' => 'آپلودشده برای این برند',
			'filter_items_list'     => 'فیلتر فهرست برندها',
			'items_list_navigation' => 'ناوبری فهرست برندها',
			'items_list'            => 'فهرست برندها',
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => $labels,
				'description'         => 'Lookazma brand / company pages',
				'public'              => true,
				'publicly_queryable'  => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_nav_menus'   => true,
				'show_in_rest'        => true,
				'has_archive'         => false,
				'exclude_from_search' => false,
				'hierarchical'        => false,
				'menu_position'       => 56,
				'menu_icon'           => 'dashicons-awards',
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ),
				// Avoid query_var "brand" clashes with filters / brand plugins.
				'query_var'           => 'lk_brand',
				'rewrite'             => array(
					'slug'       => self::REWRITE_SLUG,
					'with_front' => false,
					'feeds'      => false,
					'pages'      => true,
				),
				'taxonomies'          => array( 'product_cat', 'product_tag' ),
			)
		);
	}

	/**
	 * Bind existing WooCommerce taxonomies to brand (no custom taxonomies).
	 */
	public static function attach_woo_taxonomies(): void {
		if ( ! post_type_exists( self::POST_TYPE ) ) {
			return;
		}

		foreach ( array( 'product_cat', 'product_tag' ) as $taxonomy ) {
			if ( taxonomy_exists( $taxonomy ) ) {
				register_taxonomy_for_object_type( $taxonomy, self::POST_TYPE );
			}
		}
	}

	/**
	 * Explicit top rules → post_type + name (more reliable than CPT query_var alone).
	 */
	public static function add_rewrite_rules(): void {
		$slug = self::REWRITE_SLUG;

		// Single brand product pagination: /brands/{slug}/page/N/ (not archive /brands/page/N/).
		add_rewrite_rule(
			'^' . $slug . '/(?!page(?:/|$))([^/]+)/page/([0-9]+)/?$',
			'index.php?post_type=' . self::POST_TYPE . '&name=$matches[1]&paged=$matches[2]',
			'top'
		);
		add_rewrite_rule(
			'^' . $slug . '/(?!page(?:/|$))([^/]+)/?$',
			'index.php?post_type=' . self::POST_TYPE . '&name=$matches[1]',
			'top'
		);
	}

	/**
	 * Flush rewrite rules once after CPT/rewrite changes.
	 */
	public static function maybe_flush_rewrite_rules(): void {
		if ( get_option( self::REWRITE_FLUSH_OPTION ) ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( self::REWRITE_FLUSH_OPTION, '1', false );
	}

	/**
	 * Product category/tag archives must list products only, not brand posts.
	 *
	 * @param WP_Query $query Main query.
	 */
	public static function keep_product_tax_archives_on_products( WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( ! $query->is_tax( array( 'product_cat', 'product_tag' ) ) ) {
			return;
		}

		$query->set( 'post_type', 'product' );
	}

	/**
	 * Keep main query on brand when URL/query asks for this CPT.
	 *
	 * @param WP_Query $query Main query.
	 */
	public static function ensure_brand_singular_query( WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$post_type = $query->get( 'post_type' );
		$name      = $query->get( 'name' );
		$lk_brand  = $query->get( 'lk_brand' );
		$p         = (int) $query->get( 'p' );

		if ( is_string( $lk_brand ) && '' !== $lk_brand ) {
			$query->set( 'post_type', self::POST_TYPE );
			$query->set( 'name', sanitize_title( $lk_brand ) );
			$query->set( 'lk_brand', '' );
			return;
		}

		if ( self::POST_TYPE === $post_type && ( $p > 0 || ( is_string( $name ) && '' !== $name ) ) ) {
			$query->set( 'post_type', self::POST_TYPE );
			$query->set( 'post_status', array( 'publish', 'private' ) );
		}
	}

	/**
	 * If WP still 404s on /brands/{slug}/, resolve the brand post and fix the main query.
	 */
	public static function rescue_brand_404(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		if ( is_singular( self::POST_TYPE ) || is_page() ) {
			return;
		}

		$slug = self::detect_brand_slug_from_request();
		$post = null;

		if ( '' !== $slug ) {
			$post = self::get_brand_by_slug( $slug );
		}

		// Query-string fallbacks that still 404'd.
		if ( ! $post instanceof WP_Post ) {
			$post = self::get_brand_from_query_string();
		}

		if ( ! $post instanceof WP_Post ) {
			return;
		}

		self::prime_main_query_with_brand( $post );
	}

	/**
	 * Normalized request path (no leading/trailing slash).
	 */
	private static function request_path(): string {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$path = rawurldecode( $path );
		$path = trim( $path, '/' );

		$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$home_path = trim( $home_path, '/' );
		if ( '' !== $home_path && 0 === strpos( $path, $home_path . '/' ) ) {
			$path = substr( $path, strlen( $home_path ) + 1 );
		} elseif ( '' !== $home_path && $path === $home_path ) {
			$path = '';
		}

		return $path;
	}

	/**
	 * @return string Brand slug from /brands/{slug}/ path, or empty.
	 */
	private static function detect_brand_slug_from_request(): string {
		$path = self::request_path();
		if ( '' === $path ) {
			return '';
		}

		$slug_base = preg_quote( self::REWRITE_SLUG, '#' );
		if ( ! preg_match( '#^' . $slug_base . '/([^/]+)/?(?:page/[0-9]+)?/?$#', $path, $matches ) ) {
			return '';
		}

		$slug = sanitize_title( rawurldecode( $matches[1] ) );
		// Reserved for archive pagination (/brands/page/2/).
		if ( 'page' === $slug ) {
			return '';
		}
		return $slug;
	}

	/**
	 * @return WP_Post|null
	 */
	private static function get_brand_from_query_string(): ?WP_Post {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$get = wp_unslash( $_GET );

		if ( isset( $get['post_type'] ) && self::POST_TYPE === sanitize_key( (string) $get['post_type'] ) ) {
			if ( ! empty( $get['p'] ) ) {
				$by_id = get_post( (int) $get['p'] );
				if ( $by_id instanceof WP_Post && self::POST_TYPE === $by_id->post_type && self::is_viewable_brand( $by_id ) ) {
					return $by_id;
				}
			}
			if ( ! empty( $get['name'] ) ) {
				return self::get_brand_by_slug( sanitize_title( (string) $get['name'] ) );
			}
		}

		if ( ! empty( $get['lk_brand'] ) ) {
			return self::get_brand_by_slug( sanitize_title( (string) $get['lk_brand'] ) );
		}

		return null;
	}

	/**
	 * @param string $slug Post name.
	 * @return WP_Post|null
	 */
	private static function get_brand_by_slug( string $slug ): ?WP_Post {
		if ( '' === $slug ) {
			return null;
		}

		$posts = get_posts(
			array(
				'name'             => $slug,
				'post_type'        => self::POST_TYPE,
				'post_status'      => array( 'publish', 'private' ),
				'posts_per_page'   => 1,
				'suppress_filters' => true,
			)
		);

		if ( empty( $posts[0] ) || ! ( $posts[0] instanceof WP_Post ) ) {
			return null;
		}

		return self::is_viewable_brand( $posts[0] ) ? $posts[0] : null;
	}

	/**
	 * @param WP_Post $post Brand post.
	 */
	private static function is_viewable_brand( WP_Post $post ): bool {
		if ( self::POST_TYPE !== $post->post_type ) {
			return false;
		}
		if ( 'publish' === $post->post_status ) {
			return true;
		}
		if ( 'private' === $post->post_status && is_user_logged_in() && current_user_can( 'read_post', $post->ID ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Replace 404 main query with a resolved brand singular.
	 *
	 * @param WP_Post $post Brand post.
	 */
	private static function prime_main_query_with_brand( WP_Post $post ): void {
		global $wp_query, $wp_the_query;

		$query = new WP_Query(
			array(
				'p'              => (int) $post->ID,
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'private' ),
				'posts_per_page' => 1,
			)
		);

		if ( ! $query->have_posts() ) {
			// Direct prime if WP_Query still blocked by a filter.
			$wp_query->posts             = array( $post );
			$wp_query->post_count        = 1;
			$wp_query->found_posts       = 1;
			$wp_query->max_num_pages     = 1;
			$wp_query->queried_object    = $post;
			$wp_query->queried_object_id = (int) $post->ID;
			$wp_query->post              = $post;
			$wp_query->is_404            = false;
			$wp_query->is_single         = true;
			$wp_query->is_singular       = true;
			$wp_query->is_page           = false;
			$wp_query->is_archive        = false;
			$wp_query->is_home           = false;
			$wp_query->is_category       = false;
			$wp_query->is_tag            = false;
			$wp_query->is_tax            = false;
		} else {
			$wp_query     = $query;
			$wp_the_query = $query;
		}

		$GLOBALS['wp_query']     = $wp_query;
		$GLOBALS['wp_the_query'] = isset( $wp_the_query ) ? $wp_the_query : $wp_query;

		$wp_query->is_404 = false;
		status_header( 200 );
		nocache_headers();

		$GLOBALS['post'] = $post;
		setup_postdata( $post );
	}
}
