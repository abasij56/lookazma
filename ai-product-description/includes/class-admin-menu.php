<?php
/**
 * WordPress admin menu for AI Product Description.
 *
 * @package AI_Product_Description
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers dashboard menu pages.
 */
final class AI_Product_Desc_Admin_Menu {

	public const PARENT_SLUG   = 'ai-product-description';
	public const SETTINGS_SLUG = 'ai-product-desc-settings';
	public const CAPABILITY    = 'manage_options';

	/**
	 * Hook into WordPress admin.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Top-level menu + subpages.
	 */
	public static function register_menu(): void {
		add_menu_page(
			__( 'توضیحات محصول AI', 'ai-product-description' ),
			__( 'توضیحات محصول AI', 'ai-product-description' ),
			self::CAPABILITY,
			self::PARENT_SLUG,
			array( __CLASS__, 'render_products_page' ),
			'dashicons-edit-page',
			58
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'توضیحات محصول AI', 'ai-product-description' ),
			__( 'توضیحات محصول AI', 'ai-product-description' ),
			self::CAPABILITY,
			self::PARENT_SLUG,
			array( __CLASS__, 'render_products_page' )
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'تنظیمات', 'ai-product-description' ),
			__( 'تنظیمات', 'ai-product-description' ),
			AI_Product_Desc_Settings::CAPABILITY,
			self::SETTINGS_SLUG,
			array( AI_Product_Desc_Settings::class, 'render_page' )
		);
	}

	/**
	 * Enqueue assets on plugin admin pages.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( string $hook ): void {
		$products_hook = 'toplevel_page_' . self::PARENT_SLUG;

		if ( $hook === $products_hook ) {
			ai_product_desc_register_assets();
			wp_enqueue_style( 'ai-product-description' );
			wp_enqueue_script( 'ai-product-description' );
			ai_product_desc_localize_script();
			return;
		}

		if ( self::is_settings_admin_page( $hook ) ) {
			wp_enqueue_style(
				'ai-product-desc-admin',
				AI_PRODUCT_DESC_URL . 'assets/css/admin-settings.css',
				array(),
				AI_PRODUCT_DESC_VERSION
			);

			wp_enqueue_script(
				'ai-product-desc-admin',
				AI_PRODUCT_DESC_URL . 'assets/js/admin-settings.js',
				array(),
				AI_PRODUCT_DESC_VERSION,
				true
			);

			wp_localize_script(
				'ai-product-desc-admin',
				'aiProductDescSettings',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( AI_Product_Desc_Ajax::NONCE_ACTION ),
					'action'  => AI_Product_Desc_Ajax::TEST_CONNECTION,
					'i18n'    => array(
						'testing'    => __( 'در حال تست اتصال…', 'ai-product-description' ),
						'testButton' => __( 'تست اتصال AI', 'ai-product-description' ),
						'error'      => __( 'خطا در تست اتصال.', 'ai-product-description' ),
						'copied'     => __( 'گزارش کپی شد.', 'ai-product-description' ),
						'copyFail'   => __( 'کپی گزارش انجام نشد.', 'ai-product-description' ),
					),
				)
			);
		}
	}

	/**
	 * Whether the current admin screen is plugin settings.
	 *
	 * @param string $hook Current admin page hook.
	 */
	private static function is_settings_admin_page( string $hook ): bool {
		$settings_hook = self::PARENT_SLUG . '_page_' . self::SETTINGS_SLUG;
		if ( $hook === $settings_hook ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';

		return self::SETTINGS_SLUG === $page;
	}

	/**
	 * Main admin page: same UI as the shortcode.
	 */
	public static function render_products_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'ai-product-description' ) );
		}
		?>
		<div class="wrap ai-product-desc-admin-wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php
			if ( ! class_exists( 'WooCommerce' ) ) {
				echo '<p>' . esc_html__( 'WooCommerce is required.', 'ai-product-description' ) . '</p>';
				echo '</div>';
				return;
			}

			ai_product_desc_render_page();
			?>
		</div>
		<?php
	}
}
