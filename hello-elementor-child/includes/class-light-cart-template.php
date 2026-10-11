<?php
/**
 * Light cart template — native chrome; opt-in via Cart page template.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Cart with shared Lookazma header/footer.
 */
final class Hello_Elementor_Child_Light_Cart_Template {

	public const TEMPLATE_FILE = 'page-templates/light-cart.php';

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_filter( 'theme_page_templates', array( __CLASS__, 'register_page_template' ), 20, 4 );
		add_filter( 'timber/locations', array( __CLASS__, 'add_timber_locations' ) );

		add_action( 'template_redirect', array( __CLASS__, 'unhook_elementor_chrome' ), -6 );
		add_action( 'template_redirect', array( __CLASS__, 'force_template' ), 20 );
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
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_filter( 'woocommerce_cart_ready_to_calc_shipping', array( __CLASS__, 'force_ready_to_calc_shipping' ), 99 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'ensure_variation_item_data' ), 20, 2 );
		add_filter( 'woocommerce_add_cart_item', array( __CLASS__, 'hydrate_cart_item_variation' ), 20 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( __CLASS__, 'hydrate_cart_item_variation' ), 20 );
		/* Never hide variation attrs just because they appear in the product title. */
		add_filter( 'woocommerce_is_attribute_in_product_name', '__return_false', 99 );
		add_action( 'wp_ajax_lk_cart_step', array( __CLASS__, 'ajax_cart_step' ) );
		add_action( 'wp_ajax_nopriv_lk_cart_step', array( __CLASS__, 'ajax_cart_step' ) );
		add_action( 'acf/init', array( __CLASS__, 'register_acf_fields' ) );
	}

	/**
	 * Toggle on the cart page: توقف فرایند خرید.
	 */
	public static function register_acf_fields(): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group(
			array(
				'key'                   => 'group_lk_cart_page',
				'title'                 => __( 'تنظیمات سبد خرید', 'hello-elementor-child' ),
				'fields'                => array(
					array(
						'key'           => 'field_lk_cart_hold_checkout',
						'label'         => __( 'توقف فرایند خرید', 'hello-elementor-child' ),
						'name'          => 'cart_hold_checkout',
						'type'          => 'true_false',
						'ui'            => 1,
						'default_value' => 0,
						'instructions'  => __( 'روشن: مشتری در مرحله دوم می‌ماند و پیام پشتیبانی را می‌بیند. خاموش: مشتری به مرحله‌های بعد می‌رود.', 'hello-elementor-child' ),
					),
				),
				'location'              => array(
					array(
						array(
							'param'    => 'page_template',
							'operator' => '==',
							'value'    => self::TEMPLATE_FILE,
						),
					),
					array(
						array(
							'param'    => 'page_template',
							'operator' => '==',
							'value'    => 'light-cart.php',
						),
					),
				),
				'menu_order'            => 0,
				'position'              => 'side',
				'style'                 => 'default',
				'label_placement'       => 'top',
				'instruction_placement' => 'label',
				'active'                => true,
			)
		);
	}

	/**
	 * Whether shoppers are stopped on the support notice.
	 */
	public static function checkout_held(): bool {
		$page_id = self::get_cart_page_id();
		if ( $page_id <= 0 || ! function_exists( 'get_field' ) ) {
			return false;
		}
		return (bool) get_field( 'cart_hold_checkout', $page_id );
	}

	/**
	 * Persist resolved variation attributes onto the cart line (fixes empty “Any” maps).
	 *
	 * @param array<string, mixed> $cart_item Cart line.
	 * @return array<string, mixed>
	 */
	public static function hydrate_cart_item_variation( $cart_item ) {
		if ( ! is_array( $cart_item ) ) {
			return $cart_item;
		}

		$variation = self::resolve_cart_variation_product( $cart_item );
		if ( ! $variation instanceof WC_Product_Variation ) {
			return $cart_item;
		}

		$filled = self::collect_cart_variation_attributes( $cart_item, $variation );
		$pack   = self::resolve_packaging_attribute_map( $cart_item, $variation );
		if ( array() !== $pack ) {
			$filled = array_merge( $filled, $pack );
		}

		if ( array() === $filled ) {
			return $cart_item;
		}

		$existing = isset( $cart_item['variation'] ) && is_array( $cart_item['variation'] )
			? self::filter_filled_variation_attributes( $cart_item['variation'] )
			: array();

		if ( array() === $existing ) {
			$cart_item['variation'] = $filled;
		} else {
			$cart_item['variation'] = array_merge( $filled, $existing );
		}

		if ( empty( $cart_item['variation_id'] ) ) {
			$cart_item['variation_id'] = $variation->get_id();
		}

		return $cart_item;
	}

	/**
	 * Always show variation attributes under the cart product name
	 * (including one-variation products WC would otherwise hide).
	 *
	 * @param array<int, array<string, mixed>> $item_data Cart item data rows.
	 * @param array<string, mixed>             $cart_item Cart line.
	 * @return array<int, array<string, mixed>>
	 */
	public static function ensure_variation_item_data( $item_data, $cart_item ): array {
		if ( ! is_array( $item_data ) ) {
			$item_data = array();
		}
		if ( ! is_array( $cart_item ) ) {
			return $item_data;
		}

		return self::merge_variation_item_data( $item_data, $cart_item );
	}

	/**
	 * Cart-page HTML for line-item variation meta (packaging under the title).
	 * Always forces «بسته بندی» from the product Attributes tab when missing.
	 *
	 * @param array<string, mixed> $cart_item Cart line.
	 */
	public static function get_formatted_cart_item_data_html( array $cart_item ): string {
		$item_data = array();

		if ( function_exists( 'wc_get_formatted_cart_item_data' ) ) {
			/* Build via WC + our filter, then ensure packaging is present. */
			$html = (string) wc_get_formatted_cart_item_data( $cart_item );
			if ( '' !== trim( $html ) && false !== strpos( $html, 'بسته' ) ) {
				return $html;
			}
		}

		$item_data = self::merge_variation_item_data( array(), $cart_item );
		$item_data = self::ensure_packaging_row( $item_data, $cart_item );

		if ( array() === $item_data ) {
			return '';
		}

		foreach ( $item_data as $key => $data ) {
			$item_data[ $key ]['key']     = ! empty( $data['key'] ) ? $data['key'] : ( $data['name'] ?? '' );
			$item_data[ $key ]['display'] = ! empty( $data['display'] ) ? $data['display'] : ( $data['value'] ?? '' );
		}

		ob_start();
		wc_get_template( 'cart/cart-item-data.php', array( 'item_data' => $item_data ) );
		return (string) ob_get_clean();
	}

	/**
	 * @param array<int, array<string, mixed>> $item_data Existing rows.
	 * @param array<string, mixed>             $cart_item Cart line.
	 * @return array<int, array<string, mixed>>
	 */
	private static function merge_variation_item_data( array $item_data, array $cart_item ): array {
		$variation = self::resolve_cart_variation_product( $cart_item );
		if ( ! $variation instanceof WC_Product_Variation ) {
			return self::ensure_packaging_row( $item_data, $cart_item );
		}

		$attributes = self::collect_cart_variation_attributes( $cart_item, $variation );
		$pack_map   = self::resolve_packaging_attribute_map( $cart_item, $variation );
		if ( array() !== $pack_map ) {
			$attributes = array_merge( $attributes, $pack_map );
		}

		$rows = array();
		foreach ( $attributes as $name => $value ) {
			$value = is_string( $value ) ? trim( $value ) : '';
			if ( '' === $value ) {
				continue;
			}
			$resolved = self::format_cart_variation_attribute( (string) $name, $value, $variation );
			if ( null !== $resolved ) {
				$rows[] = $resolved;
			}
		}

		if ( array() === $rows ) {
			$rows = self::rows_from_attribute_summary( $variation );
		}

		$existing_keys = array();
		foreach ( $item_data as $row ) {
			foreach ( array( 'key', 'name' ) as $field ) {
				if ( ! empty( $row[ $field ] ) ) {
					$existing_keys[ self::str_lower( (string) $row[ $field ] ) ] = true;
				}
			}
		}

		foreach ( $rows as $resolved ) {
			$key_norm = self::str_lower( $resolved['key'] );
			if ( isset( $existing_keys[ $key_norm ] ) ) {
				continue;
			}
			$item_data[] = array(
				'key'   => $resolved['key'],
				'value' => $resolved['value'],
			);
			$existing_keys[ $key_norm ] = true;
		}

		return self::ensure_packaging_row( $item_data, $cart_item );
	}

	/**
	 * Guarantee a «بسته بندی» row from product Attributes tab data.
	 *
	 * @param array<int, array<string, mixed>> $item_data Existing rows.
	 * @param array<string, mixed>             $cart_item Cart line.
	 * @return array<int, array<string, mixed>>
	 */
	private static function ensure_packaging_row( array $item_data, array $cart_item ): array {
		foreach ( $item_data as $row ) {
			$label = (string) ( $row['key'] ?? $row['name'] ?? '' );
			if ( self::is_packaging_label( $label ) ) {
				return $item_data;
			}
		}

		$packaging = self::resolve_packaging_display( $cart_item );
		if ( null === $packaging ) {
			return $item_data;
		}

		$item_data[] = array(
			'key'   => $packaging['key'],
			'value' => $packaging['value'],
		);

		return $item_data;
	}

	/**
	 * Resolve packaging label + value for a cart line from Attributes / variation.
	 *
	 * @param array<string, mixed> $cart_item Cart line.
	 * @return array{key: string, value: string}|null
	 */
	private static function resolve_packaging_display( array $cart_item ): ?array {
		$variation = self::resolve_cart_variation_product( $cart_item );
		$parent    = self::resolve_cart_parent_product( $cart_item, $variation );

		$label = __( 'بسته بندی', 'hello-elementor-child' );
		$value = '';

		if ( $variation instanceof WC_Product_Variation ) {
			$map = self::resolve_packaging_attribute_map( $cart_item, $variation );
			if ( array() !== $map ) {
				foreach ( $map as $attr_key => $raw ) {
					$resolved = self::format_cart_variation_attribute( (string) $attr_key, (string) $raw, $variation );
					if ( null !== $resolved ) {
						return $resolved;
					}
					$value = (string) $raw;
				}
			}
		}

		if ( '' === $value && $parent instanceof WC_Product ) {
			$from_parent = self::packaging_terms_from_parent( $parent );
			if ( 1 === count( $from_parent ) ) {
				$value = (string) reset( $from_parent );
			} elseif ( $variation instanceof WC_Product_Variation && array() !== $from_parent ) {
				/* Match variation slug against parent packaging terms. */
				foreach ( self::collect_cart_variation_attributes( $cart_item, $variation ) as $raw ) {
					$raw = is_string( $raw ) ? trim( $raw ) : '';
					if ( '' === $raw ) {
						continue;
					}
					foreach ( $from_parent as $term_name ) {
						if ( self::str_lower( $term_name ) === self::str_lower( $raw )
							|| sanitize_title( $term_name ) === sanitize_title( $raw )
							|| sanitize_title( $term_name ) === sanitize_title( rawurldecode( $raw ) )
						) {
							$value = $term_name;
							break 2;
						}
					}
					/* Decoded slug fallback. */
					$decoded = rawurldecode( str_replace( array( '-', '_' ), ' ', $raw ) );
					$decoded = trim( preg_replace( '/\s+/u', ' ', $decoded ) ?: $decoded );
					if ( in_array( $decoded, $from_parent, true ) ) {
						$value = $decoded;
						break;
					}
				}
				if ( '' === $value && 1 === count( $parent->get_children() ) && 1 <= count( $from_parent ) ) {
					/* Single-variation product: if only one packaging term on Attributes, use it;
					 * if multiple terms but one child, prefer the child's resolved attribute. */
					if ( 1 === count( $from_parent ) ) {
						$value = (string) reset( $from_parent );
					}
				}
			} elseif ( 1 === count( $from_parent ) ) {
				$value = (string) reset( $from_parent );
			}
		}

		$value = trim( wp_strip_all_tags( (string) $value ) );
		if ( '' === $value ) {
			return null;
		}

		return array(
			'key'   => $label,
			'value' => $value,
		);
	}

	/**
	 * attribute_* => raw value map for packaging only.
	 *
	 * @param array<string, mixed> $cart_item Cart line.
	 * @param WC_Product_Variation $variation Variation.
	 * @return array<string, string>
	 */
	private static function resolve_packaging_attribute_map( array $cart_item, WC_Product_Variation $variation ): array {
		$parent = self::resolve_cart_parent_product( $cart_item, $variation );
		$tax    = self::find_packaging_taxonomy( $parent, $variation );

		$candidates = array();
		if ( isset( $cart_item['variation'] ) && is_array( $cart_item['variation'] ) ) {
			$candidates[] = $cart_item['variation'];
		}
		$candidates[] = $variation->get_variation_attributes( true );
		$candidates[] = $variation->get_variation_attributes( false );
		$candidates[] = $variation->get_attributes();
		if ( function_exists( 'wc_get_product_variation_attributes' ) ) {
			$meta = wc_get_product_variation_attributes( $variation->get_id() );
			if ( is_array( $meta ) ) {
				$candidates[] = $meta;
			}
		}
		$candidates[] = self::attributes_from_post_meta( $variation );

		foreach ( $candidates as $attrs ) {
			$filled = self::filter_filled_variation_attributes( $attrs );
			foreach ( $filled as $key => $val ) {
				$plain = preg_replace( '/^attribute_/', '', (string) $key );
				$plain = is_string( $plain ) ? rawurldecode( $plain ) : '';
				$lab   = function_exists( 'wc_attribute_label' ) ? (string) wc_attribute_label( $plain, $variation ) : $plain;
				if ( self::is_packaging_label( $plain ) || self::is_packaging_label( $lab )
					|| ( '' !== $tax && ( $plain === $tax || 'attribute_' . $tax === $key ) )
				) {
					return array( (string) $key => (string) $val );
				}
			}
		}

		if ( '' !== $tax ) {
			$via = $variation->get_attribute( $tax );
			if ( is_string( $via ) && '' !== trim( $via ) ) {
				return array( 'attribute_' . $tax => trim( $via ) );
			}
		}

		if ( $parent instanceof WC_Product ) {
			$terms = self::packaging_terms_from_parent( $parent );
			if ( 1 === count( $terms ) ) {
				$term_name = (string) reset( $terms );
				$slug      = sanitize_title( $term_name );
				if ( '' !== $tax ) {
					$term = get_term_by( 'name', $term_name, $tax );
					if ( $term instanceof WP_Term ) {
						$slug = $term->slug;
					}
					return array( 'attribute_' . $tax => $slug );
				}
				return array( 'attribute_pa_packaging' => $slug );
			}
		}

		return array();
	}

	/**
	 * @param array<string, mixed>          $cart_item Cart line.
	 * @param WC_Product_Variation|null     $variation Variation if known.
	 */
	private static function resolve_cart_parent_product( array $cart_item, $variation = null ): ?WC_Product {
		if ( $variation instanceof WC_Product_Variation ) {
			$parent = wc_get_product( (int) $variation->get_parent_id() );
			if ( $parent instanceof WC_Product ) {
				return $parent;
			}
		}

		$data = $cart_item['data'] ?? null;
		if ( $data instanceof WC_Product_Variable ) {
			return $data;
		}
		if ( $data instanceof WC_Product && $data->is_type( 'variable' ) ) {
			return $data;
		}

		$product_id = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
		if ( $product_id > 0 ) {
			$product = wc_get_product( $product_id );
			if ( $product instanceof WC_Product ) {
				return $product;
			}
		}

		return null;
	}

	/**
	 * Packaging term names from parent Attributes tab («بسته بندی»).
	 *
	 * @param WC_Product $parent Parent product.
	 * @return array<int, string>
	 */
	private static function packaging_terms_from_parent( WC_Product $parent ): array {
		$labels = array();

		foreach ( $parent->get_attributes() as $attribute ) {
			if ( ! $attribute instanceof WC_Product_Attribute ) {
				continue;
			}
			$attr_name = $attribute->get_name();
			$label     = function_exists( 'wc_attribute_label' )
				? (string) wc_attribute_label( $attr_name )
				: $attr_name;
			if ( ! self::is_packaging_label( $label ) && ! self::is_packaging_label( $attr_name ) ) {
				continue;
			}

			if ( $attribute->is_taxonomy() ) {
				$terms = wc_get_product_terms( $parent->get_id(), $attr_name, array( 'fields' => 'all' ) );
				if ( is_wp_error( $terms ) ) {
					continue;
				}
				foreach ( $terms as $term ) {
					if ( $term instanceof WP_Term ) {
						$name = trim( (string) $term->name );
						if ( '' !== $name && ! in_array( $name, $labels, true ) ) {
							$labels[] = $name;
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
	 * Find packaging taxonomy name on parent/variation.
	 *
	 * @param WC_Product|null          $parent    Parent.
	 * @param WC_Product_Variation|null $variation Variation.
	 */
	private static function find_packaging_taxonomy( $parent, $variation = null ): string {
		$known = array( 'pa_بسته‌ بندی', 'pa_بسته-بندی', 'pa_بسته بندی', 'pa_packaging' );
		foreach ( $known as $tax ) {
			if ( taxonomy_exists( $tax ) ) {
				return $tax;
			}
		}

		if ( $parent instanceof WC_Product ) {
			foreach ( $parent->get_attributes() as $attribute ) {
				if ( ! $attribute instanceof WC_Product_Attribute || ! $attribute->is_taxonomy() ) {
					continue;
				}
				$name  = $attribute->get_name();
				$label = function_exists( 'wc_attribute_label' ) ? (string) wc_attribute_label( $name ) : $name;
				if ( self::is_packaging_label( $label ) || self::is_packaging_label( $name ) ) {
					return $name;
				}
			}
		}

		if ( $variation instanceof WC_Product_Variation ) {
			foreach ( $variation->get_variation_attributes( false ) as $key => $_value ) {
				$plain = rawurldecode( (string) $key );
				$label = function_exists( 'wc_attribute_label' ) ? (string) wc_attribute_label( $plain, $variation ) : $plain;
				if ( self::is_packaging_label( $plain ) || self::is_packaging_label( $label ) ) {
					return $plain;
				}
			}
		}

		if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
			foreach ( (array) wc_get_attribute_taxonomies() as $attr ) {
				$name  = isset( $attr->attribute_name ) ? (string) $attr->attribute_name : '';
				$label = isset( $attr->attribute_label ) ? (string) $attr->attribute_label : '';
				if ( self::is_packaging_label( $label ) || self::is_packaging_label( $name ) ) {
					return function_exists( 'wc_attribute_taxonomy_name' )
						? wc_attribute_taxonomy_name( $name )
						: 'pa_' . $name;
				}
			}
		}

		return '';
	}

	/**
	 * Whether a label/key is packaging / بسته‌بندی.
	 */
	private static function is_packaging_label( string $label ): bool {
		$norm = self::str_lower( $label );
		$norm = str_replace( array( "\u{200C}", "\xE2\x80\x8C", '-', '_', ' ', '‌' ), '', $norm );
		if ( '' === $norm ) {
			return false;
		}
		if ( false !== strpos( $norm, 'بسته' ) || false !== strpos( $norm, 'packaging' ) || false !== strpos( $norm, 'packsize' ) ) {
			return true;
		}
		return false !== strpos( $norm, 'pack' ) && false === strpos( $norm, 'packageweight' );
	}

	/**
	 * @param string $value Text.
	 */
	private static function str_lower( string $value ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value ) : strtolower( $value );
	}

	/**
	 * @param array<string, mixed> $cart_item Cart line.
	 */
	private static function resolve_cart_variation_product( array $cart_item ): ?WC_Product_Variation {
		$data = $cart_item['data'] ?? null;
		if ( $data instanceof WC_Product_Variation ) {
			return $data;
		}

		$variation_id = isset( $cart_item['variation_id'] ) ? absint( $cart_item['variation_id'] ) : 0;
		if ( $variation_id > 0 ) {
			$product = wc_get_product( $variation_id );
			if ( $product instanceof WC_Product_Variation ) {
				return $product;
			}
		}

		/* Variable parent with a single child variation (common “one variable” case). */
		if ( $data instanceof WC_Product && $data->is_type( 'variable' ) ) {
			$children = $data->get_children();
			if ( is_array( $children ) && 1 === count( $children ) ) {
				$product = wc_get_product( (int) reset( $children ) );
				if ( $product instanceof WC_Product_Variation ) {
					return $product;
				}
			}
		}

		$product_id = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
		if ( $product_id > 0 ) {
			$parent = wc_get_product( $product_id );
			if ( $parent instanceof WC_Product_Variable ) {
				$children = $parent->get_children();
				if ( is_array( $children ) && 1 === count( $children ) ) {
					$product = wc_get_product( (int) reset( $children ) );
					if ( $product instanceof WC_Product_Variation ) {
						return $product;
					}
				}
			}
		}

		return null;
	}

	/**
	 * Gather filled attribute_key => value for a cart variation line.
	 *
	 * @param array<string, mixed> $cart_item Cart line.
	 * @param WC_Product_Variation $variation Variation product.
	 * @return array<string, string>
	 */
	private static function collect_cart_variation_attributes( array $cart_item, WC_Product_Variation $variation ): array {
		$candidates = array();

		if ( isset( $cart_item['variation'] ) && is_array( $cart_item['variation'] ) ) {
			$candidates[] = $cart_item['variation'];
		}

		$candidates[] = $variation->get_variation_attributes( true );
		$candidates[] = $variation->get_variation_attributes( false );
		$candidates[] = $variation->get_attributes();

		if ( function_exists( 'wc_get_product_variation_attributes' ) ) {
			$from_meta = wc_get_product_variation_attributes( $variation->get_id() );
			if ( is_array( $from_meta ) ) {
				$candidates[] = $from_meta;
			}
		}

		$candidates[] = self::attributes_from_post_meta( $variation );

		foreach ( $candidates as $attrs ) {
			$filled = self::filter_filled_variation_attributes( $attrs );
			if ( array() !== $filled ) {
				return $filled;
			}
		}

		$parent = wc_get_product( (int) $variation->get_parent_id() );
		if ( ! $parent instanceof WC_Product_Variable ) {
			return array();
		}

		/* Parent “used for variations” attributes with a single chosen term. */
		$from_parent = self::attributes_from_parent_single_options( $parent, $variation );
		if ( array() !== $from_parent ) {
			return $from_parent;
		}

		$out          = array();
		$parent_attrs = $parent->get_variation_attributes();
		$parent_attrs = is_array( $parent_attrs ) ? $parent_attrs : array();

		if ( array() === $parent_attrs && class_exists( 'Hello_Elementor_Child_Custom_Single_Product' ) ) {
			$parent_attrs = Hello_Elementor_Child_Custom_Single_Product::synthesize_variation_attributes( $parent );
		}

		foreach ( $parent_attrs as $taxonomy => $options ) {
			$taxonomy = (string) $taxonomy;
			$tax_key  = preg_replace( '/^attribute_/', '', $taxonomy );
			$tax_key  = is_string( $tax_key ) ? rawurldecode( $tax_key ) : $taxonomy;
			$attr_key = 'attribute_' . $tax_key;

			$raw = $variation->get_attribute( $tax_key );
			$raw = is_string( $raw ) ? trim( $raw ) : '';

			if ( '' === $raw && is_array( $options ) ) {
				$options = array_values( array_filter( array_map( 'strval', $options ), 'strlen' ) );
				if ( 1 === count( $options ) ) {
					$raw = (string) $options[0];
				}
			}

			if ( '' !== $raw ) {
				$out[ $attr_key ] = $raw;
			}
		}

		return $out;
	}

	/**
	 * Raw attribute_* post meta on the variation.
	 *
	 * @param WC_Product_Variation $variation Variation.
	 * @return array<string, string>
	 */
	private static function attributes_from_post_meta( WC_Product_Variation $variation ): array {
		$all = get_post_meta( $variation->get_id() );
		if ( ! is_array( $all ) ) {
			return array();
		}

		$out = array();
		foreach ( $all as $key => $values ) {
			$key = (string) $key;
			if ( 0 !== strpos( $key, 'attribute_' ) ) {
				continue;
			}
			$val = isset( $values[0] ) ? trim( (string) $values[0] ) : '';
			if ( '' !== $val ) {
				$out[ $key ] = $val;
			}
		}

		return $out;
	}

	/**
	 * When variation meta is empty (“Any”), use parent attribute with exactly one term.
	 *
	 * @param WC_Product_Variable  $parent    Parent.
	 * @param WC_Product_Variation $variation Variation (for label context).
	 * @return array<string, string>
	 */
	private static function attributes_from_parent_single_options( WC_Product_Variable $parent, WC_Product_Variation $variation ): array {
		unset( $variation );
		$out = array();

		foreach ( $parent->get_attributes() as $attribute ) {
			if ( ! $attribute instanceof WC_Product_Attribute || ! $attribute->get_variation() ) {
				continue;
			}

			$options = $attribute->get_options();
			if ( ! is_array( $options ) || 1 !== count( $options ) ) {
				continue;
			}

			$name    = $attribute->get_name();
			$attr_key = 'attribute_' . $name;
			$option   = reset( $options );

			if ( $attribute->is_taxonomy() ) {
				$term = get_term( (int) $option, $name );
				if ( $term instanceof WP_Term && ! is_wp_error( $term ) ) {
					$out[ $attr_key ] = $term->slug;
				}
			} elseif ( is_scalar( $option ) && '' !== trim( (string) $option ) ) {
				$out[ $attr_key ] = trim( (string) $option );
			}
		}

		return $out;
	}

	/**
	 * Parse WC variation attribute_summary (“Label: value, …”).
	 *
	 * @param WC_Product_Variation $variation Variation.
	 * @return array<int, array{key: string, value: string}>
	 */
	private static function rows_from_attribute_summary( WC_Product_Variation $variation ): array {
		$summary = trim( (string) $variation->get_attribute_summary( 'edit' ) );
		if ( '' === $summary ) {
			$summary = trim( (string) $variation->get_attribute_summary( 'view' ) );
		}
		if ( '' === $summary ) {
			return array();
		}

		$rows = array();
		if ( preg_match_all( '/([^:,]+):\s*([^,]+)/u', $summary, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$label = isset( $match[1] ) ? trim( $match[1] ) : '';
				$value = isset( $match[2] ) ? trim( $match[2] ) : '';
				if ( '' === $label || '' === $value ) {
					continue;
				}
				$rows[] = array(
					'key'   => $label,
					'value' => $value,
				);
			}
		}

		return $rows;
	}

	/**
	 * @param mixed $attrs Raw attribute map.
	 * @return array<string, string>
	 */
	private static function filter_filled_variation_attributes( $attrs ): array {
		if ( ! is_array( $attrs ) ) {
			return array();
		}

		$out = array();
		foreach ( $attrs as $key => $value ) {
			if ( is_array( $value ) ) {
				continue;
			}
			$value = is_scalar( $value ) ? trim( (string) $value ) : '';
			if ( '' === $value ) {
				continue;
			}
			$key = (string) $key;
			if ( 0 !== strpos( $key, 'attribute_' ) ) {
				$key = 'attribute_' . $key;
			}
			$out[ $key ] = $value;
		}

		return $out;
	}

	/**
	 * @param string               $name      Attribute key (with or without attribute_).
	 * @param string               $value     Slug or display value.
	 * @param WC_Product_Variation $variation Variation.
	 * @return array{key: string, value: string}|null
	 */
	private static function format_cart_variation_attribute( string $name, string $value, WC_Product_Variation $variation ): ?array {
		$attr_key = preg_replace( '/^attribute_/', '', $name );
		$attr_key = is_string( $attr_key ) ? rawurldecode( $attr_key ) : '';
		if ( '' === $attr_key ) {
			return null;
		}

		$taxonomy = $attr_key;
		if ( 0 !== strpos( $taxonomy, 'pa_' ) ) {
			$taxonomy = 'pa_' . $taxonomy;
		}
		$taxonomy = rawurldecode( $taxonomy );

		$label   = '';
		$display = $value;

		if ( taxonomy_exists( $taxonomy ) ) {
			$label      = (string) wc_attribute_label( $taxonomy, $variation );
			$slug_tries = array_unique(
				array_filter(
					array(
						$value,
						rawurldecode( $value ),
						sanitize_title( $value ),
						sanitize_title( rawurldecode( $value ) ),
					)
				)
			);
			foreach ( $slug_tries as $slug ) {
				$term = get_term_by( 'slug', $slug, $taxonomy );
				if ( $term instanceof WP_Term && $term->name ) {
					$display = $term->name;
					break;
				}
			}
			if ( $display === $value ) {
				$via_attr = $variation->get_attribute( $taxonomy );
				if ( is_string( $via_attr ) && '' !== trim( $via_attr ) ) {
					$display = trim( $via_attr );
				}
			}
		} else {
			$label   = (string) wc_attribute_label( $attr_key, $variation );
			$display = (string) apply_filters( 'woocommerce_variation_option_name', $value, null, $attr_key, $variation );
			if ( '' === $display || $display === $value ) {
				$via_attr = $variation->get_attribute( $attr_key );
				if ( is_string( $via_attr ) && '' !== trim( $via_attr ) ) {
					$display = trim( $via_attr );
				}
			}
		}

		$label = trim( $label );
		if ( 0 === stripos( $label, 'pa_' ) ) {
			$label = trim( (string) substr( $label, 3 ) );
			$label = rawurldecode( $label );
		}
		$label   = str_replace( array( "\u{200C}", "\xE2\x80\x8C", '-', '_' ), ' ', $label );
		$label   = preg_replace( '/\s+/u', ' ', $label );
		$label   = is_string( $label ) ? trim( $label ) : '';
		$display = trim( wp_strip_all_tags( (string) $display ) );
		$display = rawurldecode( $display );

		if ( '' === $label ) {
			$label = __( 'بسته بندی', 'hello-elementor-child' );
		}
		if ( '' === $display ) {
			return null;
		}

		return array(
			'key'   => $label,
			'value' => $display,
		);
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
		$templates[ self::TEMPLATE_FILE ] = __( 'Light cart template', 'hello-elementor-child' );
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
	 * WooCommerce Cart page ID.
	 */
	public static function get_cart_page_id(): int {
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return 0;
		}
		$id = (int) wc_get_page_id( 'cart' );
		return $id > 0 ? $id : 0;
	}

	/**
	 * @param int $page_id Page ID.
	 */
	public static function page_uses_template( int $page_id ): bool {
		if ( $page_id <= 0 ) {
			return false;
		}
		$slug = (string) get_page_template_slug( $page_id );
		return self::TEMPLATE_FILE === $slug || 'light-cart.php' === $slug;
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
		if ( ! function_exists( 'is_cart' ) ) {
			return false;
		}

		$cart_id = self::get_cart_page_id();
		if ( $cart_id <= 0 || ! self::page_uses_template( $cart_id ) ) {
			return false;
		}

		if ( is_cart() ) {
			return true;
		}

		$object = get_queried_object();
		if ( $object instanceof WP_Post && (int) $object->ID === $cart_id ) {
			return true;
		}

		return is_page( $cart_id );
	}

	/**
	 * PHP entry path.
	 */
	public static function get_template_path(): string {
		return HELLO_ELEMENTOR_CHILD_PATH . 'page-light-cart.php';
	}

	/**
	 * Take over after WooCommerce cart handlers.
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
	 * Address step markup. Later steps stay closed.
	 */
	public static function ajax_cart_step(): void {
		check_ajax_referer( 'lk_cart_step', 'nonce' );

		$step = isset( $_POST['step'] ) ? sanitize_key( wp_unslash( $_POST['step'] ) ) : '';
		if ( ! in_array( $step, array( '2', '3', '4', '5', '6' ), true ) ) {
			wp_send_json_error( array( 'message' => 'closed' ), 403 );
		}

		if ( '2' !== $step && self::checkout_held() ) {
			wp_send_json_error( array( 'message' => 'closed' ), 403 );
		}

		if ( function_exists( 'WC' ) && WC()->cart && WC()->cart->is_empty() ) {
			wp_send_json_error( array( 'message' => 'empty' ), 400 );
		}

		$html = self::render_step_html( $step );
		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * @param string $step Step number.
	 */
	private static function render_step_html( string $step ): string {
		ob_start();
		if ( '2' === $step ) {
			self::render_address_step();
		} elseif ( '3' === $step ) {
			self::render_shipping_step();
		} elseif ( '4' === $step ) {
			self::render_time_step();
		} elseif ( '5' === $step ) {
			self::render_invoice_step();
		} else {
			self::render_payment_step();
		}
		return (string) ob_get_clean();
	}

	private static function render_step_back( string $goto ): void {
		?>
		<button type="button" class="lk-cart-address__back" data-lk-cart-goto="<?php echo esc_attr( $goto ); ?>" aria-label="بازگشت به مرحله قبل">
			<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
		</button>
		<?php
	}

	private static function render_step_next( string $next ): void {
		?>
		<button type="button" class="lk-cart-step__next" data-lk-cart-next="<?php echo esc_attr( $next ); ?>">تأیید و ادامه</button>
		<?php
	}

	private static function render_address_step(): void {
		?>
		<section class="lk-cart-address" aria-labelledby="lk-cart-address-title">
			<?php self::render_step_back( '1' ); ?>
			<h2 class="lk-cart-address__title" id="lk-cart-address-title">
				<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>
				<span>محل دریافت کالاها را انتخاب نمایید :</span>
			</h2>
			<?php if ( ! self::checkout_held() ) : ?>
				<?php self::render_step_next( '3' ); ?>
			<?php else : ?>
				<div class="lk-cart-address__box">
					<p class="lk-cart-address__notice">لطفاً برای ثبت آدرس و هماهنگی‌های بعدی با پشتیبانی تماس بگیرید.</p>
				</div>
			<?php endif; ?>
		</section>
		<?php
	}

	private static function render_shipping_step(): void {
		?>
		<section class="lk-cart-address" aria-labelledby="lk-cart-ship-title">
			<?php self::render_step_back( '2' ); ?>
			<h2 class="lk-cart-address__title" id="lk-cart-ship-title">
				<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/></svg>
				<span>شیوه‌ی ارسال کالاها را انتخاب نمایید:</span>
			</h2>
			<div class="lk-cart-ship">
				<svg class="lk-cart-ship__icon" xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/></svg>
				<p class="lk-cart-ship__name">ارسال باربری</p>
				<p class="lk-cart-ship__name">پس‌کرایه</p>
				<p class="lk-cart-ship__extra">+ بیمه و بسته‌بندی ۲۰,۰۰۰ تومان</p>
				<p class="lk-cart-ship__note">هزینه ارسال هنگام تحویل کالا توسط مشتری پرداخت می‌شود.</p>
			</div>
			<?php self::render_step_next( '4' ); ?>
		</section>
		<?php
	}

	private static function render_time_step(): void {
		?>
		<section class="lk-cart-address" aria-labelledby="lk-cart-time-title">
			<?php self::render_step_back( '3' ); ?>
			<h2 class="lk-cart-address__title" id="lk-cart-time-title">
				<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
				<span>زمان ارسال کالاها را انتخاب نمایید:</span>
			</h2>
			<p class="lk-cart-time__text">زمان ارسال بسته به زمان اطلاعیِ باربری میباشد. برای اطلاعات بیشتر تماس بگیرید.</p>
			<?php self::render_step_next( '5' ); ?>
		</section>
		<?php
	}

	private static function render_invoice_step(): void {
		?>
		<section class="lk-cart-address" aria-labelledby="lk-cart-invoice-title">
			<?php self::render_step_back( '4' ); ?>
			<h2 class="lk-cart-address__title" id="lk-cart-invoice-title">
				<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg>
				<span>نوع سفارش خود را جهت صدور فاکتور مناسب مشخص نمایید:</span>
			</h2>
			<?php self::render_step_next( '6' ); ?>
		</section>
		<?php
	}

	private static function render_payment_step(): void {
		$summary = self::payment_summary();
		?>
		<section class="lk-cart-address lk-cart-pay" aria-labelledby="lk-cart-pay-title">
			<?php self::render_step_back( '5' ); ?>
			<h2 class="lk-cart-address__title" id="lk-cart-pay-title">
				<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="20" height="14" x="2" y="5" rx="2"/><path d="M2 10h20"/></svg>
				<span>جزئیات سفارش خود را بررسی کرده و جهت تکمیل سفارش نسبت به پرداخت صورتحساب اقدام نمایید:</span>
			</h2>
			<div class="lk-cart-pay__table">
				<div class="lk-cart-pay__group">اطلاعات سبد</div>
				<div class="lk-cart-pay__row">
					<span>تعداد کالاها</span>
					<strong><?php echo esc_html( $summary['count_label'] ); ?></strong>
				</div>
				<div class="lk-cart-pay__group">اطلاعات مالی</div>
				<div class="lk-cart-pay__row">
					<span>مجموع قیمت محصولات</span>
					<strong><?php echo wp_kses_post( $summary['subtotal'] ); ?></strong>
				</div>
				<div class="lk-cart-pay__row">
					<span>مجموع مالیات محصولات</span>
					<strong><?php echo wp_kses_post( $summary['tax'] ); ?></strong>
				</div>
				<div class="lk-cart-pay__row">
					<span>هزینه ارسال</span>
					<strong>*</strong>
				</div>
				<div class="lk-cart-pay__row">
					<span>هزینه بیمه و بسته‌بندی</span>
					<strong><?php echo wp_kses_post( $summary['insurance'] ); ?></strong>
				</div>
				<div class="lk-cart-pay__row lk-cart-pay__row--discount">
					<span>مجموع تخفیف محصولات</span>
					<strong><?php echo wp_kses_post( $summary['discount'] ); ?></strong>
				</div>
				<div class="lk-cart-pay__row lk-cart-pay__row--due">
					<span>مبلغ نهایی و قابل پرداخت</span>
					<strong><?php echo wp_kses_post( $summary['due'] ); ?></strong>
				</div>
				<div class="lk-cart-pay__group">اطلاعات ارسال</div>
				<div class="lk-cart-pay__row">
					<span>روز ارسال مرسوله</span>
					<strong>زمان ارسال بسته به زمان اطلاعیِ باربری میباشد.</strong>
				</div>
				<div class="lk-cart-pay__row">
					<span>روش ارسال مرسوله</span>
					<strong>ارسال باربری</strong>
				</div>
			</div>
		</section>
		<?php
	}

	/**
	 * @return array<string, string>
	 */
	private static function payment_summary(): array {
		$count     = 0;
		$subtotal  = 0.0;
		$tax       = 0.0;
		$discount  = 0.0;
		$total     = 0.0;
		$insurance = 20000.0;

		if ( function_exists( 'WC' ) && WC()->cart ) {
			$cart     = WC()->cart;
			$count    = (int) $cart->get_cart_contents_count();
			$subtotal = (float) $cart->get_subtotal();
			$tax      = (float) $cart->get_total_tax();
			$discount = (float) $cart->get_discount_total() + (float) $cart->get_discount_tax();
			$total    = (float) $cart->get_total( 'edit' );
		}

		$due = $total + $insurance;
		$price = static function ( float $amount ): string {
			if ( function_exists( 'wc_price' ) ) {
				return wc_price( $amount );
			}
			return esc_html( (string) $amount );
		};

		return array(
			'count_label' => sprintf( '%d کالا', $count ),
			'subtotal'    => $price( $subtotal ),
			'tax'         => $price( $tax ),
			'insurance'   => $price( $insurance ),
			'discount'    => $price( $discount ),
			'due'         => $price( $due ),
		);
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

		$cart_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/light-cart.css';
		if ( file_exists( $cart_css ) ) {
			wp_enqueue_style(
				'lk-light-cart',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/css/light-cart.css',
				array( 'lk-light-product', 'lk-lpt-breadcrumb' ),
				(string) filemtime( $cart_css )
			);
		}

		wp_enqueue_script( 'jquery' );
		if ( function_exists( 'WC' ) ) {
			wp_enqueue_script( 'woocommerce' );
			wp_enqueue_script( 'wc-cart' );
			wp_enqueue_script( 'wc-cart-fragments' );
			wp_enqueue_script( 'wc-country-select' );
			wp_enqueue_script( 'wc-address-i18n' );
			wp_enqueue_script( 'selectWoo' );
			wp_enqueue_style( 'select2' );
		}

		$steps_js = HELLO_ELEMENTOR_CHILD_PATH . 'assets/js/lk-cart-steps.js';
		if ( file_exists( $steps_js ) ) {
			wp_enqueue_script(
				'lk-cart-steps',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/js/lk-cart-steps.js',
				array(),
				(string) filemtime( $steps_js ),
				true
			);
			$redirect = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
			wp_localize_script(
				'lk-cart-steps',
				'lkCartSteps',
				array(
					'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'lk_cart_step' ),
					'loggedIn'          => is_user_logged_in(),
					'continueCheckout'  => ! self::checkout_held(),
					'loginUrl' => add_query_arg(
						array(
							'login'       => 'true',
							'redirect_to' => $redirect,
						),
						home_url( '/' )
					),
				)
			);
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
	 * Drop Elementor/Jet chrome; keep WooCommerce cart scripts.
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
				if ( self::is_keep_asset_handle( $h ) ) {
					continue;
				}
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
				if ( self::is_keep_asset_handle( $h ) ) {
					continue;
				}
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
	 * @param string $handle Lowercased handle.
	 */
	private static function is_keep_asset_handle( string $handle ): bool {
		return false !== strpos( $handle, 'digits' )
			|| false !== strpos( $handle, 'woocommerce' )
			|| 0 === strpos( $handle, 'wc-' )
			|| false !== strpos( $handle, 'select2' )
			|| false !== strpos( $handle, 'selectwoo' );
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
		$classes[] = 'lk-light-cart';
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
	 * Always allow shipping totals on this template.
	 *
	 * @param bool $ready Whether shipping can be calculated.
	 */
	public static function force_ready_to_calc_shipping( $ready ) {
		return self::is_enabled() ? true : $ready;
	}

	/**
	 * Page content for Twig.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_page_content(): array {
		$page_id = self::get_cart_page_id();
		$title   = $page_id > 0 ? get_the_title( $page_id ) : __( 'سبد خرید', 'hello-elementor-child' );
		if ( ! is_string( $title ) || '' === trim( $title ) ) {
			$title = __( 'سبد خرید', 'hello-elementor-child' );
		}

		$empty = true;
		if ( function_exists( 'WC' ) && WC()->cart ) {
			$empty = WC()->cart->is_empty();
		}

		return array(
			'title'           => $title,
			'empty'           => $empty,
			'breadcrumb_html' => function_exists( 'hello_elementor_child_get_breadcrumb_html' )
				? hello_elementor_child_get_breadcrumb_html()
				: '',
			'content_html'    => self::get_cart_html(),
		);
	}

	/**
	 * Render WooCommerce cart / empty-cart markup.
	 */
	private static function get_cart_html(): string {
		if ( ! function_exists( 'wc_get_template' ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return '';
		}

		ob_start();

		if ( WC()->cart->is_empty() ) {
			wc_get_template( 'cart/cart-empty.php' );
		} else {
			if ( WC()->customer && method_exists( WC()->customer, 'set_calculated_shipping' ) ) {
				WC()->customer->set_calculated_shipping( true );
			}
			WC()->cart->calculate_shipping();
			WC()->cart->calculate_totals();
			wc_get_template( 'cart/cart.php' );
		}

		return (string) ob_get_clean();
	}
}
