<?php
/**
 * Site-wide snippets migrated from the Code Snippets plugin.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce filters, shortcodes, and small global assets.
 */
final class Hello_Elementor_Child_Site_Snippets {

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'replace_zero_price_with_contact_text' ), 100, 2 );
		add_filter( 'woocommerce_sale_flash', array( __CLASS__, 'display_percentage_on_sale_badge' ), 20, 3 );
		add_shortcode( 'login_text', array( __CLASS__, 'login_text_shortcode' ) );
		add_action( 'wp_head', array( __CLASS__, 'render_chrome_meta_tags' ), 1 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 20 );
	}

	/**
	 * Show contact text instead of zero price.
	 *
	 * @param string      $price   Price HTML.
	 * @param WC_Product  $product Product.
	 */
	public static function replace_zero_price_with_contact_text( string $price, $product ): string {
		if ( ! is_a( $product, 'WC_Product' ) ) {
			return $price;
		}

		if ( (float) $product->get_price() === 0.0 ) {
			return '<span class="contact-for-price">با ما تماس بگیرید</span>';
		}

		return $price;
	}

	/**
	 * Replace default sale badge with percentage discount.
	 *
	 * @param string      $html    Badge HTML.
	 * @param WP_Post     $post    Post.
	 * @param WC_Product  $product Product.
	 */
	public static function display_percentage_on_sale_badge( string $html, $post, $product ): string {
		if ( ! is_a( $product, 'WC_Product' ) ) {
			return $html;
		}

		if ( $product->is_type( 'variable' ) ) {
			$percentages = array();
			$prices      = $product->get_variation_prices();

			foreach ( $prices['price'] as $key => $price ) {
				if ( $prices['regular_price'][ $key ] !== $price ) {
					$percentages[] = round( 100 - ( (float) $prices['sale_price'][ $key ] / (float) $prices['regular_price'][ $key ] * 100 ) );
				}
			}

			if ( empty( $percentages ) ) {
				return $html;
			}

			$percentage = max( $percentages ) . '%';
		} elseif ( $product->is_type( 'grouped' ) ) {
			$percentages   = array();
			$children_ids  = $product->get_children();

			foreach ( $children_ids as $child_id ) {
				$child_product = wc_get_product( $child_id );
				if ( ! $child_product ) {
					continue;
				}

				$regular_price = (float) $child_product->get_regular_price();
				$sale_price    = (float) $child_product->get_sale_price();

				if ( 0.0 !== $sale_price || ! empty( $sale_price ) ) {
					$percentages[] = round( 100 - ( $sale_price / $regular_price * 100 ) );
				}
			}

			if ( empty( $percentages ) ) {
				return $html;
			}

			$percentage = max( $percentages ) . '%';
		} else {
			$regular_price = (float) $product->get_regular_price();
			$sale_price    = (float) $product->get_sale_price();

			if ( 0.0 === $sale_price && empty( $sale_price ) ) {
				return $html;
			}

			$percentage = round( 100 - ( $sale_price / $regular_price * 100 ) ) . '%';
		}

		return '<span class="onsale">' . esc_html__( 'off', 'woocommerce' ) . ' ' . $percentage . '</span>';
	}

	/**
	 * Shortcode [login_text] — legacy Elementor/content usage.
	 */
	public static function login_text_shortcode(): string {
		return is_user_logged_in() ? 'حساب کاربری' : 'ورود';
	}

	/**
	 * Mobile browser chrome color meta tags.
	 */
	public static function render_chrome_meta_tags(): void {
		echo '<meta name="theme-color" content="#001F3F">' . "\n";
		echo '<meta name="msapplication-navbutton-color" content="#001F3F">' . "\n";
		echo '<meta name="apple-mobile-web-app-status-bar-style" content="#001F3F">' . "\n";
	}

	/**
	 * Enqueue migrated snippet styles.
	 */
	public static function enqueue_assets(): void {
		if ( is_admin() ) {
			return;
		}

		$css_path = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/site-snippets.css';
		if ( ! file_exists( $css_path ) ) {
			return;
		}

		wp_enqueue_style(
			'hello-elementor-child-site-snippets',
			HELLO_ELEMENTOR_CHILD_URI . 'assets/css/site-snippets.css',
			array(),
			(string) filemtime( $css_path )
		);
	}
}
