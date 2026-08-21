<?php
/**
 * Lookazma Search — Elementor widget + AJAX autocomplete.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the Elementor widget and AJAX search endpoint.
 */
final class Hello_Elementor_Child_Lookazma_Search {

	public const ACTION = 'lk_lookazma_search';

	public const MIN_CHARS = 3;

	public const LIMIT_CATS = 3;

	public const LIMIT_PRODUCTS = 4;

	public const LIMIT_TAGS = 4;

	public const PLACEHOLDER = 'از طریق نام محصول یا Cas No یا دسته بندی محصول مورد نظر خود را جستجو کنید';

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widget' ) );
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'register_category' ) );

		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'ajax_search' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( __CLASS__, 'ajax_search' ) );

		add_action( 'init', array( __CLASS__, 'register_assets' ), 20 );
	}

	/**
	 * Register Elementor category.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Manager.
	 */
	public static function register_category( $elements_manager ): void {
		if ( ! is_object( $elements_manager ) || ! method_exists( $elements_manager, 'add_category' ) ) {
			return;
		}
		$elements_manager->add_category(
			'lookazma',
			array(
				'title' => 'Lookazma',
				'icon'  => 'fa fa-plug',
			)
		);
	}

	/**
	 * Register the widget.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Manager.
	 */
	public static function register_widget( $widgets_manager ): void {
		$widget_file = HELLO_ELEMENTOR_CHILD_PATH . 'includes/elementor/widgets/class-lookazma-search-widget.php';
		if ( ! file_exists( $widget_file ) ) {
			return;
		}
		require_once $widget_file;
		if ( class_exists( 'Hello_Elementor_Child_Lookazma_Search_Widget' ) ) {
			$widgets_manager->register( new Hello_Elementor_Child_Lookazma_Search_Widget() );
		}
	}

	/**
	 * Register (not always enqueue) front-end assets.
	 */
	public static function register_assets(): void {
		$css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/lookazma-search.css';
		$js  = HELLO_ELEMENTOR_CHILD_PATH . 'assets/js/lookazma-search.js';

		if ( file_exists( $css ) ) {
			wp_register_style(
				'lk-lookazma-search',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/css/lookazma-search.css',
				array( HELLO_ELEMENTOR_CHILD_VAZIRMATN_HANDLE ),
				(string) filemtime( $css )
			);
		}

		if ( file_exists( $js ) ) {
			wp_register_script(
				'lk-lookazma-search',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/js/lookazma-search.js',
				array(),
				(string) filemtime( $js ),
				true
			);

			wp_localize_script(
				'lk-lookazma-search',
				'lkLookazmaSearch',
				array(
					'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
					'action'   => self::ACTION,
					'nonce'    => wp_create_nonce( 'lk_lookazma_search' ),
					'minChars' => self::MIN_CHARS,
					'i18n'     => array(
						'categories' => __( 'دسته بندی', 'hello-elementor-child' ),
						'products'   => __( 'محصولات', 'hello-elementor-child' ),
						'tags'       => __( 'شرکت ها', 'hello-elementor-child' ),
						'more'       => __( 'مشاهده سایر محصولات', 'hello-elementor-child' ),
						'empty'      => __( 'نتیجه‌ای یافت نشد.', 'hello-elementor-child' ),
						'error'      => __( 'خطا در جستجو.', 'hello-elementor-child' ),
					),
				)
			);
		}
	}

	/**
	 * Enqueue assets when the widget renders.
	 */
	public static function enqueue_assets(): void {
		wp_enqueue_style( 'lk-lookazma-search' );
		wp_enqueue_script( 'lk-lookazma-search' );
	}

	/**
	 * Markup for native (non-Elementor) templates.
	 *
	 * @param string $placeholder Input placeholder.
	 * @param string $input_id    Input element ID.
	 */
	public static function render_markup( string $placeholder = '', string $input_id = 'lk-lookazma-search-native' ): string {
		self::enqueue_assets();

		$placeholder = self::PLACEHOLDER;

		$action    = home_url( '/' );
		$dropdown  = $input_id . '-dropdown';
		$search_q  = get_search_query();

		ob_start();
		?>
		<div class="lookazma_pas" data-lk-lookazma-search>
			<form class="lookazma_pas__form" role="search" method="get" action="<?php echo esc_url( $action ); ?>">
				<label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>">
					<?php esc_html_e( 'جستجوی محصول', 'hello-elementor-child' ); ?>
				</label>
				<div class="lookazma_pas__field">
					<span class="lookazma_pas__icon" aria-hidden="true">
						<svg class="lookazma_pas__icon-search" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" focusable="false">
							<path fill="currentColor" d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
						</svg>
						<svg class="lookazma_pas__icon-loading" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" focusable="false">
							<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-dasharray="40 60"/>
						</svg>
					</span>
					<input
						type="search"
						id="<?php echo esc_attr( $input_id ); ?>"
						class="lookazma_pas__input"
						name="s"
						value="<?php echo esc_attr( $search_q ); ?>"
						placeholder="<?php echo esc_attr( $placeholder ); ?>"
						autocomplete="off"
						aria-autocomplete="list"
						aria-expanded="false"
						aria-controls="<?php echo esc_attr( $dropdown ); ?>"
					/>
				</div>
				<input type="hidden" name="post_type" value="product" />
			</form>
			<div
				class="lookazma_pas__dropdown"
				id="<?php echo esc_attr( $dropdown ); ?>"
				hidden
				role="listbox"
			>
				<div class="lookazma_pas__scroll" data-lk-scroll></div>
				<a class="lookazma_pas__more" data-lk-more href="#" hidden>
					<?php esc_html_e( 'مشاهده سایر محصولات', 'hello-elementor-child' ); ?>
				</a>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * AJAX: autocomplete results.
	 */
	public static function ajax_search(): void {
		check_ajax_referer( 'lk_lookazma_search', 'nonce' );

		$q = isset( $_REQUEST['q'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['q'] ) ) : '';
		$q = trim( $q );

		if ( mb_strlen( $q ) < self::MIN_CHARS ) {
			wp_send_json_success(
				array(
					'categories' => array(),
					'products'   => array(),
					'tags'       => array(),
					'more_url'   => self::more_url( $q ),
				)
			);
		}

		wp_send_json_success(
			array(
				'categories' => self::search_terms( $q, 'product_cat', self::LIMIT_CATS ),
				'products'   => self::search_products( $q, self::LIMIT_PRODUCTS ),
				'tags'       => self::search_terms( $q, 'product_tag', self::LIMIT_TAGS ),
				'more_url'   => self::more_url( $q ),
			)
		);
	}

	/**
	 * Full search results URL.
	 *
	 * @param string $q Query.
	 */
	public static function more_url( string $q ): string {
		return add_query_arg(
			array(
				's'         => $q,
				'post_type' => 'product',
			),
			home_url( '/' )
		);
	}

	/**
	 * Search product_cat or product_tag by name.
	 *
	 * @param string $q        Query.
	 * @param string $taxonomy Taxonomy.
	 * @param int    $limit    Max results.
	 * @return array<int, array{name:string,url:string,image:string}>
	 */
	private static function search_terms( string $q, string $taxonomy, int $limit ): array {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'number'     => $limit,
				'name__like' => $q,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		$out = array();
		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}
			$link = get_term_link( $term );
			if ( is_wp_error( $link ) ) {
				continue;
			}
			$out[] = array(
				'name'  => $term->name,
				'url'   => $link,
				'image' => self::term_thumbnail_url( (int) $term->term_id ),
			);
		}

		return $out;
	}

	/**
	 * WooCommerce / WP / ACF term thumbnail URL.
	 *
	 * @param int $term_id Term ID.
	 */
	private static function term_thumbnail_url( int $term_id ): string {
		if ( $term_id <= 0 ) {
			return '';
		}

		if ( class_exists( 'Hello_Elementor_Child_Custom_Tag_Archive' ) ) {
			$url = Hello_Elementor_Child_Custom_Tag_Archive::resolve_image_url( $term_id, 'thumbnail' );
			if ( '' !== $url ) {
				return $url;
			}
		}

		$thumb_id = (int) get_term_meta( $term_id, 'thumbnail_id', true );
		if ( $thumb_id <= 0 ) {
			return '';
		}

		$url = wp_get_attachment_image_url( $thumb_id, 'thumbnail' );
		return is_string( $url ) ? $url : '';
	}

	/**
	 * Search products by title, SKU, or ACF cas_no.
	 *
	 * @param string $q     Query.
	 * @param int    $limit Max results.
	 * @return array<int, array{name:string,url:string,meta:string,image:string}>
	 */
	private static function search_products( string $q, int $limit ): array {
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
			$limit
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$ids = $wpdb->get_col( $sql );
		if ( ! is_array( $ids ) || array() === $ids ) {
			return array();
		}

		$placeholder = '';
		if ( function_exists( 'wc_placeholder_img_src' ) ) {
			$placeholder = (string) wc_placeholder_img_src( 'large' );
		}

		$out = array();
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( $id <= 0 ) {
				continue;
			}
			$product = wc_get_product( $id );
			if ( ! $product ) {
				continue;
			}

			$cas = '';
			if ( function_exists( 'get_field' ) ) {
				$raw = get_field( 'cas_no', $id );
				if ( is_scalar( $raw ) ) {
					$cas = trim( (string) $raw );
				}
			}
			if ( '' === $cas ) {
				$meta = get_post_meta( $id, 'cas_no', true );
				if ( is_scalar( $meta ) ) {
					$cas = trim( (string) $meta );
				}
			}

			$sku  = (string) $product->get_sku();
			$meta = '' !== $cas ? $cas : $sku;

			// Main product image — same size as single-product gallery (`large`).
			$image  = '';
			$img_id = (int) $product->get_image_id();
			if ( $img_id <= 0 ) {
				$img_id = (int) get_post_thumbnail_id( $id );
			}
			if ( $img_id > 0 ) {
				$url = wp_get_attachment_image_url( $img_id, 'large' );
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
				'name'  => $product->get_name(),
				'url'   => get_permalink( $id ),
				'meta'  => $meta,
				'image' => $image,
			);
		}

		return $out;
	}
}
