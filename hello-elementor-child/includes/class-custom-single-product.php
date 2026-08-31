<?php
/**
 * Global light single product template (opt-out per product).
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Switches all products to a child-theme template and strips Elementor assets
 * unless a product explicitly opts out.
 */
final class Hello_Elementor_Child_Custom_Single_Product {

	public const META_KEY = '_lk_use_light_single';

	private const MIGRATE_OPTION = 'lk_light_single_global_v1';

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'migrate_legacy_opt_in_meta' ), 5 );

		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'render_checkbox' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_checkbox' ) );

		// Admin: light-template flag in products list.
		add_filter( 'manage_edit-product_columns', array( __CLASS__, 'add_products_list_column' ), 20 );
		add_action( 'manage_product_posts_custom_column', array( __CLASS__, 'render_products_list_column' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'render_products_list_filter' ) );
		add_filter( 'parse_query', array( __CLASS__, 'filter_products_list_by_light' ) );

		add_filter( 'timber/locations', array( __CLASS__, 'add_timber_locations' ) );

		add_action( 'template_redirect', array( __CLASS__, 'unhook_elementor_chrome' ), -6 );
		// Beat JetWooBuilder / JetThemeCore / Elementor (they often use very high priorities).
		add_action( 'template_redirect', array( __CLASS__, 'force_light_template' ), 1 );
		add_filter( 'template_include', array( __CLASS__, 'maybe_use_custom_template' ), PHP_INT_MAX );
		add_filter( 'elementor/theme/need_override_location', array( __CLASS__, 'disable_elementor_locations' ), PHP_INT_MAX, 2 );
		add_filter( 'elementor/theme/get_location_templates', array( __CLASS__, 'remove_popup_templates' ), PHP_INT_MAX, 2 );
		add_filter( 'elementor/theme/get_location_templates/popup', array( __CLASS__, 'deny_popup_templates' ), PHP_INT_MAX );
		add_filter( 'elementor/frontend/builder_content_data', array( __CLASS__, 'empty_popup_builder_data' ), PHP_INT_MAX, 2 );
		add_action( 'wp_footer', array( __CLASS__, 'unhook_elementor_popups' ), 0 );
		add_filter( 'jet-woo-builder/custom-single-template', array( __CLASS__, 'disable_jet_single_template' ), PHP_INT_MAX );
		add_filter( 'jet-theme-core/template-include', array( __CLASS__, 'disable_jet_theme_core_template' ), PHP_INT_MAX );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_light_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'dequeue_conflicting_assets' ), 100 );
		add_action( 'wp_print_styles', array( __CLASS__, 'dequeue_conflicting_assets' ), 100 );
		add_action( 'wp_print_scripts', array( __CLASS__, 'dequeue_conflicting_assets' ), 100 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
	}

	/**
	 * Admin column: light template enabled?
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public static function add_products_list_column( array $columns ): array {
		$columns['lk_light_single'] = 'تمپلیت سبک';
		return $columns;
	}

	/**
	 * Render admin column value.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Product ID.
	 */
	public static function render_products_list_column( string $column, int $post_id ): void {
		if ( 'lk_light_single' !== $column ) {
			return;
		}

		if ( self::is_enabled( $post_id ) ) {
			echo '<span style="color:#007017;font-weight:700;" title="تمپلیت سبک (پیش‌فرض)">✓</span>';
		} else {
			echo '<span style="color:#b32d2e;font-weight:700;" title="خروج از تمپلیت سبک">✕</span>';
		}
	}

	/**
	 * Dropdown filter on products list.
	 */
	public static function render_products_list_filter(): void {
		global $typenow;

		if ( 'product' !== $typenow ) {
			return;
		}

		$current = isset( $_GET['lk_light_single'] ) ? sanitize_text_field( wp_unslash( $_GET['lk_light_single'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<select name="lk_light_single">
			<option value=""><?php esc_html_e( 'تمپلیت سبک: همه', 'hello-elementor-child' ); ?></option>
			<option value="yes" <?php selected( $current, 'yes' ); ?>><?php esc_html_e( 'فقط سبک (✓)', 'hello-elementor-child' ); ?></option>
			<option value="no" <?php selected( $current, 'no' ); ?>><?php esc_html_e( 'فقط خارج‌شده (✕)', 'hello-elementor-child' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Apply products-list filter.
	 *
	 * @param \WP_Query $query Query.
	 */
	public static function filter_products_list_by_light( $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-product' !== $screen->id ) {
			return;
		}

		$value = isset( $_GET['lk_light_single'] ) ? sanitize_text_field( wp_unslash( $_GET['lk_light_single'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'yes' !== $value && 'no' !== $value ) {
			return;
		}

		$meta_query = (array) $query->get( 'meta_query' );

		if ( 'no' === $value ) {
			// Explicit opt-out only.
			$meta_query[] = array(
				'key'   => self::META_KEY,
				'value' => 'no',
			);
		} else {
			// Default light: missing meta, empty, or yes — anything except no.
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
	 * Ensure light-template body class is present.
	 *
	 * @param array<int, string> $classes Body classes.
	 * @return array<int, string>
	 */
	public static function body_class( array $classes ): array {
		if ( ! self::is_enabled() ) {
			return $classes;
		}

		$classes[] = 'lk-single-product-light';
		$classes[] = 'lk-light-product';
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
	 * Enqueue light template CSS/JS early (before wp_head in Timber).
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

		wp_enqueue_style(
			'lk-spl-css',
			HELLO_ELEMENTOR_CHILD_URI . 'assets/css/single-product-light.css',
			array( 'lk-light-product' ),
			(string) filemtime( HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/single-product-light.css' )
		);

		$intro_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/product-intro-typography.css';
		if ( file_exists( $intro_css ) ) {
			wp_enqueue_style(
				'hello-elementor-child-product-intro-typography',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/css/product-intro-typography.css',
				array( 'lk-spl-css' ),
				(string) filemtime( $intro_css )
			);
		}

		wp_enqueue_script(
			'lk-spl-js',
			HELLO_ELEMENTOR_CHILD_URI . 'assets/js/single-product-light.js',
			array(),
			(string) filemtime( HELLO_ELEMENTOR_CHILD_PATH . 'assets/js/single-product-light.js' ),
			true
		);

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

		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'wc-add-to-cart' );
		wp_enqueue_script( 'wc-cart-fragments' );
		wp_enqueue_script( 'woocommerce' );
		wp_enqueue_script( 'wc-single-product' );
		wp_enqueue_style( 'woocommerce-general' );

		if ( function_exists( 'is_product' ) && is_product() ) {
			$product = wc_get_product( get_queried_object_id() );
			if ( $product && $product->is_type( 'variable' ) ) {
				wp_enqueue_script( 'wc-add-to-cart-variation' );
			}
		}

		if ( class_exists( 'Hello_Elementor_Child_Product_Compare' ) ) {
			Hello_Elementor_Child_Product_Compare::enqueue_assets( false );
		}
	}

	/**
	 * Twig view directories for Timber.
	 *
	 * @param array<int|string, string|array<int, string>> $locations Existing locations.
	 * @return array<int|string, string|array<int, string>>
	 */
	public static function add_timber_locations( $locations ) {
		$locations[] = HELLO_ELEMENTOR_CHILD_PATH . 'views';
		return $locations;
	}

	/**
	 * Resolve current product ID more reliably than is_product() alone.
	 */
	public static function get_current_product_id(): int {
		if ( function_exists( 'is_product' ) && is_product() ) {
			$id = (int) get_queried_object_id();
			if ( $id > 0 ) {
				return $id;
			}
		}

		$queried = get_queried_object();
		if ( $queried instanceof \WP_Post && 'product' === $queried->post_type ) {
			return (int) $queried->ID;
		}

		global $post, $product;
		if ( $product instanceof \WC_Product ) {
			return (int) $product->get_id();
		}
		if ( $post instanceof \WP_Post && 'product' === $post->post_type ) {
			return (int) $post->ID;
		}

		return 0;
	}

	/**
	 * Absolute path to the light PHP entry template.
	 */
	public static function get_light_template_path(): string {
		return HELLO_ELEMENTOR_CHILD_PATH . 'woocommerce/single-product-light.php';
	}

	/**
	 * Whether the current request (or given product) uses the light template.
	 * Default is on for every product; set meta to `no` to opt out.
	 *
	 * @param int|null $product_id Optional product ID.
	 */
	public static function is_enabled( ?int $product_id = null ): bool {
		if ( null === $product_id ) {
			// Never hijack archives/search/home — only real single product requests.
			if ( ! function_exists( 'is_product' ) || ! is_product() ) {
				return false;
			}
			$product_id = self::get_current_product_id();
		}

		if ( $product_id <= 0 ) {
			return false;
		}

		return 'no' !== get_post_meta( $product_id, self::META_KEY, true );
	}

	/**
	 * One-time: clear legacy `no` values from the old opt-in era.
	 * Those meant “not opted in”, not “keep Elementor”.
	 */
	public static function migrate_legacy_opt_in_meta(): void {
		if ( get_option( self::MIGRATE_OPTION ) ) {
			return;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s",
				self::META_KEY,
				'no'
			)
		);

		update_option( self::MIGRATE_OPTION, '1', true );
	}

	/**
	 * Checkbox in WooCommerce General product tab (checked = light; uncheck to opt out).
	 */
	public static function render_checkbox(): void {
		global $post;

		$product_id = $post instanceof \WP_Post ? (int) $post->ID : 0;
		$meta       = $product_id > 0 ? (string) get_post_meta( $product_id, self::META_KEY, true ) : '';
		// Empty / yes / anything except no → light (global default).
		$value = ( 'no' === $meta ) ? 'no' : 'yes';

		echo '<div class="options_group">';
		woocommerce_wp_checkbox(
			array(
				'id'          => self::META_KEY,
				'value'       => $value,
				'cbvalue'     => 'yes',
				'label'       => __( 'تمپلیت سبک (بدون Elementor)', 'hello-elementor-child' ),
				'description' => __( 'به‌صورت پیش‌فرض برای همهٔ محصولات فعال است. برای برگشت به تمپلیت Elementor، تیک را بردارید.', 'hello-elementor-child' ),
				'desc_tip'    => true,
			)
		);
		echo '</div>';
	}

	/**
	 * Persist checkbox: checked → default light (clear meta); unchecked → opt out (`no`).
	 *
	 * @param int $product_id Product ID.
	 */
	public static function save_checkbox( int $product_id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST[ self::META_KEY ] ) ) {
			delete_post_meta( $product_id, self::META_KEY );
			return;
		}

		update_post_meta( $product_id, self::META_KEY, 'no' );
	}

	/**
	 * Hard takeover: render light template before Jet/Elementor win template_include.
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
	 * Force custom template when meta is enabled (fallback if force_light_template did not run).
	 *
	 * @param string $template Current template path.
	 */
	public static function maybe_use_custom_template( string $template ): string {
		if ( ! self::is_enabled() ) {
			return $template;
		}

		$custom = self::get_light_template_path();
		if ( file_exists( $custom ) ) {
			return $custom;
		}

		return $template;
	}

	/**
	 * @param bool   $need_override Whether Elementor wants override.
	 * @param string $location      Location name.
	 */
	public static function disable_elementor_locations( bool $need_override, string $location ): bool {
		if ( ! self::is_enabled() ) {
			return $need_override;
		}

		if ( in_array( $location, array( 'header', 'footer', 'single', 'single-product', 'archive', 'popup' ), true ) ) {
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
	 * @deprecated Kept for backwards compatibility.
	 * @param bool   $need_override Whether Elementor wants override.
	 * @param string $location      Location name.
	 */
	public static function disable_elementor_single_override( bool $need_override, string $location ): bool {
		return self::disable_elementor_locations( $need_override, $location );
	}

	/**
	 * Tell JetWooBuilder not to use its custom single template ID.
	 *
	 * @param mixed $template_id Jet template ID.
	 * @return mixed
	 */
	public static function disable_jet_single_template( $template_id ) {
		if ( self::is_enabled() ) {
			return 0;
		}

		return $template_id;
	}

	/**
	 * Prevent JetThemeCore from swapping the PHP template.
	 *
	 * @param mixed $template Current template path.
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
	 * Render Elementor (or JetThemeCore) theme location into a string.
	 *
	 * @param string $location Location name (header|footer).
	 */
	public static function capture_theme_location( string $location ): string {
		ob_start();
		self::print_theme_location( $location );
		return (string) ob_get_clean();
	}

	/**
	 * Echo a theme location (must run after wp_head so widget assets enqueue properly).
	 *
	 * @param string $location Location name (header|footer).
	 */
	public static function print_theme_location( string $location = 'header' ): void {
		$done = false;

		if ( function_exists( 'elementor_theme_do_location' ) ) {
			$done = (bool) elementor_theme_do_location( $location );
		}

		if ( ! $done && function_exists( 'jet_theme_core' ) ) {
			$core = jet_theme_core();
			if ( is_object( $core ) && isset( $core->locations ) && is_object( $core->locations )
				&& method_exists( $core->locations, 'do_location' )
			) {
				$core->locations->do_location( $location );
			}
		}
	}

	/**
	 * Strip Elementor / Jet product-builder assets on light single product pages.
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
			'woocommerce-layout',
			'woocommerce-smallscreen',
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
				if ( str_starts_with( $h, 'lk-spl-' ) || 'woocommerce-general' === $h ) {
					continue;
				}
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
				if ( str_starts_with( $h, 'lk-spl-' ) ) {
					continue;
				}
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
}

if ( ! function_exists( 'lk_light_print_theme_location' ) ) {
	/**
	 * Twig-callable wrapper: print Elementor header/footer location.
	 *
	 * @param string $location Location name.
	 */
	function lk_light_print_theme_location( string $location = 'header' ): void {
		Hello_Elementor_Child_Custom_Single_Product::print_theme_location( $location );
	}
}
