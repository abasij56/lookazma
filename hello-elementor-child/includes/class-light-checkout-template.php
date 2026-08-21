<?php
/**
 * Light checkout template — native Woo form (no Elementor / Jet Woo Builder).
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Checkout with shared Lookazma header/footer.
 */
final class Hello_Elementor_Child_Light_Checkout_Template {

	public const TEMPLATE_FILE = 'page-templates/light-checkout.php';

	/**
	 * Temporarily force Woo core templates during checkout HTML capture.
	 *
	 * @var bool
	 */
	private static $forcing_core_templates = false;

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_filter( 'theme_page_templates', array( __CLASS__, 'register_page_template' ), 20, 4 );
		add_filter( 'timber/locations', array( __CLASS__, 'add_timber_locations' ) );

		add_action( 'template_redirect', array( __CLASS__, 'unhook_elementor_chrome' ), -6 );
		add_action( 'template_redirect', array( __CLASS__, 'force_template' ), 20 );
		add_filter( 'template_include', array( __CLASS__, 'maybe_use_template' ), PHP_INT_MAX );
		add_filter( 'elementor/theme/need_override_location', array( __CLASS__, 'disable_elementor_locations' ), PHP_INT_MAX, 2 );
		add_filter( 'elementor/theme/get_location_templates', array( __CLASS__, 'remove_popup_templates' ), PHP_INT_MAX, 2 );
		add_filter( 'elementor/theme/get_location_templates/popup', array( __CLASS__, 'deny_popup_templates' ), PHP_INT_MAX );
		add_filter( 'elementor/frontend/builder_content_data', array( __CLASS__, 'empty_popup_builder_data' ), PHP_INT_MAX, 2 );
		add_action( 'wp_footer', array( __CLASS__, 'unhook_elementor_popups' ), 0 );
		add_filter( 'jet-theme-core/template-include', array( __CLASS__, 'disable_jet_theme_core_template' ), PHP_INT_MAX );
		add_filter( 'option_jet-woo-builder-settings', array( __CLASS__, 'disable_jet_checkout_settings' ), PHP_INT_MAX );
		add_filter( 'woocommerce_locate_template', array( __CLASS__, 'force_woo_checkout_templates' ), PHP_INT_MAX, 3 );
		add_filter( 'wc_get_template', array( __CLASS__, 'force_wc_get_template' ), PHP_INT_MAX, 5 );
		add_filter( 'jet-woo-builder/integration/woocommerce/custom-checkout', '__return_false', PHP_INT_MAX );
		add_filter( 'jet-woo-builder/get-template-id', array( __CLASS__, 'deny_jet_checkout_template_id' ), PHP_INT_MAX, 2 );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'dequeue_listing_assets' ), 100 );
		add_action( 'wp_print_styles', array( __CLASS__, 'dequeue_listing_assets' ), 100 );
		add_action( 'wp_print_scripts', array( __CLASS__, 'dequeue_listing_assets' ), 100 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ), PHP_INT_MAX );
		add_filter( 'woocommerce_no_available_payment_methods_message', array( __CLASS__, 'translate_no_payment_methods_message' ) );
		add_filter( 'gettext', array( __CLASS__, 'translate_woocommerce_strings' ), 20, 3 );
		add_filter( 'gettext_with_context', array( __CLASS__, 'translate_woocommerce_strings_with_context' ), 20, 4 );
	}

	/**
	 * @param array<string, string> $templates Templates.
	 * @return array<string, string>
	 */
	public static function register_page_template( $templates, $theme = null, $post = null, $post_type = '' ): array {
		if ( ! is_array( $templates ) ) {
			$templates = array();
		}
		if ( '' !== $post_type && 'page' !== $post_type ) {
			return $templates;
		}
		$templates[ self::TEMPLATE_FILE ] = __( 'Light checkout template', 'hello-elementor-child' );
		return $templates;
	}

	/**
	 * @param array<int, string> $locations Timber paths.
	 * @return array<int, string>
	 */
	public static function add_timber_locations( array $locations ): array {
		$path = HELLO_ELEMENTOR_CHILD_PATH . 'views';
		if ( ! in_array( $path, $locations, true ) ) {
			array_unshift( $locations, $path );
		}
		return $locations;
	}

	/**
	 * WooCommerce Checkout page ID.
	 */
	public static function get_checkout_page_id(): int {
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return 0;
		}
		$id = (int) wc_get_page_id( 'checkout' );
		return $id > 0 ? $id : 0;
	}

	/**
	 * @param int $page_id Page ID.
	 */
	public static function page_uses_template( int $page_id ): bool {
		if ( $page_id <= 0 ) {
			return false;
		}
		$slug = (string) get_page_template_slug( $page_id );
		return self::TEMPLATE_FILE === $slug || 'light-checkout.php' === $slug;
	}

	/**
	 * Whether this template owns the current request.
	 */
	public static function is_enabled(): bool {
		if ( wp_doing_cron() || wp_doing_ajax() ) {
			return false;
		}
		if ( is_admin() && ! wp_doing_ajax() ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		if ( ! function_exists( 'is_checkout' ) ) {
			return false;
		}

		$checkout_id = self::get_checkout_page_id();
		if ( $checkout_id <= 0 || ! self::page_uses_template( $checkout_id ) ) {
			return false;
		}

		if ( is_checkout() && ! is_wc_endpoint_url() ) {
			return true;
		}

		$object = get_queried_object();
		if ( $object instanceof WP_Post && (int) $object->ID === $checkout_id ) {
			return true;
		}

		return is_page( $checkout_id );
	}

	/**
	 * PHP entry path.
	 */
	public static function get_template_path(): string {
		return HELLO_ELEMENTOR_CHILD_PATH . 'page-light-checkout.php';
	}

	/**
	 * Take over after WooCommerce checkout handlers.
	 */
	public static function force_template(): void {
		if ( ! self::is_enabled() ) {
			return;
		}
		$path = self::get_template_path();
		if ( ! file_exists( $path ) ) {
			return;
		}
		self::unhook_elementor_chrome();
		status_header( 200 );
		include $path;
		exit;
	}

	/**
	 * @param string $template Current template.
	 */
	public static function maybe_use_template( string $template ): string {
		if ( ! self::is_enabled() ) {
			return $template;
		}
		$path = self::get_template_path();
		return file_exists( $path ) ? $path : $template;
	}

	/**
	 * @param bool   $need_override Whether override is needed.
	 * @param string $location      Location name.
	 */
	public static function disable_elementor_locations( bool $need_override, string $location ): bool {
		if ( ! self::is_enabled() ) {
			return $need_override;
		}
		if ( in_array( $location, array( 'header', 'footer', 'single', 'archive', 'popup' ), true ) ) {
			return false;
		}
		return $need_override;
	}

	/**
	 * @param mixed $templates Location templates.
	 * @param mixed $arg       Location slug or args.
	 * @return mixed
	 */
	public static function remove_popup_templates( $templates, $arg = null ) {
		if ( ! self::is_enabled() ) {
			return $templates;
		}
		$location = '';
		if ( is_string( $arg ) ) {
			$location = $arg;
		} elseif ( is_array( $arg ) && isset( $arg['location'] ) ) {
			$location = (string) $arg['location'];
		}
		if ( 'popup' === $location ) {
			return array();
		}
		return $templates;
	}

	/**
	 * @param mixed $templates Templates.
	 * @return mixed
	 */
	public static function deny_popup_templates( $templates ) {
		return self::is_enabled() ? array() : $templates;
	}

	/**
	 * @param mixed $data    Builder data.
	 * @param mixed $post_id Document id.
	 * @return mixed
	 */
	public static function empty_popup_builder_data( $data, $post_id = 0 ) {
		if ( ! self::is_enabled() ) {
			return $data;
		}
		if ( 17307 === (int) $post_id ) {
			return array();
		}
		return $data;
	}

	/**
	 * Unhook Elementor Theme Builder chrome.
	 */
	public static function unhook_elementor_chrome(): void {
		if ( ! self::is_enabled() ) {
			return;
		}
		foreach ( array(
			'elementor/theme/before_do_header',
			'elementor/theme/after_do_header',
			'elementor/theme/before_do_footer',
			'elementor/theme/after_do_footer',
			'elementor/theme/before_do_single',
			'elementor/theme/after_do_single',
			'elementor/theme/before_do_popup',
			'elementor/theme/after_do_popup',
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

	/**
	 * Unhook Elementor Pro popup print on wp_footer.
	 */
	public static function unhook_elementor_popups(): void {
		self::unhook_elementor_chrome();
	}

	/**
	 * @param mixed $template Current template.
	 * @return mixed
	 */
	public static function disable_jet_theme_core_template( $template ) {
		if ( ! self::is_enabled() ) {
			return $template;
		}
		$path = self::get_template_path();
		return file_exists( $path ) ? $path : $template;
	}

	/**
	 * Force Jet Woo Builder custom checkout off when light template is assigned.
	 *
	 * @param mixed $value Settings array.
	 * @return mixed
	 */
	public static function disable_jet_checkout_settings( $value ) {
		$checkout_id = self::get_checkout_page_id();
		if ( $checkout_id <= 0 || ! self::page_uses_template( $checkout_id ) ) {
			return $value;
		}
		if ( ! is_array( $value ) ) {
			return $value;
		}

		$off_keys = array(
			'custom_checkout',
			'custom_checkout_page',
			'enable_checkout',
			'checkout',
			'checkout_template',
			'checkout_top_template',
			'custom_thankyou',
		);

		foreach ( $off_keys as $key ) {
			if ( ! array_key_exists( $key, $value ) ) {
				continue;
			}
			// Template IDs → default; flags → empty/false.
			if ( false !== strpos( $key, 'template' ) ) {
				$value[ $key ] = 'default';
			} else {
				$value[ $key ] = '';
			}
		}

		return $value;
	}

	/**
	 * Always use WooCommerce core checkout PHP templates on this page.
	 *
	 * @param string $template      Located path.
	 * @param string $template_name Relative template name.
	 * @param string $template_path Woo template path arg.
	 */
	public static function force_woo_checkout_templates( $template, $template_name, $template_path = '' ) {
		unset( $template_path );

		if ( ! self::should_force_core_checkout_templates() || ! function_exists( 'WC' ) ) {
			return $template;
		}

		$core = self::get_core_checkout_template_path( (string) $template_name );
		return $core ? $core : $template;
	}

	/**
	 * @param string $template      Located path.
	 * @param string $template_name Relative name.
	 * @param array  $args          Template args.
	 * @param string $template_path Template path.
	 * @param string $default_path  Default path.
	 */
	public static function force_wc_get_template( $template, $template_name, $args = array(), $template_path = '', $default_path = '' ) {
		unset( $args, $template_path, $default_path );
		return self::force_woo_checkout_templates( $template, $template_name, '' );
	}

	/**
	 * Block Jet Woo Builder checkout Elementor document IDs.
	 *
	 * @param mixed  $template_id Template id.
	 * @param string $type        Template type.
	 * @return mixed
	 */
	public static function deny_jet_checkout_template_id( $template_id, $type = '' ) {
		if ( ! self::should_force_core_checkout_templates() ) {
			return $template_id;
		}
		$type = strtolower( (string) $type );
		if ( '' === $type || false !== strpos( $type, 'checkout' ) ) {
			return false;
		}
		return $template_id;
	}

	/**
	 * Whether light checkout should force Woo core templates.
	 */
	private static function should_force_core_checkout_templates(): bool {
		if ( self::$forcing_core_templates ) {
			return true;
		}
		$checkout_id = self::get_checkout_page_id();
		return $checkout_id > 0 && self::page_uses_template( $checkout_id );
	}

	/**
	 * Absolute path to a WooCommerce plugin checkout template, or empty.
	 *
	 * @param string $template_name Relative template name.
	 */
	private static function get_core_checkout_template_path( string $template_name ): string {
		$names = array(
			'checkout/form-checkout.php'  => true,
			'checkout/form-billing.php'   => true,
			'checkout/form-shipping.php'  => true,
			'checkout/form-login.php'     => true,
			'checkout/form-coupon.php'    => true,
			'checkout/form-pay.php'       => true,
			'checkout/payment.php'        => true,
			'checkout/payment-method.php' => true,
			'checkout/review-order.php'   => true,
			'checkout/terms.php'          => true,
			'cart/cart-empty.php'         => true,
		);

		if ( ! isset( $names[ $template_name ] ) || ! function_exists( 'WC' ) ) {
			return '';
		}

		$woo = trailingslashit( WC()->plugin_path() ) . 'templates/' . $template_name;
		return file_exists( $woo ) ? $woo : '';
	}

	/**
	 * Front-end assets.
	 */
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

		$checkout_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/light-checkout.css';
		if ( file_exists( $checkout_css ) ) {
			wp_enqueue_style(
				'lk-light-checkout',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/css/light-checkout.css',
				array( 'lk-light-product', 'lk-lpt-breadcrumb' ),
				(string) filemtime( $checkout_css )
			);
		}

		wp_enqueue_script( 'jquery' );
		if ( function_exists( 'WC' ) ) {
			wp_enqueue_script( 'woocommerce' );
			wp_enqueue_script( 'wc-checkout' );
			wp_enqueue_script( 'wc-country-select' );
			wp_enqueue_script( 'wc-address-i18n' );
			wp_enqueue_script( 'selectWoo' );
			wp_enqueue_style( 'select2' );
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
	 * Drop Elementor / Jet chrome; keep WooCommerce + Digits only.
	 */
	public static function dequeue_listing_assets(): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		$style_handles = array(
			'elementor-frontend',
			'elementor-icons',
			'elementor-animations',
			'e-animations',
			'hello-elementor-theme-style',
			'hello-elementor',
			'jet-woo-builder',
			'jet-smart-filters',
			'jet-engine-frontend',
		);
		$script_handles = array(
			'elementor-frontend',
			'elementor-pro-frontend',
			'elementor-pro',
			'jet-woo-builder',
			'jet-smart-filters',
			'jet-engine-frontend',
		);

		foreach ( $style_handles as $handle ) {
			wp_dequeue_style( $handle );
			wp_deregister_style( $handle );
		}
		foreach ( $script_handles as $handle ) {
			wp_dequeue_script( $handle );
			wp_deregister_script( $handle );
		}

		global $wp_styles, $wp_scripts;
		if ( $wp_styles instanceof WP_Styles ) {
			foreach ( (array) $wp_styles->queue as $handle ) {
				$h = strtolower( (string) $handle );
				if ( self::is_keep_asset_handle( $h ) ) {
					continue;
				}
				if (
					false !== strpos( $h, 'elementor' )
					|| false !== strpos( $h, 'jet-woo' )
					|| false !== strpos( $h, 'jet-smart-filter' )
					|| false !== strpos( $h, 'jet-plugins' )
					|| false !== strpos( $h, 'menu-cart' )
				) {
					wp_dequeue_style( $handle );
				}
			}
		}
		if ( $wp_scripts instanceof WP_Scripts ) {
			foreach ( (array) $wp_scripts->queue as $handle ) {
				$h = strtolower( (string) $handle );
				if ( self::is_keep_asset_handle( $h ) ) {
					continue;
				}
				if (
					false !== strpos( $h, 'elementor' )
					|| false !== strpos( $h, 'jet-woo' )
					|| false !== strpos( $h, 'jet-smart-filter' )
					|| false !== strpos( $h, 'jet-plugins' )
					|| false !== strpos( $h, 'menu-cart' )
				) {
					wp_dequeue_script( $handle );
				}
			}
		}
	}

	/**
	 * @param string $handle Lowercased handle.
	 */
	private static function is_keep_asset_handle( string $handle ): bool {
		return false !== strpos( $handle, 'digits' )
			|| false !== strpos( $handle, 'woocommerce' )
			|| 0 === strpos( $handle, 'wc-' )
			|| false !== strpos( $handle, 'select2' )
			|| false !== strpos( $handle, 'selectwoo' );
	}

	/**
	 * @param array<int, string> $classes Body classes.
	 * @return array<int, string>
	 */
	public static function body_class( array $classes ): array {
		if ( ! self::is_enabled() ) {
			return $classes;
		}

		$classes[] = 'lk-light-product';
		$classes[] = 'lk-light-checkout';
		$classes[] = 'lz-chrome';

		return self::sanitize_body_classes( $classes );
	}

	/**
	 * Strip Elementor / Jet body classes that plugins may add late.
	 *
	 * @param array<int, string> $classes Body classes.
	 * @return array<int, string>
	 */
	public static function sanitize_body_classes( array $classes ): array {
		$skip = array(
			'elementor-default'                  => true,
			'elementor-template-full-width'      => true,
			'elementor-page'                     => true,
			'jet-woo-builder-elementor-template' => true,
			'hello-elementor-default'            => true,
		);

		$classes = array_values(
			array_filter(
				$classes,
				static function ( $class ) use ( $skip ) {
					$class = (string) $class;
					if ( isset( $skip[ $class ] ) ) {
						return false;
					}
					if ( 0 === strpos( $class, 'elementor-page-' ) ) {
						return false;
					}
					if ( 0 === strpos( $class, 'elementor-' ) ) {
						return false;
					}
					if ( 0 === strpos( $class, 'e-' ) && false !== strpos( $class, 'elementor' ) ) {
						return false;
					}
					if ( 0 === strpos( $class, 'jet-woo-builder' ) ) {
						return false;
					}
					return true;
				}
			)
		);

		return array_values( array_unique( $classes ) );
	}

	/**
	 * @param string $message Payment methods notice.
	 */
	public static function translate_no_payment_methods_message( $message ): string {
		if ( ! self::is_enabled() ) {
			return (string) $message;
		}

		return 'متأسفانه در حال حاضر روش پرداختی در دسترس نیست. در صورت نیاز به راهنمایی یا هماهنگی، لطفاً با ما تماس بگیرید.';
	}

	/**
	 * @param string $translation Translated text.
	 * @param string $text        Original text.
	 * @param string $domain      Text domain.
	 */
	public static function translate_woocommerce_strings( $translation, $text, $domain ) {
		if ( 'woocommerce' !== $domain || ! self::is_enabled() ) {
			return $translation;
		}

		$map = array(
			'Sorry, it seems that there are no available payment methods. Please contact us if you require assistance or wish to make alternate arrangements.' => 'متأسفانه در حال حاضر روش پرداختی در دسترس نیست. در صورت نیاز به راهنمایی یا هماهنگی، لطفاً با ما تماس بگیرید.',
			'Have a coupon? %sClick here to enter your code%s' => 'کد تخفیف دارید؟ %sبرای وارد کردن اینجا کلیک کنید%s',
			'Have a coupon? %1$sClick here to enter your code%2$s' => 'کد تخفیف دارید؟ %1$sبرای وارد کردن اینجا کلیک کنید%2$s',
			'Returning customer? %sClick here to login%s' => 'مشتری قبلی هستید؟ %sبرای ورود اینجا کلیک کنید%s',
			'Returning customer? %1$sClick here to login%2$s' => 'مشتری قبلی هستید؟ %1$sبرای ورود اینجا کلیک کنید%2$s',
			'You must be logged in to checkout.' => 'برای تسویه حساب باید وارد حساب کاربری خود شوید.',
			'There are some issues with the items in your cart. Please go back to the cart page and resolve these issues before checking out.' => 'مشکلی در سبد خرید شما وجود دارد. لطفاً به صفحه سبد خرید برگردید و قبل از تسویه حساب آن را برطرف کنید.',
		);

		return isset( $map[ $text ] ) ? $map[ $text ] : $translation;
	}

	/**
	 * @param string $translation Translated text.
	 * @param string $text        Original text.
	 * @param string $context     Context.
	 * @param string $domain      Text domain.
	 */
	public static function translate_woocommerce_strings_with_context( $translation, $text, $context, $domain ) {
		unset( $context );
		return self::translate_woocommerce_strings( $translation, $text, $domain );
	}

	/**
	 * Page content for Twig.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_page_content(): array {
		$page_id = self::get_checkout_page_id();
		$title   = $page_id > 0 ? get_the_title( $page_id ) : __( 'تسویه حساب', 'hello-elementor-child' );
		if ( ! is_string( $title ) || '' === trim( $title ) ) {
			$title = __( 'تسویه حساب', 'hello-elementor-child' );
		}

		return array(
			'title'           => $title,
			'breadcrumb_html' => function_exists( 'hello_elementor_child_get_breadcrumb_html' )
				? hello_elementor_child_get_breadcrumb_html()
				: '',
			'notices_html'    => self::get_notices_html(),
			'content_html'    => self::get_checkout_html(),
		);
	}

	/**
	 * Print Woo notices for placement under the page title.
	 */
	private static function get_notices_html(): string {
		if ( ! function_exists( 'woocommerce_output_all_notices' ) ) {
			return '<div class="woocommerce-notices-wrapper"></div>';
		}

		ob_start();
		echo '<div class="woocommerce-notices-wrapper lk-checkout-notices">';
		woocommerce_output_all_notices();
		echo '</div>';
		return (string) ob_get_clean();
	}

	/**
	 * Render native WooCommerce checkout (core templates only).
	 */
	private static function get_checkout_html(): string {
		if ( ! function_exists( 'WC' ) || ! WC()->checkout() ) {
			return '<p class="woocommerce-info">' . esc_html__( 'ووکامرس در دسترس نیست.', 'hello-elementor-child' ) . '</p>';
		}

		self::$forcing_core_templates = true;

		// On this page Jet must not swap checkout templates for Elementor shells.
		remove_all_filters( 'woocommerce_locate_template' );
		remove_all_filters( 'wc_get_template' );
		add_filter( 'woocommerce_locate_template', array( __CLASS__, 'force_woo_checkout_templates' ), 0, 3 );
		add_filter( 'wc_get_template', array( __CLASS__, 'force_wc_get_template' ), 0, 5 );

		ob_start();

		$cart = WC()->cart;
		if ( ! $cart || $cart->is_empty() ) {
			$empty = self::get_core_checkout_template_path( 'cart/cart-empty.php' );
			if ( $empty ) {
				include $empty;
			} else {
				echo '<p class="cart-empty woocommerce-info">' . esc_html__( 'سبد خرید شما خالی است.', 'hello-elementor-child' ) . '</p>';
				if ( function_exists( 'wc_get_page_permalink' ) ) {
					echo '<p class="return-to-shop"><a class="button wc-backward" href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">' . esc_html__( 'بازگشت به فروشگاه', 'hello-elementor-child' ) . '</a></p>';
				}
			}
		} else {
			$form = self::get_core_checkout_template_path( 'checkout/form-checkout.php' );
			if ( $form ) {
				$checkout = WC()->checkout();
				include $form;
			} elseif ( class_exists( 'WC_Shortcode_Checkout' ) ) {
				WC_Shortcode_Checkout::output( array() );
			}
		}

		$html = (string) ob_get_clean();
		self::$forcing_core_templates = false;

		if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
			return '<p class="woocommerce-info">' . esc_html__( 'فرم تسویه حساب بارگذاری نشد. اگر سبد خرید خالی نیست، کش را پاک کنید و دوباره تلاش کنید.', 'hello-elementor-child' ) . '</p>';
		}

		return $html;
	}
}
