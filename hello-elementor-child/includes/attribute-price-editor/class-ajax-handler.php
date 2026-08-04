<?php
/**
 * Attribute Price Editor AJAX handler.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles price update requests from the editor table.
 */
final class Hello_Elementor_Child_Attribute_Price_Editor_Ajax {

	/**
	 * Register AJAX hooks.
	 */
	public static function init(): void {
		add_action(
			'wp_ajax_' . Hello_Elementor_Child_Attribute_Price_Editor::AJAX_ACTION,
			array( __CLASS__, 'handle_update' )
		);
	}

	/**
	 * Update product or variation price.
	 */
	public static function handle_update(): void {
		check_ajax_referer(
			Hello_Elementor_Child_Attribute_Price_Editor::NONCE_ACTION,
			'nonce'
		);

		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( __( 'دسترسی ندارید.', 'hello-elementor-child' ) );
		}

		$product_id   = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		$variation_id = isset( $_POST['variation_id'] ) ? absint( wp_unslash( $_POST['variation_id'] ) ) : 0;
		$price        = isset( $_POST['price'] ) ? floatval( wp_unslash( $_POST['price'] ) ) : 0.0;
		$sku          = isset( $_POST['sku'] ) ? wc_clean( wp_unslash( $_POST['sku'] ) ) : '';

		if ( $variation_id > 0 ) {
			self::update_variation_price( $variation_id, $price, $sku );
		} else {
			self::update_simple_product_price( $product_id, $price, $sku );
		}
	}

	/**
	 * Update a variation price and purge related caches.
	 *
	 * @param int   $variation_id Variation post ID.
	 * @param float  $price New regular price.
	 * @param string $sku   New SKU.
	 */
	private static function update_variation_price( int $variation_id, float $price, string $sku ): void {
		$product = wc_get_product( $variation_id );

		if ( ! $product ) {
			wp_send_json_error( __( 'محصول یافت نشد.', 'hello-elementor-child' ) );
		}

		$product->set_regular_price( (string) $price );
		$product->set_price( (string) $price );
		$product->set_sku( $sku );
		$product->save();

		if ( defined( 'LSCWP_V' ) ) {
			do_action( 'litespeed_purge_url', get_permalink( $variation_id ) );
		}

		$parent_id = $product->get_parent_id();
		if ( $parent_id ) {
			if ( defined( 'LSCWP_V' ) ) {
				do_action( 'litespeed_purge_post', $parent_id );
			}
			wc_delete_product_transients( $parent_id );
		}

		wp_send_json_success(
			array( 'msg' => __( 'قیمت variation به‌روز شد.', 'hello-elementor-child' ) )
		);
	}

	/**
	 * Update a simple product price and purge related caches.
	 *
	 * @param int   $product_id Product post ID.
	 * @param float  $price New regular price.
	 * @param string $sku   New SKU.
	 */
	private static function update_simple_product_price( int $product_id, float $price, string $sku ): void {
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			wp_send_json_error( __( 'محصول یافت نشد.', 'hello-elementor-child' ) );
		}

		$product->set_regular_price( (string) $price );
		$product->set_price( (string) $price );
		$product->set_sku( $sku );
		$product->save();

		if ( defined( 'LSCWP_V' ) ) {
			do_action( 'litespeed_purge_post', $product_id );
		}

		wc_delete_product_transients( $product_id );

		wp_send_json_success(
			array( 'msg' => __( 'قیمت محصول به‌روز شد.', 'hello-elementor-child' ) )
		);
	}
}
