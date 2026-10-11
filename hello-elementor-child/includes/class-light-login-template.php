<?php
/**
 * Light login page for Digits ?login=true.
 *
 * Digits replaces the whole document and exits. This template removes that
 * full-page callback and renders the site chrome around the Digits form.
 *
 * @package Hello_Elementor_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hello_Elementor_Child_Light_Login_Template {

	/** @var bool */
	private static $served = false;

	/** @var string */
	private static $forced_form = '';

	/** @var int */
	private static $ob_level = 0;

	/** @var bool */
	private static $buffering = false;

	/** @var bool */
	private static $allow_redirect = false;

	/** @var bool */
	private static $capturing_form = false;

	public static function init(): void {
		add_filter( 'request', array( __CLASS__, 'strip_page_query_var' ) );
		add_filter( 'pre_handle_404', array( __CLASS__, 'prevent_404' ), 0, 2 );
		add_filter( 'redirect_canonical', array( __CLASS__, 'skip_canonical' ), 0, 2 );
		add_filter( 'wp_redirect', array( __CLASS__, 'block_front_redirect' ), 0, 2 );
		add_filter( 'document_title_parts', array( __CLASS__, 'document_title_parts' ) );
		add_filter( 'template_include', array( __CLASS__, 'maybe_use_template' ), PHP_INT_MAX );
		add_action( 'init', array( __CLASS__, 'start_buffer' ), 0 );
		add_action( 'wp_loaded', array( __CLASS__, 'unhook_digits_page' ), PHP_INT_MIN );
		add_action( 'wp', array( __CLASS__, 'detach_canonical' ), 0 );
		add_action( 'wp', array( __CLASS__, 'unhook_digits_page' ), PHP_INT_MIN );
		add_action( 'template_redirect', array( __CLASS__, 'force_template' ), PHP_INT_MIN );
		add_action( 'shutdown', array( __CLASS__, 'recover_digits_page' ), 0 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 100 );
	}

	/**
	 * Digits sends page=1 as a form step. WordPress uses page for post pagination
	 * and 404s a normal page when that query var is present.
	 *
	 * @param mixed $query_vars Request query vars.
	 * @return mixed
	 */
	public static function strip_page_query_var( $query_vars ) {
		if ( ! self::is_enabled() || ! is_array( $query_vars ) ) {
			return $query_vars;
		}
		unset( $query_vars['page'] );
		return $query_vars;
	}

	/**
	 * @param bool     $preempt  Short-circuit flag.
	 * @param WP_Query $wp_query Query.
	 * @return bool
	 */
	public static function prevent_404( $preempt, $wp_query ) {
		if ( ! self::is_enabled() ) {
			return $preempt;
		}
		if ( $wp_query instanceof WP_Query ) {
			$wp_query->is_404 = false;
		}
		return true;
	}

	/**
	 * @param mixed $redirect_url  Redirect target.
	 * @param mixed $requested_url Requested URL.
	 * @return mixed
	 */
	public static function skip_canonical( $redirect_url, $requested_url ) {
		unset( $requested_url );
		if ( self::is_enabled() ) {
			return false;
		}
		return $redirect_url;
	}

	/**
	 * Core redirects the static front page back to / when extra query args are present.
	 */
	public static function detach_canonical(): void {
		if ( ! self::is_enabled() ) {
			return;
		}
		remove_action( 'template_redirect', 'redirect_canonical' );
	}

	/**
	 * Opening the login URL must stay on the login screen.
	 * POST still redirects after a successful code, and the account redirect is allowed.
	 *
	 * @param mixed $location Redirect target.
	 * @param mixed $status   Status code.
	 * @return mixed
	 */
	public static function block_front_redirect( $location, $status = 302 ) {
		unset( $status );
		if ( self::$allow_redirect || ! self::is_enabled() ) {
			return $location;
		}
		if ( self::$capturing_form ) {
			return false;
		}
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET';
		if ( 'GET' !== $method ) {
			return $location;
		}
		if ( self::is_bare_home( (string) $location ) ) {
			return false;
		}
		return $location;
	}

	/**
	 * @param string $template Template path.
	 */
	public static function maybe_use_template( $template ): string {
		if ( ! self::is_enabled() || self::$served ) {
			return (string) $template;
		}
		$path = self::get_template_path();
		return file_exists( $path ) ? $path : (string) $template;
	}

	/**
	 * @param array<string, string> $parts Title parts.
	 * @return array<string, string>
	 */
	public static function document_title_parts( array $parts ): array {
		if ( self::is_enabled() ) {
			$parts['title'] = 'ورود';
		}
		return $parts;
	}

	/**
	 * Hold Digits output in case it still exits before our template.
	 */
	public static function start_buffer(): void {
		if ( ! self::is_enabled() || is_user_logged_in() || self::$buffering ) {
			return;
		}
		self::$ob_level  = ob_get_level();
		self::$buffering = true;
		ob_start();
	}

	public static function is_enabled(): bool {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		return isset( $_GET['login'] );
	}

	/**
	 * Stop Digits from printing its own document and exiting.
	 */
	public static function unhook_digits_page(): void {
		if ( ! self::is_enabled() ) {
			return;
		}
		foreach ( array( 'wp_loaded', 'wp', 'template_redirect' ) as $hook ) {
			self::remove_login_page_callbacks( $hook );
		}
	}

	/**
	 * @param string $hook Hook name.
	 */
	private static function remove_login_page_callbacks( string $hook ): void {
		global $wp_filter;
		if ( empty( $wp_filter[ $hook ] ) || ! isset( $wp_filter[ $hook ]->callbacks ) ) {
			return;
		}
		foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$fn = $callback['function'] ?? null;
				if ( ! self::is_digits_page_callback( $fn ) ) {
					continue;
				}
				remove_action( $hook, $fn, (int) $priority );
			}
		}
	}

	/**
	 * @param mixed $fn Callback.
	 */
	private static function is_digits_page_callback( $fn ): bool {
		$name = '';
		if ( is_string( $fn ) ) {
			$name = $fn;
		} elseif ( is_array( $fn ) && isset( $fn[1] ) ) {
			$owner = $fn[0];
			$class = is_object( $owner ) ? get_class( $owner ) : (string) $owner;
			$name  = $class . '::' . (string) $fn[1];
		} elseif ( $fn instanceof Closure ) {
			try {
				$file = strtolower( (string) ( new ReflectionFunction( $fn ) )->getFileName() );
			} catch ( ReflectionException $e ) {
				$file = '';
			}
			if ( false !== strpos( $file, 'digit' ) && false !== strpos( $file, 'login' ) ) {
				return true;
			}
		}
		$name = strtolower( $name );
		if ( '' === $name ) {
			return false;
		}
		$is_digits = false !== strpos( $name, 'digit' ) || false !== strpos( $name, 'dig_' );
		if ( ! $is_digits ) {
			return false;
		}
		return (bool) preg_match( '/login|page/', $name );
	}

	public static function force_template(): void {
		if ( ! self::is_enabled() || self::$served ) {
			return;
		}
		self::$served = true;
		if ( is_user_logged_in() ) {
			self::$allow_redirect = true;
			wp_safe_redirect( self::account_url() );
			exit;
		}

		self::detach_canonical();
		self::unhook_elementor_chrome();
		self::mark_found();
		$path = self::get_template_path();
		if ( ! file_exists( $path ) ) {
			self::$served = false;
			return;
		}
		include $path;
		exit;
	}

	/**
	 * Logged-in visitors go to the account page, never back to the homepage.
	 */
	private static function account_url(): string {
		$dest = function_exists( 'wc_get_page_permalink' ) ? (string) wc_get_page_permalink( 'myaccount' ) : '';
		$home = untrailingslashit( home_url( '/' ) );
		if ( '' === $dest || untrailingslashit( $dest ) === $home ) {
			$dest = home_url( '/my-account/' );
		}
		return $dest;
	}

	/**
	 * @param string $url URL.
	 */
	private static function is_bare_home( string $url ): bool {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return false;
		}
		$home  = wp_parse_url( home_url( '/' ) );
		$host  = strtolower( (string) ( $parts['host'] ?? '' ) );
		$home_host = strtolower( (string) ( $home['host'] ?? '' ) );
		$path  = untrailingslashit( (string) ( $parts['path'] ?? '' ) );
		$home_path = untrailingslashit( (string) ( $home['path'] ?? '' ) );
		if ( $host !== $home_host || $path !== $home_path ) {
			return false;
		}
		$query = (string) ( $parts['query'] ?? '' );
		return '' === $query || false === strpos( $query, 'login=' );
	}

	public static function get_template_path(): string {
		return HELLO_ELEMENTOR_CHILD_PATH . 'login-light.php';
	}

	/**
	 * Clear the pagination 404 before the login document is rendered.
	 */
	private static function mark_found(): void {
		global $wp_query;
		if ( $wp_query instanceof WP_Query ) {
			$wp_query->is_404 = false;
		}
		status_header( 200 );
		nocache_headers();
	}

	/**
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public static function body_class( array $classes ): array {
		if ( ! self::is_enabled() ) {
			return $classes;
		}
		$classes[] = 'lk-light-login';
		$classes[] = 'lk-light-product';
		$classes[] = 'lz-chrome';
		return $classes;
	}

	public static function unhook_elementor_chrome(): void {
		if ( ! self::is_enabled() ) {
			return;
		}
		foreach ( array(
			'elementor/theme/before_do_header',
			'elementor/theme/after_do_header',
			'elementor/theme/before_do_footer',
			'elementor/theme/after_do_footer',
		) as $hook ) {
			remove_all_actions( $hook );
		}
		if ( ! class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
			return;
		}
		$module = \ElementorPro\Modules\ThemeBuilder\Module::instance();
		if ( ! $module || ! method_exists( $module, 'get_locations_manager' ) ) {
			return;
		}
		$manager = $module->get_locations_manager();
		if ( ! $manager ) {
			return;
		}
		foreach ( array( 'wp_body_open', 'get_header', 'get_footer', 'wp_footer', 'wp_head' ) as $hook ) {
			remove_action( $hook, array( $manager, 'do_location' ) );
			remove_action( $hook, array( $manager, 'do_header' ) );
			remove_action( $hook, array( $manager, 'do_footer' ) );
			remove_action( $hook, array( $manager, 'print_locations' ) );
		}
	}

	public static function enqueue_assets(): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		wp_enqueue_style(
			'lk-light-product',
			HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-light-product.css',
			array( HELLO_ELEMENTOR_CHILD_VAZIRMATN_HANDLE ),
			file_exists( HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-light-product.css' )
				? (string) filemtime( HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-light-product.css' )
				: HELLO_ELEMENTOR_CHILD_VERSION
		);

		$css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/light-login.css';
		if ( file_exists( $css ) ) {
			$deps = array( 'lk-light-product' );
			if ( wp_style_is( 'digits-login-style', 'registered' ) ) {
				$deps[] = 'digits-login-style';
			}
			wp_enqueue_style(
				'lk-light-login',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/css/light-login.css',
				$deps,
				(string) filemtime( $css )
			);
		}

		foreach ( array( 'digits-login-style', 'digits-style', 'digits-main-style' ) as $handle ) {
			if ( wp_style_is( $handle, 'registered' ) ) {
				wp_enqueue_style( $handle );
			}
		}
		foreach ( array( 'digits-login-script', 'digits-main-script', 'digits-frontend-script', 'digits-script' ) as $handle ) {
			if ( wp_script_is( $handle, 'registered' ) ) {
				wp_enqueue_script( $handle );
			}
		}

		wp_enqueue_script( 'jquery' );
		if ( wp_script_is( 'wc-cart-fragments', 'registered' ) ) {
			wp_enqueue_script( 'wc-cart-fragments' );
		}

		$chrome_js = HELLO_ELEMENTOR_CHILD_PATH . 'assets/js/light-archive-chrome.js';
		if ( file_exists( $chrome_js ) ) {
			wp_enqueue_script(
				'lk-light-archive-chrome',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/js/light-archive-chrome.js',
				array(),
				(string) filemtime( $chrome_js ),
				true
			);
		}
	}

	/**
	 * If Digits still replaced the document, rebuild this page around its form.
	 */
	public static function recover_digits_page(): void {
		if ( ! self::$buffering || self::$served ) {
			return;
		}
		$html = '';
		while ( ob_get_level() > self::$ob_level ) {
			$html = (string) ob_get_clean() . $html;
		}
		$form = self::extract_digits_markup( $html );
		if ( '' === $form ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}
		self::$forced_form = $form;
		self::$served      = true;
		$path              = self::get_template_path();
		if ( ! file_exists( $path ) ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}
		include $path;
	}

	/**
	 * @param string $html Full Digits document.
	 */
	private static function extract_digits_markup( string $html ): string {
		if ( '' === $html || false === strpos( $html, 'digits-form' ) || ! class_exists( 'DOMDocument' ) ) {
			return '';
		}
		$dom  = new DOMDocument();
		$prev = libxml_use_internal_errors( true );
		$loaded = $dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		if ( ! $loaded ) {
			return '';
		}
		$xpath = new DOMXPath( $dom );
		$nodes = $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " digits-form_page ")]' );
		if ( ! $nodes || ! $nodes->length ) {
			return '';
		}
		$form = (string) $dom->saveHTML( $nodes->item( 0 ) );
		$scripts = '';
		$script_nodes = $xpath->query( '//script' );
		if ( $script_nodes ) {
			foreach ( $script_nodes as $script ) {
				$src  = strtolower( (string) $script->getAttribute( 'src' ) );
				$code = strtolower( (string) $script->textContent );
				if ( false === strpos( $src, 'digit' ) && false === strpos( $code, 'digit' ) ) {
					continue;
				}
				$scripts .= (string) $dom->saveHTML( $script );
			}
		}
		return $form . $scripts;
	}

	/**
	 * Phone step first. Digits replaces it with the code step after the number is sent.
	 */
	private static function render_digits_login_form(): string {
		if ( ! function_exists( 'df_digits_form_login' ) ) {
			return '';
		}
		self::$capturing_form = true;
		$html = df_digits_form_login();
		self::$capturing_form = false;
		if ( ! is_string( $html ) ) {
			return '';
		}
		$html = trim( $html );
		if ( '' === $html ) {
			return '';
		}
		if ( false === strpos( $html, 'digits' ) ) {
			return '';
		}
		return $html;
	}

	public static function capture_form(): string {
		if ( '' !== self::$forced_form ) {
			return self::$forced_form;
		}

		$html = self::render_digits_login_form();
		if ( '' !== $html ) {
			return $html;
		}

		global $shortcode_tags;
		if ( ! is_array( $shortcode_tags ) ) {
			return '';
		}
		self::$capturing_form = true;
		$tags = array();
		foreach ( array_keys( $shortcode_tags ) as $tag ) {
			$tag = (string) $tag;
			if ( ! preg_match( '/^(dm-|digits)/', $tag ) ) {
				continue;
			}
			if ( ! preg_match( '/page|login|form/', $tag ) ) {
				continue;
			}
			if ( preg_match( '/logout|modal|button|popup/', $tag ) ) {
				continue;
			}
			$tags[] = $tag;
		}
		usort(
			$tags,
			static function ( $a, $b ) {
				$score = static function ( $tag ) {
					return false !== strpos( $tag, 'page' ) ? 0 : 1;
				};
				return $score( $a ) <=> $score( $b );
			}
		);
		foreach ( $tags as $tag ) {
			ob_start();
			$html = do_shortcode( '[' . $tag . ']' );
			$echoed = (string) ob_get_clean();
			if ( ! is_string( $html ) ) {
				$html = '';
			}
			$html .= $echoed;
			if ( '' === trim( $html ) ) {
				continue;
			}
			if ( false !== strpos( $html, 'digits-form' ) || false !== strpos( $html, 'dig_login' ) ) {
				self::$capturing_form = false;
				return $html;
			}
		}
		self::$capturing_form = false;
		return '';
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_context(): array {
		$icon = array( 'Hello_Elementor_Child_Light_About_Template', 'icon_html' );

		$features = array(
			array( 'layers.svg', 'پیگیری سفارش‌ها', 'وضعیت سفارشات خود را لحظه‌ای بررسی کنید' ),
			array( 'file-text.svg', 'مشاهده فاکتورها', 'لیست فاکتورها و دانلود فاکتور رسمی' ),
			array( 'star.svg', 'لیست علاقه‌مندی‌ها', 'محصولات مورد علاقه خود را ذخیره کنید' ),
			array( 'circle-dollar-sign.svg', 'دریافت قیمت‌های ویژه', 'مشاهده قیمت‌های اختصاصی و تخفیف‌ها' ),
			array( 'beaker.svg', 'استعلام سریع موجودی', 'موجودی محصولات را استعلام بگیرید' ),
			array( 'file-text.svg', 'دانلود MSDS', 'برگه‌های اطلاعات ایمنی مواد شیمیایی' ),
		);
		$trust = array(
			array( 'badge-check.svg', 'دسترسی به برندهای معتبر' ),
			array( 'headset.svg', 'مشاوره تخصصی' ),
			array( 'shield-check.svg', 'ضمانت اصالت کالا' ),
			array( 'truck.svg', 'ارسال سریع' ),
		);

		$feature_rows = array();
		foreach ( $features as $row ) {
			$feature_rows[] = array(
				'icon'  => is_callable( $icon ) ? (string) call_user_func( $icon, $row[0], 'lk-login__feature-icon', 22 ) : '',
				'title' => $row[1],
				'text'  => $row[2],
			);
		}
		$trust_rows = array();
		foreach ( $trust as $row ) {
			$trust_rows[] = array(
				'icon'  => is_callable( $icon ) ? (string) call_user_func( $icon, $row[0], 'lk-login__trust-icon', 22 ) : '',
				'title' => $row[1],
			);
		}

		return array(
			'story_image' => content_url( 'uploads/2023/08/man-in-lab.webp' ),
			'phone_icon'  => is_callable( $icon ) ? (string) call_user_func( $icon, 'phone.svg', 'lk-login__card-icon', 22 ) : '',
			'shield_icon' => is_callable( $icon ) ? (string) call_user_func( $icon, 'shield-check.svg', 'lk-login__privacy-icon', 20 ) : '',
			'features'    => $feature_rows,
			'trust'       => $trust_rows,
			'form_html'   => self::capture_form(),
		);
	}
}
