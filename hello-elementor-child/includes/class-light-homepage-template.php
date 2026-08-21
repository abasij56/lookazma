<?php
/**
 * Light homepage template — native chrome + Gutenberg body blocks.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Homepage with shared Lookazma header/footer and block-based body.
 */
final class Hello_Elementor_Child_Light_Homepage_Template {

	public const TEMPLATE_FILE = 'page-templates/light-homepage.php';

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		require_once HELLO_ELEMENTOR_CHILD_PATH . 'includes/blocks/class-light-homepage-blocks.php';
		Hello_Elementor_Child_Light_Homepage_Blocks::init();

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

		add_filter( 'allowed_block_types_all', array( __CLASS__, 'restrict_editor_blocks' ), 20, 2 );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_assets' ) );

		add_filter( 'use_block_editor_for_post', array( __CLASS__, 'force_block_editor' ), PHP_INT_MAX, 2 );
		add_filter( 'replace_editor', array( __CLASS__, 'keep_wordpress_editor' ), PHP_INT_MAX, 2 );
		add_filter( 'get_post_metadata', array( __CLASS__, 'filter_elementor_edit_mode_meta' ), 10, 4 );
		add_filter( 'elementor/documents/is_built_with_elementor', array( __CLASS__, 'deny_elementor_built_flag' ), 10, 2 );
		add_filter( 'page_row_actions', array( __CLASS__, 'remove_elementor_row_actions' ), 99, 2 );
		add_filter( 'post_row_actions', array( __CLASS__, 'remove_elementor_row_actions' ), 99, 2 );
		add_filter( 'admin_body_class', array( __CLASS__, 'admin_body_class' ) );
		add_action( 'admin_head', array( __CLASS__, 'hide_elementor_editor_button' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'remove_elementor_admin_bar' ), 999 );
		add_action( 'admin_init', array( __CLASS__, 'redirect_elementor_editor' ) );

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
		$templates[ self::TEMPLATE_FILE ] = __( 'Light homepage template', 'hello-elementor-child' );
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
		return self::TEMPLATE_FILE === $slug || 'light-homepage.php' === $slug;
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
		return HELLO_ELEMENTOR_CHILD_PATH . 'page-light-homepage.php';
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
	 * Admin post ID for the current editor request.
	 */
	private static function get_admin_post_id(): int {
		if ( isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return (int) $_GET['post'];
		}
		if ( isset( $_POST['post_ID'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return (int) $_POST['post_ID'];
		}
		global $post;
		if ( $post instanceof WP_Post ) {
			return (int) $post->ID;
		}
		return 0;
	}

	/**
	 * Keep Gutenberg as the editor for Light homepage pages.
	 *
	 * @param bool             $use_block_editor Whether to use the block editor.
	 * @param WP_Post|int|null $post            Post object or ID.
	 */
	public static function force_block_editor( $use_block_editor, $post ): bool {
		$post_id = $post instanceof WP_Post ? (int) $post->ID : (int) $post;
		if ( $post_id > 0 && self::page_uses_template( $post_id ) ) {
			return true;
		}
		return (bool) $use_block_editor;
	}

	/**
	 * Stop Elementor from replacing the WordPress editor on this template.
	 *
	 * @param bool             $replace Whether to replace the editor.
	 * @param WP_Post|int|null $post    Post object or ID.
	 */
	public static function keep_wordpress_editor( $replace, $post ): bool {
		$post_id = $post instanceof WP_Post ? (int) $post->ID : (int) $post;
		if ( $post_id > 0 && self::page_uses_template( $post_id ) ) {
			return false;
		}
		return (bool) $replace;
	}

	/**
	 * Pretend Elementor builder mode is off so Gutenberg owns the page.
	 * Existing Elementor data is left in the database in case the template is switched later.
	 *
	 * @param mixed  $value     Current filter value.
	 * @param int    $object_id Post ID.
	 * @param string $meta_key  Meta key.
	 * @param bool   $single    Whether a single value was requested.
	 * @return mixed
	 */
	public static function filter_elementor_edit_mode_meta( $value, $object_id, $meta_key, $single ) {
		if ( '_elementor_edit_mode' !== $meta_key ) {
			return $value;
		}
		if ( ! self::page_uses_template( (int) $object_id ) ) {
			return $value;
		}
		return $single ? '' : array( '' );
	}

	/**
	 * @param bool $is_built Whether Elementor built the document.
	 * @param int  $post_id  Post ID.
	 */
	public static function deny_elementor_built_flag( $is_built, $post_id ): bool {
		if ( self::page_uses_template( (int) $post_id ) ) {
			return false;
		}
		return (bool) $is_built;
	}

	/**
	 * @param array<string, string> $actions Row actions.
	 * @param WP_Post               $post    Post.
	 * @return array<string, string>
	 */
	public static function remove_elementor_row_actions( array $actions, $post ): array {
		if ( ! $post instanceof WP_Post || ! self::page_uses_template( (int) $post->ID ) ) {
			return $actions;
		}
		unset( $actions['elementor'], $actions['edit_with_elementor'] );
		return $actions;
	}

	/**
	 * @param string $classes Admin body classes.
	 */
	public static function admin_body_class( string $classes ): string {
		if ( self::page_uses_template( self::get_admin_post_id() ) ) {
			$classes .= ' lk-light-homepage-editor';
		}
		return $classes;
	}

	/**
	 * Hide Elementor's "Edit with Elementor" switch on this template.
	 */
	public static function hide_elementor_editor_button(): void {
		if ( ! self::page_uses_template( self::get_admin_post_id() ) ) {
			return;
		}
		echo '<style id="lk-light-homepage-hide-elementor">.lk-light-homepage-editor .elementor-switch-mode,.lk-light-homepage-editor #elementor-switch-mode,.lk-light-homepage-editor #elementor-editor,.lk-light-homepage-editor #elementor-switch-mode-button{display:none!important}</style>' . "\n";
	}

	/**
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar.
	 */
	public static function remove_elementor_admin_bar( $wp_admin_bar ): void {
		$post_id = 0;
		if ( is_admin() ) {
			$post_id = self::get_admin_post_id();
		} elseif ( self::is_enabled() ) {
			$post_id = self::get_context_page_id();
		}
		if ( $post_id <= 0 || ! self::page_uses_template( $post_id ) ) {
			return;
		}
		$wp_admin_bar->remove_node( 'elementor_edit_page' );
		$wp_admin_bar->remove_node( 'elementor-inspector' );
	}

	/**
	 * Send Elementor editor URLs back to Gutenberg for this template.
	 */
	public static function redirect_elementor_editor(): void {
		if ( ! is_admin() ) {
			return;
		}
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'elementor' !== $action ) {
			return;
		}
		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $post_id <= 0 || ! self::page_uses_template( $post_id ) ) {
			return;
		}
		$edit_link = get_edit_post_link( $post_id, 'raw' );
		if ( ! $edit_link ) {
			return;
		}
		wp_safe_redirect( $edit_link );
		exit;
	}

	/**
	 * Limit block inserter to Lookazma homepage blocks.
	 *
	 * @param bool|array<int, string> $allowed_blocks Allowed blocks.
	 * @param WP_Block_Editor_Context  $editor_context Editor context.
	 * @return bool|array<int, string>
	 */
	public static function restrict_editor_blocks( $allowed_blocks, $editor_context ) {
		$post = isset( $editor_context->post ) ? $editor_context->post : null;
		if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
			return $allowed_blocks;
		}
		if ( ! self::page_uses_template( (int) $post->ID ) ) {
			return $allowed_blocks;
		}
		return Hello_Elementor_Child_Light_Homepage_Blocks::get_block_names();
	}

	/**
	 * Block editor assets for homepage template only.
	 */
	public static function enqueue_editor_assets(): void {
		$post_id = 0;
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && ! empty( $screen->post_type ) && 'page' === $screen->post_type ) {
				$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		}
		if ( $post_id <= 0 ) {
			global $post;
			if ( $post instanceof WP_Post ) {
				$post_id = (int) $post->ID;
			}
		}
		if ( $post_id <= 0 || ! self::page_uses_template( $post_id ) ) {
			return;
		}

		wp_enqueue_style( 'lk-light-homepage-editor' );
		wp_enqueue_script( 'lk-light-homepage-blocks-editor' );
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

		$homepage_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/light-homepage.css';
		if ( file_exists( $homepage_css ) ) {
			wp_enqueue_style(
				'lk-light-homepage',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/css/light-homepage.css',
				array( 'lk-light-product' ),
				(string) filemtime( $homepage_css )
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

		$homepage_js = HELLO_ELEMENTOR_CHILD_PATH . 'assets/js/light-homepage.js';
		if ( file_exists( $homepage_js ) ) {
			wp_enqueue_script(
				'lk-light-homepage',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/js/light-homepage.js',
				array(),
				(string) filemtime( $homepage_js ),
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
		$classes[] = 'lk-light-homepage';
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
		if ( $page_id <= 0 ) {
			return array(
				'content' => '',
				'title'   => '',
			);
		}

		$post = get_post( $page_id );
		if ( ! $post instanceof WP_Post ) {
			return array(
				'content' => '',
				'title'   => '',
			);
		}

		$content = (string) $post->post_content;
		$content = apply_filters( 'the_content', $content );

		// Ensure the scrollable SEO box is present at the bottom (Elementor 01a6705).
		if ( false === strpos( $content, 'lk-seo-box' )
			&& class_exists( 'Hello_Elementor_Child_Light_Homepage_Blocks' )
		) {
			$content .= Hello_Elementor_Child_Light_Homepage_Blocks::render_seo_box( array() );
		}

		return array(
			'content' => $content,
			'title'   => get_the_title( $page_id ),
		);
	}
}
