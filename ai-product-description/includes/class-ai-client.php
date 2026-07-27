<?php
/**
 * OpenAI-compatible AI client (GapGPT, Gemini, OpenAI, custom).
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
		$content = self::chat( self::build_messages( $product_data ) );

		if ( is_wp_error( $content ) ) {
			return $content;
		}

		return self::extract_description_text( $content );
	}

	/**
	 * Generate Yoast SEO title + meta description for a product category.
	 *
	 * @param array{name: string, slug: string, description: string, parent_name: string, seo_title: string, seo_metadesc: string} $data Category data.
	 * @return array{seo_title: string, seo_metadesc: string}|WP_Error
	 */
	public static function generate_category_seo( array $data ) {
		$content = self::chat( self::build_category_seo_messages( $data ) );

		if ( is_wp_error( $content ) ) {
			return $content;
		}

		$parsed = self::extract_json_object( $content );

		if ( ! is_array( $parsed ) ) {
			return new WP_Error(
				'invalid_seo_json',
				__( 'پاسخ سئو دسته معتبر نبود.', 'ai-product-description' )
			);
		}

		$title = isset( $parsed['seo_title'] ) ? trim( (string) $parsed['seo_title'] ) : '';
		$desc  = isset( $parsed['seo_metadesc'] ) ? trim( (string) $parsed['seo_metadesc'] ) : '';

		if ( '' === $title && '' === $desc ) {
			return new WP_Error(
				'empty_seo_fields',
				__( 'عنوان و توضیح متا خالی برگشت.', 'ai-product-description' )
			);
		}

		return array(
			'seo_title'    => $title,
			'seo_metadesc' => $desc,
		);
	}

	/**
	 * Generate / rewrite category description text.
	 *
	 * @param array{name: string, slug: string, description: string, parent_name: string} $data Category data.
	 * @return string|WP_Error
	 */
	public static function generate_category_description( array $data ) {
		$content = self::chat( self::build_category_description_messages( $data ) );

		if ( is_wp_error( $content ) ) {
			return $content;
		}

		return self::extract_description_text( $content );
	}

	/**
	 * Low-level chat completion call.
	 *
	 * @param array<int, array{role: string, content: string}> $messages Chat messages.
	 * @return string|WP_Error
	 */
	public static function chat( array $messages ) {
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

		return $content;
	}

	/**
	 * Prefer plain text; if model returns JSON with description, use that field.
	 *
	 * @param string $content Raw model content.
	 * @return string
	 */
	private static function extract_description_text( string $content ): string {
		$parsed = self::extract_json_object( $content );

		if ( is_array( $parsed ) && ! empty( $parsed['description'] ) ) {
			return trim( (string) $parsed['description'] );
		}

		$trimmed = trim( $content );

		// Strip ```html ... ``` wrappers if model adds them.
		if ( preg_match( '/^```(?:html|HTML)?\s*([\s\S]*?)\s*```$/', $trimmed, $fence_match ) ) {
			$trimmed = trim( $fence_match[1] );
		}

		return $trimmed;
	}

	/**
	 * Extract first JSON object from model text.
	 *
	 * @param string $content Raw content.
	 * @return array<string, mixed>|null
	 */
	private static function extract_json_object( string $content ): ?array {
		$trimmed = trim( $content );

		if ( preg_match( '/```(?:json)?\s*([\s\S]*?)\s*```/i', $trimmed, $fence_match ) ) {
			$trimmed = trim( $fence_match[1] );
		}

		$decoded = json_decode( $trimmed, true );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}

		if ( preg_match( '/\{[\s\S]*\}/', $trimmed, $json_match ) ) {
			$decoded = json_decode( $json_match[0], true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return null;
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
تو متخصص تولید محتوای سئو برای فروشگاه مواد شیمیایی لوک آزما (https://lookazma.com/) هستی.
باید توضیح محصول را دقیقاً با ساختار الگوی زیر به زبان فارسی بنویسی.

ساختار اجباری خروجی (همین ترتیب):
1) یک عنوان اصلی کوتاه در تگ <h2> شامل نام محصول + نام فارسی/انگلیسی در صورت وجود + گرید (اگر هست) + برند (اگر هست)
   مثال: 2-پروپانول (ایزوپروپانول) گرید ACS برند دکتر مجللی
2) یک پاراگراف معرفی در <p>: نام محصول، معادل انگلیسی در صورت وجود، گرید، برند، CAS، خلوص/بسته‌بندی و سایر مشخصات موجود در داده، و کاربرد کلی برای آزمایشگاه/صنعت.
3) تیتر <h3> با فرمت: چرا [نام محصول] برند [برند]؟
   سپس یک پاراگراف درباره اعتبار برند و تناسب محصول با کاربرد آزمایشگاهی/صنعتی.
   اگر برند خالی است، این بخش را با تیتر مناسب مثل «چرا این محصول؟» بنویس و به کیفیت/کاربرد تمرکز کن.
4) اگر گرید در داده وجود دارد: تیتر <h3> مثل «گرید ACS چه مزیتی دارد؟» و یک پاراگراف توضیح مزیت همان گرید.
   اگر گرید نیست، این بخش را حذف کن.
5) تیتر <h3> کاربردهای [نام محصول] [گرید در صورت وجود]
   سپس یک پاراگراف کوتاه و بعد <ul><li>...</li></ul> شامل ۵ تا ۷ کاربرد مرتبط.
6) تیتر <h3> مشخصات محصول
   سپس پاراگراف جمع‌بندی مشخصات موجود (CAS، خلوص، بسته‌بندی، ویژگی‌ها) و اشاره به MSDS/COA فقط اگر منطقی باشد.
7) تیتر <h3> مشاهده سایر محصولات [نام ماده]
   سپس پاراگراف دعوت به مقایسه برند/گرید/خلوص/بسته‌بندی در فروشگاه لوک آزما و لینک https://lookazma.com/

قوانین:
- فقط HTML تمیز برگردان: از تگ‌های h2, h3, p, ul, li, a استفاده کن. بدون markdown، بدون JSON، بدون توضیح اضافه.
- لحن حرفه‌ای، علمی و سئو‌محور باشد.
- فقط بر اساس داده‌های داده‌شده و دانش عمومی ایمن بنویس؛ مشخصات جعلی نساز. اگر داده‌ای نبود، آن را حذف کن یا کلی و صادقانه بنویس.
- کلمات کلیدی (نام محصول، CAS، برند، گرید، کاربرد، خرید) را طبیعی در متن بگنجان.
- لینک لوک آزما را دقیقاً به صورت https://lookazma.com/ بنویس.
PROMPT;

		$user = sprintf(
			"اطلاعات محصول:\n- نام محصول: %s\n- CAS No: %s\n- نام برند: %s\n- ویژگی‌ها: %s\n\nبر اساس این اطلاعات، توضیح محصول را دقیقاً با همان ساختار الگو (عنوان، معرفی، چرا برند، مزیت گرید در صورت وجود، کاربردها با لیست، مشخصات، مشاهده سایر محصولات + لینک lookazma) به صورت HTML برگردان.",
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

	/**
	 * Messages for category SEO title + meta.
	 *
	 * @param array{name: string, slug: string, description: string, parent_name: string, seo_title: string, seo_metadesc: string} $data Category data.
	 * @return array<int, array{role: string, content: string}>
	 */
	private static function build_category_seo_messages( array $data ): array {
		$system = <<<'PROMPT'
تو متخصص سئوی فروشگاه اینترنتی مواد شیمیایی لوک آزما (https://lookazma.com/) هستی.
برای صفحه دسته محصولات ووکامرس، عنوان SEO و توضیح متا به زبان فارسی پیشنهاد بده.

قوانین:
1) خروجی فقط JSON معتبر باشد، بدون مارک‌داون و بدون متن اضافه:
{"seo_title":"...","seo_metadesc":"..."}
2) seo_title حداکثر حدود ۶۰ کاراکتر؛ شامل نام دسته و قصد خرید (مثل خرید / فروش) در صورت طبیعی بودن.
3) seo_metadesc حدود ۱۴۰ تا ۱۶۰ کاراکتر؛ جذاب، دقیق و بدون کلیشه خالی.
4) لحن حرفه‌ای و مناسب خریدار صنعتی/آزمایشگاهی.
5) اگر مقدار فعلی وجود دارد، آن را بهبود بده؛ اگر ندارد از صفر بساز.
6) اشاره طبیعی به لوک آزما فقط اگر در متا جا شود؛ اجباری نیست.
PROMPT;

		$user = sprintf(
			"اطلاعات دسته:\n- نام: %s\n- اسلاگ: %s\n- دسته والد: %s\n- توضیح فعلی دسته: %s\n- عنوان SEO فعلی: %s\n- متا فعلی: %s\n\nعنوان SEO و توضیح متا را در JSON برگردان.",
			$data['name'] ?: '—',
			$data['slug'] ?: '—',
			$data['parent_name'] ?: '—',
			$data['description'] ?: '(خالی)',
			$data['seo_title'] ?: '(خالی)',
			$data['seo_metadesc'] ?: '(خالی)'
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

	/**
	 * Messages for category description body.
	 *
	 * @param array{name: string, slug: string, description: string, parent_name: string} $data Category data.
	 * @return array<int, array{role: string, content: string}>
	 */
	private static function build_category_description_messages( array $data ): array {
		$has_current = '' !== trim( (string) ( $data['description'] ?? '' ) );

		$system = <<<'PROMPT'
تو متخصص تولید محتوای سئو برای دسته‌های فروشگاه مواد شیمیایی لوک آزما (https://lookazma.com/) هستی.
وظیفه تو نوشتن یا بازنویسی «توضیح دسته محصول» به زبان فارسی است.

قوانین:
1) متن حدود ۱۲۰ تا ۱۸۰ کلمه، حرفه‌ای و مناسب خریدار صنعتی/آزمایشگاهی.
2) روی معرفی دسته، کاربردهای رایج، و دعوت طبیعی به مشاهده/خرید از لوک آزما تمرکز کن.
3) اگر توضیح فعلی وجود دارد: آن را تمیز، روان و سئو‌محور بازنویسی کن و اطلاعات درست را حفظ کن.
4) اگر توضیح فعلی خالی است: از صفر توضیح مفید بنویس.
5) فقط متن توضیحات را برگردان؛ بدون عنوان، بدون JSON، بدون مارک‌داون.
PROMPT;

		$user = $has_current
			? sprintf(
				"نام دسته: %s\nاسلاگ: %s\nدسته والد: %s\n\nتوضیح فعلی:\n%s\n\nاین توضیح را تمیز و سئو‌محور بازنویسی کن.",
				$data['name'] ?: '—',
				$data['slug'] ?: '—',
				$data['parent_name'] ?: '—',
				$data['description']
			)
			: sprintf(
				"نام دسته: %s\nاسلاگ: %s\nدسته والد: %s\n\nتوضیح فعلی وجود ندارد. یک توضیح دسته حرفه‌ای از صفر بنویس.",
				$data['name'] ?: '—',
				$data['slug'] ?: '—',
				$data['parent_name'] ?: '—'
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
