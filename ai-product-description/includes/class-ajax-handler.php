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
 * Handles admin AJAX generate / save requests.
 */
final class AI_Product_Desc_Ajax {

	public const ACTION        = 'ai_product_desc_generate';
	public const SAVE_ACTION   = 'ai_product_desc_save';
	public const NONCE_ACTION  = 'ai_product_desc_generate';
	public const CAPABILITY    = 'manage_options';

	/**
	 * Register AJAX hooks.
	 */
	public static function init(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'generate' ) );
		add_action( 'wp_ajax_' . self::SAVE_ACTION, array( __CLASS__, 'save' ) );
	}

	/**
	 * Generate description for one master product.
	 */
	public static function generate(): void {
		self::guard_request();

		$product = self::get_master_product_from_request();
		$product_id = $product->get_id();

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
				'product_id'            => $product_id,
				'product_name'          => $product_data['name'],
				'description'           => $result,
				'current_description'   => self::get_current_description_payload( $product ),
			)
		);
	}

	/**
	 * Save (append or set) AI description into product content.
	 */
	public static function save(): void {
		self::guard_request();

		$product    = self::get_master_product_from_request();
		$product_id = $product->get_id();
		$raw_text   = isset( $_POST['description'] ) ? wp_unslash( $_POST['description'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.
		$raw_text   = is_string( $raw_text ) ? trim( $raw_text ) : '';

		if ( '' === $raw_text ) {
			wp_send_json_error(
				array(
					'message' => __( 'متن توضیحات خالی است.', 'ai-product-description' ),
				),
				400
			);
		}

		$new_html      = self::format_description_for_product( $raw_text );
		$existing_html = (string) $product->get_description();
		$existing_trim = trim( wp_strip_all_tags( $existing_html ) );

		if ( '' !== $existing_trim ) {
			$final_html = rtrim( $existing_html ) . "\n\n" . $new_html;
		} else {
			$final_html = $new_html;
		}

		$product->set_description( $final_html );
		$saved_id = $product->save();

		if ( ! $saved_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'ذخیره توضیحات محصول انجام نشد.', 'ai-product-description' ),
				),
				500
			);
		}

		$product = wc_get_product( $product_id );

		wp_send_json_success(
			array(
				'product_id'          => $product_id,
				'appended'            => '' !== $existing_trim,
				'message'             => '' !== $existing_trim
					? __( 'توضیحات به انتهای توضیحات محصول اضافه شد.', 'ai-product-description' )
					: __( 'توضیحات به عنوان توضیحات محصول ذخیره شد.', 'ai-product-description' ),
				'current_description' => self::get_current_description_payload( $product ),
			)
		);
	}

	/**
	 * Current product description for UI display.
	 *
	 * @param WC_Product|false|null $product Product object.
	 * @return array{html: string, is_empty: bool}
	 */
	private static function get_current_description_payload( $product ): array {
		if ( ! $product instanceof WC_Product ) {
			return array(
				'html'     => '',
				'is_empty' => true,
			);
		}

		$html     = (string) $product->get_description();
		$is_empty = '' === trim( wp_strip_all_tags( $html ) );

		return array(
			'html'     => $is_empty ? '' : wp_kses_post( $html ),
			'is_empty' => $is_empty,
		);
	}

	/**
	 * Capability + nonce check.
	 */
	private static function guard_request(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'دسترسی ندارید.', 'ai-product-description' ),
				),
				403
			);
		}
	}

	/**
	 * Resolve master product from POST product_id.
	 *
	 * @return WC_Product
	 */
	private static function get_master_product_from_request(): WC_Product {
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

		return $product;
	}

	/**
	 * Convert plain AI text into safe HTML for product description.
	 *
	 * @param string $text Plain description text.
	 * @return string
	 */
	private static function format_description_for_product( string $text ): string {
		$text = wp_strip_all_tags( $text );
		$text = preg_replace( "/\r\n|\r/", "\n", $text );
		$text = is_string( $text ) ? trim( $text ) : '';

		$paragraphs = preg_split( "/\n{2,}/", $text );
		$html_parts = array();

		if ( ! is_array( $paragraphs ) ) {
			$paragraphs = array( $text );
		}

		foreach ( $paragraphs as $paragraph ) {
			$paragraph = trim( $paragraph );
			if ( '' === $paragraph ) {
				continue;
			}

			$escaped = esc_html( $paragraph );
			$escaped = nl2br( $escaped );
			$escaped = self::linkify_lookazma( $escaped );
			$html_parts[] = '<p>' . $escaped . '</p>';
		}

		return implode( "\n", $html_parts );
	}

	/**
	 * Turn lookazma URLs / brand mentions into links (already-escaped HTML).
	 *
	 * @param string $escaped Escaped HTML fragment.
	 * @return string
	 */
	private static function linkify_lookazma( string $escaped ): string {
		$link = '<a href="https://lookazma.com/" target="_blank" rel="noopener noreferrer">https://lookazma.com/</a>';

		$escaped = preg_replace(
			'#https?://(?:www\.)?lookazma\.com/?#iu',
			$link,
			$escaped
		);

		if ( is_string( $escaped ) && false === strpos( $escaped, 'href="https://lookazma.com/"' ) ) {
			if ( preg_match( '/خرید\s+از\s+لوک\s*آزما/u', $escaped ) ) {
				$escaped = preg_replace(
					'/خرید\s+از\s+لوک\s*آزما/u',
					'<a href="https://lookazma.com/" target="_blank" rel="noopener noreferrer">خرید از لوک آزما</a>',
					$escaped,
					1
				);
			} elseif ( preg_match( '/لوک\s*آزما/u', $escaped ) ) {
				$escaped = preg_replace(
					'/لوک\s*آزما/u',
					'<a href="https://lookazma.com/" target="_blank" rel="noopener noreferrer">لوک آزما</a>',
					$escaped,
					1
				);
			}
		}

		return is_string( $escaped ) ? $escaped : '';
	}
}
