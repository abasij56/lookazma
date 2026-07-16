<?php
/**
 * OpenAI-compatible AI client (GapGPT, OpenAI, custom).
 *
 * @package AI_Product_Description
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends chat completion requests to the active AI provider.
 */
final class AI_Product_Desc_Client {

	/**
	 * Generate a product description from product fields.
	 *
	 * @param array{name: string, cas_no: string, brand: string, attributes: string} $product_data Product columns.
	 * @return string|WP_Error Generated description or error.
	 */
	public static function generate_description( array $product_data ) {
		$config = AI_Product_Desc_Settings::get_active_provider_config();

		if ( '' === $config['api_key'] ) {
			return new WP_Error(
				'missing_api_key',
				__( 'API Key برای ارائه‌دهنده فعال تنظیم نشده است.', 'ai-product-description' )
			);
		}

		if ( '' === $config['base_url'] ) {
			return new WP_Error(
				'missing_base_url',
				__( 'Base URL برای ارائه‌دهنده فعال تنظیم نشده است.', 'ai-product-description' )
			);
		}

		if ( '' === $config['model'] ) {
			return new WP_Error(
				'missing_model',
				__( 'مدل برای ارائه‌دهنده فعال تنظیم نشده است.', 'ai-product-description' )
			);
		}

		$endpoint = self::build_chat_completions_url( $config['base_url'] );
		$messages = self::build_messages( $product_data );

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 90,
				'headers' => array(
					'Authorization' => 'Bearer ' . $config['api_key'],
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'       => $config['model'],
						'messages'    => $messages,
						'temperature' => 0.7,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code < 200 || $code >= 300 ) {
			$message = __( 'خطا در ارتباط با سرویس هوش مصنوعی.', 'ai-product-description' );

			if ( is_array( $data ) ) {
				if ( ! empty( $data['error']['message'] ) ) {
					$message = (string) $data['error']['message'];
				} elseif ( ! empty( $data['message'] ) ) {
					$message = (string) $data['message'];
				}
			}

			return new WP_Error( 'ai_http_error', $message, array( 'status' => $code ) );
		}

		$content = '';

		if ( is_array( $data ) && ! empty( $data['choices'][0]['message']['content'] ) ) {
			$content = trim( (string) $data['choices'][0]['message']['content'] );
		}

		if ( '' === $content ) {
			return new WP_Error(
				'empty_ai_response',
				__( 'پاسخ خالی از سرویس هوش مصنوعی دریافت شد.', 'ai-product-description' )
			);
		}

		return self::extract_description_text( $content );
	}

	/**
	 * Prefer plain text; if model returns JSON with description, use that field.
	 *
	 * @param string $content Raw model content.
	 * @return string
	 */
	private static function extract_description_text( string $content ): string {
		$trimmed = trim( $content );

		if ( preg_match( '/```(?:json)?\s*([\s\S]*?)\s*```/i', $trimmed, $fence_match ) ) {
			$trimmed = trim( $fence_match[1] );
		}

		$decoded = json_decode( $trimmed, true );
		if ( is_array( $decoded ) && ! empty( $decoded['description'] ) ) {
			return trim( (string) $decoded['description'] );
		}

		if ( preg_match( '/\{[\s\S]*\}/', $trimmed, $json_match ) ) {
			$decoded = json_decode( $json_match[0], true );
			if ( is_array( $decoded ) && ! empty( $decoded['description'] ) ) {
				return trim( (string) $decoded['description'] );
			}
		}

		return $content;
	}

	/**
	 * Build chat/completions endpoint from base URL.
	 *
	 * @param string $base_url Provider base URL.
	 * @return string
	 */
	private static function build_chat_completions_url( string $base_url ): string {
		$base_url = untrailingslashit( trim( $base_url ) );

		if ( preg_match( '#/chat/completions$#i', $base_url ) ) {
			return $base_url;
		}

		return $base_url . '/chat/completions';
	}

	/**
	 * Build system + user messages for chemical SEO product copy.
	 *
	 * @param array{name: string, cas_no: string, brand: string, attributes: string} $product_data Product columns.
	 * @return array<int, array{role: string, content: string}>
	 */
	private static function build_messages( array $product_data ): array {
		$system = <<<'PROMPT'
تو یک متخصص تولید محتوای سئو برای فروشگاه مواد شیمیایی (مانند NaCl، استون، اسیدها، حلال‌ها و مواد آزمایشگاهی) هستی.
وظیفه تو نوشتن توضیح محصول حرفه‌ای، دقیق و سئو‌محور به زبان فارسی است.

قوانین اجباری:
1) متن حدود ۱۵۰ کلمه باشد (بین ۱۴۰ تا ۱۷۰ کلمه).
2) لحن حرفه‌ای، علمی و مناسب خریدار صنعتی/آزمایشگاهی باشد.
3) روی سئو تمرکز کن: کلمات کلیدی مرتبط را طبیعی در متن بگنجان (نام محصول، CAS، کاربردها، خلوص، برند در صورت وجود، خرید، فروش، قیمت در صورت منطقی بودن).
4) اگر داده ناقص است حدس خطرناک نزن؛ بر اساس داده‌های داده‌شده و دانش عمومی ایمن بنویس.
5) ساختار پیشنهادی:
   - معرفی محصول و هویت شیمیایی
   - مشخصات و کاربردهای اصلی
   - جمع‌بندی کوتاه سئو‌محور همراه با دعوت به خرید
6) در پایان متن حتماً اشاره کن که این محصول از فروشگاه اینترنتی مواد شیمیایی لوک آزما به آدرس https://lookazma.com/ قابل خرید است و از عبارت‌هایی مانند «خرید از لوک آزما» به شکل طبیعی استفاده کن. آدرس سایت را دقیقاً به صورت https://lookazma.com/ بنویس.
7) فقط متن توضیحات را برگردان؛ بدون عنوان جداگانه، بدون مارک‌داون اضافه، بدون توضیح درباره خودت، بدون تصویر و بدون JSON.
PROMPT;

		$user = sprintf(
			"اطلاعات محصول:\n- نام محصول: %s\n- CAS No: %s\n- نام برند: %s\n- ویژگی‌ها: %s\n\nبر اساس این اطلاعات یک توضیح محصول حرفه‌ای و سئو‌محور حدود ۱۵۰ کلمه‌ای بنویس و در پایان به خرید این محصول از فروشگاه لوک آزما (https://lookazma.com/) اشاره کن.",
			$product_data['name'] ?: '—',
			$product_data['cas_no'] ?: '—',
			$product_data['brand'] ?: '—',
			$product_data['attributes'] ?: '—'
		);

		return array(
			array(
				'role'    => 'system',
				'content' => $system,
			),
			array(
				'role'    => 'user',
				'content' => $user,
			),
		);
	}
}
