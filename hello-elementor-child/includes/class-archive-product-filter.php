<?php
/**
 * Custom WooCommerce archive product filters.
 *
 * Sitewide on product category archives, product tag archives, and the shop page.
 * Deactivate JetSmartFilters in WP admin (assets are also dequeued here as a safety net).
 *
 * Shortcodes:
 * - [lk_archive_filters]
 * - [lk_filtered_products]
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Archive product filter feature.
 */
final class Hello_Elementor_Child_Archive_Product_Filter {

	/**
	 * 'pilot' = only configured category slugs; 'sitewide' = categories + tags + shop.
	 */
	public const MODE = 'sitewide';

	/**
	 * Query arg prefix.
	 */
	public const QUERY_PREFIX = 'lk_f_';

	/**
	 * Pilot category slugs (URL-decoded).
	 *
	 * @var array<int, string>
	 */
	private const PILOT_SLUGS = array(
		'منتول-menthol',
		'menthol',
	);

	/**
	 * Boot hooks.
	 */
	public static function init(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_shortcode( 'lk_archive_filters', array( __CLASS__, 'shortcode_filters' ) );
		add_shortcode( 'lk_filtered_products', array( __CLASS__, 'shortcode_products' ) );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'dequeue_jetsmart_on_active' ), 100 );

		add_action( 'woocommerce_product_query', array( __CLASS__, 'filter_wc_query' ), 20 );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_main_query' ), 20 );
		add_filter( 'jet-engine/listing/grid/posts-query-args', array( __CLASS__, 'filter_jet_engine_query' ), 20, 2 );

		add_action( 'woocommerce_before_shop_loop', array( __CLASS__, 'maybe_auto_render_filters' ), 5 );
		add_action( 'wp_footer', array( __CLASS__, 'maybe_inject_filters_script' ), 5 );

		add_action( 'wp_ajax_lk_archive_filter_products', array( __CLASS__, 'ajax_filter_products' ) );
		add_action( 'wp_ajax_nopriv_lk_archive_filter_products', array( __CLASS__, 'ajax_filter_products' ) );
		add_action( 'wp_ajax_lk_add_variation_to_cart', array( __CLASS__, 'ajax_add_variation_to_cart' ) );
		add_action( 'wp_ajax_nopriv_lk_add_variation_to_cart', array( __CLASS__, 'ajax_add_variation_to_cart' ) );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
	}

	/**
	 * Whether custom filters should run on the current request.
	 */
	public static function is_active_context(): bool {
		if ( class_exists( 'Hello_Elementor_Child_Light_Product_Template' )
			&& Hello_Elementor_Child_Light_Product_Template::is_enabled()
		) {
			return true;
		}

		// Product search results use the same filter UI + shop cards (AJAX keeps the `s` query).
		if ( self::is_product_search_context() ) {
			return true;
		}

		$mode = apply_filters( 'lk_archive_filter_mode', self::MODE );

		if ( 'sitewide' === $mode ) {
			return is_product_category()
				|| is_product_tag()
				|| is_shop()
				|| self::has_filter_request();
		}

		if ( ! is_product_category() ) {
			return self::has_filter_request() && self::request_targets_pilot();
		}

		$term = get_queried_object();
		if ( ! $term instanceof WP_Term ) {
			return false;
		}

		$slugs = apply_filters( 'lk_archive_filter_pilot_slugs', self::PILOT_SLUGS );
		$slugs = array_map( 'urldecode', (array) $slugs );

		return in_array( urldecode( $term->slug ), $slugs, true )
			|| in_array( $term->slug, $slugs, true );
	}

	/**
	 * Front-end product search results (FiboSearch Enter / ?s=&post_type=product).
	 */
	public static function is_product_search_context(): bool {
		if ( ! is_search() ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( (string) wp_unslash( $_GET['post_type'] ) ) : '';
		if ( '' === $post_type || 'product' === $post_type || 'any' === $post_type ) {
			return true;
		}

		$qv = get_query_var( 'post_type' );
		if ( is_string( $qv ) && ( '' === $qv || 'product' === $qv || 'any' === $qv ) ) {
			return true;
		}
		if ( is_array( $qv ) && in_array( 'product', $qv, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Shop-style cards / grid / pagination (shop + category + tag + product search).
	 */
	public static function uses_shop_cards_ui(): bool {
		if ( class_exists( 'Hello_Elementor_Child_Light_Product_Template' )
			&& Hello_Elementor_Child_Light_Product_Template::is_enabled()
		) {
			return true;
		}

		return self::is_product_search_context()
			|| ( function_exists( 'is_shop' ) && is_shop() )
			|| ( function_exists( 'is_product_category' ) && is_product_category() )
			|| is_tax( 'product_cat' )
			|| ( function_exists( 'is_product_tag' ) && is_product_tag() )
			|| is_tax( 'product_tag' );
	}

	/**
	 * Add body class for shared shop-card styles.
	 *
	 * @param array<int, string> $classes Body classes.
	 * @return array<int, string>
	 */
	public static function body_class( array $classes ): array {
		if ( self::uses_shop_cards_ui() ) {
			$classes[] = 'lk-shop-cards-archive';
		}
		// Elementor archives always expose tax-* classes; keep our hook explicit too.
		if ( is_tax( 'product_tag' ) || ( function_exists( 'is_product_tag' ) && is_product_tag() ) ) {
			$classes[] = 'lk-shop-cards-archive';
		}
		if ( is_tax( 'product_cat' ) || ( function_exists( 'is_product_category' ) && is_product_category() ) ) {
			$classes[] = 'lk-shop-cards-archive';
		}
		return array_values( array_unique( $classes ) );
	}

	/**
	 * Current archive taxonomy for scoping (product_cat|product_tag|empty).
	 */
	public static function get_scope_taxonomy(): string {
		if ( function_exists( 'is_product_category' ) && is_product_category() ) {
			return 'product_cat';
		}
		if ( function_exists( 'is_product_tag' ) && is_product_tag() ) {
			return 'product_tag';
		}
		if ( is_tax( 'product_tag' ) ) {
			return 'product_tag';
		}
		if ( is_tax( 'product_cat' ) ) {
			return 'product_cat';
		}
		return '';
	}

	/**
	 * Resolve taxonomy for a term ID (AJAX-safe).
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Requested taxonomy hint.
	 */
	private static function resolve_taxonomy_for_term( int $term_id, string $taxonomy = '' ): string {
		$candidates = array();
		if ( in_array( $taxonomy, array( 'product_cat', 'product_tag' ), true ) ) {
			$candidates[] = $taxonomy;
		}
		// Prefer product_tag when hint is missing — tag archives are the common AJAX miss.
		$candidates[] = 'product_tag';
		$candidates[] = 'product_cat';
		$candidates   = array_values( array_unique( $candidates ) );

		foreach ( $candidates as $tax ) {
			$term = get_term( $term_id, $tax );
			if ( $term instanceof WP_Term && ! is_wp_error( $term ) ) {
				return $tax;
			}
		}

		return '';
	}

	/**
	 * Whether current GET contains our filter params.
	 */
	private static function has_filter_request(): bool {
		foreach ( array_keys( $_GET ) as $key ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( 0 === strpos( (string) $key, self::QUERY_PREFIX ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Pilot mode: only apply GET filters when category is pilot (from referer/path) or term query present.
	 */
	private static function request_targets_pilot(): bool {
		if ( is_product_category() ) {
			return true;
		}
		return false;
	}

	/**
	 * Discover filter sources (taxonomies) by label / known slugs.
	 *
	 * @return array<string, array{taxonomy: string, label: string, type: string}>
	 */
	public static function get_filter_definitions(): array {
		$attribute_taxonomies = function_exists( 'wc_get_attribute_taxonomies' )
			? wc_get_attribute_taxonomies()
			: array();

		$by_label = array();
		foreach ( $attribute_taxonomies as $tax ) {
			$name  = isset( $tax->attribute_name ) ? (string) $tax->attribute_name : '';
			$label = isset( $tax->attribute_label ) ? (string) $tax->attribute_label : $name;
			if ( '' === $name ) {
				continue;
			}
			$by_label[ mb_strtolower( $label ) ] = 'pa_' . $name;
			$by_label[ mb_strtolower( $name ) ]  = 'pa_' . $name;
		}

		$find = static function ( array $needles ) use ( $by_label ): string {
			foreach ( $needles as $needle ) {
				$needle_l = mb_strtolower( $needle );
				if ( isset( $by_label[ $needle_l ] ) && taxonomy_exists( $by_label[ $needle_l ] ) ) {
					return $by_label[ $needle_l ];
				}
				foreach ( $by_label as $label => $tax ) {
					if ( false !== mb_strpos( $label, $needle_l ) && taxonomy_exists( $tax ) ) {
						return $tax;
					}
				}
				// Direct taxonomy guess.
				$guesses = array( $needle, 'pa_' . $needle, 'pa_' . sanitize_title( $needle ) );
				foreach ( $guesses as $guess ) {
					if ( taxonomy_exists( $guess ) ) {
						return $guess;
					}
				}
			}
			return '';
		};

		// Brand on LookAzma is stored as product_tag (see single-product-light.php).
		$brand_tax = taxonomy_exists( 'product_tag' ) ? 'product_tag' : $find( array( 'برند', 'brand' ) );

		$defs = array(
			'brand' => array(
				'taxonomy' => $brand_tax,
				'label'    => __( 'برند', 'hello-elementor-child' ),
				'type'     => 'tax',
			),
			'grade' => array(
				'taxonomy' => $find( array( 'گرید', 'grade', 'گرید محصول' ) ),
				'label'    => __( 'گرید', 'hello-elementor-child' ),
				'type'     => 'tax',
			),
			'purity' => array(
				'taxonomy' => $find( array( 'خلوص', 'درصد خلوص', 'purity' ) ),
				'label'    => __( 'درصد خلوص', 'hello-elementor-child' ),
				'type'     => 'tax',
			),
			'packaging' => array(
				'taxonomy' => $find( array( 'بسته', 'بسته‌ بندی', 'بسته بندی', 'packaging' ) ),
				'label'    => __( 'بسته‌بندی', 'hello-elementor-child' ),
				'type'     => 'tax',
			),
			'country' => array(
				'taxonomy' => $find( array( 'کشور', 'کشور تولید', 'country' ) ),
				'label'    => __( 'کشور تولیدکننده', 'hello-elementor-child' ),
				'type'     => 'tax',
			),
			'price' => array(
				'taxonomy' => '',
				'label'    => __( 'محدوده قیمت', 'hello-elementor-child' ),
				'type'     => 'price',
			),
		);

		// Known packaging slug from this theme.
		if ( '' === $defs['packaging']['taxonomy'] && taxonomy_exists( 'pa_بسته‌ بندی' ) ) {
			$defs['packaging']['taxonomy'] = 'pa_بسته‌ بندی';
		}

		return apply_filters( 'lk_archive_filter_definitions', $defs );
	}

	/**
	 * Current category/tag term for scoping counts/options.
	 */
	private static function get_scope_term(): ?WP_Term {
		$taxonomy = self::get_scope_taxonomy();
		if ( '' === $taxonomy ) {
			return null;
		}
		$term = get_queried_object();
		return $term instanceof WP_Term ? $term : null;
	}

	/**
	 * Resolve a term for scoping (AJAX or current request).
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy slug.
	 */
	private static function resolve_scope_term( int $term_id = 0, string $taxonomy = '' ): ?WP_Term {
		if ( $term_id > 0 ) {
			$taxonomy = self::resolve_taxonomy_for_term( $term_id, $taxonomy );
			if ( '' === $taxonomy ) {
				return null;
			}
			$maybe = get_term( $term_id, $taxonomy );
			return $maybe instanceof WP_Term ? $maybe : null;
		}
		return self::get_scope_term();
	}

	/**
	 * Product IDs in current category/tag (for counting filter options).
	 *
	 * @param int|null                  $term_id  Optional term ID (AJAX).
	 * @param array<string, mixed>|null $selected Optional filters to apply.
	 * @param string                    $taxonomy Taxonomy for $term_id.
	 * @return array<int, int>
	 */
	private static function get_scoped_product_ids( ?int $term_id = null, ?array $selected = null, string $taxonomy = '', string $search = '' ): array {
		$term = null;
		if ( null !== $term_id && $term_id > 0 ) {
			$term = self::resolve_scope_term( $term_id, $taxonomy );
		} else {
			$term = self::get_scope_term();
		}

		$args = array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		if ( $term ) {
			$args['tax_query'] = array(
				array(
					'taxonomy'         => $term->taxonomy,
					'field'            => 'term_id',
					'terms'            => array( (int) $term->term_id ),
					'include_children' => ( 'product_cat' === $term->taxonomy ),
				),
			);
		}

		if ( null !== $selected ) {
			$args = self::apply_fragments_to_args( $args, $selected, true );
		}

		$search = is_string( $search ) ? trim( $search ) : '';
		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$ids = get_posts( $args );
		return array_map( 'intval', is_array( $ids ) ? $ids : array() );
	}

	/**
	 * Facet options/counts for each filter, excluding that filter's own selection.
	 *
	 * @param int                  $term_id  Scope term ID.
	 * @param array<string, mixed> $selected Current selections.
	 * @param string               $taxonomy Scope taxonomy.
	 * @return array<string, array<int, array{slug: string, name: string, count: int}>>
	 */
	private static function build_facets( int $term_id, array $selected, string $taxonomy = '', string $search = '' ): array {
		$defs   = self::get_filter_definitions();
		$facets = array();

		foreach ( $defs as $key => $def ) {
			if ( 'price' === $def['type'] || '' === ( $def['taxonomy'] ?? '' ) ) {
				continue;
			}

			// On a brand (product_tag) archive, brand facet is redundant.
			if ( 'brand' === $key && 'product_tag' === $taxonomy ) {
				continue;
			}

			$without          = $selected;
			$without[ $key ] = array();
			$product_ids      = self::get_scoped_product_ids( $term_id > 0 ? $term_id : null, $without, $taxonomy, $search );
			$facets[ $key ]  = self::get_terms_for_products( $def['taxonomy'], $product_ids );
		}

		return $facets;
	}

	/**
	 * Terms available for a taxonomy within scoped products (with counts).
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param array<int, int> $product_ids Product IDs.
	 * @return array<int, array{slug: string, name: string, count: int}>
	 */
	private static function get_terms_for_products( string $taxonomy, array $product_ids ): array {
		if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) || array() === $product_ids ) {
			return array();
		}

		$counts = array();
		foreach ( $product_ids as $product_id ) {
			$terms = get_the_terms( $product_id, $taxonomy );
			if ( ! is_array( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				if ( ! $term instanceof WP_Term ) {
					continue;
				}
				$key = $term->slug;
				if ( ! isset( $counts[ $key ] ) ) {
					$counts[ $key ] = array(
						'slug'  => $term->slug,
						'name'  => $term->name,
						'count' => 0,
					);
				}
				++$counts[ $key ]['count'];
			}
		}

		$out = array_values( $counts );
		usort(
			$out,
			static function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return $out;
	}

	/**
	 * Price min/max among scoped products.
	 *
	 * @param array<int, int> $product_ids IDs.
	 * @return array{min: float, max: float}
	 */
	private static function get_price_range( array $product_ids ): array {
		$min = 0.0;
		$max = 0.0;
		if ( array() === $product_ids ) {
			return array( 'min' => $min, 'max' => $max );
		}

		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $product_ids ), '%d' ) );
		$query_sql    = "SELECT MIN(CAST(meta_value AS DECIMAL(20,4))) AS min_price,
			MAX(CAST(meta_value AS DECIMAL(20,4))) AS max_price
			FROM {$wpdb->postmeta}
			WHERE meta_key = '_price'
			AND meta_value != ''
			AND post_id IN ($placeholders)";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders built above.
		$sql = $wpdb->prepare( $query_sql, ...$product_ids );
		$row = $wpdb->get_row( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $row ) {
			$min = (float) $row->min_price;
			$max = (float) $row->max_price;
		}

		return array( 'min' => $min, 'max' => $max );
	}

	/**
	 * Read selected filters from request or provided payload.
	 *
	 * @param array<string, mixed>|null $payload Optional AJAX payload.
	 * @return array<string, mixed>
	 */
	public static function get_selected_filters( ?array $payload = null ): array {
		$selected = array();
		$defs     = self::get_filter_definitions();
		$source   = null !== $payload ? $payload : $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		foreach ( $defs as $key => $def ) {
			$param = self::QUERY_PREFIX . $key;
			if ( 'price' === $def['type'] ) {
				$min = isset( $source[ $param . '_min' ] ) ? $source[ $param . '_min' ] : ( $source['price']['min'] ?? '' );
				$max = isset( $source[ $param . '_max' ] ) ? $source[ $param . '_max' ] : ( $source['price']['max'] ?? '' );
				if ( is_array( $min ) ) {
					$min = '';
				}
				if ( is_array( $max ) ) {
					$max = '';
				}
				$min = is_string( $min ) || is_numeric( $min ) ? $min : '';
				$max = is_string( $max ) || is_numeric( $max ) ? $max : '';
				$selected[ $key ] = array(
					'min' => is_numeric( $min ) ? (float) $min : null,
					'max' => is_numeric( $max ) ? (float) $max : null,
				);
				continue;
			}

			$raw = '';
			if ( isset( $source[ $param ] ) ) {
				$raw = $source[ $param ];
			} elseif ( isset( $source[ $key ] ) ) {
				$raw = $source[ $key ];
			}

			if ( is_array( $raw ) ) {
				$selected[ $key ] = array_values( array_filter( array_map( 'sanitize_title', $raw ) ) );
			} else {
				$val = sanitize_title( (string) $raw );
				$selected[ $key ] = '' !== $val ? array( $val ) : array();
			}
		}

		return $selected;
	}

	/**
	 * Build tax_query + meta_query fragments from selected filters.
	 *
	 * @param array<string, mixed>|null $selected Optional selected filters.
	 * @return array{tax_query: array<int, array<string, mixed>>, meta_query: array<int, array<string, mixed>>}
	 */
	public static function build_query_fragments( ?array $selected = null ): array {
		$tax_query  = array();
		$meta_query = array();
		$selected   = null !== $selected ? $selected : self::get_selected_filters();
		$defs       = self::get_filter_definitions();

		foreach ( $defs as $key => $def ) {
			if ( 'price' === $def['type'] ) {
				$min = $selected[ $key ]['min'] ?? null;
				$max = $selected[ $key ]['max'] ?? null;
				if ( null !== $min || null !== $max ) {
					$clause = array(
						'key'     => '_price',
						'type'    => 'NUMERIC',
						'compare' => 'BETWEEN',
					);
					if ( null !== $min && null !== $max ) {
						$clause['value'] = array( $min, $max );
					} elseif ( null !== $min ) {
						$clause['compare'] = '>=';
						$clause['value']   = $min;
					} else {
						$clause['compare'] = '<=';
						$clause['value']   = $max;
					}
					$meta_query[] = $clause;
				}
				continue;
			}

			$taxonomy = $def['taxonomy'];
			$terms    = $selected[ $key ] ?? array();
			if ( '' === $taxonomy || array() === $terms ) {
				continue;
			}

			$tax_query[] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => $terms,
				'operator' => 'IN',
			);
		}

		return array(
			'tax_query'  => $tax_query,
			'meta_query' => $meta_query,
		);
	}

	/**
	 * Merge fragments into a WP_Query-style args array.
	 *
	 * @param array<string, mixed>      $args     Query args.
	 * @param array<string, mixed>|null $selected Optional filters (AJAX).
	 * @param bool                      $force    Skip active-context check.
	 * @return array<string, mixed>
	 */
	public static function apply_fragments_to_args( array $args, ?array $selected = null, bool $force = false ): array {
		if ( ! $force && ! self::is_active_context() ) {
			return $args;
		}

		$fragments = self::build_query_fragments( $selected );
		if ( array() === $fragments['tax_query'] && array() === $fragments['meta_query'] ) {
			return $args;
		}

		if ( array() !== $fragments['tax_query'] ) {
			$existing = isset( $args['tax_query'] ) && is_array( $args['tax_query'] ) ? $args['tax_query'] : array();
			$merged   = array( 'relation' => 'AND' );
			foreach ( $existing as $k => $clause ) {
				if ( 'relation' === $k ) {
					continue;
				}
				$merged[] = $clause;
			}
			foreach ( $fragments['tax_query'] as $clause ) {
				$merged[] = $clause;
			}
			$args['tax_query'] = $merged;
		}

		if ( array() !== $fragments['meta_query'] ) {
			$existing = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : array();
			$merged   = array( 'relation' => 'AND' );
			foreach ( $existing as $k => $clause ) {
				if ( 'relation' === $k ) {
					continue;
				}
				$merged[] = $clause;
			}
			foreach ( $fragments['meta_query'] as $clause ) {
				$merged[] = $clause;
			}
			$args['meta_query'] = $merged;
		}

		return $args;
	}

	/**
	 * WooCommerce product query.
	 *
	 * @param WP_Query $query Query.
	 */
	public static function filter_wc_query( $query ): void {
		if ( ! $query instanceof WP_Query || ! self::is_active_context() ) {
			return;
		}
		$args = self::apply_fragments_to_args( $query->query_vars );
		foreach ( array( 'tax_query', 'meta_query' ) as $key ) {
			if ( isset( $args[ $key ] ) ) {
				$query->set( $key, $args[ $key ] );
			}
		}
	}

	/**
	 * Main query fallback for product archives.
	 *
	 * @param WP_Query $query Query.
	 */
	public static function filter_main_query( $query ): void {
		if ( is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() ) {
			return;
		}
		if ( ! self::is_active_context() ) {
			return;
		}
		$is_product_archive = $query->is_post_type_archive( 'product' )
			|| $query->is_tax( get_object_taxonomies( 'product' ) )
			|| ( function_exists( 'wc_get_page_id' ) && (int) $query->get( 'page_id' ) === (int) wc_get_page_id( 'shop' ) );
		if ( ! $is_product_archive ) {
			return;
		}
		self::filter_wc_query( $query );
	}

	/**
	 * JetEngine Listing Grid query.
	 *
	 * @param array<string, mixed> $args   Query args.
	 * @param mixed                $widget Widget.
	 * @return array<string, mixed>
	 */
	public static function filter_jet_engine_query( $args, $widget = null ) {
		if ( ! is_array( $args ) || ! self::is_active_context() ) {
			return $args;
		}
		return self::apply_fragments_to_args( $args );
	}

	/**
	 * Enqueue assets on active contexts.
	 */
	public static function enqueue_assets(): void {
		if ( ! self::is_active_context() ) {
			return;
		}

		$term         = self::get_scope_term();
		$taxonomy     = self::get_scope_taxonomy();
		$use_shop_ui  = self::uses_shop_cards_ui();
		$is_search    = self::is_product_search_context();
		$search_query = $is_search ? trim( (string) get_search_query( false ) ) : '';
		$is_light_archive = class_exists( 'Hello_Elementor_Child_Light_Product_Template' )
			&& Hello_Elementor_Child_Light_Product_Template::is_enabled();
		if ( $is_light_archive ) {
			$use_shop_ui = true;
		}

		wp_enqueue_style(
			'lk-archive-filters',
			HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-product-filters.css',
			array(),
			HELLO_ELEMENTOR_CHILD_VERSION
		);

		$script_deps = array();
		if ( $use_shop_ui || $is_light_archive ) {
			wp_enqueue_script( 'wc-add-to-cart' );
			$script_deps = array( 'jquery', 'wc-add-to-cart' );
		}

		wp_enqueue_script(
			'lk-archive-filters',
			HELLO_ELEMENTOR_CHILD_URI . 'assets/js/archive-product-filters.js',
			$script_deps,
			HELLO_ELEMENTOR_CHILD_VERSION,
			true
		);

		if ( $use_shop_ui ) {
			$shop_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-product-shop-cards.css';
			wp_enqueue_style(
				'lk-archive-shop-cards',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-product-shop-cards.css',
				array( 'lk-archive-filters' ),
				file_exists( $shop_css ) ? (string) filemtime( $shop_css ) : HELLO_ELEMENTOR_CHILD_VERSION
			);
		}

		wp_localize_script(
			'lk-archive-filters',
			'lkArchiveFilters',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'action'         => 'lk_archive_filter_products',
				'nonce'          => wp_create_nonce( 'lk_archive_filter' ),
				'termId'         => $term ? (int) $term->term_id : 0,
				'taxonomy'       => $taxonomy,
				'isShop'         => $use_shop_ui,
				'isSearch'       => $is_search,
				'searchQuery'    => $search_query,
				'layout'         => $use_shop_ui ? 'shop' : 'archive',
				'nativeTemplate' => (
						class_exists( 'Hello_Elementor_Child_Light_Product_Template' )
						&& Hello_Elementor_Child_Light_Product_Template::is_enabled()
					) || (
						class_exists( 'Hello_Elementor_Child_Custom_Category_Archive' )
						&& Hello_Elementor_Child_Custom_Category_Archive::is_enabled()
					) || (
						class_exists( 'Hello_Elementor_Child_Custom_Tag_Archive' )
						&& Hello_Elementor_Child_Custom_Tag_Archive::is_enabled()
					),
				'perPage'        => (
					class_exists( 'Hello_Elementor_Child_Light_Product_Template' )
					&& Hello_Elementor_Child_Light_Product_Template::is_enabled()
				)
					? Hello_Elementor_Child_Light_Product_Template::get_per_page()
					: ( (
						class_exists( 'Hello_Elementor_Child_Custom_Category_Archive' )
						&& Hello_Elementor_Child_Custom_Category_Archive::is_enabled()
					)
						? Hello_Elementor_Child_Custom_Category_Archive::get_per_page()
						: ( (
							class_exists( 'Hello_Elementor_Child_Custom_Tag_Archive' )
							&& Hello_Elementor_Child_Custom_Tag_Archive::is_enabled()
						)
							? Hello_Elementor_Child_Custom_Tag_Archive::get_per_page()
							: ( ( $use_shop_ui && 'product_cat' !== $taxonomy )
								? 12
								: ( class_exists( 'Hello_Elementor_Child_Custom_Category_Archive' )
									? Hello_Elementor_Child_Custom_Category_Archive::get_per_page()
									: 12 ) ) ) ),
				'i18n'           => array(
					'empty'      => __( 'محصولی با این فیلترها پیدا نشد.', 'hello-elementor-child' ),
					'error'      => __( 'خطا در فیلتر محصولات.', 'hello-elementor-child' ),
					'search'     => __( 'جستجو…', 'hello-elementor-child' ),
					'loading'    => __( 'در حال فیلتر…', 'hello-elementor-child' ),
					'addToCart'  => __( 'افزودن به سبد', 'hello-elementor-child' ),
					'added'      => __( 'افزوده شد', 'hello-elementor-child' ),
					'adding'     => __( 'در حال افزودن…', 'hello-elementor-child' ),
					'viewCart'   => __( 'مشاهده سبد خرید', 'hello-elementor-child' ),
					'pagination' => __( 'صفحه‌بندی محصولات', 'hello-elementor-child' ),
				),
				'cartUrl'        => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ),
			)
		);
	}

	/**
	 * Always enqueue the real lk-archive-filters CSS/JS on Light archive pages.
	 */
	public static function enqueue_light_archive_assets(): void {
		$css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-product-filters.css';
		if ( file_exists( $css ) ) {
			wp_enqueue_style(
				'lk-archive-filters',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-product-filters.css',
				array(),
				(string) filemtime( $css )
			);
		}

		$shop_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-product-shop-cards.css';
		if ( file_exists( $shop_css ) ) {
			wp_enqueue_style(
				'lk-archive-shop-cards',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-product-shop-cards.css',
				array( 'lk-archive-filters' ),
				(string) filemtime( $shop_css )
			);
		}

		$js = HELLO_ELEMENTOR_CHILD_PATH . 'assets/js/archive-product-filters.js';
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'wc-add-to-cart' );
		if ( file_exists( $js ) ) {
			wp_enqueue_script(
				'lk-archive-filters',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/js/archive-product-filters.js',
				array( 'jquery', 'wc-add-to-cart' ),
				(string) filemtime( $js ),
				true
			);
		}

		$per_page = 12;
		if ( class_exists( 'Hello_Elementor_Child_Light_Product_Template' ) ) {
			$per_page = Hello_Elementor_Child_Light_Product_Template::get_per_page();
		}

		$is_search    = self::is_product_search_context();
		$search_query = $is_search ? trim( (string) get_search_query( false ) ) : '';

		wp_localize_script(
			'lk-archive-filters',
			'lkArchiveFilters',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'action'         => 'lk_archive_filter_products',
				'nonce'          => wp_create_nonce( 'lk_archive_filter' ),
				'termId'         => 0,
				'taxonomy'       => '',
				'isShop'         => true,
				'isSearch'       => $is_search,
				'searchQuery'    => $search_query,
				'layout'         => 'shop',
				'nativeTemplate' => true,
				'perPage'        => $per_page,
				'i18n'           => array(
					'empty'      => __( 'محصولی با این فیلترها پیدا نشد.', 'hello-elementor-child' ),
					'error'      => __( 'خطا در فیلتر محصولات.', 'hello-elementor-child' ),
					'search'     => __( 'جستجو…', 'hello-elementor-child' ),
					'loading'    => __( 'در حال فیلتر…', 'hello-elementor-child' ),
					'addToCart'  => __( 'افزودن به سبد', 'hello-elementor-child' ),
					'added'      => __( 'افزوده شد', 'hello-elementor-child' ),
					'adding'     => __( 'در حال افزودن…', 'hello-elementor-child' ),
					'viewCart'   => __( 'مشاهده سبد خرید', 'hello-elementor-child' ),
					'pagination' => __( 'صفحه‌بندی محصولات', 'hello-elementor-child' ),
				),
				'cartUrl'        => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ),
			)
		);
	}

	/**
	 * Hide / dequeue JetSmartFilters on pages where custom filter is active.
	 */
	public static function dequeue_jetsmart_on_active(): void {
		if ( ! self::is_active_context() ) {
			return;
		}

		foreach ( wp_scripts()->registered as $handle => $obj ) {
			if ( false !== strpos( $handle, 'jet-smart-filters' ) ) {
				wp_dequeue_script( $handle );
				wp_deregister_script( $handle );
			}
		}
		foreach ( wp_styles()->registered as $handle => $obj ) {
			if ( false !== strpos( $handle, 'jet-smart-filters' ) ) {
				wp_dequeue_style( $handle );
				wp_deregister_style( $handle );
			}
		}

		// Hide JetSmart widgets + parent Elementor accordion that contains them.
		wp_add_inline_style(
			'lk-archive-filters',
			'.jet-smart-filters,' .
			'.jet-smart-filters-pagination,' .
			'.jet-smart-filters-range,' .
			'.jet-smart-filters-checkboxes,' .
			'.jet-smart-filters-select,' .
			'.elementor-widget-jet-smart-filters-checkboxes,' .
			'.elementor-widget-jet-smart-filters-select,' .
			'.elementor-widget-jet-smart-filters-range,' .
			'.elementor-widget-jet-smart-filters-sorting,' .
			'.elementor-widget-jet-smart-filters-remove-filters,' .
			'.elementor-widget-jet-smart-filters-active,' .
			'.elementor-widget-jet-smart-filters-pagination,' .
			'.lk-hide-old-filters{display:none!important;}'
		);
	}

	/**
	 * Auto-render before WC shop loop when active.
	 * Disabled: Elementor category templates need JS placement under old filter column.
	 */
	public static function maybe_auto_render_filters(): void {
		// Intentionally empty — filters are injected via footer JS.
	}

	/**
	 * Inject filter HTML after old JetSmartFilters column (via JS).
	 */
	public static function maybe_inject_filters_script(): void {
		if ( ! self::is_active_context() ) {
			return;
		}

		// Native Timber templates already print filters in the page.
		if ( class_exists( 'Hello_Elementor_Child_Light_Product_Template' )
			&& Hello_Elementor_Child_Light_Product_Template::is_enabled()
		) {
			return;
		}
		if ( class_exists( 'Hello_Elementor_Child_Custom_Category_Archive' )
			&& Hello_Elementor_Child_Custom_Category_Archive::is_enabled()
		) {
			return;
		}
		if ( class_exists( 'Hello_Elementor_Child_Custom_Tag_Archive' )
			&& Hello_Elementor_Child_Custom_Tag_Archive::is_enabled()
		) {
			return;
		}

		// Always provide HTML for JS placement (even if shortcode already printed elsewhere).
		$html = self::render_filters_html();
		wp_add_inline_script(
			'lk-archive-filters',
			'window.lkArchiveFiltersInjectHtml = ' . wp_json_encode( $html ) . ';',
			'before'
		);
	}

	/**
	 * Shortcode: filters form.
	 *
	 * @return string
	 */
	public static function shortcode_filters(): string {
		if ( ! self::is_active_context() ) {
			return '';
		}
		return self::render_filters_html();
	}

	/**
	 * Shortcode: filtered product cards (fallback listing).
	 *
	 * @param array<string, string>|string $atts Atts.
	 * @return string
	 */
	public static function shortcode_products( $atts = array() ): string {
		if ( ! self::is_active_context() ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'columns'  => '3',
				'per_page' => '24',
			),
			$atts,
			'lk_filtered_products'
		);

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, (int) $atts['per_page'] ),
		);

		$term = self::get_scope_term();
		if ( $term ) {
			$args['tax_query'] = array(
				array(
					'taxonomy'         => $term->taxonomy,
					'field'            => 'term_id',
					'terms'            => array( (int) $term->term_id ),
					'include_children' => ( 'product_cat' === $term->taxonomy ),
				),
			);
		}

		$args  = self::apply_fragments_to_args( $args );
		$query = new WP_Query( $args );

		ob_start();
		echo '<div class="lk-filtered-products columns-' . esc_attr( (string) $atts['columns'] ) . '">';
		if ( $query->have_posts() ) {
			woocommerce_product_loop_start();
			while ( $query->have_posts() ) {
				$query->the_post();
				wc_get_template_part( 'content', 'product' );
			}
			woocommerce_product_loop_end();
		} else {
			echo '<p class="lk-filtered-products__empty">' . esc_html__( 'محصولی با این فیلترها پیدا نشد.', 'hello-elementor-child' ) . '</p>';
		}
		echo '</div>';
		wp_reset_postdata();

		return (string) ob_get_clean();
	}

	/**
	 * Render filter form HTML (checkbox panels + search).
	 */
	public static function render_filters_html(): string {
		$GLOBALS['lk_archive_filters_rendered'] = true;

		$search_query = self::is_product_search_context()
			? trim( (string) get_search_query( false ) )
			: '';
		$product_ids = self::get_scoped_product_ids( null, null, '', $search_query );
		$defs        = self::get_filter_definitions();
		$selected    = self::get_selected_filters();
		$price_range = self::get_price_range( $product_ids );
		$term        = self::get_scope_term();
		$taxonomy    = self::get_scope_taxonomy();

		// Brand archive already scopes by tag — hide brand filter panel.
		if ( 'product_tag' === $taxonomy ) {
			unset( $defs['brand'] );
		}

		ob_start();
		?>
		<div
			class="lk-archive-filters"
			id="lk-archive-filters"
			data-lk-custom-filters="1"
			data-term-id="<?php echo esc_attr( $term ? (string) $term->term_id : '0' ); ?>"
			data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>"
		>
			<div class="lk-archive-filters__head">
				<strong class="lk-archive-filters__title"><?php esc_html_e( 'فیلترها', 'hello-elementor-child' ); ?></strong>
				<button type="button" class="lk-archive-filters__clear" id="lk-archive-filters-clear">
					<?php esc_html_e( 'حذف فیلتر', 'hello-elementor-child' ); ?>
				</button>
			</div>

			<div class="lk-archive-filters__panels">
				<?php foreach ( $defs as $key => $def ) : ?>
					<?php if ( 'price' === $def['type'] ) : ?>
						<?php
						$sel_min     = $selected[ $key ]['min'] ?? null;
						$sel_max     = $selected[ $key ]['max'] ?? null;
						$price_open  = null !== $sel_min || null !== $sel_max;
						?>
						<details class="lk-archive-filters__panel" data-lk-filter-price="1" <?php echo $price_open ? 'open' : ''; ?>>
							<summary class="lk-archive-filters__summary">
								<span class="lk-archive-filters__summary-text"><?php echo esc_html( $def['label'] ); ?></span>
								<span class="lk-archive-filters__trigger" aria-hidden="true"></span>
							</summary>
							<div class="lk-archive-filters__panel-body lk-archive-filters__group--price">
								<div class="lk-archive-filters__price-inputs">
									<input type="number" data-lk-price="min" value="<?php echo null !== $sel_min ? esc_attr( (string) $sel_min ) : ''; ?>" min="0" step="1" placeholder="<?php echo esc_attr( (string) (int) $price_range['min'] ); ?>">
									<span>—</span>
									<input type="number" data-lk-price="max" value="<?php echo null !== $sel_max ? esc_attr( (string) $sel_max ) : ''; ?>" min="0" step="1" placeholder="<?php echo esc_attr( (string) (int) $price_range['max'] ); ?>">
								</div>
								<button type="button" class="lk-archive-filters__price-apply button"><?php esc_html_e( 'اعمال قیمت', 'hello-elementor-child' ); ?></button>
							</div>
						</details>
					<?php else : ?>
						<?php
						if ( '' === $def['taxonomy'] ) {
							continue;
						}
						$options = self::get_terms_for_products( $def['taxonomy'], $product_ids );
						if ( array() === $options ) {
							continue;
						}
						$sel      = $selected[ $key ] ?? array();
						$has_sel  = array() !== $sel;
						?>
						<details class="lk-archive-filters__panel" data-lk-filter-key="<?php echo esc_attr( $key ); ?>" <?php echo $has_sel ? 'open' : ''; ?>>
							<summary class="lk-archive-filters__summary">
								<span class="lk-archive-filters__summary-text"><?php echo esc_html( $def['label'] ); ?></span>
								<span class="lk-archive-filters__trigger" aria-hidden="true"></span>
							</summary>
							<div class="lk-archive-filters__panel-body">
								<input
									type="search"
									class="lk-archive-filters__search"
									placeholder="<?php esc_attr_e( 'جستجو…', 'hello-elementor-child' ); ?>"
									autocomplete="off"
								>
								<ul class="lk-archive-filters__list" role="list">
									<?php foreach ( $options as $opt ) : ?>
										<li class="lk-archive-filters__item" data-lk-slug="<?php echo esc_attr( $opt['slug'] ); ?>" data-lk-label="<?php echo esc_attr( mb_strtolower( $opt['name'] ) ); ?>" data-lk-available="1">
											<label class="lk-archive-filters__check">
												<input
													type="checkbox"
													name="<?php echo esc_attr( self::QUERY_PREFIX . $key ); ?>[]"
													value="<?php echo esc_attr( $opt['slug'] ); ?>"
													<?php checked( in_array( $opt['slug'], $sel, true ) ); ?>
												>
												<span class="lk-archive-filters__check-title"><?php echo esc_html( $opt['name'] ); ?></span>
												<span class="lk-archive-filters__check-count">(<?php echo esc_html( (string) $opt['count'] ); ?>)</span>
											</label>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>
						</details>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * AJAX: filter products and return Elementor-loop-compatible HTML.
	 */
	public static function ajax_filter_products(): void {
		check_ajax_referer( 'lk_archive_filter', 'nonce' );

		$term_id  = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		$page     = isset( $_POST['page'] ) ? max( 1, absint( $_POST['page'] ) ) : 1;
		$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_key( (string) wp_unslash( $_POST['taxonomy'] ) ) : '';
		if ( $term_id > 0 ) {
			$taxonomy = self::resolve_taxonomy_for_term( $term_id, $taxonomy );
		} else {
			$taxonomy = '';
		}

		$raw     = isset( $_POST['filters'] ) ? wp_unslash( $_POST['filters'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$payload = is_string( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : array() );
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}

		$selected = self::get_selected_filters( $payload );

		$layout = isset( $_POST['layout'] ) ? sanitize_key( (string) wp_unslash( $_POST['layout'] ) ) : '';
		if ( 'shop' !== $layout && 'archive' !== $layout ) {
			$layout = ( in_array( $taxonomy, array( 'product_tag', 'product_cat' ), true ) || 0 === $term_id ) ? 'shop' : 'archive';
		}

		$per_page = 12;
		if ( isset( $_POST['per_page'] ) ) {
			$per_page = max( 1, absint( $_POST['per_page'] ) );
		} elseif ( class_exists( 'Hello_Elementor_Child_Light_Product_Template' ) ) {
			$per_page = Hello_Elementor_Child_Light_Product_Template::get_per_page();
		} elseif ( class_exists( 'Hello_Elementor_Child_Custom_Category_Archive' )
			&& 'product_cat' === $taxonomy
			&& Hello_Elementor_Child_Custom_Category_Archive::is_enabled( $term_id > 0 ? $term_id : null )
		) {
			$per_page = Hello_Elementor_Child_Custom_Category_Archive::get_per_page();
		} elseif ( class_exists( 'Hello_Elementor_Child_Custom_Tag_Archive' )
			&& 'product_tag' === $taxonomy
			&& Hello_Elementor_Child_Custom_Tag_Archive::is_enabled( $term_id > 0 ? $term_id : null )
		) {
			$per_page = Hello_Elementor_Child_Custom_Tag_Archive::get_per_page();
		} elseif ( class_exists( 'Hello_Elementor_Child_Custom_Category_Archive' ) ) {
			$per_page = Hello_Elementor_Child_Custom_Category_Archive::get_per_page();
		}

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
		);

		$search = isset( $_POST['s'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['s'] ) ) : '';
		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		if ( $term_id > 0 && '' !== $taxonomy ) {
			$args['tax_query'] = array(
				array(
					'taxonomy'         => $taxonomy,
					'field'            => 'term_id',
					'terms'            => array( $term_id ),
					'include_children' => ( 'product_cat' === $taxonomy ),
				),
			);
		}

		$args  = self::apply_fragments_to_args( $args, $selected, true );
		$query = new WP_Query( $args );
		$html  = self::render_products_grid_html( $query, $layout );

		wp_send_json_success(
			array(
				'html'       => $html,
				'pagination' => self::render_pagination_html( $query, $term_id, $page, $taxonomy, $search ),
				'count'      => (int) $query->found_posts,
				'facets'     => self::build_facets( $term_id, $selected, $taxonomy, $search ),
				'page'       => $page,
			)
		);
	}

	/**
	 * AJAX add a variation to the cart from shop-card option menus.
	 */
	public static function ajax_add_variation_to_cart(): void {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json( array( 'error' => true ) );
		}

		$variation_id = isset( $_POST['variation_id'] ) ? absint( wp_unslash( $_POST['variation_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$quantity     = isset( $_POST['quantity'] ) ? wc_stock_amount( wp_unslash( $_POST['quantity'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $quantity < 1 ) {
			$quantity = 1;
		}

		$variation = $variation_id ? wc_get_product( $variation_id ) : null;
		if ( ! $variation instanceof WC_Product_Variation || ! $variation->is_purchasable() || ! $variation->is_in_stock() ) {
			wp_send_json( array( 'error' => true ) );
		}

		$parent_id     = (int) $variation->get_parent_id();
		$attributes    = $variation->get_variation_attributes();
		$cart_item_key = WC()->cart->add_to_cart( $parent_id, $quantity, $variation_id, $attributes );

		if ( ! $cart_item_key ) {
			wp_send_json( array( 'error' => true ) );
		}

		if ( class_exists( 'WC_AJAX' ) ) {
			WC_AJAX::get_refreshed_fragments();
		}

		wp_send_json(
			array(
				'fragments' => array(),
				'cart_hash' => WC()->cart->get_cart_hash(),
			)
		);
	}

	/**
	 * Render product cards for loop/grid replacement.
	 *
	 * @param WP_Query $query  Query.
	 * @param string   $layout Card layout: archive|shop.
	 * @return string
	 */
	public static function render_products_grid_html( WP_Query $query, string $layout = 'archive' ): string {
		if ( ! $query->have_posts() ) {
			return '<div class="lk-filtered-products__empty" role="listitem">' . esc_html__( 'محصولی با این فیلترها پیدا نشد.', 'hello-elementor-child' ) . '</div>';
		}

		$layout = 'shop' === $layout ? 'shop' : 'archive';

		ob_start();
		while ( $query->have_posts() ) {
			$query->the_post();
			$product = wc_get_product( get_the_ID() );
			if ( ! $product ) {
				continue;
			}
			echo self::render_product_card_html( $product, $layout ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		wp_reset_postdata();

		return (string) ob_get_clean();
	}

	/**
	 * Resolve a product attribute value by label aliases (same approach as single-product-light).
	 *
	 * @param WC_Product         $product Product.
	 * @param array<int, string> $aliases Label aliases.
	 */
	private static function get_product_attr_value( WC_Product $product, array $aliases ): string {
		$attr_map = array();
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
			$attr_map[ $label ] = $value;
		}

		foreach ( $aliases as $alias ) {
			if ( isset( $attr_map[ $alias ] ) && '' !== $attr_map[ $alias ] ) {
				return $attr_map[ $alias ];
			}
		}
		foreach ( $attr_map as $label => $value ) {
			foreach ( $aliases as $alias ) {
				if ( false !== mb_stripos( (string) $label, $alias ) && '' !== $value ) {
					return $value;
				}
			}
		}

		// Fall back to filter definition taxonomy if available.
		$defs = self::get_filter_definitions();
		$tax  = isset( $defs['grade']['taxonomy'] ) ? (string) $defs['grade']['taxonomy'] : '';
		if ( '' !== $tax && taxonomy_exists( $tax ) ) {
			$terms = wc_get_product_terms( $product->get_id(), $tax, array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $terms ) && array() !== $terms ) {
				return implode( ', ', $terms );
			}
		}

		return '';
	}

	/**
	 * Single product card HTML.
	 *
	 * @param WC_Product $product Product.
	 * @param string     $layout  archive|shop.
	 * @return string
	 */
	public static function render_product_card_html( WC_Product $product, string $layout = 'archive' ): string {
		$layout    = 'shop' === $layout ? 'shop' : 'archive';
		$post_id   = $product->get_id();
		$permalink = get_permalink( $post_id );
		$title     = $product->get_name();
		$price     = $product->get_price_html();
		$image_id = (int) $product->get_image_id();
		$image    = '';
		if ( $image_id > 0 ) {
			$image = wp_get_attachment_image(
				$image_id,
				'woocommerce_single',
				false,
				array(
					'loading' => 'lazy',
					'alt'     => $title,
				)
			);
			if ( ! is_string( $image ) || '' === $image ) {
				$image = wp_get_attachment_image(
					$image_id,
					'large',
					false,
					array(
						'loading' => 'lazy',
						'alt'     => $title,
					)
				);
			}
		}
		if ( ! is_string( $image ) || '' === $image ) {
			$image = $product->get_image(
				'woocommerce_thumbnail',
				array(
					'loading' => 'lazy',
					'alt'     => $title,
				)
			);
		}

		$english = '';
		$en_keys = array( 'en-name', 'en_name', 'english_name', 'نام_انگلیسی' );
		if ( function_exists( 'get_field' ) ) {
			foreach ( $en_keys as $en_key ) {
				$raw = get_field( $en_key, $post_id );
				if ( is_scalar( $raw ) && '' !== trim( (string) $raw ) ) {
					$english = trim( (string) $raw );
					break;
				}
			}
		}
		if ( '' === $english ) {
			foreach ( $en_keys as $en_key ) {
				$meta_en = get_post_meta( $post_id, $en_key, true );
				if ( is_scalar( $meta_en ) && '' !== trim( (string) $meta_en ) ) {
					$english = trim( (string) $meta_en );
					break;
				}
			}
		}

		$brand_name  = '';
		$brand_link  = '';
		$brand_image = '';
		$tags        = get_the_terms( $post_id, 'product_tag' );
		if ( $tags && ! is_wp_error( $tags ) ) {
			$tag         = $tags[0];
			$brand_name  = $tag->name;
			$brand_link  = get_term_link( $tag );
			if ( is_wp_error( $brand_link ) ) {
				$brand_link = '';
			}
			$thumb_id = 0;
			if ( class_exists( 'Hello_Elementor_Child_Custom_Tag_Archive' ) ) {
				$thumb_id = Hello_Elementor_Child_Custom_Tag_Archive::resolve_thumbnail_id( (int) $tag->term_id );
				$url      = Hello_Elementor_Child_Custom_Tag_Archive::resolve_image_url( (int) $tag->term_id, 'medium' );
				if ( '' !== $url ) {
					$brand_image = $url;
				}
			} else {
				$thumb_id = (int) get_term_meta( $tag->term_id, 'thumbnail_id', true );
				if ( $thumb_id > 0 ) {
					$url = wp_get_attachment_image_url( $thumb_id, 'medium' );
					if ( $url ) {
						$brand_image = $url;
					}
				}
			}
		}

		if ( function_exists( 'get_field' ) ) {
			foreach ( array( 'لوگو_شرکت', 'brand_logo', 'لوگو_برند', 'brand_image', 'آدرس_برند' ) as $logo_key ) {
				$logo = get_field( $logo_key, $post_id );
				$url  = '';
				if ( is_array( $logo ) && isset( $logo['url'] ) ) {
					$url = (string) $logo['url'];
				} elseif ( is_numeric( $logo ) ) {
					$maybe = wp_get_attachment_image_url( (int) $logo, 'medium' );
					$url   = $maybe ? (string) $maybe : '';
				} elseif ( is_string( $logo ) && (bool) preg_match( '#^(https?:)?//#i', $logo ) ) {
					$url = $logo;
				}
				if ( '' !== $url ) {
					$brand_image = $url;
					break;
				}
			}
		}

		if ( 'shop' === $layout ) {
			return self::render_shop_product_card_html(
				$product,
				array(
					'permalink'   => (string) $permalink,
					'title'       => $title,
					'price'       => $price,
					'image'       => $image,
					'english'     => $english,
					'brand_name'  => $brand_name,
					'brand_link'  => (string) $brand_link,
					'brand_image' => $brand_image,
				)
			);
		}

		ob_start();
		?>
		<article
			class="elementor-loop-item elementor-grid-item lk-loop-item post-<?php echo esc_attr( (string) $post_id ); ?> product type-product"
			role="listitem"
			data-elementor-type="loop-item"
			data-product-id="<?php echo esc_attr( (string) $post_id ); ?>"
		>
			<div class="lk-loop-item__inner">
				<div class="lk-loop-item__media">
					<a
						class="lk-loop-item__cart"
						href="<?php echo esc_url( (string) $permalink ); ?>"
						aria-label="<?php echo esc_attr( $title ); ?>"
					>
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">
							<path d="M18,6A6,6,0,0,0,6,6H0V21a3,3,0,0,0,3,3H14V22H3a1,1,0,0,1-1-1V8H6v2H8V8h8v2h2V8h4v6h2V6ZM8,6a4,4,0,0,1,8,0Z"></path>
							<polygon points="21 16 19 16 19 19 16 19 16 21 19 21 19 24 21 24 21 21 24 21 24 19 21 19 21 16"></polygon>
						</svg>
					</a>

					<?php if ( $brand_image ) : ?>
						<a
							class="lk-loop-item__brand-logo"
							href="<?php echo esc_url( $brand_link ? (string) $brand_link : (string) $permalink ); ?>"
							<?php echo $brand_link ? 'target="_blank" rel="nofollow"' : ''; ?>
							aria-label="<?php echo esc_attr( $brand_name ? $brand_name : __( 'برند', 'hello-elementor-child' ) ); ?>"
						>
							<img src="<?php echo esc_url( $brand_image ); ?>" alt="<?php echo esc_attr( $brand_name ); ?>" loading="lazy" width="80" height="36">
						</a>
					<?php endif; ?>

					<a class="lk-loop-item__image" href="<?php echo esc_url( (string) $permalink ); ?>" rel="nofollow">
						<?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</a>
				</div>

				<h3 class="lk-loop-item__title">
					<a href="<?php echo esc_url( (string) $permalink ); ?>" rel="nofollow"><?php echo esc_html( $title ); ?></a>
				</h3>

				<?php if ( '' !== $english ) : ?>
					<p class="lk-loop-item__english">
						<a href="<?php echo esc_url( (string) $permalink ); ?>" rel="nofollow"><?php echo esc_html( $english ); ?></a>
					</p>
				<?php endif; ?>

				<?php if ( '' !== $brand_name ) : ?>
					<p class="lk-loop-item__brand">
						<?php if ( $brand_link ) : ?>
							<a href="<?php echo esc_url( (string) $brand_link ); ?>" aria-label="<?php echo esc_attr( $brand_name ); ?>">
								<svg aria-hidden="true" class="lk-loop-item__brand-icon" viewBox="0 0 448 512" xmlns="http://www.w3.org/2000/svg" width="14" height="14"><path d="M437.2 403.5L320 215V64h8c13.3 0 24-10.7 24-24V24c0-13.3-10.7-24-24-24H120c-13.3 0-24 10.7-24 24v16c0 13.3 10.7 24 24 24h8v151L10.8 403.5C-18.5 450.6 15.3 512 70.9 512h306.2c55.7 0 89.4-61.5 60.1-108.5zM137.9 320l48.2-77.6c3.7-5.2 5.8-11.6 5.8-18.4V64h64v160c0 6.9 2.2 13.2 5.8 18.4l48.2 77.6h-172z"></path></svg>
								<span><?php echo esc_html( sprintf( /* translators: %s brand */ __( 'محصول %s', 'hello-elementor-child' ), $brand_name ) ); ?></span>
							</a>
						<?php else : ?>
							<svg aria-hidden="true" class="lk-loop-item__brand-icon" viewBox="0 0 448 512" xmlns="http://www.w3.org/2000/svg" width="14" height="14"><path d="M437.2 403.5L320 215V64h8c13.3 0 24-10.7 24-24V24c0-13.3-10.7-24-24-24H120c-13.3 0-24 10.7-24 24v16c0 13.3 10.7 24 24 24h8v151L10.8 403.5C-18.5 450.6 15.3 512 70.9 512h306.2c55.7 0 89.4-61.5 60.1-108.5zM137.9 320l48.2-77.6c3.7-5.2 5.8-11.6 5.8-18.4V64h64v160c0 6.9 2.2 13.2 5.8 18.4l48.2 77.6h-172z"></path></svg>
							<span><?php echo esc_html( sprintf( __( 'محصول %s', 'hello-elementor-child' ), $brand_name ) ); ?></span>
						<?php endif; ?>
					</p>
				<?php endif; ?>

				<?php if ( $price ) : ?>
					<div class="lk-loop-item__price-bar">
						<div class="lk-loop-item__price price"><?php echo wp_kses_post( $price ); ?></div>
					</div>
				<?php endif; ?>
			</div>
		</article>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Purchasable variation rows for the shop-card glass menu.
	 *
	 * @param WC_Product $product Product.
	 * @return array<int, array{id:int,parent_id:int,sku:string,label:string,price_html:string,url:string,attributes:array<string,string>}>
	 */
	private static function get_shop_card_variations( WC_Product $product ): array {
		if ( ! $product->is_type( 'variable' ) || ! $product instanceof WC_Product_Variable ) {
			return array();
		}

		$objects = $product->get_available_variations( 'objects' );
		if ( ! is_array( $objects ) ) {
			return array();
		}

		$out = array();
		foreach ( $objects as $variation ) {
			if ( ! $variation instanceof WC_Product_Variation ) {
				continue;
			}
			if ( ! $variation->is_purchasable() || ! $variation->is_in_stock() ) {
				continue;
			}

			$attrs = array();
			foreach ( $variation->get_variation_attributes() as $key => $value ) {
				$attrs[ (string) $key ] = is_scalar( $value ) ? (string) $value : '';
			}

			$out[] = array(
				'id'          => $variation->get_id(),
				'parent_id'   => (int) $variation->get_parent_id(),
				'sku'         => (string) $variation->get_sku(),
				'label'       => self::format_variation_option_label( $variation ),
				'price_html'  => (string) $variation->get_price_html(),
				'url'         => (string) $variation->add_to_cart_url(),
				'attributes'  => $attrs,
			);
		}

		return $out;
	}

	/**
	 * Collapse attribute keys for fuzzy taxonomy matching.
	 *
	 * @param string $value Raw taxonomy / label.
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
	 * Resolve a variation attribute key to a registered product taxonomy.
	 *
	 * @param string $key Attribute key from variation meta.
	 */
	private static function resolve_attribute_taxonomy( string $key ): string {
		$key = preg_replace( '/^attribute_/', '', $key ) ?? $key;
		if ( taxonomy_exists( $key ) ) {
			return $key;
		}
		if ( 0 !== strpos( $key, 'pa_' ) && taxonomy_exists( 'pa_' . $key ) ) {
			return 'pa_' . $key;
		}

		$needle = self::normalize_attr_key( $key );
		if ( '' === $needle || ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
			return $key;
		}

		foreach ( (array) wc_get_attribute_taxonomies() as $tax ) {
			$name  = isset( $tax->attribute_name ) ? (string) $tax->attribute_name : '';
			$label = isset( $tax->attribute_label ) ? (string) $tax->attribute_label : '';
			if (
				$needle === self::normalize_attr_key( $name )
				|| $needle === self::normalize_attr_key( 'pa_' . $name )
				|| $needle === self::normalize_attr_key( $label )
			) {
				return function_exists( 'wc_attribute_taxonomy_name' )
					? wc_attribute_taxonomy_name( $name )
					: 'pa_' . $name;
			}
		}

		return $key;
	}

	/**
	 * Public Woo attribute label (e.g. «بسته بندی»), never a pa_ slug.
	 *
	 * @param string               $taxonomy Taxonomy or attribute key.
	 * @param WC_Product_Variation $variation Variation.
	 */
	private static function get_variation_attribute_public_label( string $taxonomy, WC_Product_Variation $variation ): string {
		$taxonomy = self::resolve_attribute_taxonomy( $taxonomy );
		$label    = function_exists( 'wc_attribute_label' )
			? (string) wc_attribute_label( $taxonomy, $variation )
			: $taxonomy;

		if ( 0 === stripos( $label, 'pa_' ) ) {
			$label = substr( $label, 3 );
		}

		$label = str_replace( array( "\u{200C}", "\xE2\x80\x8C", '-', '_' ), ' ', $label );
		$label = preg_replace( '/\s+/u', ' ', $label );

		return is_string( $label ) ? trim( $label ) : '';
	}

	/**
	 * Decode a variation slug/value (handles %da%af encoded Persian).
	 *
	 * @param string $value Raw meta / slug.
	 */
	private static function decode_variation_slug( string $value ): string {
		$value = trim( $value );
		$prev  = '';
		$guard = 0;
		while ( $value !== $prev && $guard < 4 && false !== strpos( $value, '%' ) ) {
			$prev  = $value;
			$value = rawurldecode( $value );
			++$guard;
		}

		$value = str_replace( array( "\u{200C}", "\xE2\x80\x8C", '-', '_' ), ' ', $value );
		$value = preg_replace( '/\s+/u', ' ', $value );

		return is_string( $value ) ? trim( $value ) : '';
	}

	/**
	 * Human label like «بسته بندی 100 گرم».
	 *
	 * @param WC_Product_Variation $variation Variation.
	 */
	private static function format_variation_option_label( WC_Product_Variation $variation ): string {
		$parts = array();
		foreach ( $variation->get_variation_attributes() as $key => $value ) {
			$value = is_string( $value ) ? trim( $value ) : '';
			if ( '' === $value ) {
				continue;
			}

			$taxonomy   = self::resolve_attribute_taxonomy( (string) $key );
			$attr_label = self::get_variation_attribute_public_label( $taxonomy, $variation );
			$term_name  = (string) $variation->get_attribute( $taxonomy );
			if ( false !== strpos( $term_name, '%' ) ) {
				$term_name = '';
			}

			if ( '' === $term_name && taxonomy_exists( $taxonomy ) ) {
				$slug_tries = array_unique(
					array_filter(
						array(
							$value,
							rawurldecode( $value ),
							rawurldecode( rawurldecode( $value ) ),
						)
					)
				);
				foreach ( $slug_tries as $slug ) {
					$term = get_term_by( 'slug', $slug, $taxonomy );
					if ( $term instanceof WP_Term && ! is_wp_error( $term ) ) {
						$term_name = $term->name;
						break;
					}
				}
			}

			if ( '' === $term_name ) {
				$term_name = self::decode_variation_slug( $value );
			} else {
				$term_name = self::decode_variation_slug( $term_name );
			}

			$parts[] = trim( $attr_label . ' ' . $term_name );
		}

		if ( array() === $parts ) {
			$name = trim( (string) $variation->get_name() );
			return '' !== $name ? $name : (string) $variation->get_id();
		}

		return implode( ' / ', $parts );
	}

	/**
	 * Compact shop-only product card (visual style; CTA links to product for now).
	 *
	 * @param WC_Product           $product Product.
	 * @param array<string, mixed> $data    Precomputed fields.
	 * @return string
	 */
	private static function render_shop_product_card_html( WC_Product $product, array $data ): string {
		$post_id     = $product->get_id();
		$permalink   = (string) ( $data['permalink'] ?? '' );
		$title       = (string) ( $data['title'] ?? '' );
		$price       = (string) ( $data['price'] ?? '' );
		$image       = (string) ( $data['image'] ?? '' );
		$english     = trim( (string) ( $data['english'] ?? '' ) );
		$brand_name  = (string) ( $data['brand_name'] ?? '' );
		$brand_link  = (string) ( $data['brand_link'] ?? '' );
		$brand_image = (string) ( $data['brand_image'] ?? '' );

		$grade_line = $english;

		$brand_label = '' !== $brand_name
			? sprintf(
				/* translators: %s brand name */
				__( 'محصولی از %s', 'hello-elementor-child' ),
				$brand_name
			)
			: '';

		$is_variable  = $product->is_type( 'variable' );
		$variations   = $is_variable ? self::get_shop_card_variations( $product ) : array();
		$has_var_menu = $is_variable && array() !== $variations;

		ob_start();
		?>
		<article
			class="lk-loop-item lk-loop-item--shop post-<?php echo esc_attr( (string) $post_id ); ?> product type-product<?php echo $has_var_menu ? ' lk-loop-item--variable' : ''; ?>"
			role="listitem"
			data-product-id="<?php echo esc_attr( (string) $post_id ); ?>"
			data-lk-shop-card="1"
		>
			<div class="lk-loop-item__inner">
				<div class="lk-loop-item__media">
					<?php if ( $brand_image || '' !== $brand_name ) : ?>
						<a
							class="lk-loop-item__company-badge<?php echo $brand_image ? ' lk-loop-item__company-badge--logo' : ' lk-loop-item__company-badge--text'; ?>"
							href="<?php echo esc_url( $brand_link ? $brand_link : $permalink ); ?>"
							<?php echo $brand_link ? 'target="_blank" rel="nofollow"' : ''; ?>
							aria-label="<?php echo esc_attr( $brand_name ? $brand_name : __( 'برند', 'hello-elementor-child' ) ); ?>"
						>
							<?php if ( $brand_image ) : ?>
								<img src="<?php echo esc_url( $brand_image ); ?>" alt="<?php echo esc_attr( $brand_name ); ?>" loading="lazy" width="64" height="28">
							<?php else : ?>
								<span class="lk-loop-item__company-badge-label"><?php echo esc_html( $brand_name ); ?></span>
							<?php endif; ?>
						</a>
					<?php endif; ?>

					<a class="lk-loop-item__image" href="<?php echo esc_url( $permalink ); ?>" rel="nofollow">
						<?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</a>
				</div>

				<div class="lk-loop-item__body">
					<h3 class="lk-loop-item__title">
						<a href="<?php echo esc_url( $permalink ); ?>" rel="nofollow"><?php echo esc_html( $title ); ?></a>
					</h3>

					<?php if ( '' !== $grade_line ) : ?>
						<p class="lk-loop-item__grade"><?php echo esc_html( $grade_line ); ?></p>
					<?php endif; ?>

					<?php if ( '' !== $brand_label ) : ?>
						<p class="lk-loop-item__brand">
							<?php if ( $brand_link ) : ?>
								<a class="lk-loop-item__brand-link" href="<?php echo esc_url( $brand_link ); ?>">
									<span><?php echo esc_html( $brand_label ); ?></span>
									<svg aria-hidden="true" class="lk-loop-item__brand-icon" viewBox="0 0 448 512" xmlns="http://www.w3.org/2000/svg" width="12" height="12" focusable="false"><path d="M437.2 403.5L320 215V64h8c13.3 0 24-10.7 24-24V24c0-13.3-10.7-24-24-24H120c-13.3 0-24 10.7-24 24v16c0 13.3 10.7 24 24 24h8v151L10.8 403.5C-18.5 450.6 15.3 512 70.9 512h306.2c55.7 0 89.4-61.5 60.1-108.5zM137.9 320l48.2-77.6c3.7-5.2 5.8-11.6 5.8-18.4V64h64v160c0 6.9 2.2 13.2 5.8 18.4l48.2 77.6h-172z"></path></svg>
								</a>
							<?php else : ?>
								<span class="lk-loop-item__brand-link">
									<span><?php echo esc_html( $brand_label ); ?></span>
									<svg aria-hidden="true" class="lk-loop-item__brand-icon" viewBox="0 0 448 512" xmlns="http://www.w3.org/2000/svg" width="12" height="12" focusable="false"><path d="M437.2 403.5L320 215V64h8c13.3 0 24-10.7 24-24V24c0-13.3-10.7-24-24-24H120c-13.3 0-24 10.7-24 24v16c0 13.3 10.7 24 24 24h8v151L10.8 403.5C-18.5 450.6 15.3 512 70.9 512h306.2c55.7 0 89.4-61.5 60.1-108.5zM137.9 320l48.2-77.6c3.7-5.2 5.8-11.6 5.8-18.4V64h64v160c0 6.9 2.2 13.2 5.8 18.4l48.2 77.6h-172z"></path></svg>
								</span>
							<?php endif; ?>
						</p>
					<?php endif; ?>
				</div>

				<div class="lk-loop-item__footer">
					<?php
					$can_ajax_cart = ! $is_variable
						&& $product->is_purchasable()
						&& $product->is_in_stock()
						&& $product->supports( 'ajax_add_to_cart' );
					$cart_classes  = array(
						'lk-loop-item__add-to-cart',
						'product_type_' . $product->get_type(),
					);
					if ( $has_var_menu ) {
						$cart_classes[] = 'lk-loop-item__add-to-cart--options';
					}
					if ( ( $product->is_purchasable() && $product->is_in_stock() ) || $has_var_menu ) {
						$cart_classes[] = 'add_to_cart_button';
					}
					if ( $can_ajax_cart ) {
						$cart_classes[] = 'ajax_add_to_cart';
					}
					if ( $has_var_menu ) {
						$cart_label = __( 'مشاهده گزینه‌ها', 'hello-elementor-child' );
						$cart_url   = '#';
					} elseif ( $can_ajax_cart ) {
						$cart_label = __( 'افزودن به سبد', 'hello-elementor-child' );
						$cart_url   = $product->add_to_cart_url();
					} elseif ( $is_variable ) {
						$cart_label = __( 'مشاهده گزینه‌ها', 'hello-elementor-child' );
						$cart_url   = $permalink;
					} else {
						$cart_label = __( 'مشاهده محصول', 'hello-elementor-child' );
						$cart_url   = $permalink;
					}
					$options_id = 'lk-card-opts-' . $post_id;
					?>
					<a
						class="<?php echo esc_attr( implode( ' ', $cart_classes ) ); ?>"
						href="<?php echo esc_url( (string) $cart_url ); ?>"
						data-quantity="1"
						data-product_id="<?php echo esc_attr( (string) $post_id ); ?>"
						data-product_sku="<?php echo esc_attr( (string) $product->get_sku() ); ?>"
						aria-label="<?php echo esc_attr( $cart_label ); ?>"
						<?php if ( $has_var_menu ) : ?>
							aria-expanded="false"
							aria-controls="<?php echo esc_attr( $options_id ); ?>"
							data-lk-options-trigger="1"
						<?php endif; ?>
						rel="nofollow"
					>
						<span class="lk-loop-item__add-to-cart-label"><?php echo esc_html( $cart_label ); ?></span>
						<?php if ( $is_variable ) : ?>
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" focusable="false">
								<path fill="currentColor" d="M4 5h2v2H4V5zm4 0h12v2H8V5zM4 11h2v2H4v-2zm4 0h12v2H8v-2zM4 17h2v2H4v-2zm4 0h12v2H8v-2z"/>
							</svg>
						<?php else : ?>
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" focusable="false">
								<path d="M18,6A6,6,0,0,0,6,6H0V21a3,3,0,0,0,3,3H14V22H3a1,1,0,0,1-1-1V8H6v2H8V8h8v2h2V8h4v6h2V6ZM8,6a4,4,0,0,1,8,0Z"></path>
								<polygon points="21 16 19 16 19 19 16 19 16 21 19 21 19 24 21 24 21 21 24 21 24 19 21 19 21 16"></polygon>
							</svg>
						<?php endif; ?>
					</a>
					<div class="lk-loop-item__price price">
						<?php echo $price ? wp_kses_post( $price ) : '&nbsp;'; ?>
					</div>
				</div>

				<?php if ( $has_var_menu ) : ?>
					<div class="lk-loop-item__options" id="<?php echo esc_attr( $options_id ); ?>" hidden>
						<div class="lk-loop-item__options-head">
							<span><?php esc_html_e( 'مشاهده گزینه‌ها', 'hello-elementor-child' ); ?></span>
							<button type="button" class="lk-loop-item__options-close" aria-label="<?php esc_attr_e( 'بستن', 'hello-elementor-child' ); ?>">
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" focusable="false">
									<path fill="currentColor" d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
								</svg>
							</button>
						</div>
						<ul class="lk-loop-item__options-list">
							<?php foreach ( $variations as $row ) : ?>
								<li>
									<span class="lk-loop-item__options-name"><?php echo esc_html( (string) $row['label'] ); ?></span>
									<?php if ( ! empty( $row['price_html'] ) ) : ?>
										<span class="lk-loop-item__options-price"><?php echo wp_kses_post( (string) $row['price_html'] ); ?></span>
									<?php endif; ?>
									<a
										class="lk-loop-item__options-cart product_type_variation"
										href="#"
										role="button"
										data-quantity="1"
										data-product_id="<?php echo esc_attr( (string) ( $row['parent_id'] ? $row['parent_id'] : $post_id ) ); ?>"
										data-variation_id="<?php echo esc_attr( (string) $row['id'] ); ?>"
										data-product_sku="<?php echo esc_attr( (string) $row['sku'] ); ?>"
										data-variation="<?php echo esc_attr( (string) ( wp_json_encode( $row['attributes'] ) ?: '{}' ) ); ?>"
										aria-label="<?php echo esc_attr( sprintf( /* translators: %s variation label */ __( 'افزودن %s به سبد', 'hello-elementor-child' ), (string) $row['label'] ) ); ?>"
										rel="nofollow"
									>
										<svg class="lk-loop-item__options-cart-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" focusable="false">
											<path d="M18,6A6,6,0,0,0,6,6H0V21a3,3,0,0,0,3,3H14V22H3a1,1,0,0,1-1-1V8H6v2H8V8h8v2h2V8h4v6h2V6ZM8,6a4,4,0,0,1,8,0Z"></path>
											<polygon points="21 16 19 16 19 19 16 19 16 21 19 21 19 24 21 24 21 21 24 21 24 19 21 19 21 16"></polygon>
										</svg>
										<svg class="lk-loop-item__options-cart-spinner" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" focusable="false">
											<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-dasharray="40 20"/>
										</svg>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
			</div>
		</article>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Pagination markup for light archive / AJAX updates.
	 *
	 * @param WP_Query $query    Query.
	 * @param int      $term_id  Scope term ID.
	 * @param int      $current  Current page (AJAX).
	 * @param string   $taxonomy Scope taxonomy.
	 * @param string   $search   Product search query.
	 * @return string
	 */
	public static function render_pagination_html( WP_Query $query, int $term_id = 0, int $current = 0, string $taxonomy = '', string $search = '' ): string {
		$total = (int) $query->max_num_pages;
		if ( $total <= 1 ) {
			return '';
		}

		if ( $current < 1 ) {
			$current = max( 1, (int) $query->get( 'paged' ), (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
		}

		$search = trim( $search );
		$base   = self::get_pagination_base( $term_id, $taxonomy, $search );

		$links = paginate_links(
			array(
				'base'      => $base,
				'format'    => '',
				'current'   => $current,
				'total'     => $total,
				'type'      => 'list',
				'mid_size'  => 2,
				'end_size'  => 1,
				'prev_text' => '&rarr;',
				'next_text' => '&larr;',
			)
		);

		return is_string( $links ) ? $links : '';
	}

	/**
	 * Pagination base that works for pretty permalinks and ?page_id= shop URLs.
	 *
	 * @param int    $term_id  Scope term ID.
	 * @param string $taxonomy Scope taxonomy.
	 * @param string $search   Product search query.
	 */
	private static function get_pagination_base( int $term_id, string $taxonomy, string $search ): string {
		if ( '' !== $search ) {
			return add_query_arg(
				array(
					's'         => $search,
					'post_type' => 'product',
					'paged'     => '%#%',
				),
				home_url( '/' )
			);
		}

		$url = '';
		if ( $term_id > 0 ) {
			if ( ! in_array( $taxonomy, array( 'product_cat', 'product_tag' ), true ) ) {
				$taxonomy = 'product_cat';
			}
			$term_link = get_term_link( $term_id, $taxonomy );
			if ( ! is_wp_error( $term_link ) && is_string( $term_link ) ) {
				$url = $term_link;
			}
		} elseif ( function_exists( 'wc_get_page_id' ) ) {
			$shop_id = (int) wc_get_page_id( 'shop' );
			if ( $shop_id > 0 ) {
				$permalink = get_permalink( $shop_id );
				if ( is_string( $permalink ) && '' !== $permalink ) {
					$url = $permalink;
				}
			}
			if ( '' === $url && function_exists( 'wc_get_page_permalink' ) ) {
				$shop_url = wc_get_page_permalink( 'shop' );
				if ( is_string( $shop_url ) && '' !== $shop_url ) {
					$url = $shop_url;
				}
			}
		}

		if ( '' === $url ) {
			$url = home_url( '/' );
		}

		return self::pagination_base_from_url( $url );
	}

	/**
	 * @param string $url Archive / shop URL.
	 */
	private static function pagination_base_from_url( string $url ): string {
		$url = strtok( $url, '#' );
		if ( ! is_string( $url ) || '' === $url ) {
			return add_query_arg( 'paged', '%#%', home_url( '/' ) );
		}
		if ( false !== strpos( $url, '?' ) ) {
			return add_query_arg( 'paged', '%#%', $url );
		}
		return trailingslashit( $url ) . 'page/%#%/';
	}

	/**
	 * @deprecated Use render_products_grid_html().
	 *
	 * @param WP_Query $query Query.
	 * @return string
	 */
	private static function render_loop_items_html( WP_Query $query ): string {
		return self::render_products_grid_html( $query );
	}
}
