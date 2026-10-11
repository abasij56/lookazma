<?php
/**
 * Product comparison — localStorage-driven, AJAX data.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compare list validation and AJAX.
 */
final class Hello_Elementor_Child_Product_Compare {

	public const ACTION = 'lk_compare_products';

	public const SEARCH_ACTION = 'lk_compare_search';

	public const SEARCH_LIMIT = 16;

	public const MAX_ITEMS = 4;

	public const MIN_ITEMS = 2;

	public const STORAGE_KEY = 'lk_compare_ids';

	/**
	 * Whether compare tray markup was already printed this request.
	 *
	 * @var bool
	 */
	private static bool $tray_printed = false;

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'ajax_products' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( __CLASS__, 'ajax_products' ) );
		add_action( 'wp_ajax_' . self::SEARCH_ACTION, array( __CLASS__, 'ajax_search' ) );
		add_action( 'wp_ajax_nopriv_' . self::SEARCH_ACTION, array( __CLASS__, 'ajax_search' ) );
		add_action( 'init', array( __CLASS__, 'register_assets' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_site_chrome' ), 35 );
		add_action( 'wp_head', array( __CLASS__, 'print_critical_chrome_css' ), 4 );
		add_action( 'wp_footer', array( __CLASS__, 'print_tray_button_overrides' ), 99 );
		add_action( 'wp_footer', array( __CLASS__, 'render_tray_markup' ), 5 );
		add_filter( 'style_loader_tag', array( __CLASS__, 'mark_compare_style_no_async' ), 25, 2 );
	}

	/**
	 * Critical FAB + full tray panel CSS in <head>.
	 * Keeps shop/archive trays matching the global panel when LiteSpeed async/UCSS delays light-compare.css.
	 */
	public static function print_critical_chrome_css(): void {
		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}
		if ( class_exists( 'Hello_Elementor_Child_Light_Compare_Template' )
			&& Hello_Elementor_Child_Light_Compare_Template::is_enabled()
		) {
			return;
		}
		if ( ! self::should_load_chrome() ) {
			return;
		}
		echo '<style id="lk-compare-chrome-critical">'
			. '.lk-compare-chrome{position:relative;z-index:10060}'
			. '.lk-compare-chrome[hidden]{display:none!important}'
			. '.lk-compare-fab-wrap{position:fixed;inset-inline-end:1.25rem;bottom:1.25rem;z-index:10070;width:auto;min-width:10rem}'
			. 'button.lk-compare-fab,.lk-compare-fab{position:relative;display:inline-flex!important;align-items:center;justify-content:center;gap:.55rem;width:auto!important;min-width:10rem!important;height:56px!important;min-height:56px!important;padding:0 1.15rem!important;border:none!important;border-radius:999px!important;background:#001f3f!important;background-image:none!important;color:#fff!important;font-family:inherit!important;font-size:1.05rem!important;font-weight:800!important;line-height:1!important;text-transform:none!important;box-shadow:0 12px 32px rgba(11,31,58,.3)!important;cursor:pointer}'
			. '.lk-compare-fab__label{flex:0 0 auto;line-height:1}'
			. '.lk-compare-fab__count{display:inline-flex!important;align-items:center;justify-content:center;min-width:1.6rem;height:1.6rem;padding:0 .35rem;border-radius:999px!important;background:#0d6e6e!important;color:#fff!important;font-size:.85rem;font-weight:800;line-height:1}'
			. '.lk-compare-fab__popup{position:absolute;inset-inline-end:0;bottom:calc(100% + .65rem);z-index:1;box-sizing:border-box;width:max-content;min-width:100%;max-width:min(280px,calc(100vw - 2.5rem));margin:0;padding:.7rem .95rem .8rem;border-radius:12px;background:#0d6e6e!important;color:#fff!important;font-size:.88rem;font-weight:700;line-height:1.45;text-align:center;white-space:normal;box-shadow:0 10px 24px rgba(11,31,58,.22);pointer-events:none}'
			. '.lk-compare-fab__popup[hidden]{display:none!important}'
			. '.lk-compare-tray[hidden]{display:none!important}'
			. '.lk-compare-tray{position:fixed;inset:0;z-index:2147483000;display:none!important;align-items:flex-end;justify-content:center}'
			. '.lk-compare-tray.is-open,.lk-compare-tray[data-open="1"]{display:flex!important}'
			. '.lk-compare-tray__backdrop{position:absolute;inset:0;z-index:0;background:rgba(11,31,58,.45)}'
			. '.lk-compare-tray__panel{position:relative;z-index:2;box-sizing:border-box;width:min(720px,100%);max-height:min(78vh,600px);display:flex;flex-direction:column;padding:1.15rem 1.15rem 1.25rem;border-radius:18px 18px 0 0;border-top:3px solid #0d6e6e;background:#fff!important;box-shadow:0 -12px 40px rgba(11,31,58,.18)}'
			. '.lk-compare-tray__head{display:flex;align-items:center;justify-content:space-between;gap:.75rem;margin-bottom:1rem;position:relative;z-index:2}'
			. '.lk-compare-tray__heading{display:flex;align-items:center;flex-wrap:wrap;gap:.45rem .65rem;flex:1 1 auto;min-width:0}'
			. '.lk-compare-tray__title{margin:0;font-size:1.05rem;font-weight:800;color:#0b1f3a;min-width:0}'
			. '.lk-compare-tray__count{display:inline-flex;align-items:center;justify-content:center;padding:.2rem .55rem;border-radius:999px;background:rgba(13,110,110,.1);color:#0d6e6e;font-size:.78rem;font-weight:800;line-height:1.2;white-space:nowrap}'
			. '.lk-compare-tray__count[hidden]{display:none!important}'
			. '.lk-compare-tray__close{display:none!important}'
			. '.lk-cmp-panel-x{appearance:none;display:inline-flex;align-items:center;justify-content:center;flex:0 0 2.1rem;'
			. 'box-sizing:border-box;width:2.1rem;height:2.1rem;margin:0;padding:0;border:0;border-radius:999px;'
			. 'background:#f3f7fb;color:#5b6b7c;font-family:Arial,Helvetica,sans-serif;font-size:1.35rem;font-weight:700;'
			. 'line-height:1;text-decoration:none;cursor:pointer;user-select:none;-webkit-user-select:none}'
			. '.lk-cmp-panel-x:hover,.lk-cmp-panel-x:focus{background:#eef3f8;color:#0d6e6e;outline:none}'
			. '.lk-compare-tray__list{display:flex;flex-wrap:wrap;gap:.85rem;overflow:auto;padding:.85rem .65rem;min-height:8rem;border-radius:14px;background:#f5f9fb;border:1px solid rgba(217,227,236,.85);box-sizing:border-box}'
			. '.lk-compare-tray__empty{margin:0;width:100%;text-align:center;color:#5b6b7c;font-size:.9rem;padding:1.75rem .5rem}'
			. '.lk-compare-tray__item{position:relative;flex:1 1 9.5rem;max-width:11rem;display:flex;flex-direction:column;align-items:center;gap:.5rem;padding:.85rem .65rem .9rem;border:1px solid #d9e3ec;border-radius:12px;background:#fff;text-align:center;box-sizing:border-box}'
			. '.lk-compare-tray__thumb{display:flex;align-items:center;justify-content:center;width:96px;height:96px;padding:.4rem;box-sizing:border-box;border-radius:10px;overflow:hidden;background:#fff;border:1px solid #e6eef5}'
			. '.lk-compare-tray__thumb img{display:block;max-width:100%;max-height:100%;width:auto;height:auto;object-fit:contain;object-position:center}'
			. '.lk-compare-tray__thumb--empty{background:linear-gradient(135deg,#eef3f8 0%,#e2ebf2 100%)}'
			. '.lk-compare-tray__name{margin:0;font-size:.78rem;font-weight:700;line-height:1.4;color:#0b1f3a;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}'
			. '.lk-compare-tray__actions{display:flex;align-items:center;justify-content:space-between;gap:.75rem;margin-top:1rem;padding-top:1rem;border-top:1px solid #d9e3ec;direction:ltr}'
			. '.lk-compare-tray__go.is-disabled{opacity:.45;pointer-events:none}'
			. 'html.lk-compare-tray-open{overflow:hidden}'
			. '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Late tray button resets — beat WooCommerce/theme button !important on shop.
	 */
	public static function print_tray_button_overrides(): void {
		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}
		if ( class_exists( 'Hello_Elementor_Child_Light_Compare_Template' )
			&& Hello_Elementor_Child_Light_Compare_Template::is_enabled()
		) {
			return;
		}
		if ( ! self::should_load_chrome() ) {
			return;
		}

		static $printed = false;
		if ( $printed ) {
			return;
		}
		$printed = true;

		$remove = 'body.woocommerce .lk-compare-tray button.lk-compare-tray__remove,'
			. 'body.woocommerce-page .lk-compare-tray button.lk-compare-tray__remove,'
			. 'body.woocommerce-shop .lk-compare-tray button.lk-compare-tray__remove,'
			. 'body.lk-light-product .lk-compare-tray button.lk-compare-tray__remove,'
			. '.lk-compare-tray button.lk-compare-tray__remove';

		$clear = 'body.woocommerce .lk-compare-tray button.lk-compare-tray__clear,'
			. 'body.woocommerce-page .lk-compare-tray button.lk-compare-tray__clear,'
			. 'body.woocommerce-shop .lk-compare-tray button.lk-compare-tray__clear,'
			. 'body.lk-light-product .lk-compare-tray button.lk-compare-tray__clear,'
			. '.lk-compare-tray button.lk-compare-tray__clear';

		$go = 'body.woocommerce .lk-compare-tray a.lk-compare-tray__go,'
			. 'body.woocommerce-page .lk-compare-tray a.lk-compare-tray__go,'
			. 'body.woocommerce-shop .lk-compare-tray a.lk-compare-tray__go,'
			. 'body.lk-light-product .lk-compare-tray a.lk-compare-tray__go,'
			. '.lk-compare-tray a.lk-compare-tray__go';

		echo '<style id="lk-compare-tray-btn-overrides">'
			. '.lk-compare-tray__close{display:none!important}'
			. '.lk-cmp-panel-x{appearance:none!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;'
			. 'flex:0 0 2.1rem!important;box-sizing:border-box!important;width:2.1rem!important;height:2.1rem!important;'
			. 'min-width:2.1rem!important;min-height:2.1rem!important;margin:0!important;padding:0!important;'
			. 'border:0!important;border-radius:999px!important;background:#f3f7fb!important;background-color:#f3f7fb!important;'
			. 'background-image:none!important;color:#5b6b7c!important;font-family:Arial,Helvetica,sans-serif!important;'
			. 'font-size:1.35rem!important;font-weight:700!important;line-height:1!important;text-decoration:none!important;'
			. 'box-shadow:none!important;cursor:pointer!important;user-select:none!important}'
			. '.lk-cmp-panel-x:hover,.lk-cmp-panel-x:focus{background:#eef3f8!important;color:#0d6e6e!important;outline:none!important}'
			. $remove . '{'
			. 'appearance:none!important;-webkit-appearance:none!important;'
			. 'position:absolute!important;top:.35rem!important;inset-inline-end:.35rem!important;'
			. 'display:inline-flex!important;align-items:center!important;justify-content:center!important;'
			. 'box-sizing:border-box!important;'
			. 'width:1.45rem!important;height:1.45rem!important;min-width:1.45rem!important;min-height:1.45rem!important;max-width:1.45rem!important;max-height:1.45rem!important;'
			. 'margin:0!important;padding:0!important;'
			. 'border:none!important;border-width:0!important;border-radius:999px!important;'
			. 'background:#fff!important;background-color:#fff!important;background-image:none!important;'
			. 'color:#9b1c1c!important;'
			. 'font-family:inherit!important;font-size:1rem!important;font-weight:700!important;line-height:1!important;'
			. 'letter-spacing:normal!important;text-transform:none!important;'
			. 'box-shadow:0 1px 4px rgba(11,31,58,.12)!important;cursor:pointer!important;'
			. '}'
			. $clear . '{'
			. 'appearance:none!important;-webkit-appearance:none!important;'
			. 'display:inline-flex!important;align-items:center!important;justify-content:center!important;'
			. 'box-sizing:border-box!important;'
			. 'min-height:2.5rem!important;height:auto!important;width:auto!important;min-width:0!important;max-width:none!important;'
			. 'margin:0!important;padding:.45rem .85rem!important;'
			. 'border:none!important;border-width:0!important;border-radius:10px!important;'
			. 'background:transparent!important;background-color:transparent!important;background-image:none!important;'
			. 'color:#5b6b7c!important;'
			. 'font-family:inherit!important;font-size:.88rem!important;font-weight:700!important;line-height:1.2!important;'
			. 'letter-spacing:normal!important;text-transform:none!important;text-shadow:none!important;'
			. 'box-shadow:none!important;cursor:pointer!important;'
			. '}'
			. $clear . ':hover,' . $clear . ':focus-visible{'
			. 'color:#0d6e6e!important;background:rgba(13,110,110,.08)!important;outline:none!important;'
			. '}'
			. $go . '{'
			. 'display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:.4rem!important;'
			. 'box-sizing:border-box!important;'
			. 'min-height:2.5rem!important;height:auto!important;width:auto!important;'
			. 'margin:0!important;padding:.5rem 1.35rem!important;'
			. 'border:none!important;border-radius:10px!important;'
			. 'background:#0d6e6e!important;background-color:#0d6e6e!important;background-image:none!important;'
			. 'color:#fff!important;'
			. 'font-family:inherit!important;font-size:.9rem!important;font-weight:800!important;line-height:1.2!important;'
			. 'letter-spacing:normal!important;text-transform:none!important;text-decoration:none!important;text-shadow:none!important;'
			. 'box-shadow:0 6px 16px rgba(13,110,110,.28)!important;cursor:pointer!important;'
			. '}'
			. '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Keep light-compare.css render-blocking (LiteSpeed async/UCSS often drops tray item rules on shop).
	 *
	 * @param string $html   Link tag HTML.
	 * @param string $handle Style handle.
	 */
	public static function mark_compare_style_no_async( string $html, string $handle ): string {
		if ( 'lk-light-compare' !== $handle ) {
			return $html;
		}
		if ( false !== strpos( $html, 'data-no-async' ) ) {
			return $html;
		}
		return str_replace( '<link ', '<link data-no-async="1" data-no-optimize="1" data-no-ucss="1" ', $html );
	}

	/**
	 * Compare icon SVG (Streamline Rounded Material — local asset).
	 *
	 * @param int $size Pixel size.
	 */
	public static function get_icon_svg( int $size = 20 ): string {
		$size = max( 12, min( 48, $size ) );
		$path = HELLO_ELEMENTOR_CHILD_PATH . 'assets/icons/compare.svg';
		if ( file_exists( $path ) ) {
			$svg = (string) file_get_contents( $path );
			$svg = preg_replace( '/\s(width|height)="[^"]*"/', '', $svg, 2 );
			$svg = preg_replace(
				'/<svg\b/',
				sprintf(
					'<svg class="lk-compare-icon" width="%1$d" height="%1$d" aria-hidden="true" focusable="false"',
					$size
				),
				$svg,
				1
			);
			if ( is_string( $svg ) && '' !== $svg ) {
				return $svg;
			}
		}

		return sprintf(
			'<svg class="lk-compare-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="%1$d" height="%1$d" aria-hidden="true" focusable="false"><path fill="currentColor" d="M10.55 21H4.5c-0.4 0 -0.75 -0.15 -1.05 -0.45 -0.3 -0.3 -0.45 -0.65 -0.45 -1.05V4.5c0 -0.4 0.15 -0.75 0.45 -1.05C3.75 3.15 4.1 3 4.5 3h6.05V1.75c0 -0.2125 0.07235 -0.390665 0.217 -0.5345 0.1445 -0.143665 0.32365 -0.2155 0.5375 -0.2155 0.21365 0 0.39135 0.071835 0.533 0.2155 0.14165 0.143835 0.2125 0.322 0.2125 0.5345v20.5c0 0.2125 -0.07235 0.3906 -0.217 0.53425 -0.1445 0.14385 -0.32365 0.21575 -0.5375 0.21575 -0.21365 0 -0.39135 -0.0719 -0.533 -0.21575 -0.14165 -0.14365 -0.2125 -0.32175 -0.2125 -0.53425V21ZM4.5 18.45h6.05V11.525L4.5 18.45ZM13.55 21V11.625L19.5 18.45V4.5H13.55V3H19.5c0.4 0 0.75 0.15 1.05 0.45 0.3 0.3 0.45 0.65 0.45 1.05v15c0 0.4 -0.15 0.75 -0.45 1.05 -0.3 0.3 -0.65 0.45 -1.05 0.45H13.55Z"/></svg>',
			$size
		);
	}

	/**
	 * Whether any light / chrome front template is active.
	 */
	public static function is_light_front(): bool {
		$classes = array(
			'Hello_Elementor_Child_Light_Product_Template',
			'Hello_Elementor_Child_Light_Homepage_Template',
			'Hello_Elementor_Child_Light_About_Template',
			'Hello_Elementor_Child_Light_Contact_Template',
			'Hello_Elementor_Child_Light_Cart_Template',
			'Hello_Elementor_Child_Light_Checkout_Template',
			'Hello_Elementor_Child_Light_Account_Template',
			'Hello_Elementor_Child_Light_Brands_Template',
			'Hello_Elementor_Child_Light_Article_Template',
			'Hello_Elementor_Child_Light_Content_Template',
			'Hello_Elementor_Child_Light_404_Template',
			'Hello_Elementor_Child_Light_Login_Template',
			'Hello_Elementor_Child_Custom_Category_Archive',
			'Hello_Elementor_Child_Custom_Tag_Archive',
			'Hello_Elementor_Child_Custom_Brand_Single',
			'Hello_Elementor_Child_Custom_Single_Product',
			'Hello_Elementor_Child_Custom_Single_Post',
		);

		foreach ( $classes as $class ) {
			if ( class_exists( $class ) && method_exists( $class, 'is_enabled' ) && $class::is_enabled() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Enqueue compare chrome on light front pages (tray + card toggles).
	 */
	public static function maybe_enqueue_site_chrome(): void {
		if ( class_exists( 'Hello_Elementor_Child_Light_Compare_Template' )
			&& Hello_Elementor_Child_Light_Compare_Template::is_enabled()
		) {
			return;
		}
		if ( ! self::should_load_chrome() ) {
			return;
		}
		self::enqueue_assets( false );
	}

	/**
	 * Whether compare tray/toggles should load on this request.
	 */
	public static function should_load_chrome(): bool {
		if ( self::is_light_front() ) {
			return true;
		}
		if ( class_exists( 'Hello_Elementor_Child_Archive_Product_Filter' )
			&& method_exists( 'Hello_Elementor_Child_Archive_Product_Filter', 'uses_shop_cards_ui' )
			&& Hello_Elementor_Child_Archive_Product_Filter::uses_shop_cards_ui()
		) {
			return true;
		}
		return false;
	}

	/**
	 * Bottom compare tray + floating trigger (B&H-style).
	 */
	public static function render_tray_markup(): void {
		if ( self::$tray_printed ) {
			return;
		}
		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}
		if ( class_exists( 'Hello_Elementor_Child_Light_Compare_Template' )
			&& Hello_Elementor_Child_Light_Compare_Template::is_enabled()
		) {
			return;
		}
		if ( ! self::should_load_chrome() ) {
			return;
		}
		if ( ! wp_script_is( 'lk-product-compare', 'enqueued' ) ) {
			self::enqueue_assets( false );
		}

		$html = self::get_tray_html();
		if ( '' === $html ) {
			return;
		}
		self::$tray_printed = true;
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Compare FAB + tray HTML (safe to embed in Twig).
	 */
	public static function get_tray_html(): string {
		$compare_url = class_exists( 'Hello_Elementor_Child_Light_Compare_Template' )
			? Hello_Elementor_Child_Light_Compare_Template::get_page_url()
			: home_url( '/compare/' );

		ob_start();
		?>
		<div class="lk-compare-chrome" data-lk-compare-chrome hidden>
			<div class="lk-compare-fab-wrap">
				<span class="lk-compare-fab__popup" data-lk-compare-toast hidden role="status" aria-live="polite"></span>
				<button
					type="button"
					class="lk-compare-fab"
					data-lk-compare-fab
					aria-expanded="false"
					aria-controls="lk-compare-tray"
				>
					<span class="lk-compare-fab__label"><?php esc_html_e( 'مقایسه', 'hello-elementor-child' ); ?></span>
					<span class="lk-compare-fab__count" data-lk-compare-fab-count>0</span>
				</button>
			</div>

			<div
				class="lk-compare-tray"
				id="lk-compare-tray"
				data-lk-compare-tray
				hidden
				aria-hidden="true"
			>
				<div class="lk-compare-tray__backdrop" data-lk-compare-tray-close tabindex="-1" aria-hidden="true"></div>
				<div
					class="lk-compare-tray__panel"
					role="dialog"
					aria-modal="true"
					aria-labelledby="lk-compare-tray-title"
				>
					<div class="lk-compare-tray__head">
						<div class="lk-compare-tray__heading">
							<h2 class="lk-compare-tray__title" id="lk-compare-tray-title">
								<?php esc_html_e( 'محصولات انتخاب‌شده', 'hello-elementor-child' ); ?>
							</h2>
							<span class="lk-compare-tray__count" data-lk-compare-tray-count hidden></span>
						</div>
						<span
							class="lk-cmp-panel-x"
							data-lk-cmp-panel-x
							role="button"
							tabindex="0"
							aria-label="<?php esc_attr_e( 'بستن', 'hello-elementor-child' ); ?>"
						>&times;</span>
					</div>
					<div class="lk-compare-tray__list" data-lk-compare-tray-list></div>
					<div class="lk-compare-tray__actions">
						<button type="button" class="lk-compare-tray__clear" data-lk-compare-tray-clear>
							<?php esc_html_e( 'پاک کردن', 'hello-elementor-child' ); ?>
						</button>
						<a
							class="lk-compare-tray__go"
							data-lk-compare-tray-go
							href="<?php echo esc_url( $compare_url ); ?>"
						>
							<span><?php esc_html_e( 'مقایسه', 'hello-elementor-child' ); ?></span>
						</a>
					</div>
				</div>
			</div>
		</div>
		<?php
		$html = (string) ob_get_clean();
		if ( '' !== $html ) {
			self::$tray_printed = true;
		}
		return $html;
	}

	/**
	 * Register front-end assets (enqueue separately).
	 */
	public static function register_assets(): void {
		$js = HELLO_ELEMENTOR_CHILD_PATH . 'assets/js/lk-product-compare.js';
		if ( ! file_exists( $js ) ) {
			return;
		}

		wp_register_script(
			'lk-product-compare',
			HELLO_ELEMENTOR_CHILD_URI . 'assets/js/lk-product-compare.js',
			array(),
			(string) filemtime( $js ),
			true
		);
	}

	/**
	 * Enqueue compare script with config.
	 *
	 * @param bool $is_compare_page Whether the compare page is active.
	 */
	public static function enqueue_assets( bool $is_compare_page = false ): void {
		self::register_assets();

		if ( ! wp_style_is( 'lk-light-product', 'enqueued' ) ) {
			wp_enqueue_style(
				'lk-light-product',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-light-product.css',
				array( HELLO_ELEMENTOR_CHILD_VAZIRMATN_HANDLE ),
				HELLO_ELEMENTOR_CHILD_VERSION
			);
		}

		$css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/light-compare.css';
		if ( file_exists( $css ) ) {
			/* No deps — Vazirmatn/light-product may only exist as Twig <link> on shop; deps would risk dropping this sheet. */
			wp_enqueue_style(
				'lk-light-compare',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/css/light-compare.css',
				array(),
				(string) filemtime( $css )
			);
		}

		wp_enqueue_script( 'lk-product-compare' );

		wp_localize_script(
			'lk-product-compare',
			'lkProductCompare',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'action'      => self::ACTION,
				'nonce'       => wp_create_nonce( 'lk_compare_products' ),
				'storageKey'  => self::STORAGE_KEY,
				'pendingKey'  => 'lk_compare_pending_id',
				'maxItems'    => self::MAX_ITEMS,
				'minItems'    => self::MIN_ITEMS,
				'compareUrl'  => class_exists( 'Hello_Elementor_Child_Light_Compare_Template' )
					? Hello_Elementor_Child_Light_Compare_Template::get_page_url()
					: '',
				'shopUrl'     => function_exists( 'hello_elementor_child_get_shop_url' )
					? hello_elementor_child_get_shop_url()
					: home_url( '/فروشگاه/' ),
				'isComparePage' => $is_compare_page,
				'searchAction'  => self::SEARCH_ACTION,
				'searchLimit'   => self::SEARCH_LIMIT,
				'searchMinChars' => 0,
				'i18n'        => array(
					'button'           => __( 'مقایسه محصول', 'hello-elementor-child' ),
					'compare'          => __( 'مقایسه', 'hello-elementor-child' ),
					'clear'            => __( 'پاک کردن', 'hello-elementor-child' ),
					'inCompare'        => __( 'در مقایسه', 'hello-elementor-child' ),
					'added'            => __( 'به مقایسه اضافه شد.', 'hello-elementor-child' ),
					'removed'          => __( 'از مقایسه حذف شد.', 'hello-elementor-child' ),
					'full'             => __( 'حداکثر ۴ محصول قابل مقایسه است.', 'hello-elementor-child' ),
					'slotsFullRemove'  => __( 'همه جایگاه‌های مقایسه پر است. یک محصول را حذف کنید تا محصول جدید جایگزین شود.', 'hello-elementor-child' ),
					'pickVariation'    => __( 'ابتدا بسته‌بندی را انتخاب کنید.', 'hello-elementor-child' ),
					'emptyTitle'       => __( 'محصولی برای مقایسه انتخاب نشده', 'hello-elementor-child' ),
					'emptyHint'        => __( 'محصول اول را از لیست جستجو انتخاب کنید.', 'hello-elementor-child' ),
					'addProduct'       => __( 'افزودن کالا', 'hello-elementor-child' ),
					'addAnother'       => __( 'افزودن محصول دیگر', 'hello-elementor-child' ),
					'emptyNoProduct'   => __( 'کالایی اضافه نکرده‌اید', 'hello-elementor-child' ),
					'emptyMaxHint'     => __( 'می‌توانید ۴ کالا را باهم مقایسه کنید', 'hello-elementor-child' ),
					'close'            => __( 'بستن', 'hello-elementor-child' ),
					'searchPlaceholder' => __( 'جستجوی محصول…', 'hello-elementor-child' ),
					'searchProducts'   => __( 'محصولات', 'hello-elementor-child' ),
					'searchEmpty'      => __( 'نتیجه‌ای یافت نشد.', 'hello-elementor-child' ),
					'searchError'      => __( 'خطا در جستجو.', 'hello-elementor-child' ),
					'alreadyAdded'     => __( 'این محصول قبلاً اضافه شده.', 'hello-elementor-child' ),
					'pickFirst'        => __( 'از لیست کنار انتخاب کنید.', 'hello-elementor-child' ),
					'needMore'         => __( 'حداقل ۲ محصول برای مقایسه لازم است.', 'hello-elementor-child' ),
					'remove'           => __( 'حذف', 'hello-elementor-child' ),
					'viewProduct'      => __( 'مشاهده و خرید', 'hello-elementor-child' ),
					'specLabel'        => __( 'مشخصه', 'hello-elementor-child' ),
					'emptyCell'        => '—',
					'error'            => __( 'خطا در بارگذاری مقایسه.', 'hello-elementor-child' ),
					'loading'          => __( 'در حال بارگذاری…', 'hello-elementor-child' ),
					'trayTitle'        => __( 'محصولات انتخاب‌شده', 'hello-elementor-child' ),
					'trayEmpty'        => __( 'هنوز محصولی انتخاب نشده است.', 'hello-elementor-child' ),
					/* translators: %s: selected product count (Persian digits). */
					'trayCount'        => __( '%s محصول', 'hello-elementor-child' ),
				),
			)
		);
	}

	/**
	 * Sanitize and validate product / variation IDs.
	 *
	 * @param array<int, mixed> $raw_ids Raw IDs.
	 * @return array<int, int>
	 */
	public static function sanitize_ids( array $raw_ids ): array {
		$out = array();
		foreach ( $raw_ids as $raw ) {
			$id = (int) $raw;
			if ( $id <= 0 ) {
				continue;
			}
			$compare_id = self::resolve_compare_product_id( $id );
			if ( $compare_id <= 0 || in_array( $compare_id, $out, true ) ) {
				continue;
			}
			$out[] = $compare_id;
			if ( count( $out ) >= self::MAX_ITEMS ) {
				break;
			}
		}

		return $out;
	}

	/**
	 * Resolve a product/variation ID to a compare slot ID (variable parent for variations).
	 *
	 * @param int $id Product or variation ID.
	 */
	public static function resolve_compare_product_id( int $id ): int {
		if ( $id <= 0 ) {
			return 0;
		}

		$product = wc_get_product( $id );
		if ( ! $product || 'publish' !== $product->get_status() ) {
			return 0;
		}

		if ( $product->is_type( 'variation' ) ) {
			$parent_id = (int) $product->get_parent_id();
			return $parent_id > 0 ? $parent_id : 0;
		}

		if ( $product->is_type( 'simple' ) || $product->is_type( 'variable' ) ) {
			return (int) $product->get_id();
		}

		return 0;
	}

	/**
	 * AJAX: comparison matrix for IDs.
	 */
	public static function ajax_products(): void {
		check_ajax_referer( 'lk_compare_products', 'nonce' );

		if ( ! class_exists( 'Hello_Elementor_Child_Light_Product_Specs' ) ) {
			wp_send_json_error( array( 'message' => 'specs_unavailable' ), 500 );
		}

		$slots_raw = isset( $_POST['slots'] ) ? wp_unslash( $_POST['slots'] ) : '';
		if ( is_string( $slots_raw ) && '' !== trim( $slots_raw ) ) {
			$decoded = json_decode( $slots_raw, true );
			if ( is_array( $decoded ) ) {
				$slots  = self::sanitize_slots( $decoded );
				$matrix = Hello_Elementor_Child_Light_Product_Specs::build_matrix_slots( $slots );
				$filled = count( array_filter( $slots ) );

				wp_send_json_success(
					array(
						'products'   => $matrix['products'],
						'rows'       => $matrix['rows'],
						'slots'      => $slots,
						'count'      => $filled,
						'canCompare' => $filled >= self::MIN_ITEMS,
					)
				);
			}
		}

		$raw = isset( $_POST['ids'] ) ? wp_unslash( $_POST['ids'] ) : '';
		$ids = array();
		if ( is_array( $raw ) ) {
			$ids = self::sanitize_ids( $raw );
		} elseif ( is_string( $raw ) && '' !== trim( $raw ) ) {
			$parts = array_map( 'trim', explode( ',', $raw ) );
			$ids   = self::sanitize_ids( $parts );
		}

		$matrix = Hello_Elementor_Child_Light_Product_Specs::build_matrix( $ids );

		wp_send_json_success(
			array(
				'products'   => $matrix['products'],
				'rows'       => $matrix['rows'],
				'count'      => count( $matrix['products'] ),
				'canCompare' => count( $matrix['products'] ) >= self::MIN_ITEMS,
			)
		);
	}

	/**
	 * Sanitize fixed slot array (length MAX_ITEMS).
	 *
	 * @param array<int, mixed> $raw_slots Raw slot values.
	 * @return array<int, int>
	 */
	public static function sanitize_slots( array $raw_slots ): array {
		$slots = array_fill( 0, self::MAX_ITEMS, 0 );
		$slice = array_slice( array_values( $raw_slots ), 0, self::MAX_ITEMS );

		foreach ( $slice as $index => $raw ) {
			$id = (int) $raw;
			if ( $id <= 0 ) {
				continue;
			}
			$compare_id = self::resolve_compare_product_id( $id );
			if ( $compare_id <= 0 ) {
				continue;
			}
			if ( in_array( $compare_id, $slots, true ) ) {
				continue;
			}
			$slots[ (int) $index ] = $compare_id;
		}

		return $slots;
	}

	/**
	 * AJAX: product picker list for compare page.
	 */
	public static function ajax_search(): void {
		check_ajax_referer( 'lk_compare_products', 'nonce' );

		$q = isset( $_REQUEST['q'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['q'] ) ) : '';
		$q = trim( $q );

		wp_send_json_success(
			array(
				'products' => self::search_pickable_products( $q, self::SEARCH_LIMIT ),
			)
		);
	}

	/**
	 * Products available in compare picker (simple / non-variable only).
	 *
	 * @param string $q     Search query; empty returns recent products.
	 * @param int    $limit Max rows.
	 * @return array<int, array{id:int,name:string,url:string,meta:string,image:string}>
	 */
	private static function search_pickable_products( string $q, int $limit ): array {
		$limit = max( 1, min( 30, $limit ) );
		$ids   = array();

		if ( '' === $q ) {
			$query = new WP_Query(
				array(
					'post_type'              => 'product',
					'post_status'            => 'publish',
					'posts_per_page'         => $limit * 3,
					'orderby'                => 'date',
					'order'                  => 'DESC',
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);
			$ids = is_array( $query->posts ) ? $query->posts : array();
		} else {
			global $wpdb;

			$like        = '%' . $wpdb->esc_like( $q ) . '%';
			$prefix_like = $wpdb->esc_like( $q ) . '%';

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names from $wpdb.
			$sql = $wpdb->prepare(
				"SELECT DISTINCT p.ID
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} sku
					ON ( p.ID = sku.post_id AND sku.meta_key = '_sku' )
				LEFT JOIN {$wpdb->postmeta} cas
					ON ( p.ID = cas.post_id AND cas.meta_key = 'cas_no' )
				WHERE p.post_type = 'product'
					AND p.post_status = 'publish'
					AND (
						p.post_title LIKE %s
						OR sku.meta_value LIKE %s
						OR cas.meta_value LIKE %s
					)
				ORDER BY
					CASE
						WHEN p.post_title LIKE %s THEN 0
						WHEN sku.meta_value LIKE %s THEN 1
						WHEN cas.meta_value LIKE %s THEN 2
						ELSE 3
					END,
					p.post_title ASC
				LIMIT %d",
				$like,
				$like,
				$like,
				$prefix_like,
				$prefix_like,
				$prefix_like,
				$limit * 3
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$ids = $wpdb->get_col( $sql );
			if ( ! is_array( $ids ) ) {
				$ids = array();
			}
		}

		$placeholder = '';
		if ( function_exists( 'wc_placeholder_img_src' ) ) {
			$placeholder = (string) wc_placeholder_img_src( 'thumbnail' );
		}

		$out = array();
		foreach ( $ids as $raw_id ) {
			if ( count( $out ) >= $limit ) {
				break;
			}

			$id = (int) $raw_id;
			if ( $id <= 0 ) {
				continue;
			}

			$product = wc_get_product( $id );
			if ( ! $product || 'publish' !== $product->get_status() ) {
				continue;
			}
			if ( ! $product->is_type( 'simple' ) && ! $product->is_type( 'variable' ) ) {
				continue;
			}

			$picker_id = (int) $product->get_id();

			$cas = '';
			if ( function_exists( 'get_field' ) ) {
				$raw_cas = get_field( 'cas_no', $picker_id );
				if ( is_scalar( $raw_cas ) ) {
					$cas = trim( (string) $raw_cas );
				}
			}
			if ( '' === $cas ) {
				$meta = get_post_meta( $picker_id, 'cas_no', true );
				if ( is_scalar( $meta ) ) {
					$cas = trim( (string) $meta );
				}
			}

			$sku  = (string) $product->get_sku();
			$meta = '' !== $cas ? $cas : $sku;

			$image  = '';
			$img_id = (int) $product->get_image_id();
			if ( $img_id <= 0 ) {
				$img_id = (int) get_post_thumbnail_id( $id );
			}
			if ( $img_id > 0 ) {
				$url = wp_get_attachment_image_url( $img_id, 'thumbnail' );
				if ( ! is_string( $url ) || '' === $url ) {
					$url = wp_get_attachment_url( $img_id );
				}
				if ( is_string( $url ) && '' !== $url ) {
					$image = $url;
				}
			}
			if ( '' === $image ) {
				$image = $placeholder;
			}

			$out[] = array(
				'id'    => $picker_id,
				'name'  => $product->get_name(),
				'url'   => get_permalink( $picker_id ),
				'meta'  => $meta,
				'image' => $image,
			);
		}

		return $out;
	}
}
