<?php
/**
 * Light account template — native chrome; opt-in via My Account page template.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce My Account with shared Lookazma header/footer.
 */
final class Hello_Elementor_Child_Light_Account_Template {

	public const TEMPLATE_FILE = 'page-templates/light-account.php';

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

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'dequeue_listing_assets' ), 100 );
		add_action( 'wp_print_styles', array( __CLASS__, 'dequeue_listing_assets' ), 100 );
		add_action( 'wp_print_scripts', array( __CLASS__, 'dequeue_listing_assets' ), 100 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'filter_menu_items' ), 99 );
		add_filter( 'woocommerce_save_account_details_required_fields', array( __CLASS__, 'optional_account_email' ) );
		add_filter( 'woocommerce_billing_fields', array( __CLASS__, 'shape_address_fields' ), 20 );
		add_filter( 'woocommerce_shipping_fields', array( __CLASS__, 'shape_address_fields' ), 20 );
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
		$templates[ self::TEMPLATE_FILE ] = __( 'Light account template', 'hello-elementor-child' );
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
	 * WooCommerce My Account page ID.
	 */
	public static function get_account_page_id(): int {
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return 0;
		}
		$id = (int) wc_get_page_id( 'myaccount' );
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
		return self::TEMPLATE_FILE === $slug || 'light-account.php' === $slug;
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
		if ( ! function_exists( 'is_account_page' ) ) {
			return false;
		}

		$account_id = self::get_account_page_id();
		if ( $account_id <= 0 || ! self::page_uses_template( $account_id ) ) {
			return false;
		}

		if ( is_account_page() ) {
			return true;
		}

		$object = get_queried_object();
		if ( $object instanceof WP_Post && (int) $object->ID === $account_id ) {
			return true;
		}

		return is_page( $account_id );
	}

	/**
	 * PHP entry path.
	 */
	public static function get_template_path(): string {
		return HELLO_ELEMENTOR_CHILD_PATH . 'page-light-account.php';
	}

	/**
	 * Take over after WooCommerce form handlers (priority 10).
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

		$account_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/light-account.css';
		if ( file_exists( $account_css ) ) {
			wp_enqueue_style(
				'lk-light-account',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/css/light-account.css',
				array( 'lk-light-product', 'lk-lpt-breadcrumb' ),
				(string) filemtime( $account_css )
			);
		}

		wp_enqueue_script( 'jquery' );
		if ( function_exists( 'WC' ) ) {
			wp_enqueue_script( 'woocommerce' );
			wp_enqueue_script( 'wc-cart-fragments' );
			wp_enqueue_script( 'wc-password-strength-meter' );
			wp_enqueue_script( 'wc-address-i18n' );
			wp_enqueue_script( 'selectWoo' );
			wp_enqueue_style( 'select2' );
		}

		$address_js = HELLO_ELEMENTOR_CHILD_PATH . 'assets/js/lk-account-address.js';
		if ( file_exists( $address_js ) ) {
			wp_enqueue_script(
				'lk-account-address',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/js/lk-account-address.js',
				array( 'jquery', 'wc-address-i18n' ),
				(string) filemtime( $address_js ),
				true
			);
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
	 * Drop Elementor/Jet chrome; keep WooCommerce and Digits.
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
		$classes[] = 'lk-light-account';
		$classes[] = 'lz-chrome';

		$skip = array(
			'elementor-default'             => true,
			'elementor-template-full-width' => true,
			'elementor-page'                => true,
		);
		$classes = array_values(
			array_filter(
				$classes,
				static function ( $class ) use ( $skip ) {
					$class = (string) $class;
					if ( isset( $skip[ $class ] ) ) {
						return false;
					}
					return 0 !== strpos( $class, 'elementor-page-' );
				}
			)
		);

		return array_values( array_unique( $classes ) );
	}

	/**
	 * Keep only the three account endpoints in Woo nav (logout is a separate link).
	 *
	 * @param array<string, string> $items Menu items.
	 * @return array<string, string>
	 */
	/**
	 * Email stays optional on the light account form.
	 *
	 * @param array<string, string> $fields Required field labels.
	 * @return array<string, string>
	 */
	public static function optional_account_email( array $fields ): array {
		if ( ! self::is_enabled() ) {
			return $fields;
		}
		unset( $fields['account_email'] );
		return $fields;
	}

	/**
	 * Pair address fields the same way as the account form.
	 *
	 * @param array<string, array<string, mixed>> $fields Address fields.
	 * @return array<string, array<string, mixed>>
	 */
	public static function shape_address_fields( array $fields ): array {
		if ( ! self::is_enabled() ) {
			return $fields;
		}

		$prefix = 'billing_';
		foreach ( array_keys( $fields ) as $key ) {
			if ( 0 === strpos( (string) $key, 'shipping_' ) ) {
				$prefix = 'shipping_';
				break;
			}
		}

		if ( ! isset( $fields[ $prefix . 'company' ] ) ) {
			$fields[ $prefix . 'company' ] = array(
				'label'        => __( 'نام شرکت', 'hello-elementor-child' ),
				'required'     => false,
				'class'        => array(),
				'autocomplete' => 'organization',
				'priority'     => 30,
			);
		}
		if ( ! isset( $fields[ $prefix . 'address_2' ] ) ) {
			$fields[ $prefix . 'address_2' ] = array(
				'label'    => __( 'پلاک', 'hello-elementor-child' ),
				'required' => false,
				'class'    => array( 'address-field' ),
				'priority' => 80,
			);
		}

		$layout = array(
			'first_name' => array( 10, 'lk-acc-third' ),
			'last_name'  => array( 20, 'lk-acc-third' ),
			'company'    => array( 30, 'lk-acc-third' ),
			'country'    => array( 40, 'lk-acc-third' ),
			'state'      => array( 50, 'lk-acc-third' ),
			'city'       => array( 60, 'lk-acc-third' ),
			'address_1'  => array( 70, 'lk-acc-street' ),
			'address_2'  => array( 80, 'lk-acc-plaque' ),
			'postcode'   => array( 90, 'lk-acc-half' ),
			'phone'      => array( 100, 'lk-acc-half' ),
			'email'      => array( 110, 'lk-acc-full' ),
		);
		$labels = array(
			'company'   => __( 'نام شرکت', 'hello-elementor-child' ),
			'address_1' => __( 'خیابان', 'hello-elementor-child' ),
			'address_2' => __( 'پلاک', 'hello-elementor-child' ),
		);

		foreach ( $fields as $key => $field ) {
			$slug = preg_replace( '/^(billing|shipping)_/', '', (string) $key );
			if ( ! isset( $layout[ $slug ] ) || ! is_array( $field ) ) {
				continue;
			}
			$classes = isset( $field['class'] ) && is_array( $field['class'] ) ? $field['class'] : array();
			$classes = array_values(
				array_diff(
					$classes,
					array( 'form-row-first', 'form-row-last', 'form-row-wide', 'lk-acc-third', 'lk-acc-street', 'lk-acc-plaque', 'lk-acc-half', 'lk-acc-full' )
				)
			);
			$classes[]                   = $layout[ $slug ][1];
			$fields[ $key ]['class']     = $classes;
			$fields[ $key ]['priority']  = $layout[ $slug ][0];
			$fields[ $key ]['hidden']    = false;
			if ( isset( $labels[ $slug ] ) ) {
				$fields[ $key ]['label']       = $labels[ $slug ];
				$fields[ $key ]['label_class'] = array();
			}
		}

		return $fields;
	}

	public static function filter_menu_items( array $items ): array {
		if ( ! self::is_enabled() ) {
			return $items;
		}

		return array(
			'edit-account' => __( 'اطلاعات حساب کاربری', 'hello-elementor-child' ),
			'orders'       => __( 'آخرین سفارش ها', 'hello-elementor-child' ),
			'edit-address' => __( 'آدرس حمل و نقل و صورت حساب', 'hello-elementor-child' ),
		);
	}

	/**
	 * Persian strings for WooCommerce account notices/buttons.
	 *
	 * @param string $translation Translated text.
	 * @param string $text        Original text.
	 * @param string $domain      Text domain.
	 */
	public static function translate_woocommerce_strings( $translation, $text, $domain ) {
		if ( ! self::is_enabled() ) {
			return $translation;
		}
		if ( 'digits' === $domain && 'Mobile Number' === $text ) {
			return 'شماره موبایل';
		}
		if ( 'woocommerce' !== $domain ) {
			return $translation;
		}

		$map = array(
			'Confirm email address' => 'تأیید آدرس ایمیل',
			'Confirm your email address to check for past orders and link them to your account.' => 'برای بررسی سفارش‌های قبلی و اتصال آن‌ها به حساب کاربری، آدرس ایمیل خود را تأیید کنید.',
			'Confirm your email address to check for past orders and link them to your account' => 'برای بررسی سفارش‌های قبلی و اتصال آن‌ها به حساب کاربری، آدرس ایمیل خود را تأیید کنید',
			'A confirmation link has been sent to your email address. Please check your inbox.' => 'لینک تأیید به آدرس ایمیل شما ارسال شد. لطفاً صندوق ورودی خود را بررسی کنید.',
			'A confirmation link has been sent to your email address. Please check your inbox' => 'لینک تأیید به آدرس ایمیل شما ارسال شد. لطفاً صندوق ورودی خود را بررسی کنید',
			'Confirm your email address to check for past orders. A confirmation link was sent recently — please check your inbox.' => 'برای بررسی سفارش‌های قبلی، آدرس ایمیل خود را تأیید کنید. لینک تأیید به‌تازگی ارسال شده است — لطفاً صندوق ورودی خود را بررسی کنید.',
			'Confirm your email address to check for past orders. A confirmation link was sent recently — please check your inbox' => 'برای بررسی سفارش‌های قبلی، آدرس ایمیل خود را تأیید کنید. لینک تأیید به‌تازگی ارسال شده است — لطفاً صندوق ورودی خود را بررسی کنید',
			'Confirm your email address to check for past orders. A confirmation link was sent recently - please check your inbox.' => 'برای بررسی سفارش‌های قبلی، آدرس ایمیل خود را تأیید کنید. لینک تأیید به‌تازگی ارسال شده است — لطفاً صندوق ورودی خود را بررسی کنید.',
			'Confirm your email address to check for past orders. A confirmation link was sent recently - please check your inbox' => 'برای بررسی سفارش‌های قبلی، آدرس ایمیل خود را تأیید کنید. لینک تأیید به‌تازگی ارسال شده است — لطفاً صندوق ورودی خود را بررسی کنید',
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
	 * Current WooCommerce account endpoint slug.
	 */
	public static function get_current_endpoint(): string {
		if ( ! function_exists( 'WC' ) || ! WC()->query ) {
			return '';
		}
		$endpoint = (string) WC()->query->get_current_endpoint();
		return $endpoint;
	}

	/**
	 * Active sidebar tab: account | orders | addresses.
	 */
	public static function get_active_tab(): string {
		$endpoint = self::get_current_endpoint();
		if ( in_array( $endpoint, array( 'orders', 'view-order' ), true ) ) {
			return 'orders';
		}
		if ( 'edit-address' === $endpoint ) {
			return 'addresses';
		}
		return 'account';
	}

	/**
	 * Page content for Twig.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_page_content(): array {
		$logged_in = is_user_logged_in();
		$user      = $logged_in ? wp_get_current_user() : null;
		$name      = '';
		if ( $user instanceof WP_User ) {
			$name = (string) $user->display_name;
			if ( '' === trim( $name ) ) {
				$name = (string) $user->user_login;
			}
		}

		$account_url  = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'edit-account' ) : '';
		$orders_url   = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'orders' ) : '';
		$address_url  = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'edit-address' ) : '';
		$logout_url   = function_exists( 'wc_logout_url' ) ? wc_logout_url() : wp_logout_url();
		$active_tab   = self::get_active_tab();
		$panel_files  = array(
			'account'   => 'user.svg',
			'orders'    => 'package.svg',
			'addresses' => 'map-pin.svg',
		);
		$panel_titles = array(
			'account'   => __( 'اطلاعات حساب کاربری', 'hello-elementor-child' ),
			'orders'    => __( 'آخرین سفارش ها', 'hello-elementor-child' ),
			'addresses' => __( 'آدرس حمل و نقل و صورت حساب', 'hello-elementor-child' ),
		);

		return array(
			'logged_in'       => $logged_in,
			'display_name'    => $name,
			'welcome_suffix'  => 'خوش آمدید.',
			'active_tab'      => $active_tab,
			'avatar_icon'     => self::icon_html( 'user.svg', 'lk-account-avatar__icon', 26 ),
			'chevron_icon'    => self::icon_html( 'chevron-left.svg', 'lk-account-tab__chevron', 16 ),
			'account_icon'    => self::icon_html( 'user.svg', 'lk-account-tab__icon', 18 ),
			'orders_icon'     => self::icon_html( 'package.svg', 'lk-account-tab__icon', 18 ),
			'address_icon'    => self::icon_html( 'map-pin.svg', 'lk-account-tab__icon', 18 ),
			'logout_icon'     => self::icon_html( 'log-out.svg', 'lk-account-logout__icon', 18 ),
			'panel_icon'      => self::icon_html( $panel_files[ $active_tab ] ?? 'user.svg', 'lk-account-panel__icon', 22 ),
			'panel_title'     => $panel_titles[ $active_tab ] ?? $panel_titles['account'],
			'account_url'     => $account_url,
			'orders_url'      => $orders_url,
			'address_url'     => $address_url,
			'logout_url'      => $logout_url,
			'account_label'   => $panel_titles['account'],
			'orders_label'    => $panel_titles['orders'],
			'address_label'   => $panel_titles['addresses'],
			'logout_label'    => __( 'خروج', 'hello-elementor-child' ),
			'breadcrumb_html' => function_exists( 'hello_elementor_child_get_breadcrumb_html' )
				? hello_elementor_child_get_breadcrumb_html()
				: '',
			'content_html'    => self::get_account_content_html(),
		);
	}

	/**
	 * Render WooCommerce endpoint markup (no default navigation).
	 */
	private static function get_account_content_html(): string {
		if ( ! function_exists( 'wc_get_template' ) ) {
			return '';
		}

		ob_start();

		if ( function_exists( 'wc_print_notices' ) ) {
			wc_print_notices();
		}

		if ( ! is_user_logged_in() ) {
			wc_get_template( 'myaccount/form-login.php' );
			return (string) ob_get_clean();
		}

		$endpoint = self::get_current_endpoint();
		if ( '' === $endpoint || 'dashboard' === $endpoint ) {
			if ( function_exists( 'woocommerce_account_edit_account' ) ) {
				woocommerce_account_edit_account();
			} else {
				wc_get_template(
					'myaccount/form-edit-account.php',
					array(
						'user' => get_user_by( 'id', get_current_user_id() ),
					)
				);
			}
		} elseif ( function_exists( 'woocommerce_account_content' ) ) {
			woocommerce_account_content();
		}

		return (string) ob_get_clean();
	}

	/**
	 * Inline Lucide SVG from assets/icons/.
	 *
	 * @param string $file  Filename e.g. user.svg.
	 * @param string $class SVG class.
	 * @param int    $size  Pixel size.
	 */
	public static function icon_html( string $file, string $class = 'lk-account-icon', int $size = 18 ): string {
		$file = basename( $file );
		if ( ! preg_match( '/^[a-z0-9\-]+\.svg$/i', $file ) ) {
			return '';
		}
		$path = HELLO_ELEMENTOR_CHILD_PATH . 'assets/icons/' . $file;
		if ( ! is_readable( $path ) ) {
			return '';
		}
		$svg = (string) file_get_contents( $path );
		if ( '' === $svg ) {
			return '';
		}
		$size = max( 12, min( 48, $size ) );
		$svg  = preg_replace( '/\s(width|height)="[^"]*"/i', '', $svg, 2 );
		$svg  = preg_replace(
			'/<svg\b/i',
			sprintf(
				'<svg class="%1$s" width="%2$d" height="%2$d" aria-hidden="true" focusable="false"',
				esc_attr( $class ),
				$size
			),
			$svg,
			1
		);
		return is_string( $svg ) ? $svg : '';
	}
}
