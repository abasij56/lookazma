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

	public const OPTION_PRICE_NOTICE = 'lk_product_price_notice';

	public const DEFAULT_PRICE_NOTICE = 'به علت نوسانات قیمت لطفا قبل از اقدام به خرید از طریق تماس تلفنی استعلام قیمت بگیرید';

	private const MIGRATE_OPTION = 'lk_light_single_global_v1';

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'migrate_legacy_opt_in_meta' ), 5 );

		add_action( 'customize_register', array( __CLASS__, 'register_customizer' ) );

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
		add_filter( 'woocommerce_reset_variations_link', array( __CLASS__, 'reset_variations_link' ) );
		add_filter( 'woocommerce_dropdown_variation_attribute_options_args', array( __CLASS__, 'force_variation_dropdown_visible' ) );
		add_filter( 'woocommerce_product_get_default_attributes', array( __CLASS__, 'clear_single_variation_defaults' ), 20, 2 );
		add_action( 'wp', array( __CLASS__, 'maybe_override_variable_add_to_cart' ) );

		// Light product reviews: require login (no guest name/email fields).
		add_filter( 'option_comment_registration', array( __CLASS__, 'require_login_for_reviews' ) );
		add_filter( 'woocommerce_product_review_comment_form_args', array( __CLASS__, 'review_comment_form_args' ) );
		add_action( 'pre_comment_on_post', array( __CLASS__, 'block_guest_product_reviews' ) );
	}

	/**
	 * Reject guest review POSTs on light products (UI already hides the form).
	 *
	 * @param int $post_id Post being commented on.
	 */
	public static function block_guest_product_reviews( $post_id ): void {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 || 'product' !== get_post_type( $post_id ) ) {
			return;
		}
		if ( is_user_logged_in() ) {
			return;
		}
		if ( ! self::is_enabled( $post_id ) ) {
			return;
		}

		$login_url = function_exists( 'wc_get_page_permalink' ) ? (string) wc_get_page_permalink( 'myaccount' ) : wp_login_url();
		wp_safe_redirect( $login_url ? $login_url : home_url( '/' ) );
		exit;
	}

	/**
	 * Force WordPress “must be logged in to comment” on light product singles.
	 *
	 * @param mixed $value Option value.
	 * @return mixed
	 */
	public static function require_login_for_reviews( $value ) {
		if ( self::is_enabled() ) {
			return '1';
		}
		return $value;
	}

	/**
	 * Strip guest fields and localize review form copy on light products.
	 *
	 * @param array<string, mixed> $args Comment form args.
	 * @return array<string, mixed>
	 */
	public static function review_comment_form_args( array $args ): array {
		if ( ! self::is_enabled() ) {
			return $args;
		}

		$args['fields']               = array();
		$args['logged_in_as']         = '';
		$args['comment_notes_before'] = '';
		$args['comment_notes_after']  = '';

		$login_url = function_exists( 'wc_get_page_permalink' ) ? (string) wc_get_page_permalink( 'myaccount' ) : '';
		if ( '' === $login_url ) {
			$login_url = wp_login_url( get_permalink() );
		}

		$args['must_log_in'] = '<div class="lk-reviews__login"><p class="lk-reviews__login-text">'
			. esc_html__( 'برای ثبت نظر ابتدا وارد شوید.', 'hello-elementor-child' )
			. '</p><a class="lk-reviews__login-btn" href="' . esc_url( $login_url ) . '">'
			. esc_html__( 'ورود به حساب', 'hello-elementor-child' )
			. '</a></div>';

		return $args;
	}

	/**
	 * Replace WooCommerce "Clear" / «صاف» with a compact «حذف» control.
	 *
	 * @param string $link Default reset link HTML.
	 */
	public static function reset_variations_link( $link ): string {
		return '<a class="reset_variations lk-price-card__reset" href="#" aria-label="' . esc_attr__( 'حذف انتخاب بسته‌بندی', 'hello-elementor-child' ) . '">' . esc_html__( 'حذف', 'hello-elementor-child' ) . '</a>';
	}

	/**
	 * Keep variation dropdowns visible (even with a single option).
	 *
	 * @param array<string, mixed> $args Dropdown args.
	 * @return array<string, mixed>
	 */
	public static function force_variation_dropdown_visible( array $args ): array {
		if ( ! self::is_enabled() ) {
			return $args;
		}

		if ( empty( $args['show_option_none'] ) ) {
			$args['show_option_none'] = __( 'انتخاب کنید', 'hello-elementor-child' );
		}

		$options = isset( $args['options'] ) && is_array( $args['options'] ) ? $args['options'] : array();
		if ( 1 === count( $options ) ) {
			$current = isset( $args['selected'] ) ? (string) $args['selected'] : '';
			/* Keep an explicit lock (single-packaging note); otherwise force placeholder. */
			if ( '' === $current ) {
				$args['selected'] = '';
			}
		}

		return $args;
	}

	/**
	 * Products with only one variation often ship with defaults set, which makes
	 * plugins/themes hide table.variations. Clear defaults so the UI stays visible.
	 *
	 * @param array<string, string> $defaults Default attributes.
	 * @param WC_Product            $product  Product.
	 * @return array<string, string>
	 */
	public static function clear_single_variation_defaults( $defaults, $product ) {
		if ( ! self::is_enabled() || ! $product instanceof WC_Product_Variable ) {
			return $defaults;
		}

		$available = $product->get_available_variations( 'objects' );
		if ( is_array( $available ) && count( $available ) <= 1 ) {
			return array();
		}

		return is_array( $defaults ) ? $defaults : array();
	}

	/**
	 * On light singles, replace WC variable add-to-cart so attribute dropdowns
	 * are built from child variations when parent attributes are incomplete.
	 */
	public static function maybe_override_variable_add_to_cart(): void {
		if ( ! self::is_enabled() ) {
			return;
		}
		remove_action( 'woocommerce_variable_add_to_cart', 'woocommerce_variable_add_to_cart', 30 );
		add_action( 'woocommerce_variable_add_to_cart', array( __CLASS__, 'render_variable_add_to_cart' ), 30 );
	}

	/**
	 * Variable add-to-cart with repaired attribute lists.
	 */
	public static function render_variable_add_to_cart(): void {
		global $product;

		if ( ! $product instanceof WC_Product_Variable ) {
			return;
		}

		wp_enqueue_script( 'wc-add-to-cart-variation' );

		$attributes = self::get_effective_variation_attributes( $product );
		$threshold  = (int) apply_filters( 'woocommerce_ajax_variation_threshold', 30, $product );
		$get_vars   = count( $product->get_children() ) <= $threshold;

		$selected = $product->get_default_attributes();
		$available_objects = $product->get_available_variations( 'objects' );
		if ( is_array( $available_objects ) && count( $available_objects ) <= 1 ) {
			$selected = array();
		}

		$single_packaging = self::get_single_packaging_context( $product, $attributes );

		/* Ensure hidden variations table has a packaging option so ATC can resolve. */
		if ( ! empty( $single_packaging['active'] )
			&& ! empty( $single_packaging['attribute_key'] )
			&& ! empty( $single_packaging['attribute_slug'] )
		) {
			$key = (string) $single_packaging['attribute_key'];
			$slug = (string) $single_packaging['attribute_slug'];
			if ( empty( $attributes[ $key ] ) || ! is_array( $attributes[ $key ] ) ) {
				$attributes[ $key ] = array( $slug );
			} elseif ( ! in_array( $slug, $attributes[ $key ], true ) ) {
				$attributes[ $key ][] = $slug;
			}
		}

		wc_get_template(
			'single-product/add-to-cart/variable.php',
			array(
				'available_variations' => $get_vars ? $product->get_available_variations() : false,
				'attributes'           => $attributes,
				'selected_attributes'  => $selected,
				'lk_single_packaging'  => $single_packaging,
			)
		);
	}

	/**
	 * Single-packaging products: show a fixed-height notice instead of a dropdown.
	 *
	 * @param WC_Product_Variable               $product    Product.
	 * @param array<string, array<int, string>> $attributes Effective attributes.
	 * @return array{active: bool, label: string, value: string, message: string, attribute_key: string, attribute_slug: string}
	 */
	public static function get_single_packaging_context( WC_Product_Variable $product, array $attributes ): array {
		$empty = array(
			'active'         => false,
			'label'          => __( 'بسته بندی', 'hello-elementor-child' ),
			'value'          => '',
			'message'        => '',
			'attribute_key'  => '',
			'attribute_slug' => '',
		);

		if ( ! self::is_single_packaging_product( $product, $attributes ) ) {
			return $empty;
		}

		$resolved = self::resolve_single_packaging_details( $product, $attributes );
		$value    = isset( $resolved['value'] ) ? trim( (string) $resolved['value'] ) : '';
		if ( '' === $value ) {
			return $empty;
		}

		$message = sprintf(
			/* translators: %s: packaging value, e.g. 10 گرم */
			__( 'این محصول فقط با بسته‌بندی %s عرضه می‌شود.', 'hello-elementor-child' ),
			$value
		);

		return array(
			'active'         => true,
			'label'          => __( 'بسته بندی', 'hello-elementor-child' ),
			'value'          => $value,
			'message'        => $message,
			'attribute_key'  => (string) ( $resolved['attribute_key'] ?? '' ),
			'attribute_slug' => (string) ( $resolved['attribute_slug'] ?? '' ),
		);
	}

	/**
	 * Whether this variable product should use the single-packaging notice.
	 *
	 * @param WC_Product_Variable               $product    Product.
	 * @param array<string, array<int, string>> $attributes Attributes.
	 */
	private static function is_single_packaging_product( WC_Product_Variable $product, array $attributes ): bool {
		$children = $product->get_children();
		if ( is_array( $children ) && count( $children ) <= 1 ) {
			return true;
		}

		$available = $product->get_available_variations( 'objects' );
		if ( is_array( $available ) && count( $available ) <= 1 ) {
			return true;
		}

		$pack_count = self::count_packaging_options( $product, $attributes );
		if ( 1 === $pack_count ) {
			return true;
		}

		/* Attributes map missing packaging: only if parent has exactly one packaging term. */
		if ( 0 === $pack_count ) {
			$terms = self::packaging_term_names_from_parent( $product );
			return 1 === count( $terms );
		}

		return false;
	}

	/**
	 * @param WC_Product_Variable               $product    Product.
	 * @param array<string, array<int, string>> $attributes Attributes.
	 */
	private static function count_packaging_options( WC_Product_Variable $product, array $attributes ): int {
		foreach ( $attributes as $tax => $options ) {
			$label = function_exists( 'wc_attribute_label' ) ? (string) wc_attribute_label( (string) $tax ) : (string) $tax;
			if ( ! self::is_packaging_attribute_label( $label ) && ! self::is_packaging_attribute_label( (string) $tax ) ) {
				continue;
			}
			return is_array( $options ) ? count( array_values( array_filter( array_map( 'strval', $options ) ) ) ) : 0;
		}

		$terms = self::packaging_term_names_from_parent( $product );
		return count( $terms );
	}

	/**
	 * @param WC_Product $product Parent product.
	 * @return array<int, string>
	 */
	private static function packaging_term_names_from_parent( WC_Product $product ): array {
		$labels = array();

		foreach ( $product->get_attributes() as $attribute ) {
			if ( ! $attribute instanceof WC_Product_Attribute ) {
				continue;
			}
			$name  = $attribute->get_name();
			$label = function_exists( 'wc_attribute_label' ) ? (string) wc_attribute_label( $name ) : $name;
			if ( ! self::is_packaging_attribute_label( $label ) && ! self::is_packaging_attribute_label( $name ) ) {
				continue;
			}
			if ( $attribute->is_taxonomy() ) {
				$terms = wc_get_product_terms( $product->get_id(), $name, array( 'fields' => 'all' ) );
				if ( is_wp_error( $terms ) ) {
					continue;
				}
				foreach ( $terms as $term ) {
					if ( $term instanceof WP_Term ) {
						$n = trim( (string) $term->name );
						if ( '' !== $n && ! in_array( $n, $labels, true ) ) {
							$labels[] = $n;
						}
					}
				}
			} else {
				foreach ( (array) $attribute->get_options() as $option ) {
					$option = trim( (string) $option );
					if ( '' !== $option && ! in_array( $option, $labels, true ) ) {
						$labels[] = $option;
					}
				}
			}
		}

		return $labels;
	}

	/**
	 * @param WC_Product_Variable               $product    Product.
	 * @param array<string, array<int, string>> $attributes Attributes.
	 * @return array{value: string, attribute_key: string, attribute_slug: string}
	 */
	private static function resolve_single_packaging_details( WC_Product_Variable $product, array $attributes ): array {
		$out = array(
			'value'          => '',
			'attribute_key'  => '',
			'attribute_slug' => '',
		);

		/* 1) Effective attributes map — prefer packaging with any filled options. */
		foreach ( $attributes as $tax => $options ) {
			$tax   = (string) $tax;
			$label = function_exists( 'wc_attribute_label' ) ? (string) wc_attribute_label( $tax ) : $tax;
			if ( ! self::is_packaging_attribute_label( $label ) && ! self::is_packaging_attribute_label( $tax ) ) {
				continue;
			}
			$out['attribute_key'] = $tax;
			if ( ! is_array( $options ) ) {
				continue;
			}
			$options = array_values( array_filter( array_map( 'strval', $options ) ) );
			if ( array() === $options ) {
				continue;
			}
			/* One option, or take the only one that matches the sole variation. */
			$raw = ( 1 === count( $options ) ) ? (string) $options[0] : '';
			if ( '' === $raw ) {
				$raw = self::packaging_slug_from_sole_variation( $product, $tax );
			}
			if ( '' === $raw && 1 === count( $options ) ) {
				$raw = (string) $options[0];
			}
			if ( '' === $raw ) {
				continue;
			}
			$out['attribute_slug'] = $raw;
			$out['value']          = self::format_packaging_slug( $tax, $raw, $product );
			if ( '' !== $out['value'] ) {
				return $out;
			}
		}

		/* 2) Sole / first available variation attributes. */
		$variation = self::get_sole_variation( $product );
		if ( $variation instanceof WC_Product_Variation ) {
			foreach ( $variation->get_variation_attributes( false ) as $tax => $raw ) {
				$tax   = (string) $tax;
				$raw   = is_string( $raw ) ? trim( $raw ) : '';
				$label = function_exists( 'wc_attribute_label' ) ? (string) wc_attribute_label( $tax, $variation ) : $tax;
				if ( ! self::is_packaging_attribute_label( $label ) && ! self::is_packaging_attribute_label( $tax ) ) {
					continue;
				}
				$out['attribute_key'] = $tax;
				if ( '' === $raw ) {
					$via = $variation->get_attribute( $tax );
					if ( is_string( $via ) && '' !== trim( $via ) ) {
						$out['value']          = trim( $via );
						$out['attribute_slug'] = sanitize_title( $out['value'] );
						return $out;
					}
					continue;
				}
				$out['attribute_slug'] = $raw;
				$via = $variation->get_attribute( $tax );
				$out['value'] = ( is_string( $via ) && '' !== trim( $via ) )
					? trim( $via )
					: self::format_packaging_slug( $tax, $raw, $variation );
				if ( '' !== $out['value'] ) {
					return $out;
				}
			}

			$summary = trim( (string) $variation->get_attribute_summary( 'edit' ) );
			if ( '' === $summary ) {
				$summary = trim( (string) $variation->get_attribute_summary( 'view' ) );
			}
			if ( '' !== $summary && preg_match( '/:\s*(.+)$/u', $summary, $m ) ) {
				$out['value'] = trim( (string) $m[1] );
				if ( '' !== $out['value'] ) {
					return $out;
				}
			}
		}

		/* 3) Parent Attributes tab packaging terms. */
		$terms = self::packaging_term_names_from_parent( $product );
		if ( 1 === count( $terms ) ) {
			$out['value'] = (string) reset( $terms );
			$tax          = self::find_packaging_taxonomy_on_product( $product );
			if ( '' !== $tax ) {
				$out['attribute_key'] = $tax;
				$term = get_term_by( 'name', $out['value'], $tax );
				$out['attribute_slug'] = ( $term instanceof WP_Term ) ? $term->slug : sanitize_title( $out['value'] );
			}
			return $out;
		}

		/* 4) One variation + multiple parent terms: still show the variation's display name if any packaging-looking chunk exists. */
		if ( $variation instanceof WC_Product_Variation && array() !== $terms ) {
			foreach ( $terms as $term_name ) {
				$name = $variation->get_name();
				if ( is_string( $name ) && false !== strpos( $name, $term_name ) ) {
					$out['value'] = $term_name;
					return $out;
				}
			}
		}

		return $out;
	}

	/**
	 * @param WC_Product_Variable $product Product.
	 */
	private static function get_sole_variation( WC_Product_Variable $product ): ?WC_Product_Variation {
		$available = $product->get_available_variations( 'objects' );
		if ( is_array( $available ) && 1 === count( $available ) && $available[0] instanceof WC_Product_Variation ) {
			return $available[0];
		}
		$children = $product->get_children();
		if ( is_array( $children ) && 1 === count( $children ) ) {
			$variation = wc_get_product( (int) reset( $children ) );
			if ( $variation instanceof WC_Product_Variation ) {
				return $variation;
			}
		}
		if ( is_array( $available ) && array() !== $available && $available[0] instanceof WC_Product_Variation ) {
			return $available[0];
		}
		return null;
	}

	/**
	 * @param WC_Product_Variable $product Product.
	 * @param string              $tax     Taxonomy.
	 */
	private static function packaging_slug_from_sole_variation( WC_Product_Variable $product, string $tax ): string {
		$variation = self::get_sole_variation( $product );
		if ( ! $variation instanceof WC_Product_Variation ) {
			return '';
		}
		$attrs = $variation->get_variation_attributes( false );
		if ( isset( $attrs[ $tax ] ) && is_string( $attrs[ $tax ] ) && '' !== trim( $attrs[ $tax ] ) ) {
			return trim( $attrs[ $tax ] );
		}
		$key = 'attribute_' . $tax;
		$attrs_p = $variation->get_variation_attributes( true );
		if ( isset( $attrs_p[ $key ] ) && is_string( $attrs_p[ $key ] ) && '' !== trim( $attrs_p[ $key ] ) ) {
			return trim( $attrs_p[ $key ] );
		}
		return '';
	}

	/**
	 * @param string     $tax     Taxonomy.
	 * @param string     $raw     Slug/value.
	 * @param WC_Product $product Product context.
	 */
	private static function format_packaging_slug( string $tax, string $raw, WC_Product $product ): string {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return '';
		}
		$tax = rawurldecode( $tax );
		if ( taxonomy_exists( $tax ) ) {
			foreach ( array( $raw, rawurldecode( $raw ), sanitize_title( $raw ), sanitize_title( rawurldecode( $raw ) ) ) as $slug ) {
				$term = get_term_by( 'slug', $slug, $tax );
				if ( $term instanceof WP_Term && $term->name ) {
					return trim( (string) $term->name );
				}
			}
		}
		if ( $product instanceof WC_Product_Variation ) {
			$via = $product->get_attribute( $tax );
			if ( is_string( $via ) && '' !== trim( $via ) ) {
				return trim( $via );
			}
		}
		$decoded = trim( rawurldecode( str_replace( array( '-', '_' ), ' ', $raw ) ) );
		$decoded = preg_replace( '/(?<=\d) (?=\d)/u', '.', $decoded );
		return is_string( $decoded ) && '' !== $decoded ? $decoded : $raw;
	}

	/**
	 * @param WC_Product $product Product.
	 */
	private static function find_packaging_taxonomy_on_product( WC_Product $product ): string {
		foreach ( $product->get_attributes() as $attribute ) {
			if ( ! $attribute instanceof WC_Product_Attribute || ! $attribute->is_taxonomy() ) {
				continue;
			}
			$name  = $attribute->get_name();
			$label = function_exists( 'wc_attribute_label' ) ? (string) wc_attribute_label( $name ) : $name;
			if ( self::is_packaging_attribute_label( $label ) || self::is_packaging_attribute_label( $name ) ) {
				return $name;
			}
		}
		$known = array( 'pa_بسته‌ بندی', 'pa_بسته-بندی', 'pa_بسته بندی', 'pa_packaging' );
		foreach ( $known as $tax ) {
			if ( taxonomy_exists( $tax ) ) {
				return $tax;
			}
		}
		return '';
	}

	/**
	 * @param string $label Attribute label or taxonomy.
	 */
	private static function is_packaging_attribute_label( string $label ): bool {
		$norm = function_exists( 'mb_strtolower' ) ? mb_strtolower( $label ) : strtolower( $label );
		$norm = str_replace( array( "\u{200C}", "\xE2\x80\x8C", '-', '_', ' ', '‌' ), '', $norm );
		$norm = rawurldecode( $norm );
		if ( '' === $norm ) {
			return false;
		}
		return false !== strpos( $norm, 'بسته' ) || false !== strpos( $norm, 'packaging' );
	}

	/**
	 * Whether attribute map has at least one non-empty option list.
	 *
	 * @param array<string, mixed> $attributes Variation attributes.
	 */
	private static function attributes_have_options( array $attributes ): bool {
		foreach ( $attributes as $options ) {
			if ( is_array( $options ) && array() !== $options ) {
				return true;
			}
			if ( is_string( $options ) && '' !== trim( $options ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Collapse percent-encoded / attribute_ duplicates onto one taxonomy key.
	 * e.g. pa_%d8%a8… and pa_بسته-بندی → same attribute.
	 *
	 * @param string             $tax            Raw attribute key.
	 * @param array<int, string> $preferred_keys Parent keys to prefer when matching.
	 */
	private static function canonicalize_attribute_taxonomy( string $tax, array $preferred_keys = array() ): string {
		$tax = preg_replace( '/^attribute_/', '', $tax );
		$tax = is_string( $tax ) ? $tax : '';
		if ( '' === $tax ) {
			return '';
		}

		$decoded = rawurldecode( $tax );
		if ( $decoded !== $tax && taxonomy_exists( $decoded ) ) {
			$tax = $decoded;
		}

		foreach ( $preferred_keys as $preferred ) {
			$preferred = (string) $preferred;
			if ( '' === $preferred ) {
				continue;
			}
			if ( self::attribute_taxonomy_keys_match( $tax, $preferred ) ) {
				return $preferred;
			}
		}

		if ( taxonomy_exists( $tax ) ) {
			return $tax;
		}

		if ( 0 !== strpos( $tax, 'pa_' ) && taxonomy_exists( 'pa_' . $tax ) ) {
			return 'pa_' . $tax;
		}

		$resolved = self::resolve_attribute_taxonomy_from_wc( $tax );
		if ( '' !== $resolved ) {
			return $resolved;
		}

		return $tax;
	}

	/**
	 * Match a mangled/encoded key to a registered WooCommerce product attribute taxonomy.
	 */
	private static function resolve_attribute_taxonomy_from_wc( string $tax ): string {
		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
			return '';
		}

		$needle = self::attribute_taxonomy_match_token( $tax );
		if ( '' === $needle ) {
			return '';
		}

		foreach ( (array) wc_get_attribute_taxonomies() as $attr ) {
			$name  = isset( $attr->attribute_name ) ? (string) $attr->attribute_name : '';
			$label = isset( $attr->attribute_label ) ? (string) $attr->attribute_label : '';
			if ( '' === $name ) {
				continue;
			}
			$full = function_exists( 'wc_attribute_taxonomy_name' )
				? wc_attribute_taxonomy_name( $name )
				: 'pa_' . $name;

			if (
				$needle === self::attribute_taxonomy_match_token( $name )
				|| $needle === self::attribute_taxonomy_match_token( $full )
				|| $needle === self::attribute_taxonomy_match_token( $label )
			) {
				return $full;
			}
		}

		return '';
	}

	/**
	 * Token for fuzzy attribute-key equality (decode + sanitize_title).
	 */
	private static function attribute_taxonomy_match_token( string $key ): string {
		$key = preg_replace( '/^attribute_/', '', $key );
		$key = is_string( $key ) ? $key : '';
		$key = rawurldecode( $key );
		return sanitize_title( $key );
	}

	/**
	 * Whether two attribute taxonomy keys refer to the same attribute.
	 */
	private static function attribute_taxonomy_keys_match( string $a, string $b ): bool {
		if ( $a === $b ) {
			return true;
		}
		$token = self::attribute_taxonomy_match_token( $a );
		return '' !== $token && $token === self::attribute_taxonomy_match_token( $b );
	}

	/**
	 * Dedupe attribute map so encoded + decoded keys become one row.
	 *
	 * @param array<string, array<int, string>> $attrs Attribute map.
	 * @return array<string, array<int, string>>
	 */
	private static function dedupe_attribute_map( array $attrs ): array {
		$out  = array();
		$keys = array_map( 'strval', array_keys( $attrs ) );

		foreach ( $attrs as $tax => $options ) {
			$options = is_array( $options ) ? $options : array();
			$canon   = self::canonicalize_attribute_taxonomy( (string) $tax, $keys );
			if ( '' === $canon ) {
				$canon = (string) $tax;
			}
			if ( ! isset( $out[ $canon ] ) ) {
				$out[ $canon ] = array();
			}
			$out[ $canon ] = array_values( array_unique( array_merge( $out[ $canon ], $options ) ) );
		}

		return $out;
	}

	/**
	 * Parent get_variation_attributes() plus values synthesized from children.
	 *
	 * @param WC_Product_Variable $product Product.
	 * @return array<string, array<int, string>>
	 */
	public static function get_effective_variation_attributes( WC_Product_Variable $product ): array {
		$attrs       = $product->get_variation_attributes();
		$attrs       = is_array( $attrs ) ? $attrs : array();
		$attrs       = self::dedupe_attribute_map( $attrs );
		$synthesized = self::synthesize_variation_attributes( $product );
		$parent_keys = array_map( 'strval', array_keys( $attrs ) );

		foreach ( $synthesized as $tax => $options ) {
			$tax      = self::canonicalize_attribute_taxonomy( (string) $tax, $parent_keys );
			$options  = is_array( $options ) ? $options : array();
			$existing = isset( $attrs[ $tax ] ) && is_array( $attrs[ $tax ] ) ? $attrs[ $tax ] : array();
			if ( array() === $existing ) {
				$attrs[ $tax ] = $options;
				$parent_keys[] = $tax;
				continue;
			}
			$attrs[ $tax ] = array_values( array_unique( array_merge( $existing, $options ) ) );
		}

		$attrs = self::dedupe_attribute_map( $attrs );

		if ( ! self::attributes_have_options( $attrs ) ) {
			return $synthesized;
		}

		return $attrs;
	}

	/**
	 * Build attribute => option slugs from child variations.
	 *
	 * @param WC_Product_Variable $product Product.
	 * @return array<string, array<int, string>>
	 */
	public static function synthesize_variation_attributes( WC_Product_Variable $product ): array {
		$out = array();

		foreach ( $product->get_children() as $child_id ) {
			$variation = wc_get_product( (int) $child_id );
			if ( ! $variation instanceof WC_Product_Variation || ! $variation->exists() ) {
				continue;
			}

			foreach ( $variation->get_variation_attributes( true ) as $key => $value ) {
				$value = is_string( $value ) ? trim( $value ) : '';
				if ( '' === $value ) {
					continue;
				}
				$tax = preg_replace( '/^attribute_/', '', (string) $key );
				$tax = is_string( $tax ) ? $tax : (string) $key;
				$tax = self::canonicalize_attribute_taxonomy( $tax, array_map( 'strval', array_keys( $out ) ) );
				if ( '' === $tax ) {
					continue;
				}
				if ( ! isset( $out[ $tax ] ) ) {
					$out[ $tax ] = array();
				}
				if ( ! in_array( $value, $out[ $tax ], true ) ) {
					$out[ $tax ][] = $value;
				}
			}
		}

		return self::dedupe_attribute_map( $out );
	}

	/**
	 * Customizer: price notice on light single product.
	 *
	 * @param WP_Customize_Manager $wp_customize Customizer.
	 */
	public static function register_customizer( $wp_customize ): void {
		if ( ! is_object( $wp_customize ) || ! method_exists( $wp_customize, 'add_section' ) ) {
			return;
		}

		$wp_customize->add_section(
			'lk_light_single_product',
			array(
				'title'       => __( 'تمپلیت سبک محصول', 'hello-elementor-child' ),
				'description' => __( 'تنظیمات صفحهٔ تک‌محصول سبک. اگر فیلد متن اعلان خالی باشد، متن پیش‌فرض نمایش داده می‌شود.', 'hello-elementor-child' ),
				'priority'    => 46,
			)
		);

		$wp_customize->add_setting(
			self::OPTION_PRICE_NOTICE,
			array(
				'default'           => '',
				'type'              => 'option',
				'sanitize_callback' => 'sanitize_textarea_field',
			)
		);
		$wp_customize->add_control(
			self::OPTION_PRICE_NOTICE,
			array(
				'label'       => __( 'متن اعلان قیمت', 'hello-elementor-child' ),
				'description' => __( 'خالی بگذارید تا متن پیش‌فرض («به علت نوسانات قیمت…») استفاده شود.', 'hello-elementor-child' ),
				'section'     => 'lk_light_single_product',
				'type'        => 'textarea',
			)
		);
	}

	/**
	 * Resolved price-notice text (custom or default).
	 */
	public static function get_price_notice(): string {
		$custom = trim( (string) get_option( self::OPTION_PRICE_NOTICE, '' ) );
		if ( '' !== $custom ) {
			return $custom;
		}
		return self::DEFAULT_PRICE_NOTICE;
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

		$shop_cards = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-product-shop-cards.css';
		if ( file_exists( $shop_cards ) ) {
			wp_enqueue_style(
				'lk-archive-shop-cards',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-product-shop-cards.css',
				array( 'lk-light-product' ),
				(string) filemtime( $shop_cards )
			);
		}

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
			array( 'jquery' ),
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

		/* Related/upsell shop cards: variation options panel + AJAX add */
		if ( class_exists( 'Hello_Elementor_Child_Archive_Product_Filter' ) ) {
			Hello_Elementor_Child_Archive_Product_Filter::enqueue_shop_card_scripts();
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
