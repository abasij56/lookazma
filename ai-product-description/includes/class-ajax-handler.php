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

	public const ACTION             = 'ai_product_desc_generate';
	public const SAVE_ACTION        = 'ai_product_desc_save';
	public const CAT_SEO_GENERATE   = 'ai_product_desc_cat_seo_generate';
	public const CAT_SEO_SAVE       = 'ai_product_desc_cat_seo_save';
	public const CAT_DESC_GENERATE  = 'ai_product_desc_cat_desc_generate';
	public const CAT_DESC_SAVE      = 'ai_product_desc_cat_desc_save';
	public const CAT_SPECS_GENERATE = 'ai_product_desc_cat_specs_generate';
	public const CAT_SPECS_SAVE     = 'ai_product_desc_cat_specs_save';
	public const NONCE_ACTION       = 'ai_product_desc_generate';
	public const CAPABILITY         = 'manage_options';

	/**
	 * Register AJAX hooks.
	 */
	public static function init(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'generate' ) );
		add_action( 'wp_ajax_' . self::SAVE_ACTION, array( __CLASS__, 'save' ) );
		add_action( 'wp_ajax_' . self::CAT_SEO_GENERATE, array( __CLASS__, 'category_seo_generate' ) );
		add_action( 'wp_ajax_' . self::CAT_SEO_SAVE, array( __CLASS__, 'category_seo_save' ) );
		add_action( 'wp_ajax_' . self::CAT_DESC_GENERATE, array( __CLASS__, 'category_desc_generate' ) );
		add_action( 'wp_ajax_' . self::CAT_DESC_SAVE, array( __CLASS__, 'category_desc_save' ) );
		add_action( 'wp_ajax_' . self::CAT_SPECS_GENERATE, array( __CLASS__, 'category_specs_generate' ) );
		add_action( 'wp_ajax_' . self::CAT_SPECS_SAVE, array( __CLASS__, 'category_specs_save' ) );
	}

	/**
	 * Generate description for one master product.
	 */
	public static function generate(): void {
		self::guard_request();

		$product = self::get_master_product_from_request();
		$product_id = $product->get_id();

		$categories = ai_product_desc_get_product_categories( $product_id );
		$best_url   = ai_product_desc_pick_best_category_url( $categories, $product->get_name() );

		$product_data = array(
			'name'         => $product->get_name(),
			'cas_no'       => ai_product_desc_get_acf_value( $product_id, 'cas_no' ),
			'brand'        => ai_product_desc_get_acf_value( $product_id, 'آدرس_برند' ),
			'attributes'   => ai_product_desc_format_attributes( $product ),
			'categories'   => $categories,
			'category_url' => $best_url,
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

		$result = ai_product_desc_ensure_category_link( $result, $categories, $best_url );

		wp_send_json_success(
			array(
				'product_id'          => $product_id,
				'product_name'        => $product_data['name'],
				'description'         => $result,
				'category_url'        => $best_url,
				'current_description' => self::get_current_description_payload( $product ),
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
	 * Generate SEO title + metadesc for product category.
	 */
	public static function category_seo_generate(): void {
		self::guard_request();
		$term = self::get_product_cat_from_request();
		$ctx  = AI_Product_Desc_Category_Tools::get_category_context( $term );
		$result = AI_Product_Desc_Client::generate_category_seo( $ctx );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		}

		wp_send_json_success(
			array(
				'term_id'      => (int) $term->term_id,
				'seo_title'    => $result['seo_title'],
				'seo_metadesc' => $result['seo_metadesc'],
				'seo_focuskw'  => $result['seo_focuskw'],
				'current'      => array(
					'seo_title'    => $ctx['seo_title'],
					'seo_metadesc' => $ctx['seo_metadesc'],
					'seo_focuskw'  => $ctx['seo_focuskw'],
				),
			)
		);
	}

	/**
	 * Save SEO fields into Yoast term meta.
	 */
	public static function category_seo_save(): void {
		self::guard_request();
		$term = self::get_product_cat_from_request();

		$title   = isset( $_POST['seo_title'] ) ? sanitize_text_field( wp_unslash( $_POST['seo_title'] ) ) : '';
		$meta    = isset( $_POST['seo_metadesc'] ) ? sanitize_textarea_field( wp_unslash( $_POST['seo_metadesc'] ) ) : '';
		$focuskw = isset( $_POST['seo_focuskw'] ) ? sanitize_text_field( wp_unslash( $_POST['seo_focuskw'] ) ) : '';

		if ( '' === $title && '' === $meta && '' === $focuskw ) {
			wp_send_json_error(
				array( 'message' => __( 'عنوان، متا و کلمه کلیدی برای ذخیره خالی است.', 'ai-product-description' ) ),
				400
			);
		}

		$ok = AI_Product_Desc_Category_Tools::set_yoast_seo( (int) $term->term_id, $title, $meta, $focuskw );
		if ( ! $ok ) {
			wp_send_json_error(
				array( 'message' => __( 'ذخیره سئو در یوست انجام نشد.', 'ai-product-description' ) ),
				500
			);
		}

		$saved = AI_Product_Desc_Category_Tools::get_yoast_seo( (int) $term->term_id );

		wp_send_json_success(
			array(
				'message'      => __( 'سئو دسته در یوست ذخیره شد.', 'ai-product-description' ),
				'seo_title'    => $saved['title'],
				'seo_metadesc' => $saved['metadesc'],
				'seo_focuskw'  => $saved['focuskw'],
			)
		);
	}

	/**
	 * Generate category description preview.
	 */
	public static function category_desc_generate(): void {
		self::guard_request();
		$term   = self::get_product_cat_from_request();
		$ctx    = AI_Product_Desc_Category_Tools::get_category_context( $term );
		$result = AI_Product_Desc_Client::generate_category_description( $ctx );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		}

		wp_send_json_success(
			array(
				'term_id'     => (int) $term->term_id,
				'description' => $result,
				'current'     => array(
					'html'     => '' === trim( wp_strip_all_tags( $term->description ) ) ? '' : wp_kses_post( wpautop( $term->description ) ),
					'is_empty' => '' === trim( wp_strip_all_tags( $term->description ) ),
				),
			)
		);
	}

	/**
	 * Replace category term description with AI text.
	 */
	public static function category_desc_save(): void {
		self::guard_request();
		$term     = self::get_product_cat_from_request();
		$raw_text = isset( $_POST['description'] ) ? wp_unslash( $_POST['description'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw_text = is_string( $raw_text ) ? trim( $raw_text ) : '';

		if ( '' === $raw_text || '' === trim( wp_strip_all_tags( $raw_text ) ) ) {
			wp_send_json_error(
				array( 'message' => __( 'متن توضیحات خالی است.', 'ai-product-description' ) ),
				400
			);
		}

		// Preserve HTML from visual editor; fall back to plain-text formatting.
		if ( false !== strpos( $raw_text, '<' ) ) {
			$html = wp_kses_post( $raw_text );
		} else {
			$html = self::format_description_for_product( $raw_text );
		}

		$updated = wp_update_term(
			(int) $term->term_id,
			AI_Product_Desc_Category_Tools::TAXONOMY,
			array(
				'description' => $html,
			)
		);

		if ( is_wp_error( $updated ) ) {
			wp_send_json_error(
				array( 'message' => $updated->get_error_message() ),
				500
			);
		}

		$fresh = get_term( (int) $term->term_id, AI_Product_Desc_Category_Tools::TAXONOMY );
		$desc  = $fresh instanceof WP_Term ? (string) $fresh->description : $html;

		wp_send_json_success(
			array(
				'message'     => __( 'توضیحات دسته ذخیره شد.', 'ai-product-description' ),
				'description' => $desc,
				'html'        => wp_kses_post( $desc ),
			)
		);
	}

	/**
	 * Generate ACF technical specs preview for product category.
	 */
	public static function category_specs_generate(): void {
		self::guard_request();
		$term         = self::get_product_cat_from_request();
		$ctx          = AI_Product_Desc_Category_Tools::get_category_context( $term );
		$ctx['specs'] = AI_Product_Desc_Category_Tools::get_acf_specs( (int) $term->term_id );

		$result = AI_Product_Desc_Client::generate_category_specs( $ctx );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		}

		wp_send_json_success(
			array(
				'term_id'            => (int) $term->term_id,
				'is_single_chemical' => ! empty( $result['is_single_chemical'] ),
				'fields'             => $result['fields'],
				'current'            => $ctx['specs'],
			)
		);
	}

	/**
	 * Replace ACF technical specs for product category.
	 */
	public static function category_specs_save(): void {
		self::guard_request();
		$term = self::get_product_cat_from_request();

		$raw = isset( $_POST['fields'] ) ? wp_unslash( $_POST['fields'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
		} elseif ( is_array( $raw ) ) {
			$decoded = $raw;
		} else {
			$decoded = null;
		}

		if ( ! is_array( $decoded ) || array() === $decoded ) {
			wp_send_json_error(
				array( 'message' => __( 'مشخصات برای ذخیره خالی یا نامعتبر است.', 'ai-product-description' ) ),
				400
			);
		}

		$allowed = array_keys( AI_Product_Desc_Category_Tools::get_acf_spec_fields() );
		$clean   = array();
		foreach ( $allowed as $name ) {
			if ( ! array_key_exists( $name, $decoded ) ) {
				continue;
			}
			$clean[ $name ] = sanitize_textarea_field( (string) $decoded[ $name ] );
		}

		if ( array() === $clean ) {
			wp_send_json_error(
				array( 'message' => __( 'هیچ فیلد معتبری برای ذخیره ارسال نشد.', 'ai-product-description' ) ),
				400
			);
		}

		$ok = AI_Product_Desc_Category_Tools::set_acf_specs( (int) $term->term_id, $clean );
		if ( ! $ok ) {
			wp_send_json_error(
				array( 'message' => __( 'ذخیره مشخصات در ACF انجام نشد.', 'ai-product-description' ) ),
				500
			);
		}

		$saved = AI_Product_Desc_Category_Tools::get_acf_specs( (int) $term->term_id );

		wp_send_json_success(
			array(
				'message' => __( 'مشخصات فنی دسته در ACF ذخیره شد.', 'ai-product-description' ),
				'fields'  => $saved,
			)
		);
	}

	/**
	 * Resolve product_cat term from request.
	 *
	 * @return WP_Term
	 */
	private static function get_product_cat_from_request(): WP_Term {
		$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;

		if ( $term_id <= 0 ) {
			wp_send_json_error(
				array( 'message' => __( 'شناسه دسته نامعتبر است.', 'ai-product-description' ) ),
				400
			);
		}

		$term = get_term( $term_id, AI_Product_Desc_Category_Tools::TAXONOMY );

		if ( ! $term instanceof WP_Term || is_wp_error( $term ) ) {
			wp_send_json_error(
				array( 'message' => __( 'دسته محصول پیدا نشد.', 'ai-product-description' ) ),
				404
			);
		}

		return $term;
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
	 * Convert AI text into safe HTML for product description.
	 * Preserves structured HTML (h2/h3/ul/...) when already present.
	 *
	 * @param string $text Plain or HTML description.
	 * @return string
	 */
	private static function format_description_for_product( string $text ): string {
		$text = trim( $text );

		if ( '' === $text ) {
			return '';
		}

		// Structured HTML from the new product prompt.
		if ( false !== strpos( $text, '<' ) ) {
			return wp_kses_post( $text );
		}

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

		// Only bare homepage — do not rewrite /product-category/... URLs.
		$escaped = preg_replace(
			'#https?://(?:www\.)?lookazma\.com/?(?=[\s<]|$)#iu',
			$link,
			$escaped
		);

		if ( is_string( $escaped ) && ! preg_match( '#href=["\']https?://[^"\']*lookazma\.com#iu', $escaped ) ) {
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
