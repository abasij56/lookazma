<?php
/**
 * AJAX handlers for AI product description.
 *
 * @package AI_Product_Description
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles front-end AJAX generate requests.
 */
final class AI_Product_Desc_Ajax {

	public const ACTION       = 'ai_product_desc_generate';
	public const NONCE_ACTION = 'ai_product_desc_generate';
	public const CAPABILITY   = 'edit_products';

	/**
	 * Register AJAX hooks.
	 */
	public static function init(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'generate' ) );
	}

	/**
	 * Generate description for one master product.
	 */
	public static function generate(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'دسترسی ندارید.', 'ai-product-description' ),
				),
				403
			);
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

		if ( $product_id <= 0 ) {
			wp_send_json_error(
				array(
					'message' => __( 'شناسه محصول نامعتبر است.', 'ai-product-description' ),
				),
				400
			);
		}

		$product = wc_get_product( $product_id );

		if ( ! $product || $product->is_type( 'variation' ) || (int) $product->get_parent_id() > 0 ) {
			wp_send_json_error(
				array(
					'message' => __( 'فقط محصول اصلی پشتیبانی می‌شود.', 'ai-product-description' ),
				),
				400
			);
		}

		$product_data = array(
			'name'       => $product->get_name(),
			'cas_no'     => ai_product_desc_get_acf_value( $product_id, 'cas_no' ),
			'brand'      => ai_product_desc_get_acf_value( $product_id, 'آدرس_برند' ),
			'attributes' => ai_product_desc_format_attributes( $product ),
		);

		$result = AI_Product_Desc_Client::generate_description( $product_data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
				),
				500
			);
		}

		wp_send_json_success(
			array(
				'product_id'   => $product_id,
				'product_name' => $product_data['name'],
				'description'  => $result,
			)
		);
	}
}
