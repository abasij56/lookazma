<?php
/**
 * WooCommerce product exporter — filter by brand (product_tag).
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds a brand multi-select to Products → Export and scopes the CSV query.
 */
final class Hello_Elementor_Child_Product_Export_Brand_Filter {

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'woocommerce_product_export_row', array( __CLASS__, 'render_brand_row' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'woocommerce_product_export_product_query_args', array( __CLASS__, 'filter_query_args' ) );
	}

	/**
	 * Whether the current admin screen is the product exporter.
	 */
	private static function is_exporter_screen(): bool {
		if ( ! is_admin() ) {
			return false;
		}
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return 'product_exporter' === $page;
	}

	/**
	 * Brand terms used as Lookazma product brands (product_tag).
	 *
	 * @return array<int, WP_Term>
	 */
	private static function get_brand_terms(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_tag',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		if ( ! is_array( $terms ) || is_wp_error( $terms ) ) {
			return array();
		}
		return $terms;
	}

	/**
	 * Exporter form row — multi-select like columns/category.
	 */
	public static function render_brand_row(): void {
		$terms = self::get_brand_terms();
		?>
		<tr>
			<th scope="row">
				<label for="woocommerce-exporter-brand"><?php esc_html_e( 'کدام برند ها باید صادر شود؟', 'hello-elementor-child' ); ?></label>
			</th>
			<td>
				<select
					id="woocommerce-exporter-brand"
					class="woocommerce-exporter-brand wc-enhanced-select"
					style="width:100%;"
					multiple
					data-placeholder="<?php esc_attr_e( 'برون‌بری همه برندها', 'hello-elementor-child' ); ?>"
				>
					<?php foreach ( $terms as $term ) : ?>
						<option value="<?php echo esc_attr( (string) (int) $term->term_id ); ?>">
							<?php echo esc_html( $term->name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<?php
	}

	/**
	 * Inject selected brands into the exporter AJAX payload.
	 */
	public static function enqueue_assets( string $hook = '' ): void {
		unset( $hook );
		if ( ! self::is_exporter_screen() ) {
			return;
		}

		$js = HELLO_ELEMENTOR_CHILD_PATH . 'assets/js/admin-product-export-brand.js';
		if ( ! file_exists( $js ) ) {
			return;
		}

		$deps = array( 'jquery' );
		if ( wp_script_is( 'wc-product-export', 'registered' )
			|| wp_script_is( 'wc-product-export', 'enqueued' )
		) {
			$deps[] = 'wc-product-export';
		}

		wp_enqueue_script(
			'lk-product-export-brand',
			HELLO_ELEMENTOR_CHILD_URI . 'assets/js/admin-product-export-brand.js',
			$deps,
			(string) filemtime( $js ),
			true
		);
	}

	/**
	 * Scope export to selected product_tag (brand) IDs.
	 *
	 * @param array<string, mixed> $args wc_get_products args.
	 * @return array<string, mixed>
	 */
	public static function filter_query_args( array $args ): array {
		$raw = isset( $_POST['export_brand'] ) ? wp_unslash( $_POST['export_brand'] ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( null === $raw || '' === $raw ) {
			return $args;
		}

		if ( ! is_array( $raw ) ) {
			$raw = array( $raw );
		}

		$ids = array_values(
			array_filter(
				array_map( 'absint', $raw )
			)
		);
		if ( array() === $ids ) {
			return $args;
		}

		// Resolve product IDs with these brand tags so the exporter also pulls
		// variations of matched variable products (same path as category export).
		$product_ids = get_posts(
			array(
				'post_type'              => 'product',
				'post_status'            => array( 'private', 'publish', 'draft', 'future', 'pending' ),
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'tax_query'              => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => 'product_tag',
						'field'    => 'term_id',
						'terms'    => $ids,
						'operator' => 'IN',
					),
				),
			)
		);

		if ( ! is_array( $product_ids ) || array() === $product_ids ) {
			$args['include'] = array( 0 );
			return $args;
		}

		$args['include'] = array_map( 'absint', $product_ids );
		return $args;
	}
}
