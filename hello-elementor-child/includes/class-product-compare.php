<?php
/**
 * Product comparison — localStorage-driven, AJAX data.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compare list validation and AJAX.
 */
final class Hello_Elementor_Child_Product_Compare {

	public const ACTION = 'lk_compare_products';

	public const SEARCH_ACTION = 'lk_compare_search';

	public const SEARCH_LIMIT = 16;

	public const MAX_ITEMS = 4;

	public const MIN_ITEMS = 2;

	public const STORAGE_KEY = 'lk_compare_ids';

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'ajax_products' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( __CLASS__, 'ajax_products' ) );
		add_action( 'wp_ajax_' . self::SEARCH_ACTION, array( __CLASS__, 'ajax_search' ) );
		add_action( 'wp_ajax_nopriv_' . self::SEARCH_ACTION, array( __CLASS__, 'ajax_search' ) );
		add_action( 'init', array( __CLASS__, 'register_assets' ), 20 );
	}

	/**
	 * Register front-end assets (enqueue separately).
	 */
	public static function register_assets(): void {
		$js = HELLO_ELEMENTOR_CHILD_PATH . 'assets/js/lk-product-compare.js';
		if ( ! file_exists( $js ) ) {
			return;
		}

		wp_register_script(
			'lk-product-compare',
			HELLO_ELEMENTOR_CHILD_URI . 'assets/js/lk-product-compare.js',
			array(),
			(string) filemtime( $js ),
			true
		);
	}

	/**
	 * Enqueue compare script with config.
	 *
	 * @param bool $is_compare_page Whether the compare page is active.
	 */
	public static function enqueue_assets( bool $is_compare_page = false ): void {
		self::register_assets();

		if ( ! wp_style_is( 'lk-light-product', 'enqueued' ) ) {
			wp_enqueue_style(
				'lk-light-product',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-light-product.css',
				array( HELLO_ELEMENTOR_CHILD_VAZIRMATN_HANDLE ),
				HELLO_ELEMENTOR_CHILD_VERSION
			);
		}

		$css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/light-compare.css';
		if ( file_exists( $css ) ) {
			wp_enqueue_style(
				'lk-light-compare',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/css/light-compare.css',
				array( 'lk-light-product' ),
				(string) filemtime( $css )
			);
		}

		wp_enqueue_script( 'lk-product-compare' );

		wp_localize_script(
			'lk-product-compare',
			'lkProductCompare',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'action'      => self::ACTION,
				'nonce'       => wp_create_nonce( 'lk_compare_products' ),
				'storageKey'  => self::STORAGE_KEY,
				'pendingKey'  => 'lk_compare_pending_id',
				'maxItems'    => self::MAX_ITEMS,
				'minItems'    => self::MIN_ITEMS,
				'compareUrl'  => class_exists( 'Hello_Elementor_Child_Light_Compare_Template' )
					? Hello_Elementor_Child_Light_Compare_Template::get_page_url()
					: '',
				'shopUrl'     => function_exists( 'hello_elementor_child_get_shop_url' )
					? hello_elementor_child_get_shop_url()
					: home_url( '/فروشگاه/' ),
				'isComparePage' => $is_compare_page,
				'searchAction'  => self::SEARCH_ACTION,
				'searchLimit'   => self::SEARCH_LIMIT,
				'searchMinChars' => 0,
				'i18n'        => array(
					'button'           => __( 'مقایسه محصول', 'hello-elementor-child' ),
					'inCompare'        => __( 'در مقایسه', 'hello-elementor-child' ),
					'added'            => __( 'به مقایسه اضافه شد.', 'hello-elementor-child' ),
					'removed'          => __( 'از مقایسه حذف شد.', 'hello-elementor-child' ),
					'full'             => __( 'حداکثر ۴ محصول قابل مقایسه است.', 'hello-elementor-child' ),
					'slotsFullRemove'  => __( 'همه جایگاه‌های مقایسه پر است. یک محصول را حذف کنید تا محصول جدید جایگزین شود.', 'hello-elementor-child' ),
					'pickVariation'    => __( 'ابتدا بسته‌بندی را انتخاب کنید.', 'hello-elementor-child' ),
					'emptyTitle'       => __( 'محصولی برای مقایسه انتخاب نشده', 'hello-elementor-child' ),
					'emptyHint'        => __( 'محصول اول را از لیست جستجو انتخاب کنید.', 'hello-elementor-child' ),
					'addProduct'       => __( 'افزودن کالا', 'hello-elementor-child' ),
					'addAnother'       => __( 'افزودن محصول دیگر', 'hello-elementor-child' ),
					'emptyNoProduct'   => __( 'کالایی اضافه نکرده‌اید', 'hello-elementor-child' ),
					'emptyMaxHint'     => __( 'می‌توانید ۴ کالا را باهم مقایسه کنید', 'hello-elementor-child' ),
					'close'            => __( 'بستن', 'hello-elementor-child' ),
					'searchPlaceholder' => __( 'جستجوی محصول…', 'hello-elementor-child' ),
					'searchProducts'   => __( 'محصولات', 'hello-elementor-child' ),
					'searchEmpty'      => __( 'نتیجه‌ای یافت نشد.', 'hello-elementor-child' ),
					'searchError'      => __( 'خطا در جستجو.', 'hello-elementor-child' ),
					'alreadyAdded'     => __( 'این محصول قبلاً اضافه شده.', 'hello-elementor-child' ),
					'pickFirst'        => __( 'از لیست کنار انتخاب کنید.', 'hello-elementor-child' ),
					'needMore'         => __( 'حداقل ۲ محصول برای مقایسه لازم است.', 'hello-elementor-child' ),
					'remove'           => __( 'حذف', 'hello-elementor-child' ),
					'viewProduct'      => __( 'مشاهده و خرید', 'hello-elementor-child' ),
					'specLabel'        => __( 'مشخصه', 'hello-elementor-child' ),
					'emptyCell'        => '—',
					'error'            => __( 'خطا در بارگذاری مقایسه.', 'hello-elementor-child' ),
					'loading'          => __( 'در حال بارگذاری…', 'hello-elementor-child' ),
				),
			)
		);
	}

	/**
	 * Sanitize and validate product / variation IDs.
	 *
	 * @param array<int, mixed> $raw_ids Raw IDs.
	 * @return array<int, int>
	 */
	public static function sanitize_ids( array $raw_ids ): array {
		$out = array();
		foreach ( $raw_ids as $raw ) {
			$id = (int) $raw;
			if ( $id <= 0 ) {
				continue;
			}
			$compare_id = self::resolve_compare_product_id( $id );
			if ( $compare_id <= 0 || in_array( $compare_id, $out, true ) ) {
				continue;
			}
			$out[] = $compare_id;
			if ( count( $out ) >= self::MAX_ITEMS ) {
				break;
			}
		}

		return $out;
	}

	/**
	 * Resolve a product/variation ID to a compare slot ID (variable parent for variations).
	 *
	 * @param int $id Product or variation ID.
	 */
	public static function resolve_compare_product_id( int $id ): int {
		if ( $id <= 0 ) {
			return 0;
		}

		$product = wc_get_product( $id );
		if ( ! $product || 'publish' !== $product->get_status() ) {
			return 0;
		}

		if ( $product->is_type( 'variation' ) ) {
			$parent_id = (int) $product->get_parent_id();
			return $parent_id > 0 ? $parent_id : 0;
		}

		if ( $product->is_type( 'simple' ) || $product->is_type( 'variable' ) ) {
			return (int) $product->get_id();
		}

		return 0;
	}

	/**
	 * AJAX: comparison matrix for IDs.
	 */
	public static function ajax_products(): void {
		check_ajax_referer( 'lk_compare_products', 'nonce' );

		if ( ! class_exists( 'Hello_Elementor_Child_Light_Product_Specs' ) ) {
			wp_send_json_error( array( 'message' => 'specs_unavailable' ), 500 );
		}

		$slots_raw = isset( $_POST['slots'] ) ? wp_unslash( $_POST['slots'] ) : '';
		if ( is_string( $slots_raw ) && '' !== trim( $slots_raw ) ) {
			$decoded = json_decode( $slots_raw, true );
			if ( is_array( $decoded ) ) {
				$slots  = self::sanitize_slots( $decoded );
				$matrix = Hello_Elementor_Child_Light_Product_Specs::build_matrix_slots( $slots );
				$filled = count( array_filter( $slots ) );

				wp_send_json_success(
					array(
						'products'   => $matrix['products'],
						'rows'       => $matrix['rows'],
						'slots'      => $slots,
						'count'      => $filled,
						'canCompare' => $filled >= self::MIN_ITEMS,
					)
				);
			}
		}

		$raw = isset( $_POST['ids'] ) ? wp_unslash( $_POST['ids'] ) : '';
		$ids = array();
		if ( is_array( $raw ) ) {
			$ids = self::sanitize_ids( $raw );
		} elseif ( is_string( $raw ) && '' !== trim( $raw ) ) {
			$parts = array_map( 'trim', explode( ',', $raw ) );
			$ids   = self::sanitize_ids( $parts );
		}

		$matrix = Hello_Elementor_Child_Light_Product_Specs::build_matrix( $ids );

		wp_send_json_success(
			array(
				'products'   => $matrix['products'],
				'rows'       => $matrix['rows'],
				'count'      => count( $matrix['products'] ),
				'canCompare' => count( $matrix['products'] ) >= self::MIN_ITEMS,
			)
		);
	}

	/**
	 * Sanitize fixed slot array (length MAX_ITEMS).
	 *
	 * @param array<int, mixed> $raw_slots Raw slot values.
	 * @return array<int, int>
	 */
	public static function sanitize_slots( array $raw_slots ): array {
		$slots = array_fill( 0, self::MAX_ITEMS, 0 );
		$slice = array_slice( array_values( $raw_slots ), 0, self::MAX_ITEMS );

		foreach ( $slice as $index => $raw ) {
			$id = (int) $raw;
			if ( $id <= 0 ) {
				continue;
			}
			$compare_id = self::resolve_compare_product_id( $id );
			if ( $compare_id <= 0 ) {
				continue;
			}
			if ( in_array( $compare_id, $slots, true ) ) {
				continue;
			}
			$slots[ (int) $index ] = $compare_id;
		}

		return $slots;
	}

	/**
	 * AJAX: product picker list for compare page.
	 */
	public static function ajax_search(): void {
		check_ajax_referer( 'lk_compare_products', 'nonce' );

		$q = isset( $_REQUEST['q'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['q'] ) ) : '';
		$q = trim( $q );

		wp_send_json_success(
			array(
				'products' => self::search_pickable_products( $q, self::SEARCH_LIMIT ),
			)
		);
	}

	/**
	 * Products available in compare picker (simple / non-variable only).
	 *
	 * @param string $q     Search query; empty returns recent products.
	 * @param int    $limit Max rows.
	 * @return array<int, array{id:int,name:string,url:string,meta:string,image:string}>
	 */
	private static function search_pickable_products( string $q, int $limit ): array {
		$limit = max( 1, min( 30, $limit ) );
		$ids   = array();

		if ( '' === $q ) {
			$query = new WP_Query(
				array(
					'post_type'              => 'product',
					'post_status'            => 'publish',
					'posts_per_page'         => $limit * 3,
					'orderby'                => 'date',
					'order'                  => 'DESC',
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);
			$ids = is_array( $query->posts ) ? $query->posts : array();
		} else {
			global $wpdb;

			$like        = '%' . $wpdb->esc_like( $q ) . '%';
			$prefix_like = $wpdb->esc_like( $q ) . '%';

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names from $wpdb.
			$sql = $wpdb->prepare(
				"SELECT DISTINCT p.ID
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} sku
					ON ( p.ID = sku.post_id AND sku.meta_key = '_sku' )
				LEFT JOIN {$wpdb->postmeta} cas
					ON ( p.ID = cas.post_id AND cas.meta_key = 'cas_no' )
				WHERE p.post_type = 'product'
					AND p.post_status = 'publish'
					AND (
						p.post_title LIKE %s
						OR sku.meta_value LIKE %s
						OR cas.meta_value LIKE %s
					)
				ORDER BY
					CASE
						WHEN p.post_title LIKE %s THEN 0
						WHEN sku.meta_value LIKE %s THEN 1
						WHEN cas.meta_value LIKE %s THEN 2
						ELSE 3
					END,
					p.post_title ASC
				LIMIT %d",
				$like,
				$like,
				$like,
				$prefix_like,
				$prefix_like,
				$prefix_like,
				$limit * 3
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$ids = $wpdb->get_col( $sql );
			if ( ! is_array( $ids ) ) {
				$ids = array();
			}
		}

		$placeholder = '';
		if ( function_exists( 'wc_placeholder_img_src' ) ) {
			$placeholder = (string) wc_placeholder_img_src( 'thumbnail' );
		}

		$out = array();
		foreach ( $ids as $raw_id ) {
			if ( count( $out ) >= $limit ) {
				break;
			}

			$id = (int) $raw_id;
			if ( $id <= 0 ) {
				continue;
			}

			$product = wc_get_product( $id );
			if ( ! $product || 'publish' !== $product->get_status() ) {
				continue;
			}
			if ( ! $product->is_type( 'simple' ) && ! $product->is_type( 'variable' ) ) {
				continue;
			}

			$picker_id = (int) $product->get_id();

			$cas = '';
			if ( function_exists( 'get_field' ) ) {
				$raw_cas = get_field( 'cas_no', $picker_id );
				if ( is_scalar( $raw_cas ) ) {
					$cas = trim( (string) $raw_cas );
				}
			}
			if ( '' === $cas ) {
				$meta = get_post_meta( $picker_id, 'cas_no', true );
				if ( is_scalar( $meta ) ) {
					$cas = trim( (string) $meta );
				}
			}

			$sku  = (string) $product->get_sku();
			$meta = '' !== $cas ? $cas : $sku;

			$image  = '';
			$img_id = (int) $product->get_image_id();
			if ( $img_id <= 0 ) {
				$img_id = (int) get_post_thumbnail_id( $id );
			}
			if ( $img_id > 0 ) {
				$url = wp_get_attachment_image_url( $img_id, 'thumbnail' );
				if ( ! is_string( $url ) || '' === $url ) {
					$url = wp_get_attachment_url( $img_id );
				}
				if ( is_string( $url ) && '' !== $url ) {
					$image = $url;
				}
			}
			if ( '' === $image ) {
				$image = $placeholder;
			}

			$out[] = array(
				'id'    => $picker_id,
				'name'  => $product->get_name(),
				'url'   => get_permalink( $picker_id ),
				'meta'  => $meta,
				'image' => $image,
			);
		}

		return $out;
	}
}
