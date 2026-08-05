<?php
/**
 * Opt-in light product category archive template (per category checkbox).
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Switches selected product categories to a Timber archive template.
 */
final class Hello_Elementor_Child_Custom_Category_Archive {

	public const META_KEY = '_lk_use_light_category';

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'product_cat_add_form_fields', array( __CLASS__, 'render_add_checkbox' ) );
		add_action( 'product_cat_edit_form_fields', array( __CLASS__, 'render_edit_checkbox' ), 10, 1 );
		add_action( 'created_product_cat', array( __CLASS__, 'save_checkbox' ) );
		add_action( 'edited_product_cat', array( __CLASS__, 'save_checkbox' ) );

		add_filter( 'manage_edit-product_cat_columns', array( __CLASS__, 'add_list_column' ) );
		add_filter( 'manage_product_cat_custom_column', array( __CLASS__, 'render_list_column' ), 10, 3 );

		add_filter( 'timber/locations', array( __CLASS__, 'add_timber_locations' ) );

		add_action( 'template_redirect', array( __CLASS__, 'force_light_template' ), 1 );
		add_filter( 'template_include', array( __CLASS__, 'maybe_use_custom_template' ), PHP_INT_MAX );
		add_filter( 'elementor/theme/need_override_location', array( __CLASS__, 'disable_elementor_archive_override' ), PHP_INT_MAX, 2 );
		add_filter( 'jet-theme-core/template-include', array( __CLASS__, 'disable_jet_theme_core_template' ), PHP_INT_MAX );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_light_assets' ), 5 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
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
	 * Whether the light category template is active for the current (or given) term.
	 *
	 * @param int|null $term_id Optional term ID.
	 */
	public static function is_enabled( ?int $term_id = null ): bool {
		if ( null === $term_id ) {
			if ( ! is_product_category() ) {
				return false;
			}
			$term = get_queried_object();
			if ( ! $term instanceof WP_Term ) {
				return false;
			}
			$term_id = (int) $term->term_id;
		}

		if ( $term_id <= 0 ) {
			return false;
		}

		return 'yes' === get_term_meta( $term_id, self::META_KEY, true );
	}

	/**
	 * Checkbox on "Add category" screen.
	 */
	public static function render_add_checkbox(): void {
		?>
		<div class="form-field">
			<label for="<?php echo esc_attr( self::META_KEY ); ?>">
				<input type="checkbox" name="<?php echo esc_attr( self::META_KEY ); ?>" id="<?php echo esc_attr( self::META_KEY ); ?>" value="1">
				<?php esc_html_e( 'تمپلیت سبک دسته (بدون Elementor)', 'hello-elementor-child' ); ?>
			</label>
			<p><?php esc_html_e( 'اگر فعال باشد، صفحه این دسته با تمپلیت Timber نمایش داده می‌شود (هدر/فوتر Elementor حفظ می‌شود).', 'hello-elementor-child' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Checkbox on "Edit category" screen.
	 *
	 * @param WP_Term $term Term.
	 */
	public static function render_edit_checkbox( WP_Term $term ): void {
		$checked = 'yes' === get_term_meta( (int) $term->term_id, self::META_KEY, true );
		?>
		<tr class="form-field">
			<th scope="row">
				<label for="<?php echo esc_attr( self::META_KEY ); ?>"><?php esc_html_e( 'تمپلیت سبک', 'hello-elementor-child' ); ?></label>
			</th>
			<td>
				<label for="<?php echo esc_attr( self::META_KEY ); ?>">
					<input type="checkbox" name="<?php echo esc_attr( self::META_KEY ); ?>" id="<?php echo esc_attr( self::META_KEY ); ?>" value="1" <?php checked( $checked ); ?>>
					<?php esc_html_e( 'تمپلیت سبک دسته (بدون Elementor)', 'hello-elementor-child' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'اگر فعال باشد، صفحه این دسته با تمپلیت Timber نمایش داده می‌شود (هدر/فوتر Elementor حفظ می‌شود).', 'hello-elementor-child' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Persist checkbox.
	 *
	 * @param int $term_id Term ID.
	 */
	public static function save_checkbox( int $term_id ): void {
		$value = isset( $_POST[ self::META_KEY ] ) ? 'yes' : 'no'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		update_term_meta( $term_id, self::META_KEY, $value );
	}

	/**
	 * Admin list column.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public static function add_list_column( array $columns ): array {
		$columns['lk_light_category'] = __( 'تمپلیت سبک', 'hello-elementor-child' );
		return $columns;
	}

	/**
	 * Admin list column value.
	 *
	 * @param string $content Column content.
	 * @param string $column  Column key.
	 * @param int    $term_id Term ID.
	 */
	public static function render_list_column( string $content, string $column, int $term_id ): string {
		if ( 'lk_light_category' !== $column ) {
			return $content;
		}
		if ( 'yes' === get_term_meta( $term_id, self::META_KEY, true ) ) {
			return '<span style="color:#007017;font-weight:700;" title="تمپلیت سبک فعال">✓</span>';
		}
		return '<span style="color:#bbb;">—</span>';
	}

	/**
	 * Hard takeover before Elementor/Jet archive templates.
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
	 * Fallback template_include swap.
	 *
	 * @param string $template Current template.
	 */
	public static function maybe_use_custom_template( string $template ): string {
		if ( ! self::is_enabled() ) {
			return $template;
		}
		$custom = self::get_light_template_path();
		return file_exists( $custom ) ? $custom : $template;
	}

	/**
	 * Keep Elementor header/footer; block archive content override.
	 *
	 * @param bool   $need_override Whether override is needed.
	 * @param string $location      Location name.
	 */
	public static function disable_elementor_archive_override( bool $need_override, string $location ): bool {
		if ( ! self::is_enabled() ) {
			return $need_override;
		}
		if ( in_array( $location, array( 'archive', 'product_archive', 'product-archive' ), true ) ) {
			return false;
		}
		return $need_override;
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
		$custom = self::get_light_template_path();
		return file_exists( $custom ) ? $custom : $template;
	}

	/**
	 * Light template PHP path.
	 */
	public static function get_light_template_path(): string {
		return HELLO_ELEMENTOR_CHILD_PATH . 'woocommerce/taxonomy-product_cat-light.php';
	}

	/**
	 * Enqueue light archive CSS.
	 */
	public static function enqueue_light_assets(): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		wp_enqueue_style(
			'lk-archive-product-light',
			HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-product-light.css',
			array( HELLO_ELEMENTOR_CHILD_VAZIRMATN_HANDLE ),
			HELLO_ELEMENTOR_CHILD_VERSION
		);

		wp_enqueue_script(
			'lk-elementor-menu-cart',
			HELLO_ELEMENTOR_CHILD_URI . 'assets/js/lk-elementor-menu-cart.js',
			array(),
			(string) filemtime( HELLO_ELEMENTOR_CHILD_PATH . 'assets/js/lk-elementor-menu-cart.js' ),
			true
		);

		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'wc-add-to-cart' );
		wp_enqueue_script( 'wc-cart-fragments' );
		wp_enqueue_script( 'woocommerce' );
	}

	/**
	 * Body class.
	 *
	 * @param array<int, string> $classes Classes.
	 * @return array<int, string>
	 */
	public static function body_class( array $classes ): array {
		if ( self::is_enabled() ) {
			$classes[] = 'lk-archive-product-light';
		}
		return $classes;
	}

	/**
	 * Products per page for light archive.
	 */
	public static function get_per_page(): int {
		$per_page = (int) apply_filters( 'loop_shop_per_page', wc_get_default_products_per_row() * wc_get_default_product_rows_per_page() );
		if ( $per_page < 1 ) {
			$per_page = 12;
		}
		return (int) apply_filters( 'lk_archive_light_per_page', $per_page );
	}
}
