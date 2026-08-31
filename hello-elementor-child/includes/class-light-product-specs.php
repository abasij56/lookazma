<?php
/**
 * Shared product specification builder for light templates.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds attribute tables aligned with single-product-light.php.
 */
final class Hello_Elementor_Child_Light_Product_Specs {

	/**
	 * Build compare-ready product data.
	 *
	 * @param WC_Product $product Product or variation.
	 * @return array<string, mixed>|null
	 */
	public static function build( WC_Product $product ): ?array {
		if ( ! $product->get_id() ) {
			return null;
		}

		$post_id      = (int) $product->get_id();
		$meta_post_id = $product->is_type( 'variation' ) ? (int) $product->get_parent_id() : $post_id;
		if ( $meta_post_id <= 0 ) {
			$meta_post_id = $post_id;
		}

		$attr_map        = array();
		$attributes_list = self::collect_attributes( $product );

		foreach ( $attributes_list as $row ) {
			$attr_map[ $row['label'] ] = $row['value'];
		}

		$cas_no = self::get_field( $meta_post_id, 'cas_no' );
		if ( '' === $cas_no ) {
			$cas_no = self::find_attr( $attr_map, array( 'CAS', 'Cas No', 'CAS Number' ) );
		}

		$english_name = '';
		foreach ( array( 'en-name', 'en_name', 'english_name', 'نام_انگلیسی', 'english' ) as $en_key ) {
			$english_name = self::get_field( $meta_post_id, $en_key );
			if ( '' !== $english_name && ! self::is_url( $english_name ) ) {
				break;
			}
			$english_name = '';
		}
		if ( '' === $english_name ) {
			$english_name = self::find_attr( $attr_map, array( 'نام انگلیسی', 'English Name', 'English', 'EN Name' ) );
		}

		$brand_name  = '';
		$brand_link  = '';
		$brand_image = '';
		$tags        = get_the_terms( $meta_post_id, 'product_tag' );
		if ( $tags && ! is_wp_error( $tags ) ) {
			$first_tag  = $tags[0];
			$brand_name = $first_tag->name;
			$brand_link = get_term_link( $first_tag );
			if ( is_wp_error( $brand_link ) ) {
				$brand_link = '';
			}
			if ( class_exists( 'Hello_Elementor_Child_Custom_Tag_Archive' ) ) {
				$brand_image = Hello_Elementor_Child_Custom_Tag_Archive::resolve_image_url( (int) $first_tag->term_id, 'thumbnail' );
			} else {
				$thumb_id = (int) get_term_meta( $first_tag->term_id, 'thumbnail_id', true );
				if ( $thumb_id > 0 ) {
					$url = wp_get_attachment_image_url( $thumb_id, 'thumbnail' );
					if ( $url ) {
						$brand_image = $url;
					}
				}
			}
		}

		$brand_acf = self::get_field( $meta_post_id, 'آدرس_برند' );
		if ( '' !== $brand_acf && ! self::is_url( $brand_acf ) && '' === $brand_name ) {
			$brand_name = $brand_acf;
		}

		foreach ( array( 'لوگو_شرکت', 'لوگو شرکت', 'brand_logo', 'لوگو_برند', 'brand_image' ) as $logo_key ) {
			$logo = self::get_field( $meta_post_id, $logo_key );
			if ( '' !== $logo && self::is_url( $logo ) ) {
				$brand_image = $logo;
				break;
			}
		}

		if ( '' === $brand_image && '' !== $brand_acf && self::is_url( $brand_acf ) ) {
			$brand_image = $brand_acf;
		}

		if ( '' === $brand_name ) {
			$brand_name = self::find_attr( $attr_map, array( 'برند', 'Brand' ) );
		}

		$table_attrs = $attributes_list;
		if ( '' !== $brand_name ) {
			$has_brand = false;
			foreach ( $table_attrs as $row ) {
				if ( 'برند' === $row['label'] ) {
					$has_brand = true;
					break;
				}
			}
			if ( ! $has_brand ) {
				$table_attrs[] = array(
					'label' => 'برند',
					'value' => $brand_name,
				);
			}
		}
		if ( '' !== $cas_no ) {
			$table_attrs[] = array(
				'label' => 'CAS Number',
				'value' => $cas_no,
			);
		}
		if ( $product->get_sku() ) {
			$table_attrs[] = array(
				'label' => 'SKU',
				'value' => (string) $product->get_sku(),
			);
		}
		if ( '' !== $english_name ) {
			$has_en = false;
			foreach ( $table_attrs as $row ) {
				if ( false !== mb_stripos( (string) $row['label'], 'انگلیسی' ) ) {
					$has_en = true;
					break;
				}
			}
			if ( ! $has_en ) {
				$table_attrs[] = array(
					'label' => 'نام انگلیسی',
					'value' => $english_name,
				);
			}
		}

		if ( $product->is_type( 'variable' ) && $product instanceof WC_Product_Variable ) {
			$packaging = self::collect_variable_packaging_summary( $product );
			$table_attrs = self::apply_packaging_summary( $table_attrs, $packaging );
		}

		$image_id = (int) $product->get_image_id();
		if ( $image_id <= 0 && $product->is_type( 'variation' ) ) {
			$parent = wc_get_product( $meta_post_id );
			if ( $parent ) {
				$image_id = (int) $parent->get_image_id();
			}
		}

		$image_url = '';
		if ( $image_id > 0 ) {
			$maybe = wp_get_attachment_image_url( $image_id, 'medium' );
			if ( is_string( $maybe ) && '' !== $maybe ) {
				$image_url = $maybe;
			}
		}

		$permalink = get_permalink( $post_id );
		if ( ! is_string( $permalink ) ) {
			$permalink = '';
		}

		return array(
			'id'         => $post_id,
			'parent_id'  => $product->is_type( 'variation' ) ? $meta_post_id : 0,
			'name'       => $product->get_name(),
			'permalink'  => $permalink,
			'price_html' => (string) $product->get_price_html(),
			'image'      => $image_url,
			'attributes' => $table_attrs,
		);
	}

	/**
	 * @param array<int, int> $ids Product or variation IDs.
	 * @return array{products: array<int, array<string, mixed>>, rows: array<int, array{label:string,values:array<int,string>}>}
	 */
	public static function build_matrix( array $ids ): array {
		$products = array();
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( $id <= 0 ) {
				continue;
			}
			$product = wc_get_product( $id );
			if ( ! $product || 'publish' !== $product->get_status() ) {
				continue;
			}
			$built = self::build( $product );
			if ( null !== $built ) {
				$products[] = $built;
			}
		}

		$priority = array(
			'برند',
			'CAS Number',
			'SKU',
			'نام انگلیسی',
			'گرید',
			'درصد خلوص',
			'خلوص',
			'کشور تولید کننده',
			'کشور سازنده',
			'بسته بندی',
			'بسته‌بندی',
		);

		$labels = array();
		foreach ( $products as $product ) {
			foreach ( $product['attributes'] as $row ) {
				$label = (string) $row['label'];
				if ( '' === $label || in_array( $label, $labels, true ) ) {
					continue;
				}
				$labels[] = $label;
			}
		}

		$ordered = array();
		foreach ( $priority as $label ) {
			if ( in_array( $label, $labels, true ) ) {
				$ordered[] = $label;
			}
		}
		foreach ( $labels as $label ) {
			if ( ! in_array( $label, $ordered, true ) ) {
				$ordered[] = $label;
			}
		}

		$rows = array();
		foreach ( $ordered as $label ) {
			$values = array();
			foreach ( $products as $product ) {
				$value = '';
				foreach ( $product['attributes'] as $row ) {
					if ( (string) $row['label'] === $label ) {
						$value = (string) $row['value'];
						break;
					}
				}
				$values[] = $value;
			}
			$rows[] = array(
				'label'  => $label,
				'values' => $values,
			);
		}

		return array(
			'products' => $products,
			'rows'     => $rows,
		);
	}

	/**
	 * Build matrix with values aligned to fixed compare slots.
	 *
	 * @param array<int, int> $slot_ids Slot-indexed product IDs (length 4).
	 * @return array{products: array<int, array<string, mixed>>, rows: array<int, array{label:string,values:array<int,string>}>}
	 */
	public static function build_matrix_slots( array $slot_ids ): array {
		$max = class_exists( 'Hello_Elementor_Child_Product_Compare' )
			? Hello_Elementor_Child_Product_Compare::MAX_ITEMS
			: 4;
		$slot_ids = array_values( array_pad( array_slice( array_map( 'intval', $slot_ids ), 0, $max ), $max, 0 ) );

		$products_by_slot = array();
		$products         = array();

		foreach ( $slot_ids as $index => $id ) {
			if ( $id <= 0 ) {
				continue;
			}
			$product = wc_get_product( $id );
			if ( ! $product || 'publish' !== $product->get_status() ) {
				continue;
			}
			$built = self::build( $product );
			if ( null !== $built ) {
				$products_by_slot[ (int) $index ] = $built;
				$products[]                       = $built;
			}
		}

		$priority = array(
			'برند',
			'CAS Number',
			'SKU',
			'نام انگلیسی',
			'گرید',
			'درصد خلوص',
			'خلوص',
			'کشور تولید کننده',
			'کشور سازنده',
			'بسته بندی',
			'بسته‌بندی',
		);

		$labels = array();
		foreach ( $products_by_slot as $product ) {
			foreach ( $product['attributes'] as $row ) {
				$label = (string) $row['label'];
				if ( '' === $label || in_array( $label, $labels, true ) ) {
					continue;
				}
				$labels[] = $label;
			}
		}

		$ordered = array();
		foreach ( $priority as $label ) {
			if ( in_array( $label, $labels, true ) ) {
				$ordered[] = $label;
			}
		}
		foreach ( $labels as $label ) {
			if ( ! in_array( $label, $ordered, true ) ) {
				$ordered[] = $label;
			}
		}

		$rows = array();
		foreach ( $ordered as $label ) {
			$values = array_fill( 0, $max, '' );
			foreach ( $products_by_slot as $index => $product ) {
				$value = '';
				foreach ( $product['attributes'] as $row ) {
					if ( (string) $row['label'] === $label ) {
						$value = (string) $row['value'];
						break;
					}
				}
				$values[ (int) $index ] = $value;
			}
			$rows[] = array(
				'label'  => $label,
				'values' => $values,
			);
		}

		return array(
			'products' => $products,
			'rows'     => $rows,
		);
	}

	/**
	 * @param WC_Product $product Product.
	 * @return array<int, array{label:string,value:string}>
	 */
	private static function collect_attributes( WC_Product $product ): array {
		if ( $product->is_type( 'variation' ) ) {
			return self::collect_variation_attributes( $product );
		}

		return self::collect_simple_attributes( $product );
	}

	/**
	 * @param WC_Product $product Simple or parent product.
	 * @return array<int, array{label:string,value:string}>
	 */
	private static function collect_simple_attributes( WC_Product $product ): array {
		$list = array();
		foreach ( $product->get_attributes() as $attribute ) {
			$label = wc_attribute_label( $attribute->get_name() );
			if ( $attribute->is_taxonomy() ) {
				$terms = wc_get_product_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'names' ) );
				$value = is_wp_error( $terms ) ? '' : implode( ', ', $terms );
			} else {
				$value = implode( ', ', array_map( 'strval', $attribute->get_options() ) );
			}
			if ( '' === $value ) {
				continue;
			}
			$list[] = array(
				'label' => $label,
				'value' => $value,
			);
		}

		return $list;
	}

	/**
	 * @param WC_Product $variation Variation product.
	 * @return array<int, array{label:string,value:string}>
	 */
	private static function collect_variation_attributes( WC_Product $variation ): array {
		$list = array();
		$map  = array();

		$parent_id = (int) $variation->get_parent_id();
		$parent    = $parent_id > 0 ? wc_get_product( $parent_id ) : null;
		if ( $parent ) {
			foreach ( self::collect_simple_attributes( $parent ) as $row ) {
				$map[ $row['label'] ] = $row['value'];
			}
		}

		if ( $variation instanceof WC_Product_Variation ) {
			foreach ( $variation->get_variation_attributes() as $taxonomy => $value ) {
				$value = is_scalar( $value ) ? trim( (string) $value ) : '';
				if ( '' === $value ) {
					continue;
				}
				$tax_key = str_replace( 'attribute_', '', (string) $taxonomy );
				$label   = wc_attribute_label( $tax_key, $variation );
				$display = self::format_variation_attribute_value(
					$tax_key,
					$value,
					$variation
				);
				$map[ $label ] = $display;
			}
		}

		foreach ( $map as $label => $value ) {
			$list[] = array(
				'label' => (string) $label,
				'value' => (string) $value,
			);
		}

		return $list;
	}

	/**
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta / ACF key.
	 */
	private static function get_field( int $post_id, string $key ): string {
		$value = function_exists( 'get_field' ) ? get_field( $key, $post_id ) : null;
		if ( null === $value || false === $value || '' === $value ) {
			$value = get_post_meta( $post_id, $key, true );
		}

		if ( is_array( $value ) ) {
			if ( isset( $value['url'] ) && '' !== (string) $value['url'] ) {
				return (string) $value['url'];
			}
			return '';
		}

		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}

	/**
	 * @param string $value Candidate URL.
	 */
	private static function is_url( string $value ): bool {
		return (bool) preg_match( '#^(https?:)?//#i', $value ) || false !== strpos( $value, '/wp-content/' );
	}

	/**
	 * @param array<string, string> $attr_map Attribute map.
	 * @param array<int, string>    $aliases  Label aliases.
	 */
	private static function find_attr( array $attr_map, array $aliases ): string {
		foreach ( $aliases as $alias ) {
			if ( isset( $attr_map[ $alias ] ) && '' !== $attr_map[ $alias ] ) {
				return (string) $attr_map[ $alias ];
			}
		}

		foreach ( $attr_map as $label => $value ) {
			foreach ( $aliases as $alias ) {
				if ( false !== mb_stripos( (string) $label, $alias ) && '' !== $value ) {
					return (string) $value;
				}
			}
		}

		return '';
	}

	/**
	 * Normalize attribute label/key for fuzzy matching.
	 *
	 * @param string $value Raw label or taxonomy.
	 */
	private static function normalize_attr_key( string $value ): string {
		$value = str_replace( array( "\u{200C}", "\xE2\x80\x8C" ), '', $value );
		$value = strtolower( $value );
		$value = preg_replace( '/^attribute_/', '', $value ) ?? $value;
		$value = preg_replace( '/^pa_/', '', $value ) ?? $value;
		$clean = preg_replace( '/[^\p{L}\p{N}]+/u', '', $value );

		return is_string( $clean ) ? $clean : $value;
	}

	/**
	 * Whether a label/key refers to packaging / بسته‌بندی.
	 *
	 * @param string $label Attribute label or taxonomy fragment.
	 */
	private static function is_packaging_label( string $label ): bool {
		$norm = self::normalize_attr_key( $label );
		if ( '' === $norm ) {
			return false;
		}

		$needles = array( 'بستهبندی', 'packaging', 'packsize', 'pack' );
		foreach ( $needles as $needle ) {
			if ( $norm === $needle || false !== strpos( $norm, $needle ) ) {
				return true;
			}
		}

		return false !== strpos( $norm, 'بسته' );
	}

	/**
	 * Resolve WooCommerce attribute taxonomy from a variation key.
	 *
	 * @param string $key Attribute key or taxonomy.
	 */
	private static function resolve_attribute_taxonomy( string $key ): string {
		$key = preg_replace( '/^attribute_/', '', $key ) ?? $key;
		if ( taxonomy_exists( $key ) ) {
			return $key;
		}
		if ( 0 !== strpos( $key, 'pa_' ) && taxonomy_exists( 'pa_' . $key ) ) {
			return 'pa_' . $key;
		}

		return $key;
	}

	/**
	 * Human-readable variation attribute value.
	 *
	 * @param string               $tax_key Taxonomy or attribute key.
	 * @param string               $value   Raw slug/value.
	 * @param WC_Product_Variation $variation Variation product.
	 */
	private static function format_variation_attribute_value( string $tax_key, string $value, WC_Product_Variation $variation ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}

		$candidates = array( $value );
		$decoded    = rawurldecode( $value );
		if ( $decoded !== $value ) {
			$candidates[] = $decoded;
		}

		$taxonomy = self::resolve_attribute_taxonomy( $tax_key );
		if ( taxonomy_exists( $taxonomy ) ) {
			foreach ( $candidates as $slug ) {
				$slug = trim( $slug );
				if ( '' === $slug ) {
					continue;
				}
				$term = get_term_by( 'slug', $slug, $taxonomy );
				if ( $term && ! is_wp_error( $term ) ) {
					return (string) $term->name;
				}
			}

			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
				)
			);
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$term_slug = (string) $term->slug;
					foreach ( $candidates as $slug ) {
						if ( $term_slug === $slug || rawurldecode( $term_slug ) === $slug ) {
							return (string) $term->name;
						}
					}
				}
			}
		}

		$readable = str_replace( '-', ' ', $decoded );
		return trim( $readable );
	}

	/**
	 * Packaging term names from a variable parent (preferred — proper labels).
	 *
	 * @param WC_Product_Variable $product Variable parent.
	 * @return array<int, string>
	 */
	private static function collect_packaging_term_names_from_parent( WC_Product_Variable $product ): array {
		$labels = array();

		foreach ( $product->get_attributes() as $attribute ) {
			$attr_name = $attribute->get_name();
			$label     = wc_attribute_label( $attr_name );
			if ( ! self::is_packaging_label( $label ) && ! self::is_packaging_label( $attr_name ) ) {
				continue;
			}

			if ( $attribute->is_taxonomy() ) {
				$terms = wc_get_product_terms( $product->get_id(), $attr_name, array( 'fields' => 'all' ) );
				if ( is_wp_error( $terms ) ) {
					continue;
				}
				foreach ( $terms as $term ) {
					$name = trim( (string) $term->name );
					if ( '' !== $name && ! in_array( $name, $labels, true ) ) {
						$labels[] = $name;
					}
				}
			} else {
				foreach ( $attribute->get_options() as $option ) {
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
	 * All packaging options for a variable product (comma-separated).
	 *
	 * @param WC_Product_Variable $product Variable parent.
	 */
	private static function collect_variable_packaging_summary( WC_Product_Variable $product ): string {
		$labels = self::collect_packaging_term_names_from_parent( $product );

		if ( array() === $labels ) {
			$objects = $product->get_available_variations( 'objects' );
			if ( is_array( $objects ) ) {
				foreach ( $objects as $variation ) {
					if ( ! $variation instanceof WC_Product_Variation ) {
						continue;
					}

					foreach ( $variation->get_variation_attributes() as $taxonomy => $raw_value ) {
						$tax_key = str_replace( 'attribute_', '', (string) $taxonomy );
						$label   = wc_attribute_label( $tax_key, $variation );
						if ( ! self::is_packaging_label( $label ) && ! self::is_packaging_label( $tax_key ) ) {
							continue;
						}

						$display = self::format_variation_attribute_value(
							$tax_key,
							is_scalar( $raw_value ) ? (string) $raw_value : '',
							$variation
						);
						if ( '' !== $display && ! in_array( $display, $labels, true ) ) {
							$labels[] = $display;
						}
					}
				}
			}
		}

		return implode( '، ', $labels );
	}

	/**
	 * Set or append packaging row with aggregated values.
	 *
	 * @param array<int, array{label:string,value:string}> $table_attrs Attribute rows.
	 * @param string                                       $summary     Packaging list.
	 * @return array<int, array{label:string,value:string}>
	 */
	private static function apply_packaging_summary( array $table_attrs, string $summary ): array {
		if ( '' === $summary ) {
			return $table_attrs;
		}

		$found = false;
		foreach ( $table_attrs as $index => $row ) {
			if ( ! self::is_packaging_label( (string) $row['label'] ) ) {
				continue;
			}
			$table_attrs[ $index ]['value'] = $summary;
			$found                          = true;
			break;
		}

		if ( ! $found ) {
			$table_attrs[] = array(
				'label' => 'بسته‌بندی',
				'value' => $summary,
			);
		}

		return $table_attrs;
	}
}
