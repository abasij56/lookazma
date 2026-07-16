<?php
/**
 * Plugin Name: AI Product Description
 * Description: نمایش محصولات اصلی بر اساس برچسب برای تولید توضیحات محصول.
 * Version:     1.0.0
 * Author:      Your Name
 * Text Domain: ai-product-description
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AI_PRODUCT_DESC_VERSION', '1.0.4' );
define( 'AI_PRODUCT_DESC_PATH', plugin_dir_path( __FILE__ ) );
define( 'AI_PRODUCT_DESC_URL', plugin_dir_url( __FILE__ ) );

require_once AI_PRODUCT_DESC_PATH . 'includes/class-settings.php';
require_once AI_PRODUCT_DESC_PATH . 'includes/class-ai-client.php';
require_once AI_PRODUCT_DESC_PATH . 'includes/class-ajax-handler.php';

/**
 * Bootstrap plugin.
 */
function ai_product_desc_init() {
	add_shortcode( 'ai_product_description', 'ai_product_desc_shortcode' );
	add_action( 'wp_enqueue_scripts', 'ai_product_desc_register_assets' );

	AI_Product_Desc_Ajax::init();

	if ( is_admin() ) {
		AI_Product_Desc_Settings::init();
	}
}
add_action( 'plugins_loaded', 'ai_product_desc_init' );

/**
 * Register front-end assets (enqueued when shortcode renders).
 */
function ai_product_desc_register_assets() {
	wp_register_style(
		'ai-product-description',
		AI_PRODUCT_DESC_URL . 'assets/css/ai-product-description.css',
		array(),
		AI_PRODUCT_DESC_VERSION
	);

	wp_register_script(
		'ai-product-description',
		AI_PRODUCT_DESC_URL . 'assets/js/ai-product-description.js',
		array(),
		AI_PRODUCT_DESC_VERSION,
		true
	);
}

/**
 * Front-end JS config for AJAX + settings checks.
 *
 * @return array<string, mixed>
 */
function ai_product_desc_get_frontend_config(): array {
	$provider_config = AI_Product_Desc_Settings::get_active_provider_config();
	$is_configured   = '' !== trim( $provider_config['api_key'] )
		&& '' !== trim( $provider_config['base_url'] )
		&& '' !== trim( $provider_config['model'] );

	return array(
		'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
		'action'       => AI_Product_Desc_Ajax::ACTION,
		'nonce'        => wp_create_nonce( AI_Product_Desc_Ajax::NONCE_ACTION ),
		'isConfigured' => $is_configured ? 1 : 0,
		'i18n'         => array(
			'error'         => __( 'خطا در ساخت توضیحات. دوباره تلاش کنید.', 'ai-product-description' ),
			'notConfigured' => __( 'تنظیمات ارائه‌دهنده هوش مصنوعی کامل نیست. لطفاً API Key، Base URL و Model را در تنظیمات AIP وارد کنید.', 'ai-product-description' ),
			'loading'       => __( 'در حال ساخت...', 'ai-product-description' ),
		),
	);
}

/**
 * Attach AJAX config when shortcode assets load.
 */
function ai_product_desc_localize_script(): void {
	$config = ai_product_desc_get_frontend_config();

	wp_localize_script(
		'ai-product-description',
		'aiProductDesc',
		$config
	);

	wp_add_inline_script(
		'ai-product-description',
		'window.aiProductDesc = window.aiProductDesc || ' . wp_json_encode( $config ) . ';',
		'before'
	);
}

/**
 * Shortcode callback.
 *
 * @return string
 */
function ai_product_desc_shortcode() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return '<p>' . esc_html__( 'WooCommerce is required.', 'ai-product-description' ) . '</p>';
	}

	wp_enqueue_style( 'ai-product-description' );
	wp_enqueue_script( 'ai-product-description' );
	ai_product_desc_localize_script();

	ob_start();
	ai_product_desc_render_page();
	return ob_get_clean();
}

/**
 * Render tag filter form and product table.
 */
function ai_product_desc_render_page() {
	$tags = get_terms(
		array(
			'taxonomy'   => 'product_tag',
			'hide_empty' => false,
		)
	);

	if ( is_wp_error( $tags ) ) {
		$tags = array();
	}

	$selected_tag = isset( $_POST['ai_product_desc_tag'] ) ? absint( wp_unslash( $_POST['ai_product_desc_tag'] ) ) : 0;
	$config_json  = wp_json_encode( ai_product_desc_get_frontend_config() );

	echo '<div id="ai-product-desc-root" class="ai-product-desc" data-config="' . esc_attr( $config_json ) . '">';

	ai_product_desc_render_tag_form( $tags, $selected_tag );

	if ( $selected_tag > 0 ) {
		ai_product_desc_render_products_table( $selected_tag );
	}

	echo '</div>';

	$script_url = AI_PRODUCT_DESC_URL . 'assets/js/ai-product-description.js?ver=' . rawurlencode( AI_PRODUCT_DESC_VERSION );
	?>
	<script>
	(function () {
		var root = document.getElementById('ai-product-desc-root');
		if (root && root.getAttribute('data-config')) {
			try {
				window.aiProductDesc = Object.assign(
					{},
					window.aiProductDesc || {},
					JSON.parse(root.getAttribute('data-config'))
				);
			} catch (e) {}
		}

		if (window.aiProductDescBound) {
			return;
		}

		var existing = document.querySelector('script[data-ai-product-desc="1"]');
		if (existing) {
			return;
		}

		var script = document.createElement('script');
		script.src = <?php echo wp_json_encode( $script_url ); ?>;
		script.defer = true;
		script.setAttribute('data-ai-product-desc', '1');
		document.head.appendChild(script);
	})();
	</script>
	<?php
}

/**
 * Render tag selection form.
 *
 * @param WP_Term[] $tags         Product tags.
 * @param int       $selected_tag Selected tag ID.
 */
function ai_product_desc_render_tag_form( array $tags, int $selected_tag ) {
	?>
		<h2><?php esc_html_e( 'توضیحات محصول با هوش مصنوعی', 'ai-product-description' ); ?></h2>

		<div id="ai-product-desc-result" class="ai-product-desc__result" hidden>
			<hr>
			<h3 id="ai-product-desc-result-title" class="ai-product-desc__result-title" hidden></h3>
			<div id="ai-product-desc-result-body" class="ai-product-desc__result-body"></div>
		</div>

		<form method="post" class="ai-product-desc__form">
			<label for="ai-product-desc-tag-select"><?php esc_html_e( 'انتخاب برچسب:', 'ai-product-description' ); ?></label>
			<select id="ai-product-desc-tag-select" name="ai_product_desc_tag">
				<option value=""><?php esc_html_e( '-- انتخاب برچسب --', 'ai-product-description' ); ?></option>
				<?php foreach ( $tags as $tag ) : ?>
					<option value="<?php echo esc_attr( (string) $tag->term_id ); ?>" <?php selected( $selected_tag, $tag->term_id ); ?>>
						<?php echo esc_html( $tag->name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<button type="submit"><?php esc_html_e( 'نمایش محصولات', 'ai-product-description' ); ?></button>
		</form>
	<?php
}

/**
 * Query master products by tag and render table.
 *
 * @param int $tag_id Product tag term ID.
 */
function ai_product_desc_render_products_table( int $tag_id ) {
	$query = new WP_Query(
		array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'post_parent'    => 0,
			'tax_query'      => array(
				array(
					'taxonomy' => 'product_tag',
					'field'    => 'term_id',
					'terms'    => $tag_id,
				),
			),
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);

	if ( ! $query->have_posts() ) {
		echo '<p>' . esc_html__( 'هیچ محصولی با این برچسب پیدا نشد.', 'ai-product-description' ) . '</p>';
		wp_reset_postdata();
		return;
	}

	$products_data = array();

	while ( $query->have_posts() ) {
		$query->the_post();

		$product_id = get_the_ID();
		$product    = wc_get_product( $product_id );

		if ( ! $product || $product->is_type( 'variation' ) ) {
			continue;
		}

		$products_data[] = array(
			'id'              => $product_id,
			'name'            => $product->get_name(),
			'cas_no'          => ai_product_desc_get_acf_value( $product_id, 'cas_no' ),
			'brand'           => ai_product_desc_get_acf_value( $product_id, 'آدرس_برند' ),
			'attributes_text' => ai_product_desc_format_attributes( $product ),
		);
	}

	wp_reset_postdata();

	if ( empty( $products_data ) ) {
		echo '<p>' . esc_html__( 'هیچ محصول اصلی با این برچسب پیدا نشد.', 'ai-product-description' ) . '</p>';
		return;
	}
	?>
	<hr>
	<div class="ai-product-desc__table-wrap">
		<label for="ai-product-desc-search"><?php esc_html_e( 'جستجو بر اساس نام محصول:', 'ai-product-description' ); ?></label>
		<input type="text" id="ai-product-desc-search" class="ai-product-desc__search" placeholder="<?php esc_attr_e( 'نام محصول...', 'ai-product-description' ); ?>">

		<table class="widefat ai-product-desc__table" id="ai-product-desc-table">
			<thead>
				<tr>
					<th><?php esc_html_e( '#', 'ai-product-description' ); ?></th>
					<th><?php esc_html_e( 'نام محصول', 'ai-product-description' ); ?></th>
					<th><?php esc_html_e( 'CAS No', 'ai-product-description' ); ?></th>
					<th><?php esc_html_e( 'نام برند', 'ai-product-description' ); ?></th>
					<th><?php esc_html_e( 'ویژگی‌ها', 'ai-product-description' ); ?></th>
					<th><?php esc_html_e( 'عمل', 'ai-product-description' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$row = 1;
				foreach ( $products_data as $product_data ) :
					?>
					<tr data-product-id="<?php echo esc_attr( (string) $product_data['id'] ); ?>">
						<td><?php echo esc_html( (string) $row ); ?></td>
						<td class="ai-product-desc__product-name"><?php echo esc_html( $product_data['name'] ); ?></td>
						<td><?php echo esc_html( $product_data['cas_no'] ); ?></td>
						<td><?php echo esc_html( $product_data['brand'] ); ?></td>
						<td class="ai-product-desc__attributes"><?php echo esc_html( $product_data['attributes_text'] ); ?></td>
						<td class="ai-product-desc__actions">
							<button
								type="button"
								class="ai-product-desc__generate-btn"
								data-product-id="<?php echo esc_attr( (string) $product_data['id'] ); ?>"
							>
								<?php esc_html_e( 'ساخت توضیحات', 'ai-product-description' ); ?>
							</button>
						</td>
					</tr>
					<?php
					++$row;
				endforeach;
				?>
			</tbody>
		</table>
	</div>
	<?php
}

/**
 * Read an ACF field value when ACF is available.
 *
 * @param int    $post_id Post ID.
 * @param string $field   ACF field name.
 * @return string
 */
function ai_product_desc_get_acf_value( int $post_id, string $field ): string {
	if ( function_exists( 'get_field' ) ) {
		$value = get_field( $field, $post_id );
	} else {
		$value = get_post_meta( $post_id, $field, true );
	}

	if ( is_array( $value ) ) {
		$value = implode( ', ', array_map( 'strval', $value ) );
	}

	return is_scalar( $value ) ? (string) $value : '';
}

/**
 * Format all product attributes as readable text.
 *
 * @param WC_Product $product Product object.
 * @return string
 */
function ai_product_desc_format_attributes( WC_Product $product ): string {
	$attributes     = $product->get_attributes();
	$formatted_rows = array();

	foreach ( $attributes as $attribute ) {
		$label = ai_product_desc_get_attribute_label( $attribute );

		if ( $attribute->is_taxonomy() ) {
			$terms = wc_get_product_terms(
				$product->get_id(),
				$attribute->get_name(),
				array(
					'fields' => 'names',
				)
			);

			if ( is_wp_error( $terms ) ) {
				$terms = array();
			}

			$value = implode( ', ', $terms );
		} else {
			$value = implode( ', ', $attribute->get_options() );
		}

		if ( '' !== $value ) {
			$formatted_rows[] = $label . ': ' . $value;
		}
	}

	return implode( ' | ', $formatted_rows );
}

/**
 * Resolve a readable attribute label.
 *
 * @param WC_Product_Attribute $attribute Product attribute.
 * @return string
 */
function ai_product_desc_get_attribute_label( WC_Product_Attribute $attribute ): string {
	if ( $attribute->is_taxonomy() ) {
		$taxonomy = $attribute->get_name();
		$label    = wc_attribute_label( $taxonomy );

		return $label ? $label : $taxonomy;
	}

	$label = wc_attribute_label( $attribute->get_name() );

	return $label ? $label : $attribute->get_name();
}
