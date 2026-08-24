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
	 * Rewrite an existing product description based on brand and grade rules.
	 *
	 * @param array{name: string, cas_no: string, brand: string, attributes: string, description: string, categories?: array, category_url?: string} $product_data Product + current HTML.
	 * @return string|WP_Error
	 */
	public static function refine_description_brand_grade( array $product_data ) {
		$content = self::chat( self::build_refine_brand_grade_messages( $product_data ), 120 );

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
	 * Generate / rewrite category description via two API calls (Q&A then article).
	 *
	 * @param array{name: string, slug: string, description: string, parent_name: string} $data Category data.
	 * @return array{description: string, stage1_html: string}|WP_Error
	 */
	public static function generate_category_description( array $data ) {
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		$qa_raw = self::chat( self::build_category_qa_messages( $data ), 180 );
		if ( is_wp_error( $qa_raw ) ) {
			return $qa_raw;
		}

		$qa_parsed = self::extract_json_object( $qa_raw );
		if ( ! is_array( $qa_parsed ) ) {
			return new WP_Error(
				'invalid_qa_json',
				__( 'پاسخ مرحله اول (سوال و جواب) معتبر نبود.', 'ai-product-description' )
			);
		}

		$qa_items = self::normalize_category_qa_items( $qa_parsed );
		if ( count( $qa_items ) < 10 ) {
			return new WP_Error(
				'insufficient_qa_items',
				__( 'تعداد سوال و جواب‌های مرحله اول کافی نبود.', 'ai-product-description' )
			);
		}

		$qa_payload = wp_json_encode(
			array(
				'material' => (string) ( $data['name'] ?? '' ),
				'items'    => $qa_items,
			),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);

		if ( ! is_string( $qa_payload ) || '' === $qa_payload ) {
			return new WP_Error(
				'qa_encode_failed',
				__( 'آماده‌سازی داده سوال و جواب برای مرحله دوم انجام نشد.', 'ai-product-description' )
			);
		}

		$article = self::chat( self::build_category_article_from_qa_messages( $data, $qa_payload ), 180 );
		if ( is_wp_error( $article ) ) {
			return $article;
		}

		return array(
			'description' => self::extract_description_text( $article ),
			'stage1_html' => self::format_category_qa_html( $qa_items, (string) ( $data['name'] ?? '' ) ),
		);
	}

	/**
	 * Build readable HTML preview for Call 1 Q&A items.
	 *
	 * @param array<int, array{q: string, a: string}> $items   Q&A rows.
	 * @param string                                  $material Category / material name.
	 */
	public static function format_category_qa_html( array $items, string $material = '' ): string {
		$parts = array();

		if ( '' !== trim( $material ) ) {
			$parts[] = '<h2>' . esc_html(
				sprintf(
					/* translators: %s: category name */
					__( 'سوال و جواب تخصصی: %s', 'ai-product-description' ),
					$material
				)
			) . '</h2>';
		}

		$parts[] = '<ol class="ai-cat-stage1-list">';

		foreach ( $items as $item ) {
			$q = isset( $item['q'] ) ? trim( (string) $item['q'] ) : '';
			$a = isset( $item['a'] ) ? trim( (string) $item['a'] ) : '';
			if ( '' === $q && '' === $a ) {
				continue;
			}
			$parts[] = '<li>';
			if ( '' !== $q ) {
				$parts[] = '<p><strong>' . esc_html( $q ) . '</strong></p>';
			}
			if ( '' !== $a ) {
				$parts[] = '<p>' . esc_html( $a ) . '</p>';
			}
			$parts[] = '</li>';
		}

		$parts[] = '</ol>';

		return implode( '', $parts );
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
		return self::chat_request( $messages, AI_Product_Desc_Settings::get_active_provider_config(), $timeout );
	}

	/**
	 * Send a minimal request to verify provider credentials.
	 *
	 * @param array{id: string, label: string, api_key: string, base_url: string, model: string} $config Provider connection.
	 * @return array{reply: string}|WP_Error
	 */
	public static function test_connection( array $config ) {
		$messages = array(
			array(
				'role'    => 'user',
				'content' => 'Reply with exactly: OK',
			),
		);

		$result = self::chat_request( $messages, $config, 30 );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'reply' => $result,
		);
	}

	/**
	 * Low-level chat completion call with explicit provider config.
	 *
	 * @param array<int, array{role: string, content: string}> $messages Chat messages.
	 * @param array{id: string, label: string, api_key: string, base_url: string, model: string} $config   Provider connection.
	 * @param int                                              $timeout  Request timeout in seconds.
	 * @return string|WP_Error
	 */
	private static function chat_request( array $messages, array $config, int $timeout = 90 ) {
		if ( '' === trim( (string) ( $config['api_key'] ?? '' ) ) ) {
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

		if ( preg_match( '/\[[\s\S]*\]/', $trimmed, $json_match ) ) {
			$decoded = json_decode( $json_match[0], true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return null;
	}

	/**
	 * Whether array is a consecutive 0-indexed list (PHP 8.1 array_is_list polyfill).
	 *
	 * @param array<mixed> $arr Array.
	 */
	private static function is_list_array( array $arr ): bool {
		if ( function_exists( 'array_is_list' ) ) {
			return array_is_list( $arr );
		}

		if ( array() === $arr ) {
			return true;
		}

		return array_keys( $arr ) === range( 0, count( $arr ) - 1 );
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
تو Chemist + Scientific Technical Writer + SEO Copywriter برای فروشگاه مواد شیمیایی لوک آزما (https://lookazma.com/) هستی.
خروجی: فقط HTML نهایی توضیح محصول به فارسی.

هدف: متنی با همان ظاهر، عمق، ریتم و کیفیت نمونه مرجع لوک آزما (صفحه 2-پروپانول گرید Extra Pure برند دکتر مجللی) بنویس؛ ولی محتوای هر محصول باید کاملاً اختصاصی، علمی و غیرتکراری باشد.

لحن کل متن (اجباری):
- تخصصی، علمی و آزمایشگاهی — مثل Technical Writer شیمی برای مخاطب QC / R&D / آزمایشگاه.
- دقیق و قابل دفاع؛ کلی‌گویی مبهم، روایت احساسی و لحن بازاریابی ممنوع.
- هرجا مرتبط است از اصطلاحات فنی درست استفاده کن (و انگلیسی نگه دار): Batch-to-Batch Consistency، QC، impurity profile، Sample Preparation، HPLC، GC، LC-MS، ICP و مشابه — فقط اگر برای همان ماده/گرید علمی درست باشد.
- ادعاهای اغراق‌آمیز ممنوع: بهترین، بی‌نظیر، فوق‌العاده، محبوب‌ترین، عالی‌ترین، ادعای پزشکی/درمانی.

=========================
Style Lock — فقط فرم، نه جملات
=========================
الگوی ظاهری اجباری (دقیقاً همین ترتیب و عمق):

1) عنوان کامل در <h2>:
[نام فارسی] ([نام انگلیسی در صورت وجود]) گرید [X] برند [Y]

2) معرفی — دقیقاً یک <p> غنی (حدود ۸۰ تا ۱۴۰ کلمه)
شامل موارد موجود در داده: معرفی علمی ماده + نام انگلیسی + گرید + خلوص + CAS + فهرست بسته‌بندی‌ها + کاربرد کلی آزمایشگاهی/صنعتی.
بدون لیست در این بخش. لحن علمی و فشرده؛ از تعاریف مبهم یا شعاری پرهیز کن.

3) <h3> چرا [نام ماده] برند [Brand]؟
- در تیتر و در پاراگراف این بخش، فقط نام برند را با <strong>…</strong> برجسته کن (مثلاً <strong>دکتر مجللی</strong>).
- نام برند را در بقیه بخش‌های متن (معرفی، مشخصات، کاربردها و …) strong نکن.
- ساختار اجباری همین است (فرم را عوض نکن):
  الف) دقیقاً یک <p> تخصصی درباره اعتبار فنی برند — نه داستان‌سرایی تبلیغاتی.
  ب) دقیقاً این انتقال: <p>از مهم‌ترین مزایای این محصول می‌توان به موارد زیر اشاره کرد:</p>
  ج) <ul> با دقیقاً ۵ آیتم <li> مشخص، فنی و وابسته به همین محصول/برند.
- تمایز برند (اجباری):
  1) برای هر برند یک «داستان فنی یکتا» انتخاب کن و همان را محور پاراگراف قرار بده؛ بین محصولات/برندها این زاویه را عوض کن.
     مثال زاویه (فقط یکی، و فقط اگر منطقی باشد): مستندات دیجیتال/CoA و ردیابی لات، یکپارچگی بسته‌بندی و آب‌بندی، فرهنگ کنترل ناخالصی، پایداری تأمین برای آزمایشگاه، و مشابه.
  2) به‌جای شعار کلی «کنترل کیفیت»، یک جنبه ملموس و قابل تصور برجسته کن (مثلاً جنس درب/آب‌بندی، فرمت Certificate of Analysis، برچسب‌گذاری لات، قابلیت ردیابی بچ) — کلی‌گویی مبهم ممنوع.
  3) هر مزیت را مستقیم به درد آزمایشگاه و کاربر نهایی وصل کن: چگونه خطای اندازه‌گیری، آلودگی متقاطع، تکرار آزمایش، یا اتلاف زمان را کم می‌کند.
  4) ترتیب و نقطه شروع ۵ بولت را کاملاً بین محصولات عوض کن؛ اگر یکی با Batch-to-Batch Consistency شروع شد، دیگری مثلاً با سرعت/شفافیت مستندات، یکپارچگی بسته‌بندی، یا ردیابی لات شروع شود — الگوی ثابت بولت‌ها ممنوع.
- تقسیم نقش (ضد تکرار):
  • پاراگراف = همان داستان فنی یکتای برند؛ فهرست مزایا را اینجا ردیف نکن.
  • بولت‌ها = ۵ فایده فنی مجزا؛ بازنویسی یا خلاصه‌کردن همان پاراگراف ممنوع.
  • یک ادعا فقط در یکی از دو جا بیاید نه هر دو.
- مزایای اختصاصی گرید را اینجا ننویس (مربوط به بخش ۴ است).
- چیزی invent نکن؛ اگر جزئیات ملموس برند در داده نیست، از دانش عمومی معتبر همان برند در حد محتاطانه استفاده کن یا روی فایده آزمایشگاهی کلیِ قابل دفاع بمان.

4) <h3> گرید [X] چه مزیتی دارد؟
اگر گرید نیست، کل بخش حذف شود.
- در تیتر و متن این بخش، فقط نام گرید را با <strong>…</strong> برجسته کن (مثلاً <strong>Extra Pure</strong>).
- نام گرید را در بخش‌های دیگر متن به‌صورت پیش‌فرض strong نکن.
- یک <p> علمی کامل درباره معنای گرید، کنترل ناخالصی‌ها، استاندارد/کاربردهای آنالیزی مناسب.
- سپس دقیقاً این انتقال: <p>مزایای استفاده از [نام ماده] گرید <strong>[X]</strong> عبارت‌اند از:</p>
- سپس <ul> با دقیقاً ۵ آیتم <li> اختصاصی همین گرید (فنی، نه شعاری).
- در صورت مرتبط بودن، یک <p> با شروع <strong>نکته:</strong> و ادامه متن هشدار علمی درباره محدودیت گرید نسبت به تکنیک‌های دستگاهی حساس (مثل GC، HPLC، LC-MS، ICP / Instrument Grade / HPLC Grade) — فقط اگر از نظر علمی درست باشد.
Extra Pure ≠ HPLC Grade و Extra Pure ≠ GC Grade مگر در داده صریحاً آمده باشد.

5) <h3> کاربردهای [نام ماده] ([انگلیسی اگر هست]) گرید [X]
- یک <p> مقدمه کوتاه علمی درباره جایگاه ماده در چارچوب همین گرید.
- سپس ۵ تا ۷ کاربرد شماره‌دار. برای هر کاربرد دقیقاً این فرمت (عنوان بزرگ مثل تیتر، توضیح مثل زیرتیتر):
  <h2>N. عنوان کاربرد</h2>
  <p>توضیح ۱ تا ۳ جمله‌ای تخصصی که چرا این ماده/گرید برای این کاربرد مناسب است (مکانیسم/نقش حلال یا معرف را در حد علمی مختصر بگو).</p>
- عنوان هر کاربرد باید تیتر بزرگ (<h2>) باشد و توضیح بلافاصله زیر آن در <p> بیاید (نه داخل همان تیتر).
کاربردها باید اختصاصی ماهیت همین ماده و همین گرید باشند. مثال‌های اختصاصی نمونه مرجع (PCB، لوسیون، ضدعفونی پزشکی و …) را کپی نکن مگر برای همین ماده واقعاً مرتبط باشند.

6) <h3> مشخصات محصول
یک <p> جمع‌بندی فقط از داده‌های موجود (CAS، خلوص، بسته‌بندی، گرید، برند، کشور و …). مقدار ساختگی ننویس.

7) <h3> مشاهده سایر محصولات [نام ماده]
یک <p> کوتاه برای دعوت به مقایسه برند/گرید/بسته‌بندی + دقیقاً یک لینک.
فرمت اجباری لینک:
<a class="lk-cat-archive-btn" href="URL_دسته_انتخاب‌شده">دسته بندی نام‌دسته</a>
یعنی متن قابل‌نمایش لینک = «دسته بندی » + نام دسته انتخاب‌شده (با یک فاصله).
مثال درست: اگر دسته «اسیداستیک گلاسیال» است → <a class="lk-cat-archive-btn" href="https://.../">دسته بندی اسیداستیک گلاسیال</a>
ممنوع: متن لینک برابر URL، برابر «...»، فقط نام دسته بدون پیشوند «دسته بندی»، یا نام کامل محصول با گرید/برند.

=========================
ممنوعیت کپی / تکراری بودن
=========================
- نمونه مرجع فقط معیار ظاهر و عمق است؛ کپی یا نزدیک‌نویسی جملات آن ممنوع است.
- متنی که فقط با عوض کردن نام ماده از نمونه ساخته شود مردود است.
- اگر دو محصول فقط در برند یا گرید فرق دارند، بخش برند/گرید/کاربرد باید واقعاً بازنویسی شود.
- شروع جمله‌ها، مثال‌های کاربردی و متن بولت‌ها را برای هر محصول متفاوت بنویس.

=========================
منبع حقیقت
=========================
فقط داده‌های پیام کاربر:
- نام محصول
- CAS No
- نام برند (ممکن است URL/مسیر تصویر باشد؛ در آن صورت از ویژگی «برند» یا پرانتز نام محصول استفاده کن)
- ویژگی‌ها به شکل «برچسب: مقدار» جدا شده با |
- دسته‌ها + URL
- URL دسته پیشنهادی

CAS، خلوص، بسته‌بندی، فرمول، کشور و مشابه را فقط اگر در داده هست بنویس؛ invent نکن.
دانش عمومی فقط برای توضیح علمی ماده و مفهوم شناخته‌شده همان گرید مجاز است.

=========================
تحلیل داخلی (چاپ نکن)
=========================
نوع ماده (حلال/اسید/باز/نمک/معرف/…)، ویژگی فیزیکوشیمیایی اصلی، کاربردهای واقعی آزمایشگاهی، محدودیت گرید را مشخص کن؛ سپس بنویس.

=========================
لینک دسته
=========================
خاص‌ترین دسته ماده را انتخاب کن (عمیق‌ترین و نزدیک‌ترین به نام محصول).
href را عیناً از URL همان دسته در لیست کپی کن؛ جعلی نساز.
متن قابل‌نمایش لینک (anchor text) باید دقیقاً این باشد: «دسته بندی » + نام همان دسته انتخاب‌شده (فیلد نام در لیست دسته‌ها).
مثال: دسته بندی اسیداستیک گلاسیال
ممنوع: فقط نام دسته بدون «دسته بندی»، URL به‌عنوان متن لینک، یا «...».
اگر فقط یک دسته بود همان؛ اگر دسته‌ای نبود: href=https://lookazma.com/ و متن لینک = «دسته بندی لوک آزما».
در کل متن فقط یک لینک، در بخش آخر.

=========================
سئو و حجم
=========================
طول هدف: هم‌تراز نمونه مرجع (حدود ۶۵۰ تا ۹۵۰ کلمه).
نام ماده و مترادف‌های علمی را طبیعی پخش کن؛ Keyword Stuffing ممنوع.
اصطلاحات تخصصی مرتبط را انگلیسی نگه دار: Batch-to-Batch Consistency، QC، R&D، HPLC، GC، LC-MS، ICP، Recrystallization، Sample Preparation و مشابه.

قوانین برجسته‌سازی (مهم):
- <strong> فقط برای: نام برند در بخش «چرا برند…»، نام گرید در بخش «گرید … چه مزیتی دارد؟»، و کلمه «نکته:» در هشدار گرید.
- نام برند/گرید را در معرفی، مشخصات و سایر بخش‌ها strong نکن.
- هر کاربرد شماره‌دار باید با <h2>N. عنوان</h2> بیاید و توضیح آن بلافاصله در <p> زیر همان تیتر باشد (تیتر بزرگ + توضیح زیرتیتر).
- عنوان اصلی محصول فقط یک بار در ابتدای متن با <h2> است؛ تیترهای کاربرد هم <h2> هستند و این عمدی است تا شبیه نمونه مرجع دیده شوند.

تگ‌های مجاز: h2, h3, p, ul, li, strong, a
بدون Markdown، JSON، مقدمه، یا توضیح درباره Prompt/AI.
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
			"اطلاعات محصول (منبع حقیقت — چیزی خارج از این اضافه نکن):\n- نام محصول: %s\n- CAS No: %s\n- نام برند (مقدار خام؛ اگر URL یا مسیر تصویر بود نادیده بگیر): %s\n- ویژگی‌ها (برچسب: مقدار، جدا شده با |): %s\n\nدسته‌های محصول (یکی را انتخاب کن):\n%s\n\nپیشنهاد سیستم برای مناسب‌ترین دسته (در صورت تردید از همین استفاده کن):\n%s\n\nظاهر و عمق را مثل نمونه مرجع لوک آزما نگه دار، ولی جملات و کاربردها را کاملاً برای همین محصول بنویس. فقط HTML نهایی را برگردان. در بخش آخر لینک دسته را با href برابر URL دسته و متن قابل‌نمایش برابر «دسته بندی » + نام همان دسته بگذار (نه URL و نه سه نقطه).",
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
	 * Messages for brand/grade rewrite of an existing product description HTML.
	 *
	 * @param array{name: string, cas_no: string, brand: string, attributes: string, description: string, categories?: array, category_url?: string} $product_data Product + HTML.
	 * @return array<int, array{role: string, content: string}>
	 */
	private static function build_refine_brand_grade_messages( array $product_data ): array {
		$system = <<<'PROMPT'
تو یک شیمیدان متخصص، کارشناس مواد شیمیایی آزمایشگاهی، نویسنده فنی (Technical Writer)، متخصص SEO و آشنا با استانداردهای بین‌المللی مواد شیمیایی هستی.

وظیفه تو بازنویسی محتوای کاملاً اختصاصی، علمی، سئومحور و منطبق با EEAT برای توضیح HTML موجود یک محصول فروشگاهی مواد شیمیایی لوک آزما است — بر اساس برند و گرید همان محصول.

ورودی: HTML توضیح فعلی + داده‌های محصول (نام، برند، ویژگی‌ها/گرید، CAS، دسته).
خروجی: فقط HTML نهایی بازنویسی‌شده به فارسی (بدون Markdown، JSON، مقدمه یا توضیح اضافه).

=========================
قوانین اصلی
=========================

۱) ابتدا گرید را تحلیل کن (چاپ نکن):
- این گرید برای چه کاربردی تولید شده؟
- چه استانداردی را رعایت می‌کند؟
- چه ناخالصی‌هایی کنترل می‌شوند؟
- مهم‌ترین مزیت و محدودیت‌ها چیست؟
- مناسب چه صنایع/آزمایشگاه‌هایی است؟
سپس متن را فقط بر اساس همین تحلیل بازنویسی کن.
اگر اطلاعات کافی نبود، ویژگی علمی/فنی را حدس نزن.

۲) معرفی ماده:
- هسته معرفی را حفظ کن.
- فقط اشتباه علمی یا نگارشی را اصلاح کن.
- ویژگی برند یا گرید را در این بخش اضافه نکن.

۳) بخش «چرا برند …»:
- فقط درباره برند باشد: سابقه، کیفیت تولید، کنترل کیفیت، Batch-to-Batch Consistency، مستندات فنی، بسته‌بندی، اعتبار، حوزه‌های مصرف.
- ممنوع: بهترین، بی‌نظیر، فوق‌العاده، عالی‌ترین کیفیت و مشابه.

۴) بخش «گرید … چه مزیتی دارد؟» (مهم‌ترین بخش):
کاملاً اختصاصی همین گرید: تعریف علمی، دلیل ایجاد، استاندارد، کنترل کیفیت، مزیت نسبت به گریدهای عمومی، کاربردهای آنالیزی/صنعتی مناسب، محدودیت‌ها.
ویژگی سایر گریدها را به این گرید نسبت نده.

۵) کاربردها:
بر اساس همان گرید نوشته شوند نه فقط خود ماده.
مثال راهنما (فقط اگر مرتبط):
- HPLC → Mobile Phase، Sample Preparation، Method Validation
- GC → GC، GC-MS، Calibration، Standard Preparation
- UV → UV-Vis، Blank Solution، Spectrophotometry
- USP → Pharmaceutical QC/Manufacturing/Formulation
- ACS → General Laboratory، Analytical Chemistry، Research
- LC-MS → LC-MS/MS، Trace Analysis
- Molecular Biology → DNA، RNA، PCR، Cell Biology
- Trace Metal → ICP، ICP-MS، Metal Analysis
کاربرد غیرمرتبط ننویس.

۶) مشخصات محصول:
فقط اطلاعات موجود در ورودی را بازنویسی کن؛ چیز جدید invent نکن.
اگر چیزی در ورودی نیست، حذف کن.

۷) مشاهده سایر محصولات:
سئومحور؛ دعوت به مقایسه برند/گرید/بسته‌بندی/خلوص.
لینک دسته (اگر در HTML ورودی هست) را با همان href و متن «دسته بندی …» حفظ کن؛ class="lk-cat-archive-btn" را اگر بود نگه دار.
href جعلی نساز.

=========================
جلوگیری از تکراری بودن
=========================
متن باید بر اساس برند و گرید از نو و اختصاصی نوشته شود؛ صرفاً بازنویسی ادبی سطحی نباشد.
اگر دو محصول فقط در برند/گرید فرق دارند، بخش برند/گرید/کاربرد باید واقعاً متفاوت باشد.
هر ادعای علمی باید با ویژگی واقعی همان گرید سازگار باشد.

=========================
قالب خروجی HTML
=========================
همان اسکلت ورودی را حفظ کن (ترتیب بخش‌ها، تعداد تقریبی بولت‌ها و کاربردهای شماره‌دار):
- عنوان در <h2>
- معرفی در <p>
- <h3> چرا برند … + <p> + انتقال + <ul><li>…
- <h3> گرید … چه مزیتی دارد؟ + محتوا (اگر در ورودی نبود، اضافه نکن)
- کاربردها با <h2>N. عنوان</h2> + <p> توضیح
- <h3> مشخصات محصول
- <h3> مشاهده سایر محصولات + لینک

تگ‌های مجاز: h2, h3, p, ul, li, strong, a
<strong> فقط مثل الگوی ورودی (برند در بخش برند، گرید در بخش گرید، و «نکته:» در صورت وجود).
بدون Markdown و بدون متن خارج از HTML.
PROMPT;

		$suggested = ! empty( $product_data['category_url'] )
			? (string) $product_data['category_url']
			: 'https://lookazma.com/';

		$user = sprintf(
			"بازنویسی بر اساس برند و گرید — داده‌های محصول:\n- نام محصول: %s\n- CAS No: %s\n- نام برند (مقدار خام؛ اگر URL/مسیر تصویر بود نادیده بگیر و از ویژگی‌ها/نام محصول استفاده کن): %s\n- ویژگی‌ها (برچسب: مقدار): %s\n- URL دسته پیشنهادی: %s\n\nHTML توضیح فعلی (منبع بازنویسی):\n%s\n\nفقط HTML نهایی بازنویسی‌شده را برگردان.",
			$product_data['name'] ?: '—',
			$product_data['cas_no'] ?: '—',
			$product_data['brand'] ?: '—',
			$product_data['attributes'] ?: '—',
			$suggested,
			$product_data['description'] ?: ''
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
	 * Normalize Q&A items from model JSON.
	 *
	 * @param array<string, mixed> $parsed Parsed JSON.
	 * @return array<int, array{q: string, a: string}>
	 */
	private static function normalize_category_qa_items( array $parsed ): array {
		$raw = array();

		if ( isset( $parsed['items'] ) && is_array( $parsed['items'] ) ) {
			$raw = $parsed['items'];
		} elseif ( isset( $parsed['qa'] ) && is_array( $parsed['qa'] ) ) {
			$raw = $parsed['qa'];
		} elseif ( self::is_list_array( $parsed ) ) {
			$raw = $parsed;
		}

		$items = array();
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$q = '';
			if ( isset( $row['q'] ) && is_scalar( $row['q'] ) ) {
				$q = trim( (string) $row['q'] );
			} elseif ( isset( $row['question'] ) && is_scalar( $row['question'] ) ) {
				$q = trim( (string) $row['question'] );
			}

			$a = '';
			if ( isset( $row['a'] ) && is_scalar( $row['a'] ) ) {
				$a = trim( (string) $row['a'] );
			} elseif ( isset( $row['answer'] ) && is_scalar( $row['answer'] ) ) {
				$a = trim( (string) $row['answer'] );
			}

			if ( '' === $q || '' === $a ) {
				continue;
			}

			$items[] = array(
				'q' => $q,
				'a' => $a,
			);
		}

		return $items;
	}

	/**
	 * Call 1: 50 expert Q&As with short one-paragraph answers (JSON).
	 *
	 * @param array{name: string, slug: string, description: string, parent_name: string} $data Category data.
	 * @return array<int, array{role: string, content: string}>
	 */
	private static function build_category_qa_messages( array $data ): array {
		$system = <<<'PROMPT'
تو یک شیمیدان خبره و استاد دانشگاه هستی که باید دانش تخصصی را برای فروشگاه مواد شیمیایی لوک آزما تولید کنی.

وظیفه (مرحله اول — فقط دانش خام):
دقیقاً ۵۰ سوال تخصصی سطح بالا درباره موضوع دسته بساز و به هر کدام یک پاسخ کوتاه و تخصصی بده.

قوانین پاسخ:
- هر پاسخ دقیقاً یک پاراگراف کوتاه (حدود ۲ تا ۴ جمله / حدود ۴۰ تا ۸۰ کلمه)
- بدون لیست، بدون چند پاراگراف، بدون HTML، بدون Markdown
- لحن علمی و دقیق؛ واحدها SI؛ نام‌های استاندارد
- هر سوال زاویه متمایز داشته باشد؛ سوال تکراری یا نزدیک به هم ممنوع
- عدد، CAS، نقطه جوش/ذوب و ادعاهای کمی را فقط اگر مطمئن هستی بنویس؛ در غیر این صورت حدس نزن و در پاسخ بگو اطلاعات معتبر در دسترس نیست
- برای دسته گروهی (مثل حلال‌ها) روی خانواده/انتخاب/کاربرد تمرکز کن؛ برای ماده مشخص روی خود ماده

پوشش موضوعی (تقریباً متعادل بین این‌ها):
هویت و نام‌گذاری، خواص فیزیکی/شیمیایی، کاربرد آزمایشگاهی و صنعتی، گریدها و خلوص، ایمنی و نگهداری، نکات خرید و بسته‌بندی، مقایسه با مواد مشابه، محدودیت‌ها و اشتباهات رایج

خروجی فقط JSON معتبر، بدون متن اضافه و بدون بلوک کد:
{
  "material": "نام دسته",
  "items": [
    {"q": "سوال؟", "a": "پاسخ کوتاه یک‌پاراگرافی."}
  ]
}
دقیقاً ۵۰ آیتم در items.
PROMPT;

		$user = sprintf(
			"موضوع دسته:\n- نام: %s\n- اسلاگ: %s\n- دسته والد: %s\n\nبرای این موضوع دقیقاً ۵۰ سوال و جواب تخصصی با پاسخ‌های کوتاه یک‌پاراگرافی بساز. فقط JSON را برگردان.",
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
	 * Call 2: SEO HTML article from Q&A answers only.
	 *
	 * @param array{name: string, slug: string, description: string, parent_name: string} $data       Category data.
	 * @param string                                                                     $qa_payload JSON string of Q&A items.
	 * @return array<int, array{role: string, content: string}>
	 */
	private static function build_category_article_from_qa_messages( array $data, string $qa_payload ): array {
		$has_current = '' !== trim( (string) ( $data['description'] ?? '' ) );

		$system = <<<'PROMPT'
تو متخصص شیمی، نویسنده علمی، کارشناس سئو و EEAT برای فروشگاه مواد شیمیایی لوک آزما (https://lookazma.com/) هستی.
وظیفه (مرحله دوم): از روی JSON سوال‌وجواب مرحله قبل، یک مقاله/توضیح دسته حرفه‌ای، نسبتاً بلند و سئوفرندلی به فارسی بنویس که بالای لیست محصولات ووکامرس قابل انتشار باشد.

قوانین محتوا:
- سوالات را چاپ نکن؛ فقط از پاسخ‌ها به‌عنوان منبع استفاده کن
- پاسخ‌های هم‌پوشان را ادغام و تکراری‌ها را حذف کن
- عنوان‌های بخش‌ها غنی و توصیفی باشند (نه تک‌کلمه)
- بدون اغراق و بدون افزودن واقعیت ساختگی فراتر از JSON؛ اگر چیزی در JSON نیست اختراع نکن
- کلمات کلیدی (نام دسته، خرید، کاربرد، گرید، آزمایشگاهی) طبیعی پخش شوند
- حجم هدف: حدود ۱۴۰۰ تا ۲۰۰۰ کلمه (کوتاه‌تر از این ننویس مگر JSON واقعاً ضعیف باشد)
- هر بخش اصلی حداقل ۲ تا ۴ پاراگراف substantive داشته باشد؛ لیست‌ها مکمل پاراگراف‌اند نه جایگزین

عنوان اصلی (اجباری):
- دقیقاً یک <h1> در ابتدای مطلب
- عنوان ساده مثل فقط نام ماده/دسته ممنوع است
- عنوان باید سئوپسند، حرفه‌ای و جذاب باشد؛ ترکیبی از نام دسته + کاربرد/کاربرد آزمایشگاهی یا صنعتی + ارزش برای خریدار
- مثال الگو (فقط الگو؛ کپی نکن): «خرید [نام] آزمایشگاهی؛ راهنمای گرید، کاربرد و انتخاب»
- طول عنوان حدود ۸ تا ۱۶ کلمه

تشخیص نوع دسته:
A) ماده مشخص: ماده‌محور
B) گروه/خانواده: گروهی؛ CAS تک‌ماده را اجباری نکن

ساختار خروجی HTML (همین ترتیب؛ فقط بخش‌های مرتبط):
1) <h1> عنوان سئوپسند غنی (طبق قوانین بالا)
2) <h2> معرفی [نام دسته] و جایگاه آن — ۲ تا ۳ پاراگراف کامل
3) <h2> مشخصات و ویژگی‌های مهم — پاراگراف‌های کافی + در صورت مفید <ul> یا <table>
4) <h2> کاربردها در آزمایشگاه و صنعت — مقدمه + <ul> با ۸ تا ۱۲ کاربرد واقعی + توضیح کوتاه هر مورد در صورت امکان
5) <h2> گریدها، خلوص و راهنمای خرید — نکات عملی انتخاب برای خریدار
6) <h2> نگهداری، ایمنی و نکات عملی — خلاصه کاربردی؛ بدون MSDS کامل
7) <h2> سوالات متداول — دقیقاً ۵ تا ۷ مورد:
   <h3>سؤال؟</h3><p>پاسخ حدود ۶۰ تا ۱۲۰ کلمه؛ تکرار بخش‌های قبل ممنوع.</p>
8) <h2> خرید [نام دسته] از لوک آزما
   دعوت کامل‌تر به مقایسه برند/گرید/بسته‌بندی و استعلام با لینک دقیقاً:
   <a href="https://lookazma.com/">لوک آزما</a>

قوانین سخت خروجی:
- فقط HTML تمیز: h1, h2, h3, p, ul, ol, li, table, thead, tbody, tr, th, td, strong, em, a
- بدون Markdown، JSON، Meta Description، بلوک کد، توضیح اضافه
- فقط یک h1؛ برای زیربخش‌های FAQ از h3 استفاده کن
- لحن علمی، حرفه‌ای، قابل فهم؛ پاراگراف‌ها متوسط (نه خیلی کوتاه)
PROMPT;

		$current_block = $has_current
			? "\n\nتوضیح فعلی دسته (در صورت سازگاری با JSON، نکات درست را حفظ و تقویت کن):\n" . (string) $data['description']
			: '';

		$user = sprintf(
			"اطلاعات دسته:\n- نام: %s\n- اسلاگ: %s\n- دسته والد: %s%s\n\nJSON سوال و جواب مرحله اول (منبع):\n%s\n\nیک مقاله کامل و نسبتاً بلند با <h1> سئوپسند غنی (نه فقط نام دسته) و ساختار HTML اجباری بنویس. حدود ۱۴۰۰ تا ۲۰۰۰ کلمه. فقط HTML نهایی را برگردان.",
			$data['name'] ?: '—',
			$data['slug'] ?: '—',
			$data['parent_name'] ?: '—',
			$current_block,
			$qa_payload
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
