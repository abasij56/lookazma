<?php
/**
 * Attribute Price Editor view renderer.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the tag filter form and product price table.
 */
final class Hello_Elementor_Child_Attribute_Price_Editor_Renderer {

	/**
	 * WooCommerce attribute taxonomy for packaging.
	 */
	public const ATTRIBUTE_TAXONOMY = 'pa_بسته‌ بندی';

	/**
	 * Output the full editor page.
	 */
	public static function render_page(): void {
		$tags = get_terms(
			array(
				'taxonomy'   => 'product_tag',
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $tags ) ) {
			$tags = array();
		}

		$selected_tag = isset( $_POST['attr_tag'] ) ? absint( wp_unslash( $_POST['attr_tag'] ) ) : 0;

		self::render_tag_form( $tags, $selected_tag );

		if ( $selected_tag > 0 ) {
			self::render_products_table( $selected_tag );
		}
	}

	/**
	 * Render tag selection form.
	 *
	 * @param WP_Term[] $tags          Product tags.
	 * @param int       $selected_tag  Currently selected tag ID.
	 */
	private static function render_tag_form( array $tags, int $selected_tag ): void {
		?>
		<h2><?php esc_html_e( 'ویرایش قیمت محصولات بر اساس برچسب', 'hello-elementor-child' ); ?></h2>
		<form method="post">
			<label for="tag-select"><?php esc_html_e( 'انتخاب برچسب:', 'hello-elementor-child' ); ?></label>
			<select id="tag-select" name="attr_tag">
				<option value=""><?php esc_html_e( '-- همه برچسب‌ها --', 'hello-elementor-child' ); ?></option>
				<?php foreach ( $tags as $tag ) : ?>
					<option value="<?php echo esc_attr( (string) $tag->term_id ); ?>" <?php selected( $selected_tag, $tag->term_id ); ?>>
						<?php echo esc_html( $tag->name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<button type="submit"><?php esc_html_e( 'نمایش محصولات', 'hello-elementor-child' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Query products by tag and render the editable price table.
	 *
	 * @param int $tag_id Product tag term ID.
	 */
	private static function render_products_table( int $tag_id ): void {
		$query = new WP_Query(
			array(
				'post_type'      => 'product',
				'posts_per_page' => -1,
				'tax_query'      => array(
					array(
						'taxonomy' => 'product_tag',
						'field'    => 'term_id',
						'terms'    => $tag_id,
					),
				),
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		if ( ! $query->have_posts() ) {
			echo '<p>' . esc_html__( 'هیچ محصولی با این برچسب پیدا نشد.', 'hello-elementor-child' ) . '</p>';
			wp_reset_postdata();
			return;
		}
		?>
		<hr>
		<label for="price-table-search"><?php esc_html_e( 'جستجو بر اساس نام محصول:', 'hello-elementor-child' ); ?></label>
		<input type="text" id="price-table-search" placeholder="<?php esc_attr_e( 'نام محصول...', 'hello-elementor-child' ); ?>" style="margin-bottom:10px;width:100%;">

		<table class="widefat" id="price-table">
			<thead>
				<tr>
					<th>#</th>
					<th><?php esc_html_e( 'تصویر', 'hello-elementor-child' ); ?></th>
					<th><?php esc_html_e( 'نام محصول', 'hello-elementor-child' ); ?></th>
					<th><?php esc_html_e( 'ویژگی', 'hello-elementor-child' ); ?></th>
					<th><?php esc_html_e( 'قیمت', 'hello-elementor-child' ); ?></th>
					<th><?php esc_html_e( 'SKU', 'hello-elementor-child' ); ?></th>
					<th><?php esc_html_e( 'عمل', 'hello-elementor-child' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$row = 1;
				while ( $query->have_posts() ) :
					$query->the_post();
					$row = self::render_product_rows( $row );
				endwhile;
				?>
			</tbody>
		</table>
		<?php
		wp_reset_postdata();
	}

	/**
	 * Render table rows for a single product (simple or variable).
	 *
	 * @param int $row Current row number.
	 * @return int    Next row number.
	 */
	private static function render_product_rows( int $row ): int {
		$product_id   = get_the_ID();
		$product_img  = get_the_post_thumbnail( $product_id, 'thumbnail' );
		$product_name = get_the_title();
		$wc_product   = wc_get_product( $product_id );

		if ( ! $wc_product ) {
			return $row;
		}

		if ( $wc_product->is_type( 'variable' ) ) {
			foreach ( $wc_product->get_children() as $variation_id ) {
				$variation = wc_get_product( $variation_id );
				if ( ! $variation ) {
					continue;
				}

				$attribute_name = self::get_variation_attribute_label( $variation );
				self::render_row(
					$row,
					$product_id,
					$variation_id,
					$product_img,
					$product_name,
					$attribute_name,
					$variation->get_regular_price(),
					$variation->get_sku()
				);
				++$row;
			}
		} else {
			self::render_row(
				$row,
				$product_id,
				0,
				$product_img,
				$product_name,
				self::ATTRIBUTE_TAXONOMY,
				$wc_product->get_regular_price(),
				$wc_product->get_sku()
			);
			++$row;
		}

		return $row;
	}

	/**
	 * Resolve human-readable attribute label for a variation.
	 *
	 * @param WC_Product_Variation $variation Variation product.
	 * @return string
	 */
	private static function get_variation_attribute_label( WC_Product_Variation $variation ): string {
		$attribute_slug = $variation->get_attribute( self::ATTRIBUTE_TAXONOMY );
		$decoded_slug   = urldecode( $attribute_slug );

		if ( ! $decoded_slug ) {
			return __( 'بدون مقدار', 'hello-elementor-child' );
		}

		$term = get_term_by( 'slug', $decoded_slug, self::ATTRIBUTE_TAXONOMY );

		if ( $term && ! is_wp_error( $term ) ) {
			return $term->name;
		}

		return $decoded_slug;
	}

	/**
	 * Render a single table row.
	 *
	 * @param int    $row            Row index.
	 * @param int    $product_id     Parent product ID.
	 * @param int    $variation_id   Variation ID (0 for simple products).
	 * @param string $product_img    Thumbnail HTML.
	 * @param string $product_name   Product title.
	 * @param string $attribute_name Attribute label column.
	 * @param string $price          Regular price.
	 * @param string $sku            Product or variation SKU.
	 */
	private static function render_row(
		int $row,
		int $product_id,
		int $variation_id,
		string $product_img,
		string $product_name,
		string $attribute_name,
		string $price,
		string $sku
	): void {
		$sku_index = $row - 1;
		?>
		<tr data-product="<?php echo esc_attr( (string) $product_id ); ?>"
			data-variation="<?php echo esc_attr( (string) $variation_id ); ?>">
			<td><?php echo esc_html( (string) $row ); ?></td>
			<td><?php echo $product_img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WC thumbnail HTML. ?></td>
			<td class="product-name"><?php echo esc_html( $product_name ); ?></td>
			<td><?php echo esc_html( $attribute_name ); ?></td>
			<td>
				<input type="number" step="0.01" min="0" class="attr-price-input" value="<?php echo esc_attr( $price ); ?>">
			</td>
			<td>
				<input type="text"
					class="short attr-sku-input"
					name="variable_sku[<?php echo esc_attr( (string) $sku_index ); ?>]"
					id="variable_sku<?php echo esc_attr( (string) $sku_index ); ?>"
					value="<?php echo esc_attr( $sku ); ?>"
					placeholder="">
			</td>
			<td>
				<button type="button" class="attr-save-btn"
					data-product="<?php echo esc_attr( (string) $product_id ); ?>"
					data-variation="<?php echo esc_attr( (string) $variation_id ); ?>">
					<?php esc_html_e( 'ذخیره', 'hello-elementor-child' ); ?>
				</button>
			</td>
		</tr>
		<?php
	}
}
