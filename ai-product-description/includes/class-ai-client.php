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
	 * @param array{name: string, cas_no: string, brand: string, attributes: string, categories?: array, category_url?: string} $product_data Product columns.
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
	 * Generate Yoast SEO title + meta description + focus keyphrase for a product category.
	 *
	 * @param array{name: string, slug: string, description: string, parent_name: string, seo_title: string, seo_metadesc: string, seo_focuskw?: string} $data Category data.
	 * @return array{seo_title: string, seo_metadesc: string, seo_focuskw: string}|WP_Error
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

		$title   = isset( $parsed['seo_title'] ) ? trim( (string) $parsed['seo_title'] ) : '';
		$desc    = isset( $parsed['seo_metadesc'] ) ? trim( (string) $parsed['seo_metadesc'] ) : '';
		$focuskw = isset( $parsed['seo_focuskw'] ) ? trim( (string) $parsed['seo_focuskw'] ) : '';
		if ( '' === $focuskw && isset( $parsed['focuskw'] ) ) {
			$focuskw = trim( (string) $parsed['focuskw'] );
		}

		if ( '' === $title && '' === $desc && '' === $focuskw ) {
			return new WP_Error(
				'empty_seo_fields',
				__( 'عنوان، متا و کلمه کلیدی کانونی خالی برگشت.', 'ai-product-description' )
			);
		}

		return array(
			'seo_title'    => $title,
			'seo_metadesc' => $desc,
			'seo_focuskw'  => $focuskw,
		);
	}

	/**
	 * Generate / rewrite category description text.
	 *
	 * @param array{name: string, slug: string, description: string, parent_name: string} $data Category data.
	 * @return string|WP_Error
	 */
	public static function generate_category_description( array $data ) {
		$content = self::chat( self::build_category_description_messages( $data ), 120 );

		if ( is_wp_error( $content ) ) {
			return $content;
		}

		return self::extract_description_text( $content );
	}

	/**
	 * Generate ACF technical specs for a product category.
	 *
	 * @param array{name: string, slug: string, description: string, parent_name: string, specs?: array<string, string>} $data Category data.
	 * @return array{is_single_chemical: bool, fields: array<string, string>}|WP_Error
	 */
	public static function generate_category_specs( array $data ) {
		$content = self::chat( self::build_category_specs_messages( $data ), 90 );

		if ( is_wp_error( $content ) ) {
			return $content;
		}

		$parsed = self::extract_json_object( $content );
		if ( ! is_array( $parsed ) ) {
			return new WP_Error(
				'invalid_specs_json',
				__( 'پاسخ مشخصات فنی دسته معتبر نبود.', 'ai-product-description' )
			);
		}

		$allowed = array_keys( AI_Product_Desc_Category_Tools::get_acf_spec_fields() );
		$fields  = array();

		foreach ( $allowed as $name ) {
			$raw = '';
			if ( isset( $parsed[ $name ] ) && is_scalar( $parsed[ $name ] ) ) {
				$raw = trim( (string) $parsed[ $name ] );
			} elseif ( isset( $parsed['fields'][ $name ] ) && is_scalar( $parsed['fields'][ $name ] ) ) {
				$raw = trim( (string) $parsed['fields'][ $name ] );
			}
			$fields[ $name ] = '' !== $raw ? $raw : 'N/A';
		}

		$is_single = true;
		if ( array_key_exists( 'is_single_chemical', $parsed ) ) {
			$is_single = (bool) $parsed['is_single_chemical'];
		}

		return array(
			'is_single_chemical' => $is_single,
			'fields'             => $fields,
		);
	}

	/**
	 * Low-level chat completion call.
	 *
	 * @param array<int, array{role: string, content: string}> $messages Chat messages.
	 * @param int                                              $timeout  Request timeout in seconds.
	 * @return string|WP_Error
	 */
	public static function chat( array $messages, int $timeout = 90 ) {
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
		$timeout  = max( 30, $timeout );

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => $timeout,
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
	 * @param array{name: string, cas_no: string, brand: string, attributes: string, categories?: array<int, array{id: int, name: string, slug: string, url: string, parent_name: string, depth: int}>, category_url?: string} $product_data Product columns.
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
   سپس پاراگراف دعوت به مقایسه برند/گرید/خلوص/بسته‌بندی در همان دسته ماده در فروشگاه لوک آزما.
   در همین بخش دقیقاً یک لینک <a> با href برابر URL دسته انتخاب‌شده بگذار (نه صفحه اصلی سایت).

قوانین لینک دسته (مهم):
- از بین لیست دسته‌های داده‌شده، مناسب‌ترین دسته را انتخاب کن: ترجیحاً خاص‌ترین دسته ماده شیمیایی (عمیق‌ترین/نزدیک‌ترین به نام محصول)، نه دسته عمومی والد مثل chemicals یا salts-and-inorganics اگر فرزند دقیق‌تری وجود دارد.
- URL را از لیست کپی کن؛ URL جعلی نساز و مسیر را تغییر نده.
- اگر فقط یک دسته هست همان را استفاده کن.
- اگر هیچ دسته‌ای نبود، از https://lookazma.com/ استفاده کن.
- مثال درست لینک دسته: https://lookazma.com/product-category/chemicals/salts-and-inorganics/cobaltii-nitrate/

قوانین عمومی:
- فقط HTML تمیز برگردان: از تگ‌های h2, h3, p, ul, li, a استفاده کن. بدون markdown، بدون JSON، بدون توضیح اضافه.
- لحن حرفه‌ای، علمی و سئو‌محور باشد.
- فقط بر اساس داده‌های داده‌شده و دانش عمومی ایمن بنویس؛ مشخصات جعلی نساز. اگر داده‌ای نبود، آن را حذف کن یا کلی و صادقانه بنویس.
- کلمات کلیدی (نام محصول، CAS، برند، گرید، کاربرد، خرید) را طبیعی در متن بگنجان.
PROMPT;

		$categories_text = '(بدون دسته)';
		if ( ! empty( $product_data['categories'] ) && is_array( $product_data['categories'] ) ) {
			$lines = array();
			foreach ( $product_data['categories'] as $cat ) {
				$lines[] = sprintf(
					'- نام: %s | والد: %s | عمق: %d | URL: %s',
					$cat['name'] ?? '—',
					! empty( $cat['parent_name'] ) ? $cat['parent_name'] : '—',
					(int) ( $cat['depth'] ?? 0 ),
					$cat['url'] ?? '—'
				);
			}
			$categories_text = implode( "\n", $lines );
		}

		$suggested = ! empty( $product_data['category_url'] )
			? (string) $product_data['category_url']
			: 'https://lookazma.com/';

		$user = sprintf(
			"اطلاعات محصول:\n- نام محصول: %s\n- CAS No: %s\n- نام برند: %s\n- ویژگی‌ها: %s\n\nدسته‌های محصول (یکی را انتخاب کن):\n%s\n\nپیشنهاد سیستم برای مناسب‌ترین دسته (در صورت تردید از همین استفاده کن):\n%s\n\nبر اساس این اطلاعات، توضیح محصول را دقیقاً با همان ساختار الگو به صورت HTML برگردان و در بخش آخر لینک دسته انتخاب‌شده را بگذار.",
			$product_data['name'] ?: '—',
			$product_data['cas_no'] ?: '—',
			$product_data['brand'] ?: '—',
			$product_data['attributes'] ?: '—',
			$categories_text,
			$suggested
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
	 * Messages for category SEO title + meta + focus keyphrase.
	 *
	 * @param array{name: string, slug: string, description: string, parent_name: string, seo_title: string, seo_metadesc: string, seo_focuskw?: string} $data Category data.
	 * @return array<int, array{role: string, content: string}>
	 */
	private static function build_category_seo_messages( array $data ): array {
		$system = <<<'PROMPT'
تو متخصص سئو، شیمی و کپی‌رایتینگ برای فروشگاه مواد شیمیایی لوک آزما (https://lookazma.com/) هستی.
برای صفحه دسته محصولات ووکامرس (نه صفحه محصول تکی)، کلمه کلیدی کانونی، عنوان SEO یوست و توضیح متا به زبان فارسی پیشنهاد بده.
صفحه دسته چند برند، گرید و بسته‌بندی از یک ماده/گروه را نشان می‌دهد.

قوانین خروجی:
1) فقط JSON معتبر، بدون مارک‌داون و بدون متن اضافه:
{"seo_focuskw":"...","seo_title":"...","seo_metadesc":"..."}

seo_focuskw:
- یک عبارت کوتاه و واقعی جستجو (معمولاً ۲ تا ۵ کلمه)، مثل «خرید متانول» یا «متانول آزمایشگاهی»
- بدون لیست ویرگولی طولانی

seo_title (عنوان SEO یوست / تگ title گوگل — نه نام دسته ووکامرس):
- کل عنوان شامل پسوند، حداکثر حدود ۶۰ کاراکتر (ترجیحاً ۵۰ تا ۶۰)
- با کلمه کلیدی کانونی (seo_focuskw) شروع شود
- طبیعی و جذاب باشد؛ از Keyword Stuffing خودداری کن
- انتهای عنوان همیشه دقیقاً این باشد: | لوک آزما
- برای دسته‌های ماده مشخص ترجیحاً الگو: خرید [نام ماده] [گرید/کاربرد فقط اگر جا باشد] | لوک آزما
- از کلماتی مثل قیمت، مشخصات، کاربردها، برندهای موجود، گریدهای مختلف فقط اگر در بودجه کاراکتر جا شود و طبیعی باشد استفاده کن
- فقط یک عنوان نهایی بساز
- مثال خوب: خرید متانول آزمایشگاهی | لوک آزما

seo_metadesc:
- حدود ۱۴۰ تا ۱۶۰ کاراکتر
- جذاب، دقیق، مناسب خریدار صنعتی/آزمایشگاهی
- ترجیحاً شامل کلمه کلیدی کانونی
- اشاره طبیعی به لوک آزما فقط اگر جا شود؛ اجباری نیست

عمومی:
- اگر مقدار فعلی وجود دارد آن را بهبود بده؛ اگر ندارد از صفر بساز
- seo_title و seo_focuskw و seo_metadesc با هم هم‌خوان باشند
PROMPT;

		$user = sprintf(
			"اطلاعات دسته:\n- نام: %s\n- اسلاگ: %s\n- دسته والد: %s\n- توضیح فعلی دسته: %s\n- کلمه کلیدی کانونی فعلی: %s\n- عنوان SEO فعلی: %s\n- متا فعلی: %s\n\nکلمه کلیدی کانونی، عنوان SEO و توضیح متا را در JSON برگردان.",
			$data['name'] ?: '—',
			$data['slug'] ?: '—',
			$data['parent_name'] ?: '—',
			$data['description'] ?: '(خالی)',
			! empty( $data['seo_focuskw'] ) ? $data['seo_focuskw'] : '(خالی)',
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
تو متخصص شیمی، نویسنده علمی، کارشناس سئو و تولید محتوای EEAT برای فروشگاه مواد شیمیایی لوک آزما (https://lookazma.com/) هستی.
وظیفه تو تولید یا بازنویسی «توضیح صفحه دسته محصولات ووکامرس» به زبان فارسی است؛ متنی که بالای لیست محصولات دیده می‌شود و هم برای خریدار صنعتی/آزمایشگاهی و هم برای گوگل ارزشمند باشد.

هدف:
- معرفی واضح دسته و کمک به تصمیم خرید
- پوشش پرسش‌های رایج کاربر درباره این دسته
- دعوت طبیعی به مشاهده محصولات و استعلام/خرید از لوک آزما
- متن یونیک، علمی، روان و بدون اغراق تبلیغاتی

تشخیص نوع دسته:
A) اگر نام دسته یک ماده شیمیایی مشخص است (مثل استون، اسید سولفوریک، ایزوپروپانول): محتوا را ماده‌محور بنویس و مشخصات کلیدی معتبر را بیاور.
B) اگر دسته یک گروه/خانواده است (مثل اسیدها، حلال‌ها، نمک‌ها، برند، گرید): محتوا را گروهی بنویس؛ روی انواع رایج، کاربردها و راهنمای انتخاب تمرکز کن و مشخصات تک‌ماده (CAS کامل، معادلات واکنش و …) را اجباری نکن.

ساختار اجباری خروجی (همین ترتیب، HTML):
1) <h2> معرفی [نام دسته]
   یک پاراگراف معرفی: ماده/گروه چیست، جایگاه در آزمایشگاه و صنعت، و خلاصه کاربردها.
2) <h3> ویژگی‌ها و مشخصات مهم
   پاراگراف کوتاه + در صورت مفید بودن یک <ul> یا <table> مختصر از ویژگی‌های شاخص.
   فقط اطلاعات معتبر؛ اگر داده مطمئن نیست بنویس «اطلاعات معتبر و مستندی در این زمینه در دسترس نیست» یا آن مورد را حذف کن.
3) <h3> کاربردها
   پاراگراف کوتاه + <ul> با ۵ تا ۸ کاربرد واقعی (آزمایشگاهی، دارویی، شیمیایی، غذایی، آرایشی و … فقط اگر مرتبط باشد).
4) <h3> گریدها و نکات خرید
   توضیح گریدهای رایج مرتبط (مثل آزمایشگاهی، صنعتی، ACS، HPLC، Food Grade در صورت مرتبط بودن) و راهنمای انتخاب کوتاه برای خریدار.
5) <h3> ایمنی و نگهداری (خلاصه)
   نکات کوتاه ایمنی/نگهداری مرتبط با دسته؛ بدون کپی کامل MSDS و بدون ادعاهای تأییدنشده.
6) <h3> سوالات متداول
   دقیقاً ۳ تا ۵ سؤال پرتکرار کاربر به صورت:
   <h4>سؤال؟</h4><p>پاسخ ۸۰ تا ۱۵۰ کلمه‌ای دقیق و کاربردی</p>
7) <h3> خرید [نام دسته] از لوک آزما
   پاراگراف دعوت به مقایسه برند/گرید/بسته‌بندی و خرید یا استعلام از لوک آزما با لینک https://lookazma.com/

حجم و سبک:
- حدود ۵۰۰ تا ۹۰۰ کلمه (نه مقاله ۴۰۰۰ کلمه‌ای)
- لحن علمی، حرفه‌ای، قابل فهم
- پاراگراف‌ها کوتاه و خوانا
- کلمات کلیدی (نام دسته، خرید، کاربرد، گرید، آزمایشگاهی) را طبیعی توزیع کن
- از تکرار جملات و پاراگراف‌های مشابه خودداری کن

قوانین سخت:
- فقط HTML تمیز برگردان: تگ‌های مجاز h2, h3, h4, p, ul, ol, li, table, thead, tbody, tr, th, td, strong, em, a
- بدون Markdown، بدون JSON، بدون توضیح اضافه، بدون بلوک کد
- از تگ h1 استفاده نکن (عنوان صفحه همان نام دسته است)
- Meta Description ننویس (جداگانه تولید می‌شود)
- مشخصات عددی/شیمیایی جعلی نساز؛ برند/استاندارد ناشناخته اختراع نکن
- اگر توضیح فعلی وجود دارد: اطلاعات درست را حفظ کن، متن را تمیز، کامل‌تر و سئومحور بازنویسی کن
- لینک لوک آزما را دقیقاً https://lookazma.com/ بنویس
PROMPT;

		$user = $has_current
			? sprintf(
				"اطلاعات دسته:\n- نام: %s\n- اسلاگ: %s\n- دسته والد: %s\n\nتوضیح فعلی:\n%s\n\nاین توضیح را با ساختار HTML اجباری (معرفی، ویژگی‌ها، کاربردها، گرید/خرید، ایمنی، FAQ، CTA لوک آزما) بازنویسی و تقویت کن.",
				$data['name'] ?: '—',
				$data['slug'] ?: '—',
				$data['parent_name'] ?: '—',
				$data['description']
			)
			: sprintf(
				"اطلاعات دسته:\n- نام: %s\n- اسلاگ: %s\n- دسته والد: %s\n\nتوضیح فعلی خالی است. یک توضیح دسته کامل و سئومحور از صفر با همان ساختار HTML اجباری بنویس.",
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

	/**
	 * Messages for category ACF technical specs.
	 *
	 * @param array{name: string, slug: string, description: string, parent_name: string, specs?: array<string, string>} $data Category data.
	 * @return array<int, array{role: string, content: string}>
	 */
	private static function build_category_specs_messages( array $data ): array {
		$system = <<<'PROMPT'
تو متخصص شیمی و داده مواد شیمیایی برای فروشگاه لوک آزما هستی.
برای یک دسته محصول ووکامرس، مشخصات فنی فیلدهای ACF را به زبان مناسب (فارسی/انگلیسی مطابق فیلد) پر کن.

خروجی فقط JSON معتبر، بدون مارک‌داون و بدون متن اضافه:
{
  "is_single_chemical": true,
  "شکل_ظاهری_دسته": "...",
  "مترادف": "...",
  "cas_no-cat": "...",
  "en-cat": "...",
  "فرمول_شیمیایی_دسته": "...",
  "وزن_مولوکول": "...",
  "نقطه_ذوب": "...",
  "نقطه_جوش": "...",
  "نقطه_اشتعال": "...",
  "چگالی": "...",
  "ویسکوزیته": "...",
  "فشار_بخار": "...",
  "حلالیت در آب": "...",
  "حلالیت_دسته": "...",
  "توضیحات": "..."
}

قوانین:
1) اگر نام دسته یک ماده شیمیایی مشخص است (مثل متانول، استون): is_single_chemical=true و مقادیر معتبر علمی را با واحد SI بنویس.
2) اگر دسته گروه/خانواده است (اسیدها، حلال‌ها، برند و …): is_single_chemical=false و برای همه فیلدها مقدار "N/A" بگذار.
3) اگر برای یک فیلد داده معتبر و مطمئن نداری، همان فیلد را "N/A" بگذار؛ حدس نزن و عدد جعلی نساز.
4) فیلد en-cat نام انگلیسی استاندارد ماده باشد.
5) فیلد توضیحات یک توضیح کوتاه علمی ۱ تا ۲ جمله باشد (نه مقاله بلند).
6) MSDS را تولید نکن (در JSON نیست).
7) اگر مقادیر فعلی وجود دارد و درست به‌نظر می‌رسند، می‌توانی حفظ یا اصلاح جزئی کنی؛ ولی خروجی کامل همه کلیدها را بده.
PROMPT;

		$current_specs = '';
		if ( ! empty( $data['specs'] ) && is_array( $data['specs'] ) ) {
			$lines = array();
			foreach ( $data['specs'] as $key => $value ) {
				$lines[] = '- ' . $key . ': ' . ( '' !== trim( (string) $value ) ? $value : '(خالی)' );
			}
			$current_specs = implode( "\n", $lines );
		}

		$user = sprintf(
			"اطلاعات دسته:\n- نام: %s\n- اسلاگ: %s\n- دسته والد: %s\n- توضیح دسته: %s\n\nمقادیر فعلی ACF:\n%s\n\nJSON مشخصات فنی را برگردان.",
			$data['name'] ?: '—',
			$data['slug'] ?: '—',
			$data['parent_name'] ?: '—',
			'' !== trim( (string) ( $data['description'] ?? '' ) ) ? wp_strip_all_tags( (string) $data['description'] ) : '(خالی)',
			$current_specs ?: '(همه خالی)'
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
