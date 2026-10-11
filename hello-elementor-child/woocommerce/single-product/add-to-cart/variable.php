<?php
/**
 * Variable product add to cart (Lookazma light single override).
 *
 * Ensures attribute dropdowns render even when parent variation
 * attributes are empty/incomplete (common with a single variation).
 * Single-packaging products show a fixed notice box (same footprint as variations).
 *
 * @package HelloElementorChild
 * @version 9.6.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product instanceof WC_Product_Variable ) {
	return;
}

$attribute_keys      = array_keys( $attributes );
$variations_json     = wp_json_encode( $available_variations );
$variations_attr     = function_exists( 'wc_esc_json' ) ? wc_esc_json( $variations_json ) : _wp_specialchars( $variations_json, ENT_QUOTES, 'UTF-8', true );
$lk_single_packaging = ( isset( $lk_single_packaging ) && is_array( $lk_single_packaging ) ) ? $lk_single_packaging : array();

/*
 * Always resolve from $product here so the notice works even when stock
 * woocommerce_variable_add_to_cart() loads this template without our args.
 */
if (
	( empty( $lk_single_packaging['active'] ) || empty( $lk_single_packaging['message'] ) )
	&& $product instanceof WC_Product_Variable
	&& class_exists( 'Hello_Elementor_Child_Custom_Single_Product' )
) {
	$attrs_for_note = is_array( $attributes ) ? $attributes : array();
	if ( array() === $attrs_for_note && method_exists( 'Hello_Elementor_Child_Custom_Single_Product', 'get_effective_variation_attributes' ) ) {
		$attrs_for_note = Hello_Elementor_Child_Custom_Single_Product::get_effective_variation_attributes( $product );
	}
	$lk_single_packaging = Hello_Elementor_Child_Custom_Single_Product::get_single_packaging_context( $product, $attrs_for_note );

	/* Ensure the (hidden) variations table can select the sole packaging option. */
	if (
		! empty( $lk_single_packaging['active'] )
		&& ! empty( $lk_single_packaging['attribute_key'] )
		&& ! empty( $lk_single_packaging['attribute_slug'] )
	) {
		$key  = (string) $lk_single_packaging['attribute_key'];
		$slug = (string) $lk_single_packaging['attribute_slug'];
		if ( empty( $attributes[ $key ] ) || ! is_array( $attributes[ $key ] ) ) {
			$attributes[ $key ] = array( $slug );
		} elseif ( ! in_array( $slug, $attributes[ $key ], true ) ) {
			$attributes[ $key ][] = $slug;
		}
		$attribute_keys = array_keys( $attributes );
	}
}

$show_packaging_note = ! empty( $lk_single_packaging['active'] ) && ! empty( $lk_single_packaging['message'] );

do_action( 'woocommerce_before_add_to_cart_form' );
?>

<form class="variations_form cart<?php echo $show_packaging_note ? ' lk-variations-form--single-packaging' : ''; ?>" action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>" method="post" enctype="multipart/form-data" data-product_id="<?php echo absint( $product->get_id() ); ?>" data-product_variations="<?php echo $variations_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
	<?php do_action( 'woocommerce_before_variations_form' ); ?>

	<?php if ( empty( $available_variations ) && false !== $available_variations ) : ?>
		<p class="stock out-of-stock"><?php echo esc_html( apply_filters( 'woocommerce_out_of_stock_message', __( 'This product is currently out of stock and unavailable.', 'woocommerce' ) ) ); ?></p>
	<?php else : ?>
		<?php if ( $show_packaging_note ) : ?>
			<div class="lk-price-card__packaging-note" role="status">
				<span class="lk-price-card__packaging-note-label"><?php echo esc_html( (string) ( $lk_single_packaging['label'] ?? __( 'بسته بندی', 'hello-elementor-child' ) ) ); ?></span>
				<div class="lk-price-card__packaging-note-box">
					<?php echo esc_html( (string) $lk_single_packaging['message'] ); ?>
				</div>
			</div>
		<?php endif; ?>

		<table class="variations<?php echo $show_packaging_note ? ' lk-variations--sr-only' : ''; ?>" cellspacing="0" role="presentation"<?php echo $show_packaging_note ? ' aria-hidden="true"' : ''; ?>>
			<tbody>
				<?php foreach ( $attributes as $attribute_name => $options ) : ?>
					<?php
					$selected_value = isset( $selected_attributes[ $attribute_name ] ) ? $selected_attributes[ $attribute_name ] : '';
					if ( $show_packaging_note && ( '' === $selected_value || null === $selected_value ) && is_array( $options ) ) {
						$slug = isset( $lk_single_packaging['attribute_slug'] ) ? (string) $lk_single_packaging['attribute_slug'] : '';
						if ( '' !== $slug && in_array( $slug, $options, true ) ) {
							$selected_value = $slug;
						} elseif ( 1 === count( $options ) ) {
							$selected_value = (string) reset( $options );
						}
					}
					?>
					<tr>
						<th class="label">
							<label for="<?php echo esc_attr( sanitize_title( $attribute_name ) ); ?>">
								<?php echo wc_attribute_label( $attribute_name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</label>
						</th>
						<td class="value">
							<?php
							wc_dropdown_variation_attribute_options(
								array(
									'options'   => $options,
									'attribute' => $attribute_name,
									'product'   => $product,
									'selected'  => $selected_value,
								)
							);
							echo end( $attribute_keys ) === $attribute_name
								? wp_kses_post( apply_filters( 'woocommerce_reset_variations_link', '<a class="reset_variations" href="#">' . esc_html__( 'Clear', 'woocommerce' ) . '</a>' ) )
								: '';
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<div class="reset_variations_alert screen-reader-text" role="alert" aria-live="polite" aria-relevant="all"></div>
		<?php do_action( 'woocommerce_after_variations_table' ); ?>

		<div class="single_variation_wrap">
			<?php
			do_action( 'woocommerce_before_single_variation' );
			do_action( 'woocommerce_single_variation' );
			do_action( 'woocommerce_after_single_variation' );
			?>
		</div>
	<?php endif; ?>

	<?php do_action( 'woocommerce_after_variations_form' ); ?>
</form>

<?php
do_action( 'woocommerce_after_add_to_cart_form' );
