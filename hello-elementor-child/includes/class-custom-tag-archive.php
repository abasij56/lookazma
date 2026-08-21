<?php
/**
 * Light product tag (brand) archive — all product_tag archives.
 *
 * Native Lookazma chrome + shop cards + brand hero.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Switches product tag archives to the Timber light template (opt-out per tag).
 */
final class Hello_Elementor_Child_Custom_Tag_Archive {

	public const META_LIGHT = '_lk_use_light_tag';

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_filter( 'timber/locations', array( __CLASS__, 'add_timber_locations' ) );

		add_action( 'product_tag_add_form_fields', array( __CLASS__, 'render_add_fields' ) );
		add_action( 'product_tag_edit_form_fields', array( __CLASS__, 'render_edit_fields' ), 10, 1 );
		add_action( 'created_product_tag', array( __CLASS__, 'save_term_fields' ) );
		add_action( 'edited_product_tag', array( __CLASS__, 'save_term_fields' ) );

		add_filter( 'woocommerce_get_breadcrumb', array( __CLASS__, 'filter_breadcrumb' ), 20 );

		add_action( 'template_redirect', array( __CLASS__, 'force_light_template' ), 1 );
		add_filter( 'template_include', array( __CLASS__, 'maybe_use_custom_template' ), PHP_INT_MAX );
		add_filter( 'elementor/theme/need_override_location', array( __CLASS__, 'disable_elementor_locations' ), PHP_INT_MAX, 2 );
		add_filter( 'elementor/theme/get_location_templates', array( __CLASS__, 'remove_popup_templates' ), PHP_INT_MAX, 2 );
		add_filter( 'elementor/theme/get_location_templates/popup', array( __CLASS__, 'deny_popup_templates' ), PHP_INT_MAX );
		add_filter( 'jet-theme-core/template-include', array( __CLASS__, 'disable_jet_theme_core_template' ), PHP_INT_MAX );
		add_filter( 'jet-woo-builder/custom-archive-template', array( __CLASS__, 'disable_jet_archive_template' ), PHP_INT_MAX );
		add_filter( 'jet-woo-builder/custom-shop-template', array( __CLASS__, 'disable_jet_archive_template' ), PHP_INT_MAX );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_light_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'dequeue_listing_assets' ), 100 );
		add_action( 'wp_print_styles', array( __CLASS__, 'dequeue_listing_assets' ), 100 );
		add_action( 'wp_print_scripts', array( __CLASS__, 'dequeue_listing_assets' ), 100 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
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
	 * Whether the light tag template is active for the current (or given) term.
	 * Default on; set meta to `no` to opt out.
	 *
	 * @param int|null $term_id Optional product_tag term ID (e.g. AJAX filters).
	 */
	public static function is_enabled( ?int $term_id = null ): bool {
		if ( null === $term_id ) {
			if ( ! function_exists( 'is_product_tag' ) || ! is_product_tag() ) {
				return false;
			}
			$term = get_queried_object();
			$term_id = $term instanceof WP_Term ? (int) $term->term_id : 0;
		}

		if ( $term_id <= 0 ) {
			return false;
		}

		$term = get_term( $term_id, 'product_tag' );
		if ( ! $term instanceof WP_Term || is_wp_error( $term ) ) {
			return false;
		}

		return 'no' !== get_term_meta( $term_id, self::META_LIGHT, true );
	}

	/**
	 * Fields on “Add tag”.
	 */
	public static function render_add_fields(): void {
		?>
		<div class="form-field term-lk-light-wrap">
			<label>
				<input type="checkbox" name="<?php echo esc_attr( self::META_LIGHT ); ?>" value="yes" checked="checked">
				<?php esc_html_e( 'تمپلیت سبک (بدون Elementor)', 'hello-elementor-child' ); ?>
			</label>
			<p><?php esc_html_e( 'به‌صورت پیش‌فرض فعال است. برای برگشت به Elementor، تیک را بردارید.', 'hello-elementor-child' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Fields on “Edit tag”.
	 *
	 * @param \WP_Term $term Term.
	 */
	public static function render_edit_fields( $term ): void {
		if ( ! $term instanceof WP_Term ) {
			return;
		}
		$term_id  = (int) $term->term_id;
		$light_on = 'no' !== get_term_meta( $term_id, self::META_LIGHT, true );
		?>
		<tr class="form-field term-lk-light-wrap">
			<th scope="row">
				<label for="<?php echo esc_attr( self::META_LIGHT ); ?>"><?php esc_html_e( 'تمپلیت سبک', 'hello-elementor-child' ); ?></label>
			</th>
			<td>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( self::META_LIGHT ); ?>" id="<?php echo esc_attr( self::META_LIGHT ); ?>" value="yes" <?php checked( $light_on ); ?>>
					<?php esc_html_e( 'تمپلیت سبک (بدون Elementor)', 'hello-elementor-child' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'به‌صورت پیش‌فرض برای همهٔ برچسب‌ها فعال است. برای برگشت به Elementor، تیک را بردارید.', 'hello-elementor-child' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Persist light toggle.
	 *
	 * @param int $term_id Term ID.
	 */
	public static function save_term_fields( int $term_id ): void {
		if ( $term_id <= 0 ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- term form nonce handled by core.
		$light = isset( $_POST[ self::META_LIGHT ] ) ? 'yes' : 'no';
		if ( 'yes' === $light ) {
			delete_term_meta( $term_id, self::META_LIGHT );
		} else {
			update_term_meta( $term_id, self::META_LIGHT, 'no' );
		}
	}

	/**
	 * Candidate meta / ACF field names for brand tag logo.
	 *
	 * @return array<int, string>
	 */
	private static function logo_field_keys(): array {
		return array(
			'لوگوی_برچسب_برند',
			'لوگوی برچسب برند',
			'لوگو_برچسب_برند',
			'لوگو برچسب برند',
			'لوگوی_برچست_برند',
			'brand_tag_logo',
			'tag_brand_logo',
			'brand_logo_tag',
			'thumbnail_id',
			'_thumbnail_id',
			'thumbnail',
			'image',
			'logo',
			'brand_logo',
			'brand_image',
			'لوگو',
			'لوگو_برند',
			'تصویر',
			'تصویر_برند',
		);
	}

	/**
	 * Resolve attachment ID for a product_tag logo (ACF «لوگوی برچسب برند» + fallbacks).
	 *
	 * @param int $term_id Term ID.
	 */
	public static function resolve_thumbnail_id( int $term_id ): int {
		if ( $term_id <= 0 ) {
			return 0;
		}

		$acf_id = 'product_tag_' . $term_id;

		// 1) ACF fields matched by label (e.g. «لوگوی برچسب برند»).
		if ( function_exists( 'acf_get_field_objects' ) ) {
			$fields = acf_get_field_objects( $acf_id );
			if ( is_array( $fields ) ) {
				foreach ( $fields as $field ) {
					if ( ! is_array( $field ) ) {
						continue;
					}
					$label = isset( $field['label'] ) ? (string) $field['label'] : '';
					$name  = isset( $field['name'] ) ? (string) $field['name'] : '';
					$type  = isset( $field['type'] ) ? (string) $field['type'] : '';
					if ( ! self::is_logo_field( $label, $name, $type ) ) {
						continue;
					}
					$id = self::parse_media_id( $field['value'] ?? null );
					if ( $id > 0 ) {
						return $id;
					}
					// Unformatted value (attachment ID).
					if ( function_exists( 'get_field' ) && '' !== $name ) {
						$id = self::parse_media_id( get_field( $name, $acf_id, false ) );
						if ( $id > 0 ) {
							return $id;
						}
					}
				}
			}
		}

		// 2) Known keys via get_field / term meta.
		foreach ( self::logo_field_keys() as $key ) {
			if ( function_exists( 'get_field' ) ) {
				$id = self::parse_media_id( get_field( $key, $acf_id, false ) );
				if ( $id > 0 ) {
					return $id;
				}
				$id = self::parse_media_id( get_field( $key, $acf_id, true ) );
				if ( $id > 0 ) {
					return $id;
				}
			}
			$id = self::parse_media_id( get_term_meta( $term_id, $key, true ) );
			if ( $id > 0 ) {
				return $id;
			}
		}

		// 3) Any term meta key that looks like a logo field.
		foreach ( array_keys( (array) get_term_meta( $term_id ) ) as $meta_key ) {
			$meta_key = (string) $meta_key;
			if ( str_starts_with( $meta_key, '_' ) ) {
				continue;
			}
			if ( ! self::is_logo_field( $meta_key, $meta_key, 'image' ) ) {
				continue;
			}
			$id = self::parse_media_id( get_term_meta( $term_id, $meta_key, true ) );
			if ( $id > 0 ) {
				return $id;
			}
		}

		return 0;
	}

	/**
	 * Resolve logo URL for a product_tag.
	 *
	 * @param int    $term_id Term ID.
	 * @param string $size    Image size.
	 */
	public static function resolve_image_url( int $term_id, string $size = 'large' ): string {
		$id = self::resolve_thumbnail_id( $term_id );
		if ( $id > 0 ) {
			$url = wp_get_attachment_image_url( $id, $size );
			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}

		$acf_id = 'product_tag_' . $term_id;

		if ( function_exists( 'acf_get_field_objects' ) ) {
			$fields = acf_get_field_objects( $acf_id );
			if ( is_array( $fields ) ) {
				foreach ( $fields as $field ) {
					if ( ! is_array( $field ) ) {
						continue;
					}
					$label = isset( $field['label'] ) ? (string) $field['label'] : '';
					$name  = isset( $field['name'] ) ? (string) $field['name'] : '';
					$type  = isset( $field['type'] ) ? (string) $field['type'] : '';
					if ( ! self::is_logo_field( $label, $name, $type ) ) {
						continue;
					}
					$url = self::extract_url_from_value( $field['value'] ?? null );
					if ( '' !== $url ) {
						return $url;
					}
					if ( function_exists( 'get_field' ) && '' !== $name ) {
						$url = self::extract_url_from_value( get_field( $name, $acf_id, true ) );
						if ( '' !== $url ) {
							return $url;
						}
					}
				}
			}
		}

		foreach ( self::logo_field_keys() as $key ) {
			if ( function_exists( 'get_field' ) ) {
				$url = self::extract_url_from_value( get_field( $key, $acf_id, true ) );
				if ( '' !== $url ) {
					return $url;
				}
			}
			$url = self::extract_url_from_value( get_term_meta( $term_id, $key, true ) );
			if ( '' !== $url ) {
				return $url;
			}
		}

		return '';
	}

	/**
	 * Whether a field looks like the brand-tag logo («لوگوی برچسب برند»).
	 */
	private static function is_logo_field( string $label, string $name, string $type ): bool {
		$hay = $label . ' ' . $name;
		$hay = mb_strtolower( $hay );

		if ( false !== mb_stripos( $hay, 'لوگوی برچسب برند' )
			|| false !== mb_stripos( $hay, 'لوگوی برچست برند' )
			|| false !== mb_stripos( $hay, 'لوگو برچسب برند' )
		) {
			return true;
		}

		$has_logo  = ( false !== mb_stripos( $hay, 'لوگو' ) || false !== strpos( $hay, 'logo' ) );
		$has_brand = ( false !== mb_stripos( $hay, 'برند' )
			|| false !== mb_stripos( $hay, 'برچسب' )
			|| false !== strpos( $hay, 'brand' )
			|| false !== strpos( $hay, 'tag' ) );

		if ( $has_logo && ( $has_brand || in_array( $type, array( 'image', 'file', 'url' ), true ) ) ) {
			return true;
		}

		return in_array( $name, self::logo_field_keys(), true );
	}

	/**
	 * @param mixed $value Raw value.
	 */
	private static function extract_url_from_value( $value ): string {
		if ( is_array( $value ) ) {
			if ( ! empty( $value['url'] ) && is_string( $value['url'] ) ) {
				return (string) $value['url'];
			}
			if ( ! empty( $value['sizes']['large'] ) && is_string( $value['sizes']['large'] ) ) {
				return (string) $value['sizes']['large'];
			}
			if ( ! empty( $value['sizes']['medium'] ) && is_string( $value['sizes']['medium'] ) ) {
				return (string) $value['sizes']['medium'];
			}
		}
		if ( is_string( $value ) && preg_match( '#^(https?:)?//#i', $value ) ) {
			return $value;
		}
		$id = self::parse_media_id( $value );
		if ( $id > 0 ) {
			$url = wp_get_attachment_image_url( $id, 'large' );
			return is_string( $url ) ? $url : '';
		}
		return '';
	}

	/**
	 * @param mixed $value Raw media value.
	 */
	private static function parse_media_id( $value ): int {
		if ( null === $value || false === $value || '' === $value ) {
			return 0;
		}
		if ( is_numeric( $value ) ) {
			return (int) $value > 0 ? (int) $value : 0;
		}
		if ( is_array( $value ) ) {
			if ( isset( $value['ID'] ) ) {
				return (int) $value['ID'];
			}
			if ( isset( $value['id'] ) ) {
				return (int) $value['id'];
			}
			if ( ! empty( $value['url'] ) && is_string( $value['url'] ) ) {
				$from_url = attachment_url_to_postid( $value['url'] );
				return $from_url > 0 ? $from_url : 0;
			}
		}
		if ( is_string( $value ) && preg_match( '#^(https?:)?//#i', $value ) ) {
			$from_url = attachment_url_to_postid( $value );
			return $from_url > 0 ? $from_url : 0;
		}
		return 0;
	}

	/**
	 * Breadcrumb: خانه > محصولات [tag-name]
	 *
	 * @param array<int, array{0?:string,1?:string}> $crumbs Crumbs.
	 * @return array<int, array{0?:string,1?:string}>
	 */
	public static function filter_breadcrumb( array $crumbs ): array {
		if ( ! function_exists( 'is_product_tag' ) || ! is_product_tag() ) {
			return $crumbs;
		}

		$term = get_queried_object();
		if ( ! $term instanceof WP_Term || 'product_tag' !== $term->taxonomy ) {
			return $crumbs;
		}

		$home_label = __( 'خانه', 'hello-elementor-child' );
		$home_url   = home_url( '/' );
		if ( isset( $crumbs[0] ) && is_array( $crumbs[0] ) ) {
			$home_label = isset( $crumbs[0][0] ) ? (string) $crumbs[0][0] : $home_label;
			$home_url   = isset( $crumbs[0][1] ) ? (string) $crumbs[0][1] : $home_url;
		}

		return array(
			array( $home_label, $home_url ),
			array(
				sprintf(
					/* translators: %s: brand/tag name */
					__( 'محصولات %s', 'hello-elementor-child' ),
					$term->name
				),
				'',
			),
		);
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
	 * @param bool   $need_override Whether override is needed.
	 * @param string $location      Location name.
	 */
	public static function disable_elementor_locations( bool $need_override, string $location ): bool {
		if ( ! self::is_enabled() ) {
			return $need_override;
		}
		if ( in_array( $location, array( 'header', 'footer', 'archive', 'product_archive', 'product-archive', 'popup' ), true ) ) {
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
	 * @param mixed $template_id Jet template ID.
	 * @return mixed
	 */
	public static function disable_jet_archive_template( $template_id ) {
		return self::is_enabled() ? 0 : $template_id;
	}

	/**
	 * Light template PHP path.
	 */
	public static function get_light_template_path(): string {
		return HELLO_ELEMENTOR_CHILD_PATH . 'woocommerce/taxonomy-product_tag-light.php';
	}

	/**
	 * Products per page.
	 */
	public static function get_per_page(): int {
		return 12;
	}

	/**
	 * Front-end assets.
	 */
	public static function enqueue_light_assets(): void {
		if ( ! self::is_enabled() ) {
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

		wp_add_inline_style( 'lk-light-product', 'body.lk-light-tag{--lk-lpt-cols:4;}' );

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
		if ( self::is_enabled() ) {
			$classes[] = 'lk-light-product';
			$classes[] = 'lk-light-tag';
			$classes[] = 'lk-shop-cards-archive';
			$classes[] = 'lz-chrome';

			$skip = array(
				'lk-single-product-light'       => true,
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
		}
		return array_values( array_unique( $classes ) );
	}
}
