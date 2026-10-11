<?php
/**
 * Sticky sidebar mini-cart drawer (Lookazma light chrome).
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders mini-cart, fragments, and qty/remove AJAX.
 */
final class Hello_Elementor_Child_Sticky_Mini_Cart {

	public const FRAGMENT_PANEL = 'div.lk-mini-cart__panel';

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 40 );
		/* Render before wp_print_footer_scripts (20) so JS can find the drawer. */
		add_action( 'wp_footer', array( __CLASS__, 'render_drawer' ), 5 );

		add_filter( 'woocommerce_add_to_cart_fragments', array( __CLASS__, 'cart_fragments' ), 20 );
		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'flag_open_after_add' ), 20 );

		add_action( 'wp_ajax_lk_mini_cart_set_qty', array( __CLASS__, 'ajax_set_qty' ) );
		add_action( 'wp_ajax_nopriv_lk_mini_cart_set_qty', array( __CLASS__, 'ajax_set_qty' ) );
		add_action( 'wp_ajax_lk_mini_cart_remove', array( __CLASS__, 'ajax_remove' ) );
		add_action( 'wp_ajax_nopriv_lk_mini_cart_remove', array( __CLASS__, 'ajax_remove' ) );
	}

	/**
	 * Remember to open the drawer after a non-AJAX add-to-cart redirect.
	 */
	public static function flag_open_after_add(): void {
		if ( wp_doing_ajax() ) {
			return;
		}
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}
		WC()->session->set( 'lk_open_mini_cart', '1' );
	}

	/**
	 * Whether session asks to open the drawer once.
	 */
	public static function consume_open_flag(): bool {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return false;
		}
		$flag = (string) WC()->session->get( 'lk_open_mini_cart' );
		if ( '1' === $flag ) {
			WC()->session->set( 'lk_open_mini_cart', null );
			return true;
		}
		return false;
	}

	/**
	 * Enqueue CSS/JS on storefront.
	 */
	public static function enqueue_assets(): void {
		if ( is_admin() || ! function_exists( 'WC' ) ) {
			return;
		}

		$css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/sticky-mini-cart.css';
		$js  = HELLO_ELEMENTOR_CHILD_PATH . 'assets/js/sticky-mini-cart.js';
		if ( ! file_exists( $css ) || ! file_exists( $js ) ) {
			return;
		}

		wp_enqueue_style(
			'lk-sticky-mini-cart',
			HELLO_ELEMENTOR_CHILD_URI . 'assets/css/sticky-mini-cart.css',
			array(),
			(string) filemtime( $css )
		);

		wp_enqueue_script(
			'lk-sticky-mini-cart',
			HELLO_ELEMENTOR_CHILD_URI . 'assets/js/sticky-mini-cart.js',
			array( 'jquery' ),
			(string) filemtime( $js ),
			true
		);

		wp_localize_script(
			'lk-sticky-mini-cart',
			'lkMiniCart',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'wcAjaxUrl'  => class_exists( 'WC_AJAX' ) ? WC_AJAX::get_endpoint( '%%endpoint%%' ) : home_url( '/?wc-ajax=%%endpoint%%' ),
				'nonce'      => wp_create_nonce( 'lk_mini_cart' ),
				'cartUrl'    => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ),
				'openOnLoad' => self::consume_open_flag(),
				'i18n'       => array(
					'empty' => __( 'سبد خرید خالی است.', 'hello-elementor-child' ),
				),
			)
		);

		wp_enqueue_script( 'wc-cart-fragments' );
	}

	/**
	 * Footer markup.
	 */
	public static function render_drawer(): void {
		if ( is_admin() || ! function_exists( 'WC' ) ) {
			return;
		}
		?>
		<div class="lk-mini-cart" id="lk-mini-cart" hidden data-lk-mini-cart>
			<div class="lk-mini-cart__backdrop" data-lk-mini-cart-close tabindex="-1"></div>
			<div class="lk-mini-cart__panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'سبد خرید', 'hello-elementor-child' ); ?>">
				<?php self::render_panel_inner(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Panel body (also used as AJAX fragment).
	 */
	public static function render_panel_inner(): void {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		$cart     = WC()->cart;
		$items    = $cart->get_cart();
		$cart_url = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
		?>
		<div class="lk-mini-cart__head">
			<h2 class="lk-mini-cart__title"><?php esc_html_e( 'سبد خرید', 'hello-elementor-child' ); ?></h2>
			<button type="button" class="lk-mini-cart__close" data-lk-mini-cart-close aria-label="<?php esc_attr_e( 'بستن', 'hello-elementor-child' ); ?>">×</button>
		</div>

		<div class="lk-mini-cart__body">
			<?php if ( empty( $items ) ) : ?>
				<p class="lk-mini-cart__empty"><?php esc_html_e( 'سبد خرید خالی است.', 'hello-elementor-child' ); ?></p>
			<?php else : ?>
				<ul class="lk-mini-cart__list">
					<?php foreach ( $items as $key => $item ) : ?>
						<?php
						$product = isset( $item['data'] ) && is_object( $item['data'] ) ? $item['data'] : null;
						if ( ! $product || ! $product->exists() ) {
							continue;
						}
						$qty   = (int) $item['quantity'];
						$price = (float) wc_get_price_to_display( $product );
						$name  = $product->get_name();
						$thumb = $product->get_image( 'woocommerce_single', array( 'class' => 'lk-mini-cart__thumb-img' ) );
						$link  = $product->is_visible() ? $product->get_permalink() : '';
						?>
						<li class="lk-mini-cart__item" data-cart-key="<?php echo esc_attr( $key ); ?>">
							<div class="lk-mini-cart__thumb">
								<?php if ( $link ) : ?>
									<a href="<?php echo esc_url( $link ); ?>"><?php echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
								<?php else : ?>
									<?php echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<?php endif; ?>
							</div>
							<div class="lk-mini-cart__info">
								<p class="lk-mini-cart__name">
									<?php if ( $link ) : ?>
										<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $name ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $name ); ?>
									<?php endif; ?>
								</p>
								<p class="lk-mini-cart__line-price">
									<?php
									echo esc_html( (string) $qty );
									echo ' × ';
									echo wp_kses_post( wc_price( $price ) );
									?>
								</p>
								<div class="lk-mini-cart__qty" data-lk-mini-qty>
									<button type="button" class="lk-mini-cart__qty-btn" data-lk-mini-qty-minus aria-label="<?php esc_attr_e( 'کاهش تعداد', 'hello-elementor-child' ); ?>">−</button>
									<span class="lk-mini-cart__qty-val" data-lk-mini-qty-val><?php echo esc_html( (string) $qty ); ?></span>
									<button type="button" class="lk-mini-cart__qty-btn" data-lk-mini-qty-plus aria-label="<?php esc_attr_e( 'افزایش تعداد', 'hello-elementor-child' ); ?>">+</button>
								</div>
							</div>
							<button type="button" class="lk-mini-cart__remove" data-lk-mini-remove aria-label="<?php esc_attr_e( 'حذف', 'hello-elementor-child' ); ?>">×</button>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>

		<div class="lk-mini-cart__foot">
			<?php if ( ! empty( $items ) ) : ?>
				<dl class="lk-mini-cart__totals">
					<div class="lk-mini-cart__total-row">
						<dt><?php esc_html_e( 'جمع جزء', 'hello-elementor-child' ); ?></dt>
						<dd><?php echo wp_kses_post( $cart->get_cart_subtotal() ); ?></dd>
					</div>
					<div class="lk-mini-cart__total-row">
						<dt><?php esc_html_e( 'هزینه ارسال', 'hello-elementor-child' ); ?></dt>
						<dd><?php echo wp_kses_post( self::get_shipping_display() ); ?></dd>
					</div>
					<?php if ( wc_tax_enabled() ) : ?>
						<div class="lk-mini-cart__total-row">
							<dt><?php esc_html_e( 'مالیات', 'hello-elementor-child' ); ?></dt>
							<dd><?php echo wp_kses_post( wc_price( (float) $cart->get_total_tax() ) ); ?></dd>
						</div>
					<?php endif; ?>
					<div class="lk-mini-cart__total-row lk-mini-cart__total-row--grand">
						<dt><?php esc_html_e( 'جمع کل', 'hello-elementor-child' ); ?></dt>
						<dd><?php echo wp_kses_post( $cart->get_total() ); ?></dd>
					</div>
				</dl>
			<?php endif; ?>

			<a class="lk-mini-cart__cart-btn" href="<?php echo esc_url( $cart_url ); ?>">
				<?php esc_html_e( 'سبد خرید', 'hello-elementor-child' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Shipping line for mini-cart totals.
	 */
	private static function get_shipping_display(): string {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return esc_html__( 'در مرحله بعد', 'hello-elementor-child' );
		}
		$cart  = WC()->cart;
		$total = (float) $cart->get_shipping_total();
		if ( $total <= 0 ) {
			return esc_html__( 'در مرحله بعد', 'hello-elementor-child' );
		}
		return wc_price( $total + (float) $cart->get_shipping_tax() );
	}

	/**
	 * Cart fragments for panel.
	 *
	 * @param array<string, string> $fragments Fragments.
	 * @return array<string, string>
	 */
	public static function cart_fragments( array $fragments ): array {
		ob_start();
		echo '<div class="lk-mini-cart__panel" role="dialog" aria-modal="true" aria-label="' . esc_attr__( 'سبد خرید', 'hello-elementor-child' ) . '">';
		self::render_panel_inner();
		echo '</div>';
		$fragments[ self::FRAGMENT_PANEL ] = ob_get_clean();

		return $fragments;
	}

	/**
	 * AJAX: set line quantity.
	 */
	public static function ajax_set_qty(): void {
		check_ajax_referer( 'lk_mini_cart', 'nonce' );
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error();
		}
		$key = isset( $_POST['key'] ) ? wc_clean( wp_unslash( (string) $_POST['key'] ) ) : '';
		$qty = isset( $_POST['qty'] ) ? (int) $_POST['qty'] : 0;
		if ( '' === $key ) {
			wp_send_json_error();
		}
		if ( $qty <= 0 ) {
			WC()->cart->remove_cart_item( $key );
		} else {
			WC()->cart->set_quantity( $key, $qty, true );
		}
		WC()->cart->calculate_totals();
		wp_send_json_success( array( 'fragments' => apply_filters( 'woocommerce_add_to_cart_fragments', array() ) ) );
	}

	/**
	 * AJAX: remove line.
	 */
	public static function ajax_remove(): void {
		check_ajax_referer( 'lk_mini_cart', 'nonce' );
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error();
		}
		$key = isset( $_POST['key'] ) ? wc_clean( wp_unslash( (string) $_POST['key'] ) ) : '';
		if ( '' === $key ) {
			wp_send_json_error();
		}
		WC()->cart->remove_cart_item( $key );
		WC()->cart->calculate_totals();
		wp_send_json_success( array( 'fragments' => apply_filters( 'woocommerce_add_to_cart_fragments', array() ) ) );
	}
}
