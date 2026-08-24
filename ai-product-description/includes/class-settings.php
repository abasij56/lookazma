<?php
/**
 * Admin settings for AI providers.
 *
 * @package AI_Product_Description
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers multi-provider AI credentials settings.
 */
final class AI_Product_Desc_Settings {

	public const OPTION_KEY = 'ai_product_desc_settings';
	public const PAGE_SLUG  = 'ai-product-desc-settings';
	public const CAPABILITY = 'manage_options';

	/**
	 * Hook into WordPress admin.
	 */
	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * Resolve provider config from AJAX POST (unsaved form) or saved settings.
	 *
	 * @return array{id: string, label: string, api_key: string, base_url: string, model: string}|WP_Error
	 */
	public static function resolve_provider_config_from_request() {
		$providers = self::get_providers();
		$settings  = self::get_settings();

		$provider_id = isset( $_POST['provider_id'] ) ? sanitize_key( wp_unslash( (string) $_POST['provider_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( '' === $provider_id ) {
			$provider_id = $settings['active_provider'];
		}

		if ( ! isset( $providers[ $provider_id ] ) ) {
			return new WP_Error(
				'invalid_provider',
				__( 'ارائه‌دهنده نامعتبر است.', 'ai-product-description' )
			);
		}

		$saved = $settings['providers'][ $provider_id ] ?? $providers[ $provider_id ]['defaults'];

		$api_key  = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['api_key'] ) ) : (string) $saved['api_key']; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$base_url = isset( $_POST['base_url'] ) ? esc_url_raw( trim( wp_unslash( (string) $_POST['base_url'] ) ) ) : (string) $saved['base_url']; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$model    = isset( $_POST['model'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['model'] ) ) : (string) $saved['model']; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		return array(
			'id'       => $provider_id,
			'label'    => $providers[ $provider_id ]['label'],
			'api_key'  => $api_key,
			'base_url' => $base_url,
			'model'    => $model,
		);
	}

	/**
	 * Build a shareable diagnostic report for host/support (no secrets).
	 *
	 * @param array{id: string, label: string, api_key: string, base_url: string, model: string} $config Provider config.
	 * @param bool                                                                              $success Whether test succeeded.
	 * @param string                                                                            $message Human-readable result.
	 * @param array<string, mixed>                                                              $extra   Optional http/code/reply.
	 */
	public static function build_connection_test_report( array $config, bool $success, string $message, array $extra = array() ): string {
		$ref = 'LKAI-' . gmdate( 'Ymd-His' ) . '-' . strtoupper( substr( md5( home_url( '/' ) . (string) ( $extra['ts'] ?? time() ) . $config['id'] ), 0, 6 ) );

		$base_host = '';
		if ( '' !== trim( $config['base_url'] ) ) {
			$parsed = wp_parse_url( $config['base_url'] );
			$base_host = is_array( $parsed ) && ! empty( $parsed['host'] ) ? (string) $parsed['host'] : $config['base_url'];
		}

		$lines = array(
			'LK-AI-TEST',
			'ref=' . $ref,
			'time=' . gmdate( 'c' ),
			'site=' . home_url( '/' ),
			'plugin=ai-product-description/' . AI_PRODUCT_DESC_VERSION,
			'provider=' . $config['id'] . ' (' . $config['label'] . ')',
			'model=' . ( '' !== $config['model'] ? $config['model'] : '(empty)' ),
			'base_host=' . ( '' !== $base_host ? $base_host : '(empty)' ),
			'status=' . ( $success ? 'ok' : 'fail' ),
		);

		if ( isset( $extra['http'] ) && is_scalar( $extra['http'] ) ) {
			$lines[] = 'http=' . (string) $extra['http'];
		}
		if ( isset( $extra['code'] ) && is_scalar( $extra['code'] ) ) {
			$lines[] = 'code=' . (string) $extra['code'];
		}
		if ( '' !== $message ) {
			$lines[] = 'message=' . $message;
		}
		if ( $success && ! empty( $extra['reply'] ) && is_scalar( $extra['reply'] ) ) {
			$reply = trim( (string) $extra['reply'] );
			if ( strlen( $reply ) > 120 ) {
				$reply = substr( $reply, 0, 117 ) . '...';
			}
			$lines[] = 'reply=' . $reply;
		}

		return implode( "\n", $lines );
	}

	/**
	 * Persian explanation of a connection-test failure for the admin UI.
	 *
	 * @param string     $code        WP_Error code.
	 * @param string     $raw_message Raw error message from API or WordPress.
	 * @param int|null   $http        Optional HTTP status code.
	 */
	public static function get_connection_error_explanation( string $code, string $raw_message = '', $http = null ): string {
		$http      = is_numeric( $http ) ? (int) $http : 0;
		$raw_lower = strtolower( $raw_message );

		switch ( $code ) {
			case 'missing_api_key':
				return __(
					'دلیل: فیلد API Key برای ارائه‌دهنده انتخاب‌شده خالی است.

راه‌حل: از پنل ارائه‌دهنده (مثل Google AI Studio، OpenAI یا GapGPT) یک کلید API بسازید، در فیلد API Key همین بخش وارد کنید و دوباره «تست اتصال AI» را بزنید.',
					'ai-product-description'
				);

			case 'missing_base_url':
				return __(
					'دلیل: آدرس پایه (Base URL) وارد نشده است.

راه‌حل: آدرس API ارائه‌دهنده را در فیلد Base URL قرار دهید. برای Gemini معمولاً:
https://generativelanguage.googleapis.com/v1beta/openai',
					'ai-product-description'
				);

			case 'missing_model':
				return __(
					'دلیل: نام مدل (Model) وارد نشده است.

راه‌حل: نام مدل پشتیبانی‌شده را وارد کنید — مثلاً gemini-2.0-flash برای Gemini یا gpt-4o برای OpenAI.',
					'ai-product-description'
				);

			case 'invalid_provider':
				return __(
					'دلیل: ارائه‌دهنده انتخاب‌شده در سیستم شناخته نمی‌شود.

راه‌حل: از منوی «Active provider» یکی از گزینه‌های موجود را انتخاب کنید و دوباره تست کنید.',
					'ai-product-description'
				);

			case 'empty_ai_response':
				return __(
					'دلیل: ارتباط برقرار شد اما پاسخ API خالی بود.

راه‌حل: نام Model را بررسی کنید یا چند دقیقه بعد دوباره تست کنید. اگر تکرار شد، گزارش زیر را برای پشتیبانی ارسال کنید.',
					'ai-product-description'
				);
		}

		if ( $http > 0 ) {
			switch ( $http ) {
				case 401:
					return __(
						'دلیل: کلید API نامعتبر، اشتباه یا منقضی شده است (خطای 401).

راه‌حل: کلید را از پنل ارائه‌دهنده دوباره کپی کنید (بدون فاصله اضافه). مطمئن شوید کلید مربوط به همان سرویس (Gemini / OpenAI / GapGPT) است.',
						'ai-product-description'
					);

				case 403:
					return __(
						'دلیل: دسترسی رد شد (خطای 403). ممکن است کلید API محدودیت داشته باشد، مدل برای حساب شما فعال نباشد، یا IP سرور سایت مسدود شده باشد.

راه‌حل: در پنل ارائه‌دهنده محدودیت‌های API Key و دسترسی به مدل را بررسی کنید. اگر مشکل ادامه داشت، گزارش را برای پشتیبانی هاست بفرستید.',
						'ai-product-description'
					);

				case 404:
					return __(
						'دلیل: آدرس API یا مدل پیدا نشد (خطای 404).

راه‌حل: Base URL و نام Model را با مستندات ارائه‌دهنده مقایسه کنید. برای Gemini آدرس باید شامل /v1beta/openai باشد.',
						'ai-product-description'
					);

				case 429:
					return __(
						'دلیل: تعداد درخواست‌ها از حد مجاز API بیشتر شده (خطای 429).

راه‌حل: چند دقیقه صبر کنید و دوباره تست کنید. در پنل ارائه‌دهنده سقف استفاده (quota) حساب خود را بررسی کنید.',
						'ai-product-description'
					);

				case 500:
				case 502:
				case 503:
				case 504:
					return __(
						'دلیل: سرور ارائه‌دهنده هوش مصنوعی موقتاً در دسترس نیست (خطای سرور).

راه‌حل: چند دقیقه بعد دوباره تست کنید. اگر مدام تکرار می‌شود، وضعیت سرویس ارائه‌دهنده را بررسی کنید.',
						'ai-product-description'
					);
			}
		}

		if ( 'http_request_failed' === $code || false !== strpos( $raw_lower, 'curl error' ) ) {
			if ( false !== strpos( $raw_lower, 'could not resolve host' ) ) {
				return __(
					'دلیل: سرور سایت نتوانست آدرس API را پیدا کند (مشکل DNS).

راه‌حل: Base URL را بررسی کنید. اگر درست است، ممکن است هاست شما DNS خارجی را مسدود کرده باشد — گزارش را برای پشتیبانی هاست ارسال کنید.',
					'ai-product-description'
				);
			}

			if ( false !== strpos( $raw_lower, 'timed out' ) || false !== strpos( $raw_lower, 'timeout' ) ) {
				return __(
					'دلیل: درخواست به API بیش از حد طول کشید و قطع شد (timeout).

راه‌حل: اتصال اینترنت سرور را بررسی کنید. اگر هاست فیلتر دارد یا API از ایران کند پاسخ می‌دهد، از پشتیبانی هاست بخواهید outbound HTTPS را بررسی کند.',
					'ai-product-description'
				);
			}

			if ( false !== strpos( $raw_lower, 'ssl' ) || false !== strpos( $raw_lower, 'certificate' ) ) {
				return __(
					'دلیل: خطای SSL/TLS هنگام اتصال به API.

راه‌حل: Base URL باید با https:// شروع شود. اگر آدرس درست است، ممکن است گواهی SSL روی سرور سایت یا فایروال هاست مشکل ایجاد کند.',
					'ai-product-description'
				);
			}

			return __(
				'دلیل: سرور وردپرس نتوانست به API ارائه‌دهنده وصل شود.

راه‌حل: Base URL را بررسی کنید. اگر درست است، احتمالاً هاست دسترسی خروجی (outbound) به این دامنه را مسدود کرده — گزارش زیر را برای پشتیبانی هاست بفرستید.',
				'ai-product-description'
			);
		}

		if ( 'ai_http_error' === $code ) {
			return __(
				'دلیل: API پاسخ خطا داد. ممکن است کلید API، Base URL یا نام Model اشتباه باشد.

راه‌حل: هر سه فیلد را با مستندات ارائه‌دهنده مقایسه کنید. پیام فنی API در پایین همین باکس نمایش داده می‌شود.',
				'ai-product-description'
			);
		}

		return __(
			'دلیل: اتصال به سرویس هوش مصنوعی برقرار نشد.

راه‌حل: API Key، Base URL و Model را بررسی کنید. اگر مطمئن هستید درست است، گزارش تشخیصی زیر را برای پشتیبانی هاست ارسال کنید.',
			'ai-product-description'
		);
	}

	/**
	 * Persian explanation shown when the connection test succeeds.
	 */
	public static function get_connection_success_explanation(): string {
		return __(
			'اتصال با موفقیت برقرار شد. تنظیمات فعلی (API Key، Base URL و Model) درست است و سرویس هوش مصنوعی به درخواست پاسخ داد.',
			'ai-product-description'
		);
	}

	/**
	 * Short Persian title for connection test result.
	 *
	 * @param bool $success Whether the test passed.
	 */
	public static function get_connection_result_title( bool $success ): string {
		return $success
			? __( 'اتصال برقرار است', 'ai-product-description' )
			: __( 'اتصال برقرار نشد', 'ai-product-description' );
	}

	/**
	 * Optional technical detail line (usually English API text).
	 *
	 * @param string $code        WP_Error code.
	 * @param string $raw_message Raw error message.
	 */
	public static function get_connection_error_detail( string $code, string $raw_message ): string {
		if ( in_array( $code, array( 'missing_api_key', 'missing_base_url', 'missing_model', 'invalid_provider', 'empty_ai_response' ), true ) ) {
			return '';
		}

		return trim( $raw_message );
	}

	/**
	 * Supported AI providers and their default connection fields.
	 *
	 * Add a new provider here when you want another backend later.
	 *
	 * @return array<string, array{label: string, defaults: array<string, string>}>
	 */
	public static function get_providers(): array {
		return array(
			'gapgpt'  => array(
				'label'    => __( 'GapGPT', 'ai-product-description' ),
				'defaults' => array(
					'api_key'  => '',
					'base_url' => 'https://api.gapgpt.app/v1',
					'model'    => 'gpt-4o',
				),
			),
			'gemini'  => array(
				'label'    => __( 'Google Gemini', 'ai-product-description' ),
				'defaults' => array(
					'api_key'  => '',
					'base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
					'model'    => 'gemini-2.0-flash',
				),
			),
			'openai'  => array(
				'label'    => __( 'OpenAI (ChatGPT)', 'ai-product-description' ),
				'defaults' => array(
					'api_key'  => '',
					'base_url' => 'https://api.openai.com/v1',
					'model'    => 'gpt-4o',
				),
			),
			'custom'  => array(
				'label'    => __( 'Custom (OpenAI-compatible)', 'ai-product-description' ),
				'defaults' => array(
					'api_key'  => '',
					'base_url' => '',
					'model'    => '',
				),
			),
		);
	}

	/**
	 * Default full option structure.
	 *
	 * @return array{active_provider: string, providers: array<string, array<string, string>>}
	 */
	public static function get_defaults(): array {
		$providers = array();

		foreach ( self::get_providers() as $id => $provider ) {
			$providers[ $id ] = $provider['defaults'];
		}

		return array(
			'active_provider' => 'gapgpt',
			'providers'       => $providers,
		);
	}

	/**
	 * Merged saved settings with defaults.
	 *
	 * @return array{active_provider: string, providers: array<string, array<string, string>>}
	 */
	public static function get_settings(): array {
		$saved    = get_option( self::OPTION_KEY, array() );
		$defaults = self::get_defaults();

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$settings = wp_parse_args( $saved, $defaults );
		$settings['providers'] = isset( $saved['providers'] ) && is_array( $saved['providers'] )
			? $saved['providers']
			: array();

		foreach ( $defaults['providers'] as $id => $provider_defaults ) {
			$settings['providers'][ $id ] = wp_parse_args(
				$settings['providers'][ $id ] ?? array(),
				$provider_defaults
			);
		}

		if ( ! isset( self::get_providers()[ $settings['active_provider'] ] ) ) {
			$settings['active_provider'] = $defaults['active_provider'];
		}

		return $settings;
	}

	/**
	 * Active provider id + its connection fields.
	 *
	 * @return array{id: string, label: string, api_key: string, base_url: string, model: string}
	 */
	public static function get_active_provider_config(): array {
		$settings  = self::get_settings();
		$providers = self::get_providers();
		$id        = $settings['active_provider'];
		$config    = $settings['providers'][ $id ] ?? $providers[ $id ]['defaults'];

		return array(
			'id'       => $id,
			'label'    => $providers[ $id ]['label'],
			'api_key'  => (string) ( $config['api_key'] ?? '' ),
			'base_url' => (string) ( $config['base_url'] ?? '' ),
			'model'    => (string) ( $config['model'] ?? '' ),
		);
	}

	/**
	 * Register option + sections/fields.
	 */
	public static function register_settings(): void {
		register_setting(
			'ai_product_desc_settings_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => self::get_defaults(),
			)
		);

		add_settings_section(
			'ai_product_desc_provider_section',
			__( 'AI Provider', 'ai-product-description' ),
			array( __CLASS__, 'render_provider_section' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'active_provider',
			__( 'Active provider', 'ai-product-description' ),
			array( __CLASS__, 'render_active_provider_field' ),
			self::PAGE_SLUG,
			'ai_product_desc_provider_section'
		);

		foreach ( self::get_providers() as $provider_id => $provider ) {
			add_settings_section(
				'ai_product_desc_section_' . $provider_id,
				sprintf(
					/* translators: %s: provider name */
					__( '%s connection', 'ai-product-description' ),
					$provider['label']
				),
				static function () use ( $provider_id, $provider ): void {
					self::render_provider_connection_section( $provider_id, $provider['label'] );
				},
				self::PAGE_SLUG
			);

			add_settings_field(
				$provider_id . '_api_key',
				__( 'API Key', 'ai-product-description' ),
				static function () use ( $provider_id ): void {
					self::render_provider_text_field( $provider_id, 'api_key', 'password' );
				},
				self::PAGE_SLUG,
				'ai_product_desc_section_' . $provider_id
			);

			add_settings_field(
				$provider_id . '_base_url',
				__( 'Base URL', 'ai-product-description' ),
				static function () use ( $provider_id ): void {
					self::render_provider_text_field( $provider_id, 'base_url', 'url' );
				},
				self::PAGE_SLUG,
				'ai_product_desc_section_' . $provider_id
			);

			add_settings_field(
				$provider_id . '_model',
				__( 'Model', 'ai-product-description' ),
				static function () use ( $provider_id ): void {
					self::render_provider_text_field( $provider_id, 'model', 'text' );
				},
				self::PAGE_SLUG,
				'ai_product_desc_section_' . $provider_id
			);
		}
	}

	/**
	 * Sanitize saved option.
	 *
	 * @param mixed $input Raw form input.
	 * @return array{active_provider: string, providers: array<string, array<string, string>>}
	 */
	public static function sanitize_settings( $input ): array {
		$defaults = self::get_defaults();
		$output   = $defaults;

		if ( ! is_array( $input ) ) {
			return $output;
		}

		$providers_def = self::get_providers();
		$active        = isset( $input['active_provider'] ) ? sanitize_key( $input['active_provider'] ) : $defaults['active_provider'];

		if ( ! isset( $providers_def[ $active ] ) ) {
			$active = $defaults['active_provider'];
		}

		$output['active_provider'] = $active;
		$output['providers']       = array();

		foreach ( $providers_def as $id => $provider ) {
			$raw = isset( $input['providers'][ $id ] ) && is_array( $input['providers'][ $id ] )
				? $input['providers'][ $id ]
				: array();

			$output['providers'][ $id ] = array(
				'api_key'  => isset( $raw['api_key'] ) ? sanitize_text_field( $raw['api_key'] ) : '',
				'base_url' => isset( $raw['base_url'] ) ? esc_url_raw( trim( (string) $raw['base_url'] ) ) : $provider['defaults']['base_url'],
				'model'    => isset( $raw['model'] ) ? sanitize_text_field( $raw['model'] ) : $provider['defaults']['model'],
			);
		}

		return $output;
	}

	/**
	 * Settings page markup.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'ai-product-description' ) );
		}
		?>
		<div class="wrap ai-product-desc-settings">
			<h1><?php esc_html_e( 'تنظیمات', 'ai-product-description' ); ?></h1>
			<p><?php esc_html_e( 'Configure AI providers for product description generation. Choose an active provider and fill its connection fields.', 'ai-product-description' ); ?></p>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'ai_product_desc_settings_group' );
				do_settings_sections( self::PAGE_SLUG );
				submit_button( __( 'Save settings', 'ai-product-description' ) );
				?>
			</form>
			<?php self::render_test_result_panel(); ?>
		</div>
		<?php
		self::print_connection_test_script();
	}

	/**
	 * Connection test result panel (outside the settings table for visibility).
	 */
	public static function render_test_result_panel(): void {
		?>
		<div id="ai-product-desc-test-result" class="ai-product-desc-test-result" hidden>
			<p id="ai-product-desc-test-message" class="ai-product-desc-test-message"></p>
			<div id="ai-product-desc-test-explanation" class="ai-product-desc-test-explanation" hidden></div>
			<p id="ai-product-desc-test-detail" class="ai-product-desc-test-detail" hidden></p>
			<label for="ai-product-desc-test-report" class="screen-reader-text"><?php esc_html_e( 'گزارش تشخیصی', 'ai-product-description' ); ?></label>
			<textarea id="ai-product-desc-test-report" class="ai-product-desc-test-report" rows="10" readonly></textarea>
			<p>
				<button type="button" class="button" id="ai-product-desc-copy-test-report">
					<?php esc_html_e( 'کپی گزارش', 'ai-product-description' ); ?>
				</button>
				<span id="ai-product-desc-copy-test-status" class="description" hidden></span>
			</p>
		</div>
		<?php
	}

	/**
	 * Inline connection-test script so the button works even if admin-settings.js is cached.
	 */
	public static function print_connection_test_script(): void {
		$config = array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( AI_Product_Desc_Ajax::NONCE_ACTION ),
			'action'  => AI_Product_Desc_Ajax::TEST_CONNECTION,
			'i18n'    => array(
				'testing'    => __( 'در حال تست اتصال…', 'ai-product-description' ),
				'testButton' => __( 'تست اتصال AI', 'ai-product-description' ),
				'error'      => __( 'خطا در تست اتصال.', 'ai-product-description' ),
				'copied'     => __( 'گزارش کپی شد.', 'ai-product-description' ),
				'copyFail'   => __( 'کپی گزارش انجام نشد.', 'ai-product-description' ),
				'failTitle'  => __( 'اتصال برقرار نشد', 'ai-product-description' ),
				'okTitle'    => __( 'اتصال برقرار است', 'ai-product-description' ),
				'invalidJson' => __(
					'دلیل: سرور وردپرس پاسخی برگرداند که قابل پردازش نبود.

راه‌حل: صفحه را رفرش کنید و دوباره تست کنید. اگر تکرار شد، ممکن است افزونه امنیتی یا کش AJAX را مختل کرده باشد.',
					'ai-product-description'
				),
				'networkError' => __(
					'دلیل: درخواست به سرور وردپرس ارسال نشد (مشکل شبکه یا مرورگر).

راه‌حل: اتصال اینترنت را بررسی کنید، صفحه را رفرش کنید و دوباره «تست اتصال AI» را بزنید.',
					'ai-product-description'
				),
				'configError' => __(
					'دلیل: تنظیمات AJAX صفحه به‌درستی بارگذاری نشده است.

راه‌حل: صفحه را با Ctrl+F5 رفرش کنید. اگر مشکل ادامه داشت، کش افزونه یا CDN را پاک کنید.',
					'ai-product-description'
				),
				'detailPrefix' => __( 'پیام فنی API:', 'ai-product-description' ),
			),
		);
		?>
		<script>
		(function () {
			if (window.aiProductDescTestBound) {
				return;
			}
			window.aiProductDescTestBound = true;
			window.aiProductDescSettings = Object.assign(
				{},
				window.aiProductDescSettings || {},
				<?php echo wp_json_encode( $config ); ?>
			);

			function byId(id) {
				return document.getElementById(id);
			}

			var cfg = window.aiProductDescSettings;
			var i18n = cfg.i18n || {};
			var testWrap = byId('ai-product-desc-test-wrap');
			var testBtn = byId('ai-product-desc-test-connection');
			var spinner = byId('ai-product-desc-test-spinner');
			var btnLabel = testBtn ? testBtn.querySelector('.ai-product-desc-test-btn-label') : null;
			var resultWrap = byId('ai-product-desc-test-result');
			var messageEl = byId('ai-product-desc-test-message');
			var explanationEl = byId('ai-product-desc-test-explanation');
			var detailEl = byId('ai-product-desc-test-detail');
			var reportEl = byId('ai-product-desc-test-report');
			var copyBtn = byId('ai-product-desc-copy-test-report');
			var copyStatus = byId('ai-product-desc-copy-test-status');
			var select = byId('ai-product-desc-active-provider');

			function getAjaxConfig() {
				return {
					ajaxUrl: cfg.ajaxUrl || (testWrap && testWrap.getAttribute('data-ajax-url')) || (typeof window.ajaxurl === 'string' ? window.ajaxurl : ''),
					nonce: cfg.nonce || (testWrap && testWrap.getAttribute('data-nonce')) || '',
					action: cfg.action || (testWrap && testWrap.getAttribute('data-action')) || 'ai_product_desc_test_connection',
				};
			}

			function getFieldValue(providerId, field) {
				var el = byId('ai-product-desc-' + providerId + '-' + field);
				return el ? String(el.value || '').trim() : '';
			}

			function setTestLoading(loading) {
				if (testBtn) {
					testBtn.disabled = loading;
					testBtn.setAttribute('aria-busy', loading ? 'true' : 'false');
				}
				if (btnLabel) {
					btnLabel.textContent = loading ? (i18n.testing || '') : (i18n.testButton || '');
				}
				if (spinner) {
					spinner.classList.toggle('is-active', loading);
				}
			}

			function showResult(isSuccess, message, report, explanation, detail) {
				if (!resultWrap || !messageEl || !reportEl) {
					return;
				}
				resultWrap.hidden = false;
				messageEl.textContent = message || (isSuccess ? (i18n.okTitle || '') : (i18n.failTitle || i18n.error || ''));
				messageEl.classList.toggle('is-success', !!isSuccess);
				messageEl.classList.toggle('is-error', !isSuccess);
				messageEl.classList.remove('is-loading');
				if (explanationEl) {
					if (explanation) {
						explanationEl.hidden = false;
						explanationEl.textContent = explanation;
						explanationEl.classList.toggle('is-success', !!isSuccess);
						explanationEl.classList.toggle('is-error', !isSuccess);
					} else {
						explanationEl.hidden = true;
						explanationEl.textContent = '';
						explanationEl.classList.remove('is-success', 'is-error');
					}
				}
				if (detailEl) {
					if (!isSuccess && detail) {
						detailEl.hidden = false;
						detailEl.textContent = (i18n.detailPrefix || '') + ' ' + detail;
					} else {
						detailEl.hidden = true;
						detailEl.textContent = '';
					}
				}
				reportEl.value = report || '';
				if (copyStatus) {
					copyStatus.hidden = true;
					copyStatus.textContent = '';
				}
				resultWrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
			}

			function showTestingState() {
				if (!resultWrap || !messageEl || !reportEl) {
					return;
				}
				resultWrap.hidden = false;
				messageEl.textContent = i18n.testing || '';
				messageEl.classList.remove('is-success', 'is-error');
				messageEl.classList.add('is-loading');
				if (explanationEl) {
					explanationEl.hidden = true;
					explanationEl.textContent = '';
					explanationEl.classList.remove('is-success', 'is-error');
				}
				if (detailEl) {
					detailEl.hidden = true;
					detailEl.textContent = '';
				}
				reportEl.value = '';
				resultWrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
			}

			if (testBtn) {
				testBtn.addEventListener('click', function (event) {
					event.preventDefault();
					event.stopPropagation();

					var ajaxCfg = getAjaxConfig();
					if (!ajaxCfg.ajaxUrl || !ajaxCfg.nonce || !ajaxCfg.action) {
						showResult(false, i18n.failTitle || i18n.error || '', '', i18n.configError || i18n.error || '', '');
						return;
					}

					var providerId = select ? select.value : '';
					if (!providerId) {
						showResult(false, i18n.failTitle || i18n.error || '', '', i18n.configError || i18n.error || '', '');
						return;
					}

					var formData = new FormData();
					formData.append('action', ajaxCfg.action);
					formData.append('nonce', ajaxCfg.nonce);
					formData.append('provider_id', providerId);
					formData.append('api_key', getFieldValue(providerId, 'api_key'));
					formData.append('base_url', getFieldValue(providerId, 'base_url'));
					formData.append('model', getFieldValue(providerId, 'model'));

					setTestLoading(true);
					showTestingState();

					fetch(ajaxCfg.ajaxUrl, {
						method: 'POST',
						credentials: 'same-origin',
						body: formData,
					})
						.then(function (response) {
							return response.text().then(function (text) {
								var payload = null;
								try {
									payload = JSON.parse(text);
								} catch (e) {
									payload = null;
								}
								return { ok: response.ok, payload: payload, raw: text };
							});
						})
						.then(function (result) {
							setTestLoading(false);
							if (!result.payload || typeof result.payload !== 'object') {
								showResult(
									false,
									i18n.failTitle || i18n.error || '',
									'LK-AI-TEST\nstatus=fail\nmessage=Invalid JSON response from server',
									i18n.invalidJson || i18n.error || '',
									''
								);
								return;
							}
							var payload = result.payload;
							var data = payload.data || {};
							showResult(
								!!payload.success,
								data.message || (payload.success ? (i18n.okTitle || '') : (i18n.failTitle || i18n.error || '')),
								data.report || '',
								data.explanation || '',
								data.detail || ''
							);
						})
						.catch(function () {
							setTestLoading(false);
							showResult(
								false,
								i18n.failTitle || i18n.error || '',
								'',
								i18n.networkError || i18n.error || '',
								''
							);
						});
				});
			}

			if (copyBtn && reportEl) {
				copyBtn.addEventListener('click', function () {
					var text = reportEl.value || '';
					if (!text) {
						return;
					}
					var done = function (ok) {
						if (!copyStatus) {
							return;
						}
						copyStatus.hidden = false;
						copyStatus.textContent = ok ? (i18n.copied || '') : (i18n.copyFail || '');
					};
					if (navigator.clipboard && navigator.clipboard.writeText) {
						navigator.clipboard.writeText(text).then(
							function () { done(true); },
							function () {
								reportEl.focus();
								reportEl.select();
								done(document.execCommand('copy'));
							}
						);
						return;
					}
					reportEl.focus();
					reportEl.select();
					done(document.execCommand('copy'));
				});
			}
		})();
		</script>
		<?php
	}

	/**
	 * Intro for active-provider section.
	 */
	public static function render_provider_section(): void {
		echo '<p>' . esc_html__( 'Select which provider the plugin should use. Credentials for other providers are kept so you can switch later.', 'ai-product-description' ) . '</p>';
	}

	/**
	 * Active provider dropdown.
	 */
	public static function render_active_provider_field(): void {
		$settings = self::get_settings();
		?>
		<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[active_provider]" id="ai-product-desc-active-provider">
			<?php foreach ( self::get_providers() as $id => $provider ) : ?>
				<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $settings['active_provider'], $id ); ?>>
					<?php echo esc_html( $provider['label'] ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="ai-product-desc-test-wrap" id="ai-product-desc-test-wrap"
			data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( AI_Product_Desc_Ajax::NONCE_ACTION ) ); ?>"
			data-action="<?php echo esc_attr( AI_Product_Desc_Ajax::TEST_CONNECTION ); ?>"
		>
			<button type="button" class="button button-secondary" id="ai-product-desc-test-connection">
				<span class="ai-product-desc-test-btn-label"><?php esc_html_e( 'تست اتصال AI', 'ai-product-description' ); ?></span>
			</button>
			<span id="ai-product-desc-test-spinner" class="spinner ai-product-desc-test-spinner" aria-hidden="true"></span>
			<span class="description">
				<?php esc_html_e( 'یک درخواست ساده با تنظیمات فعلی (حتی قبل از ذخیره) ارسال می‌شود.', 'ai-product-description' ); ?>
			</span>
		</p>
		<?php
	}

	/**
	 * Section description for a provider connection block.
	 *
	 * @param string $provider_id Provider id.
	 * @param string $label       Provider label.
	 */
	public static function render_provider_connection_section( string $provider_id, string $label ): void {
		printf(
			'<p class="ai-product-desc-provider-panel" data-provider="%1$s">%2$s</p>',
			esc_attr( $provider_id ),
			esc_html(
				sprintf(
					/* translators: %s: provider name */
					__( 'Connection details for %s. Fill these fields, then set this provider as Active above if you want to use it.', 'ai-product-description' ),
					$label
				)
			)
		);
	}

	/**
	 * Single text/password/url field for a provider.
	 *
	 * @param string $provider_id Provider id.
	 * @param string $field       Field key.
	 * @param string $type        Input type.
	 */
	public static function render_provider_text_field( string $provider_id, string $field, string $type = 'text' ): void {
		$settings = self::get_settings();
		$value    = $settings['providers'][ $provider_id ][ $field ] ?? '';
		$name     = sprintf( '%s[providers][%s][%s]', self::OPTION_KEY, $provider_id, $field );
		$id       = sprintf( 'ai-product-desc-%s-%s', $provider_id, $field );
		?>
		<input
			type="<?php echo esc_attr( $type ); ?>"
			class="regular-text"
			name="<?php echo esc_attr( $name ); ?>"
			id="<?php echo esc_attr( $id ); ?>"
			value="<?php echo esc_attr( (string) $value ); ?>"
			autocomplete="off"
		>
		<?php
	}
}
