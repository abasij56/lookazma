<?php
/**
 * Attribute Price Editor feature.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers shortcode, assets, and AJAX for the price editor UI.
 */
final class Hello_Elementor_Child_Attribute_Price_Editor {

	public const SHORTCODE       = 'attr_price_editor';
	public const SCRIPT_HANDLE   = 'hello-elementor-child-attr-price-editor';
	public const STYLE_HANDLE    = 'hello-elementor-child-attr-price-editor';
	public const AJAX_ACTION     = 'attr_price_editor_update';
	public const NONCE_ACTION    = 'attr_price_editor_update';

	/**
	 * Hook into WordPress.
	 */
	public static function init(): void {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );

		Hello_Elementor_Child_Attribute_Price_Editor_Ajax::init();
	}

	/**
	 * Register front-end assets (enqueued when shortcode renders).
	 */
	public static function register_assets(): void {
		wp_register_style(
			self::STYLE_HANDLE,
			HELLO_ELEMENTOR_CHILD_URI . 'assets/css/attribute-price-editor.css',
			array(),
			HELLO_ELEMENTOR_CHILD_VERSION
		);

		wp_register_script(
			self::SCRIPT_HANDLE,
			HELLO_ELEMENTOR_CHILD_URI . 'assets/js/attribute-price-editor.js',
			array( 'jquery' ),
			HELLO_ELEMENTOR_CHILD_VERSION,
			true
		);

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'attrPriceEditor',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::AJAX_ACTION,
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
			)
		);
	}

	/**
	 * Shortcode callback.
	 *
	 * @return string
	 */
	public static function render_shortcode(): string {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return '<p>' . esc_html__( 'WooCommerce is required.', 'hello-elementor-child' ) . '</p>';
		}

		wp_enqueue_style( self::STYLE_HANDLE );
		wp_enqueue_script( self::SCRIPT_HANDLE );

		ob_start();
		Hello_Elementor_Child_Attribute_Price_Editor_Renderer::render_page();
		return ob_get_clean();
	}
}
