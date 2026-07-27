<?php
/**
 * Opt-in light single product template (per product checkbox).
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Switches selected products to a child-theme template and strips Elementor assets.
 */
final class Hello_Elementor_Child_Custom_Single_Product {

	public const META_KEY = '_lk_use_light_single';

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'render_checkbox' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_checkbox' ) );

		// Temporary: show light-template flag in products list.
		add_filter( 'manage_edit-product_columns', array( __CLASS__, 'add_products_list_column' ), 20 );
		add_action( 'manage_product_posts_custom_column', array( __CLASS__, 'render_products_list_column' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'render_products_list_filter' ) );
		add_filter( 'parse_query', array( __CLASS__, 'filter_products_list_by_light' ) );

		add_filter( 'timber/locations', array( __CLASS__, 'add_timber_locations' ) );

		// Beat JetWooBuilder / JetThemeCore / Elementor (they often use very high priorities).
		add_action( 'template_redirect', array( __CLASS__, 'force_light_template' ), 1 );
		add_filter( 'template_include', array( __CLASS__, 'maybe_use_custom_template' ), PHP_INT_MAX );
		add_filter( 'elementor/theme/need_override_location', array( __CLASS__, 'disable_elementor_single_override' ), PHP_INT_MAX, 2 );
		add_filter( 'jet-woo-builder/custom-single-template', array( __CLASS__, 'disable_jet_single_template' ), PHP_INT_MAX );
		add_filter( 'jet-theme-core/template-include', array( __CLASS__, 'disable_jet_theme_core_template' ), PHP_INT_MAX );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_light_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'dequeue_conflicting_assets' ), 100 );
		add_action( 'wp_print_styles', array( __CLASS__, 'dequeue_conflicting_assets' ), 100 );
		add_action( 'wp_print_scripts', array( __CLASS__, 'dequeue_conflicting_assets' ), 100 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
	}

	/**
	 * Temporary admin column: light template enabled?
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public static function add_products_list_column( array $columns ): array {
		$columns['lk_light_single'] = 'تمپلیت سبک';
		return $columns;
	}

	/**
	 * Render temporary admin column value.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Product ID.
	 */
	public static function render_products_list_column( string $column, int $post_id ): void {
		if ( 'lk_light_single' !== $column ) {
			return;
		}

		if ( 'yes' === get_post_meta( $post_id, self::META_KEY, true ) ) {
			echo '<span style="color:#007017;font-weight:700;" title="تمپلیت سبک فعال">✓</span>';
		} else {
			echo '<span style="color:#bbb;">—</span>';
		}
	}

	/**
	 * Temporary dropdown filter on products list.
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
			<option value="no" <?php selected( $current, 'no' ); ?>><?php esc_html_e( 'بدون سبک', 'hello-elementor-child' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Apply temporary products-list filter.
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

		if ( 'yes' === $value ) {
			$meta_query[] = array(
				'key'   => self::META_KEY,
				'value' => 'yes',
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
					'value'   => 'yes',
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
		if ( self::is_enabled() && ! in_array( 'lk-single-product-light', $classes, true ) ) {
			$classes[] = 'lk-single-product-light';
		}

		return $classes;
	}

	/**
	 * Enqueue light template CSS/JS early (before wp_head in Timber).
	 */
	public static function enqueue_light_assets(): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		wp_enqueue_style(
			'lk-spl-vazirmatn',
			'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap',
			array(),
			null
		);

		wp_enqueue_style(
			'lk-spl-css',
			HELLO_ELEMENTOR_CHILD_URI . 'assets/css/single-product-light.css',
			array( 'lk-spl-vazirmatn' ),
			(string) filemtime( HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/single-product-light.css' )
		);

		wp_enqueue_script(
			'lk-elementor-menu-cart',
			HELLO_ELEMENTOR_CHILD_URI . 'assets/js/lk-elementor-menu-cart.js',
			array(),
			(string) filemtime( HELLO_ELEMENTOR_CHILD_PATH . 'assets/js/lk-elementor-menu-cart.js' ),
			true
		);

		wp_enqueue_script(
			'lk-spl-js',
			HELLO_ELEMENTOR_CHILD_URI . 'assets/js/single-product-light.js',
			array( 'lk-elementor-menu-cart' ),
			(string) filemtime( HELLO_ELEMENTOR_CHILD_PATH . 'assets/js/single-product-light.js' ),
			true
		);

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
	 *
	 * @param int|null $product_id Optional product ID.
	 */
	public static function is_enabled( ?int $product_id = null ): bool {
		if ( null === $product_id ) {
			$product_id = self::get_current_product_id();
		}

		if ( $product_id <= 0 ) {
			return false;
		}

		return 'yes' === get_post_meta( $product_id, self::META_KEY, true );
	}

	/**
	 * Checkbox in WooCommerce General product tab.
	 */
	public static function render_checkbox(): void {
		echo '<div class="options_group">';
		woocommerce_wp_checkbox(
			array(
				'id'          => self::META_KEY,
				'label'       => __( 'تمپلیت سبک (بدون Elementor)', 'hello-elementor-child' ),
				'description' => __( 'اگر فعال باشد، صفحهٔ این محصول با تمپلیت Timber در child theme نمایش داده می‌شود و CSS/JS المنتور لود نمی‌شود.', 'hello-elementor-child' ),
				'desc_tip'    => true,
			)
		);
		echo '</div>';
	}

	/**
	 * Persist checkbox value.
	 *
	 * @param int $product_id Product ID.
	 */
	public static function save_checkbox( int $product_id ): void {
		$value = isset( $_POST[ self::META_KEY ] ) ? 'yes' : 'no'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		update_post_meta( $product_id, self::META_KEY, $value );
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
	 * Stop Elementor Theme Builder from owning the product "single" location only.
	 * Header / footer locations stay active for the hybrid chrome.
	 *
	 * @param bool   $need_override Whether Elementor wants override.
	 * @param string $location      Location name.
	 */
	public static function disable_elementor_single_override( bool $need_override, string $location ): bool {
		if ( ! self::is_enabled() ) {
			return $need_override;
		}

		if ( in_array( $location, array( 'single', 'single-product' ), true ) ) {
			return false;
		}

		return $need_override;
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
	 * Strip Jet Woo Builder assets only — keep Elementor for real header/footer.
	 */
	public static function dequeue_conflicting_assets(): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		$style_handles = array(
			'jet-woo-builder',
			'jet-woo-builder-frontend-font',
			'woocommerce-layout',
			'woocommerce-smallscreen',
		);

		$script_handles = array(
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
				if ( self::is_conflicting_style_handle( $handle ) ) {
					wp_dequeue_style( $handle );
					wp_deregister_style( $handle );
				}
			}
		}

		if ( $wp_scripts instanceof WP_Scripts ) {
			foreach ( (array) $wp_scripts->queue as $handle ) {
				if ( self::is_conflicting_script_handle( $handle ) ) {
					wp_dequeue_script( $handle );
					wp_deregister_script( $handle );
				}
			}
		}
	}

	/**
	 * @param string $handle Asset handle.
	 */
	private static function is_conflicting_style_handle( string $handle ): bool {
		$handle = strtolower( $handle );

		if ( str_starts_with( $handle, 'lk-spl-' ) ) {
			return false;
		}

		if ( 'woocommerce-general' === $handle ) {
			return false;
		}

		// Only Jet Woo product-builder CSS — Elementor must stay for header/footer.
		return str_starts_with( $handle, 'jet-woo' )
			|| false !== strpos( $handle, 'jet-woo' );
	}

	/**
	 * @param string $handle Asset handle.
	 */
	private static function is_conflicting_script_handle( string $handle ): bool {
		$handle = strtolower( $handle );

		if ( str_starts_with( $handle, 'lk-spl-' ) ) {
			return false;
		}

		return str_starts_with( $handle, 'jet-woo' )
			|| false !== strpos( $handle, 'jet-woo' );
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
