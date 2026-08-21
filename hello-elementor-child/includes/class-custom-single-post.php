<?php
/**
 * Global light single blog post (opt-out per post).
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Switches blog singles to native chrome + Timber body; strips Elementor single.
 */
final class Hello_Elementor_Child_Custom_Single_Post {

	public const META_KEY = '_lk_use_light_single_post';

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_post_meta' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_box' ) );
		add_action( 'save_post_post', array( __CLASS__, 'save_meta_box' ), 10, 2 );

		add_filter( 'manage_post_posts_columns', array( __CLASS__, 'add_posts_list_column' ), 20 );
		add_action( 'manage_post_posts_custom_column', array( __CLASS__, 'render_posts_list_column' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'render_posts_list_filter' ) );
		add_filter( 'parse_query', array( __CLASS__, 'filter_posts_list_by_light' ) );

		add_filter( 'timber/locations', array( __CLASS__, 'add_timber_locations' ) );
		add_filter( 'woocommerce_get_breadcrumb', array( __CLASS__, 'filter_breadcrumb' ), 20 );

		add_action( 'template_redirect', array( __CLASS__, 'unhook_elementor_chrome' ), -6 );
		add_action( 'template_redirect', array( __CLASS__, 'force_light_template' ), 1 );
		add_filter( 'template_include', array( __CLASS__, 'maybe_use_custom_template' ), PHP_INT_MAX );
		add_filter( 'elementor/theme/need_override_location', array( __CLASS__, 'disable_elementor_locations' ), PHP_INT_MAX, 2 );
		add_filter( 'elementor/theme/get_location_templates', array( __CLASS__, 'remove_popup_templates' ), PHP_INT_MAX, 2 );
		add_filter( 'elementor/theme/get_location_templates/popup', array( __CLASS__, 'deny_popup_templates' ), PHP_INT_MAX );
		add_filter( 'elementor/frontend/builder_content_data', array( __CLASS__, 'empty_popup_builder_data' ), PHP_INT_MAX, 2 );
		add_action( 'wp_footer', array( __CLASS__, 'unhook_elementor_popups' ), 0 );
		add_filter( 'jet-theme-core/template-include', array( __CLASS__, 'disable_jet_theme_core_template' ), PHP_INT_MAX );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_light_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'dequeue_conflicting_assets' ), 100 );
		add_action( 'wp_print_styles', array( __CLASS__, 'dequeue_conflicting_assets' ), 100 );
		add_action( 'wp_print_scripts', array( __CLASS__, 'dequeue_conflicting_assets' ), 100 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
	}

	/**
	 * @param array<int|string, string|array<int, string>> $locations Locations.
	 * @return array<int|string, string|array<int, string>>
	 */
	public static function add_timber_locations( $locations ) {
		$path = HELLO_ELEMENTOR_CHILD_PATH . 'views';
		if ( ! in_array( $path, $locations, true ) ) {
			$locations[] = $path;
		}
		return $locations;
	}

	/**
	 * Register meta for REST / block editor awareness (default = light on).
	 */
	public static function register_post_meta(): void {
		register_post_meta(
			'post',
			self::META_KEY,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'auth_callback'     => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'sanitize_callback' => static function ( $value ): string {
					return 'no' === $value ? 'no' : '';
				},
			)
		);
	}

	/**
	 * Meta box on post edit (side panel; checked = light, default on).
	 */
	public static function register_meta_box(): void {
		add_meta_box(
			'lk_light_single_post',
			__( 'تمپلیت سبک', 'hello-elementor-child' ),
			array( __CLASS__, 'render_meta_box' ),
			'post',
			'side',
			'high'
		);
	}

	/**
	 * @param \WP_Post $post Post.
	 */
	public static function render_meta_box( $post ): void {
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		// Empty / anything except `no` → light ON (global default).
		$light_on = 'no' !== get_post_meta( (int) $post->ID, self::META_KEY, true );
		wp_nonce_field( 'lk_light_single_post_save', 'lk_light_single_post_nonce' );
		?>
		<label for="<?php echo esc_attr( self::META_KEY ); ?>" style="display:flex;gap:0.5rem;align-items:flex-start;cursor:pointer;">
			<input type="checkbox" name="<?php echo esc_attr( self::META_KEY ); ?>" id="<?php echo esc_attr( self::META_KEY ); ?>" value="yes" <?php checked( $light_on ); ?> style="margin-top:0.2rem;">
			<span>
				<strong><?php esc_html_e( 'تمپلیت سبک (بدون Elementor)', 'hello-elementor-child' ); ?></strong>
				<br>
				<span class="description"><?php esc_html_e( 'پیش‌فرض: روشن. برای برگشت به تمپلیت Elementor، تیک را بردارید.', 'hello-elementor-child' ); ?></span>
			</span>
		</label>
		<?php
	}

	/**
	 * Admin column: light template enabled?
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public static function add_posts_list_column( array $columns ): array {
		$columns['lk_light_single_post'] = __( 'تمپلیت سبک', 'hello-elementor-child' );
		return $columns;
	}

	/**
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public static function render_posts_list_column( string $column, int $post_id ): void {
		if ( 'lk_light_single_post' !== $column ) {
			return;
		}
		if ( self::is_enabled( $post_id ) ) {
			echo '<span style="color:#007017;font-weight:700;" title="تمپلیت سبک (پیش‌فرض)">✓</span>';
		} else {
			echo '<span style="color:#b32d2e;font-weight:700;" title="خروج از تمپلیت سبک">✕</span>';
		}
	}

	/**
	 * Dropdown filter on posts list.
	 */
	public static function render_posts_list_filter(): void {
		global $typenow;
		if ( 'post' !== $typenow ) {
			return;
		}
		$current = isset( $_GET['lk_light_single_post'] ) ? sanitize_text_field( wp_unslash( $_GET['lk_light_single_post'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<select name="lk_light_single_post">
			<option value=""><?php esc_html_e( 'تمپلیت سبک: همه', 'hello-elementor-child' ); ?></option>
			<option value="yes" <?php selected( $current, 'yes' ); ?>><?php esc_html_e( 'فقط سبک (✓)', 'hello-elementor-child' ); ?></option>
			<option value="no" <?php selected( $current, 'no' ); ?>><?php esc_html_e( 'فقط خارج‌شده (✕)', 'hello-elementor-child' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Apply posts-list filter.
	 *
	 * @param \WP_Query $query Query.
	 */
	public static function filter_posts_list_by_light( $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-post' !== $screen->id ) {
			return;
		}
		$value = isset( $_GET['lk_light_single_post'] ) ? sanitize_text_field( wp_unslash( $_GET['lk_light_single_post'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'yes' !== $value && 'no' !== $value ) {
			return;
		}
		$meta_query = (array) $query->get( 'meta_query' );
		if ( 'no' === $value ) {
			$meta_query[] = array(
				'key'   => self::META_KEY,
				'value' => 'no',
			);
		} else {
			$meta_query[] = array(
				'relation' => 'OR',
				array(
					'key'     => self::META_KEY,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => self::META_KEY,
					'value'   => 'no',
					'compare' => '!=',
				),
			);
		}
		$query->set( 'meta_query', $meta_query );
	}

	/**
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public static function save_meta_box( int $post_id, $post ): void {
		if ( ! $post instanceof WP_Post || 'post' !== $post->post_type ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['lk_light_single_post_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lk_light_single_post_nonce'] ) ), 'lk_light_single_post_save' )
		) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		if ( isset( $_POST[ self::META_KEY ] ) ) {
			delete_post_meta( $post_id, self::META_KEY );
			return;
		}
		update_post_meta( $post_id, self::META_KEY, 'no' );
	}

	/**
	 * Current post ID when viewing a single post.
	 */
	public static function get_current_post_id(): int {
		if ( is_singular( 'post' ) ) {
			$id = (int) get_queried_object_id();
			if ( $id > 0 ) {
				return $id;
			}
		}
		$queried = get_queried_object();
		if ( $queried instanceof WP_Post && 'post' === $queried->post_type ) {
			return (int) $queried->ID;
		}
		return 0;
	}

	/**
	 * Absolute path to the light PHP entry.
	 */
	public static function get_light_template_path(): string {
		return HELLO_ELEMENTOR_CHILD_PATH . 'single-post-light.php';
	}

	/**
	 * Default on for every blog post; meta `no` opts out.
	 *
	 * @param int|null $post_id Optional post ID.
	 */
	public static function is_enabled( ?int $post_id = null ): bool {
		if ( null === $post_id ) {
			if ( ! is_singular( 'post' ) ) {
				return false;
			}
			$post_id = self::get_current_post_id();
		}
		if ( $post_id <= 0 ) {
			return false;
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || 'post' !== $post->post_type ) {
			return false;
		}
		return 'no' !== get_post_meta( $post_id, self::META_KEY, true );
	}

	/**
	 * @param array<int, array{0:string,1:string}> $crumbs Crumbs.
	 * @return array<int, array{0:string,1:string}>
	 */
	public static function filter_breadcrumb( array $crumbs ): array {
		if ( ! self::is_enabled() ) {
			return $crumbs;
		}

		$post_id = self::get_current_post_id();
		if ( $post_id <= 0 ) {
			return $crumbs;
		}

		$home = array( __( 'خانه', 'hello-elementor-child' ), home_url( '/' ) );
		$cats = get_the_category( $post_id );
		$out  = array( $home );

		if ( is_array( $cats ) && isset( $cats[0] ) && $cats[0] instanceof WP_Term ) {
			$out[] = array( $cats[0]->name, get_category_link( $cats[0]->term_id ) );
		}

		$out[] = array( get_the_title( $post_id ), '' );
		return $out;
	}

	/**
	 * Force light template early.
	 */
	public static function force_light_template(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		if ( ! self::is_enabled() ) {
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
	 * @param string $template Template path.
	 */
	public static function maybe_use_custom_template( string $template ): string {
		if ( ! self::is_enabled() ) {
			return $template;
		}
		$custom = self::get_light_template_path();
		return file_exists( $custom ) ? $custom : $template;
	}

	/**
	 * @param bool   $need_override Override flag.
	 * @param string $location      Location.
	 */
	public static function disable_elementor_locations( bool $need_override, string $location ): bool {
		if ( ! self::is_enabled() ) {
			return $need_override;
		}
		if ( in_array( $location, array( 'header', 'footer', 'single', 'single-post', 'archive', 'popup' ), true ) ) {
			return false;
		}
		return $need_override;
	}

	/**
	 * @param mixed $templates Templates.
	 * @param mixed $arg       Args.
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
	 * @param mixed $template Template.
	 * @return mixed
	 */
	public static function disable_jet_theme_core_template( $template ) {
		if ( ! self::is_enabled() ) {
			return $template;
		}
		$custom = self::get_light_template_path();
		return file_exists( $custom ) ? $custom : $template;
	}

	/**
	 * Front-end assets.
	 */
	public static function enqueue_light_assets(): void {
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

		$article_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/light-article.css';
		if ( file_exists( $article_css ) ) {
			wp_enqueue_style(
				'lk-light-article',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/css/light-article.css',
				array( 'lk-light-product', 'lk-lpt-breadcrumb' ),
				(string) filemtime( $article_css )
			);
		}

		$css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/light-single-post.css';
		if ( file_exists( $css ) ) {
			wp_enqueue_style(
				'lk-light-single-post',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/css/light-single-post.css',
				array( 'lk-light-product', 'lk-lpt-breadcrumb' ),
				(string) filemtime( $css )
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
		// light-single-post.js is linked from the Twig base (avoid double-bind).
	}

	/**
	 * Strip Elementor / Jet assets on light singles.
	 */
	public static function dequeue_conflicting_assets(): void {
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
		);
		$script_handles = array(
			'elementor-frontend',
			'elementor-pro-frontend',
			'elementor-pro',
			'jet-woo-builder',
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
		$classes[] = 'lk-light-single-post';
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
	 * Annotate content headings and build nested TOC (h2 → h3 children).
	 *
	 * @param string $html Post content HTML.
	 * @return array{content:string,toc:array<int,array{id:string,label:string,level:int,children:array<int,array{id:string,label:string,level:int}>}>}
	 */
	public static function prepare_content_with_toc( string $html ): array {
		$html = trim( $html );
		$out  = array(
			'content' => $html,
			'toc'     => array(),
		);
		if ( '' === $html ) {
			return $out;
		}

		if ( ! preg_match_all( '/<h([23])\b([^>]*)>(.*?)<\/h\1>/is', $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			$out['content'] = wp_kses_post( $html );
			return $out;
		}

		$annotated = $html;
		$flat      = array();
		// Walk reverse so offsets stay valid while injecting.
		for ( $i = count( $matches ) - 1; $i >= 0; $i-- ) {
			$full      = (string) $matches[ $i ][0][0];
			$offset    = (int) $matches[ $i ][0][1];
			$level     = (int) $matches[ $i ][1][0];
			$inner     = (string) $matches[ $i ][3][0];
			$id        = 'lk-post-sec-' . ( $i + 1 );
			$label     = trim( wp_strip_all_tags( $inner ) );
			$with_id   = self::inject_heading_id( $full, $id );
			$annotated = substr( $annotated, 0, $offset ) . $with_id . substr( $annotated, $offset + strlen( $full ) );

			$flat[] = array(
				'id'    => $id,
				'label' => '' !== $label ? $label : sprintf(
					/* translators: %d section number */
					__( 'بخش %d', 'hello-elementor-child' ),
					$i + 1
				),
				'level' => $level,
				'_i'    => $i,
			);
		}

		usort(
			$flat,
			static function ( $a, $b ) {
				return (int) $a['_i'] <=> (int) $b['_i'];
			}
		);

		$nested = array();
		$current_h2 = null;
		foreach ( $flat as $item ) {
			unset( $item['_i'] );
			if ( 2 === (int) $item['level'] ) {
				$item['children'] = array();
				$nested[]         = $item;
				$current_h2       = count( $nested ) - 1;
				continue;
			}
			// Orphan h3 before any h2 → promote to top-level group.
			if ( null === $current_h2 ) {
				$item['children'] = array();
				$nested[]         = $item;
				continue;
			}
			$nested[ $current_h2 ]['children'][] = $item;
		}

		$out['content'] = wp_kses_post( $annotated );
		$out['toc']     = $nested;
		return $out;
	}

	/**
	 * @param string $heading_html Heading markup.
	 * @param string $id           Anchor id.
	 */
	private static function inject_heading_id( string $heading_html, string $id ): string {
		$id = preg_replace( '/[^a-zA-Z0-9_-]/', '', $id );
		if ( ! is_string( $id ) || '' === $id ) {
			return $heading_html;
		}
		if ( preg_match( '/\sid="/i', $heading_html ) ) {
			$replaced = preg_replace( '/\sid="[^"]*"/i', ' id="' . $id . '"', $heading_html, 1 );
			return is_string( $replaced ) ? $replaced : $heading_html;
		}
		$replaced = preg_replace( '/^<([a-zA-Z0-9]+)\b/i', '<$1 id="' . $id . '"', $heading_html, 1 );
		return is_string( $replaced ) ? $replaced : $heading_html;
	}

	/**
	 * Related posts in the same primary category.
	 *
	 * @param int $post_id Current post.
	 * @param int $limit   Max posts.
	 * @return array<int, array{id:int,url:string,title:string,date:string,image:string}>
	 */
	public static function get_related_posts( int $post_id, int $limit = 5 ): array {
		$cats = get_the_category( $post_id );
		$args = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => $limit,
			'post__not_in'        => array( $post_id ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);
		if ( is_array( $cats ) && isset( $cats[0] ) && $cats[0] instanceof WP_Term ) {
			$args['cat'] = (int) $cats[0]->term_id;
		}

		$query = new WP_Query( $args );
		$posts = array();
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$thumb = get_the_post_thumbnail_url( $post, 'medium_large' );
			$posts[] = array(
				'id'    => (int) $post->ID,
				'url'   => (string) get_permalink( $post ),
				'title' => get_the_title( $post ),
				'date'  => get_the_date( '', $post ),
				'image' => is_string( $thumb ) ? $thumb : '',
			);
		}
		wp_reset_postdata();
		return $posts;
	}

	/**
	 * Context payload for the Twig view.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public static function get_post_context( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return array();
		}

		$raw     = (string) $post->post_content;
		$raw     = apply_filters( 'the_content', $raw );
		$prepared = self::prepare_content_with_toc( $raw );

		$author_id   = (int) $post->post_author;
		$author_name = get_the_author_meta( 'display_name', $author_id );
		$author_url  = get_author_posts_url( $author_id );

		$categories = array();
		foreach ( (array) get_the_category( $post_id ) as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}
			$categories[] = array(
				'name' => $term->name,
				'url'  => get_category_link( $term->term_id ),
			);
		}

		$image = null;
		if ( has_post_thumbnail( $post_id ) ) {
			$thumb_id = (int) get_post_thumbnail_id( $post_id );
			$url      = wp_get_attachment_image_url( $thumb_id, 'large' );
			if ( is_string( $url ) && '' !== $url ) {
				$alt = (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true );
				if ( '' === $alt ) {
					$alt = get_the_title( $post_id );
				}
				$image = array(
					'url' => $url,
					'alt' => $alt,
				);
			}
		}

		return array(
			'id'              => $post_id,
			'title'           => get_the_title( $post_id ),
			'content'         => $prepared['content'],
			'toc'             => $prepared['toc'],
			'toc_title'       => __( 'مطالبی که در این مقاله می خوانید', 'hello-elementor-child' ),
			'author_name'     => $author_name,
			'author_url'      => $author_url,
			'time'            => get_the_time( '', $post ),
			'date'            => get_the_date( '', $post ),
			'categories'      => $categories,
			'image'           => $image,
			'related'         => self::get_related_posts( $post_id, 5 ),
			'more_label'      => __( 'ادامه مطلب', 'hello-elementor-child' ),
			'related_title'   => __( 'مطالب مرتبط', 'hello-elementor-child' ),
			'cta_text'        => __( '“لوک آزما، مرجع تخصصی فروش مواد شیمایی، با ارائه محصولات با کیفیت و خدمات حرفه ای، همراه مطمئن شما در مسیر پیشرفت علمی و صنعتی است. جهت مشاوره و ارتباط با ما تماس بگیرید”', 'hello-elementor-child' ),
			'cta_phone'       => '09122114322',
			'cta_phone_href'  => 'tel:+989122114322',
			'breadcrumb_html' => function_exists( 'hello_elementor_child_get_breadcrumb_html' )
				? hello_elementor_child_get_breadcrumb_html()
				: '',
		);
	}
}
