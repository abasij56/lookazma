<?php
/**
 * Auto-submit product descriptions by brand (product_tag).
 *
 * @package AI_Product_Description
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin page: batch AI generate + save for all products of a brand.
 */
final class AI_Product_Desc_Auto_Submit {

	public const SLUG = 'ai-product-desc-auto-submit';

	/**
	 * Hook into admin.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_submenu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Register submenu under AI Product Description.
	 */
	public static function register_submenu(): void {
		add_submenu_page(
			AI_Product_Desc_Admin_Menu::PARENT_SLUG,
			__( 'ثبت اتوماتیک', 'ai-product-description' ),
			__( 'ثبت اتوماتیک', 'ai-product-description' ),
			AI_Product_Desc_Admin_Menu::CAPABILITY,
			self::SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Enqueue assets on auto-submit page only.
	 *
	 * @param string $hook Admin hook.
	 */
	public static function enqueue_assets( string $hook ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';
		$expected_hook = AI_Product_Desc_Admin_Menu::PARENT_SLUG . '_page_' . self::SLUG;
		if ( $page !== self::SLUG && $hook !== $expected_hook ) {
			return;
		}

		wp_enqueue_style( 'dashicons' );

		wp_enqueue_style(
			'ai-product-desc-auto-submit',
			AI_PRODUCT_DESC_URL . 'assets/css/admin-auto-submit.css',
			array( 'dashicons' ),
			AI_PRODUCT_DESC_VERSION
		);

		wp_enqueue_script(
			'ai-product-desc-auto-submit',
			AI_PRODUCT_DESC_URL . 'assets/js/admin-auto-submit.js',
			array(),
			AI_PRODUCT_DESC_VERSION,
			true
		);

		$provider   = AI_Product_Desc_Settings::get_active_provider_config();
		$configured = '' !== trim( $provider['api_key'] )
			&& '' !== trim( $provider['base_url'] )
			&& '' !== trim( $provider['model'] );

		wp_localize_script(
			'ai-product-desc-auto-submit',
			'aiProductDescAuto',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( AI_Product_Desc_Ajax::NONCE_ACTION ),
				'isConfigured' => $configured ? 1 : 0,
				'actions'      => array(
					'brandProducts' => AI_Product_Desc_Ajax::AUTO_BRAND_PRODUCTS,
					'processOne'    => AI_Product_Desc_Ajax::AUTO_PROCESS_ONE,
				),
				'i18n'         => array(
					'notConfigured' => __( 'تنظیمات AI کامل نیست. از منوی «توضیحات محصول AI ← تنظیمات» پر کنید.', 'ai-product-description' ),
					'pickBrand'     => __( 'ابتدا یک برند انتخاب کنید.', 'ai-product-description' ),
					'noProducts'    => __( 'محصولی برای این برند پیدا نشد.', 'ai-product-description' ),
					'invalidStart'  => __( 'شماره محصول نامعتبر است.', 'ai-product-description' ),
					'invalidCount'  => __( 'تعداد محصول نامعتبر است.', 'ai-product-description' ),
					'genericError'  => __( 'خطا در ارتباط با سرور.', 'ai-product-description' ),
					'all'           => __( 'همه', 'ai-product-description' ),
					'customCount'   => __( 'تعداد دلخواه', 'ai-product-description' ),
				),
			)
		);
	}

	/**
	 * Render admin page.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( AI_Product_Desc_Admin_Menu::CAPABILITY ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'ai-product-description' ) );
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			echo '<div class="wrap"><p>' . esc_html__( 'WooCommerce is required.', 'ai-product-description' ) . '</p></div>';
			return;
		}

		$tags = get_terms(
			array(
				'taxonomy'   => 'product_tag',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		if ( is_wp_error( $tags ) ) {
			$tags = array();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin filter via GET.
		$selected_tag = isset( $_GET['ai_auto_tag'] ) ? absint( wp_unslash( $_GET['ai_auto_tag'] ) ) : 0;

		$products = array();
		if ( $selected_tag > 0 ) {
			$products = AI_Product_Desc_Ajax::get_master_products_for_tag( $selected_tag );
		}
		$count = count( $products );
		?>
		<div class="wrap ai-auto-submit" id="ai-auto-submit-root" data-selected-tag="<?php echo esc_attr( (string) $selected_tag ); ?>" data-product-count="<?php echo esc_attr( (string) $count ); ?>">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'برند را انتخاب کنید، پیش‌نمایش محصولات را ببینید، سپس ثبت اتوماتیک را بزنید.', 'ai-product-description' ); ?>
			</p>

			<div class="ai-auto-submit__panel">
				<form method="get" action="" class="ai-auto-submit__brand-form" id="ai-auto-brand-form">
					<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>" />
					<div class="ai-auto-submit__row ai-auto-submit__row--brand">
						<label for="ai-auto-brand" class="ai-auto-submit__label">
							<?php esc_html_e( 'برند', 'ai-product-description' ); ?>
						</label>
						<div class="ai-auto-submit__brand-controls">
							<select id="ai-auto-brand" name="ai_auto_tag" class="ai-auto-submit__select">
								<option value=""><?php esc_html_e( '-- انتخاب برند --', 'ai-product-description' ); ?></option>
								<?php foreach ( $tags as $tag ) : ?>
									<?php if ( ! $tag instanceof WP_Term ) { continue; } ?>
									<option value="<?php echo esc_attr( (string) $tag->term_id ); ?>" <?php selected( $selected_tag, (int) $tag->term_id ); ?>>
										<?php echo esc_html( $tag->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<button type="submit" class="button button-secondary" id="ai-auto-show-btn">
								<?php esc_html_e( 'نمایش پیش‌نمایش', 'ai-product-description' ); ?>
							</button>
						</div>
					</div>
				</form>

				<?php if ( $selected_tag > 0 ) : ?>
					<div class="ai-auto-submit__row ai-auto-submit__row--count" id="ai-auto-count-row">
						<p id="ai-auto-count" class="ai-auto-submit__count">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: product count */
									__( 'تعداد محصولات این برند %d می باشد', 'ai-product-description' ),
									$count
								)
							);
							?>
						</p>
					</div>

					<?php if ( $count > 0 ) : ?>
						<div class="ai-auto-submit__row ai-auto-submit__row--range" id="ai-auto-range-row">
							<div class="ai-auto-submit__range-controls">
								<label class="ai-auto-submit__range-label" for="ai-auto-start-row">
									<?php esc_html_e( 'ثبت از محصول شمارهٔ', 'ai-product-description' ); ?>
									<input
										type="number"
										id="ai-auto-start-row"
										class="ai-auto-submit__range-input small-text"
										min="1"
										max="<?php echo esc_attr( (string) $count ); ?>"
										value="1"
									/>
									<?php esc_html_e( 'به تعداد', 'ai-product-description' ); ?>
									<select id="ai-auto-count-mode" class="ai-auto-submit__range-select">
										<option value="all"><?php esc_html_e( 'همه', 'ai-product-description' ); ?></option>
										<option value="custom"><?php esc_html_e( 'تعداد دلخواه', 'ai-product-description' ); ?></option>
									</select>
									<input
										type="number"
										id="ai-auto-count-process"
										class="ai-auto-submit__range-input small-text"
										min="1"
										max="<?php echo esc_attr( (string) $count ); ?>"
										value="1"
										hidden
									/>
									<?php esc_html_e( 'محصول', 'ai-product-description' ); ?>
								</label>
							</div>
							<div class="ai-auto-submit__range-actions">
								<button
									type="button"
									id="ai-auto-cancel"
									class="button ai-auto-submit__cancel"
									hidden
								>
									<?php esc_html_e( 'لغو', 'ai-product-description' ); ?>
								</button>
								<button
									type="button"
									id="ai-auto-start"
									class="button button-primary ai-auto-submit__start"
								>
									<?php esc_html_e( 'ثبت اتوماتیک توضیحات محصولات', 'ai-product-description' ); ?>
								</button>
							</div>
						</div>
					<?php endif; ?>

					<div class="ai-auto-submit__notice" id="ai-auto-notice" hidden role="alert"></div>

					<div class="ai-auto-submit__preview-wrap" id="ai-auto-preview-wrap" <?php echo 0 === $count ? 'hidden' : ''; ?>>
						<h2 class="ai-auto-submit__preview-title"><?php esc_html_e( 'پیش‌نمایش محصولات این برند', 'ai-product-description' ); ?></h2>
						<?php if ( 0 === $count ) : ?>
							<p><?php esc_html_e( 'محصولی برای این برند پیدا نشد.', 'ai-product-description' ); ?></p>
						<?php else : ?>
							<div class="ai-auto-submit__preview-table-wrap">
								<table class="widefat striped ai-auto-submit__preview-table" id="ai-auto-preview-table">
									<thead>
										<tr>
											<th class="ai-auto-submit__col-num"><?php esc_html_e( 'ردیف', 'ai-product-description' ); ?></th>
											<th class="ai-auto-submit__col-id"><?php esc_html_e( 'شناسه', 'ai-product-description' ); ?></th>
											<th class="ai-auto-submit__col-name"><?php esc_html_e( 'نام محصول', 'ai-product-description' ); ?></th>
											<th class="ai-auto-submit__col-has-desc"><?php esc_html_e( 'توضیحات دارد', 'ai-product-description' ); ?></th>
											<th class="ai-auto-submit__col-status"><?php esc_html_e( 'وضعیت', 'ai-product-description' ); ?></th>
										</tr>
									</thead>
									<tbody id="ai-auto-preview-body">
										<?php foreach ( $products as $i => $item ) : ?>
											<tr data-product-id="<?php echo esc_attr( (string) $item['id'] ); ?>">
												<td><?php echo esc_html( (string) ( $i + 1 ) ); ?></td>
												<td class="ai-auto-submit__product-id"><?php echo esc_html( (string) $item['id'] ); ?></td>
												<td><?php echo esc_html( $item['name'] ); ?></td>
												<td class="ai-auto-submit__has-desc <?php echo ! empty( $item['has_description'] ) ? 'ai-auto-submit__has-desc--yes' : 'ai-auto-submit__has-desc--no'; ?>">
													<?php
													echo esc_html(
														! empty( $item['has_description'] )
															? __( 'بله', 'ai-product-description' )
															: __( 'خیر', 'ai-product-description' )
													);
													?>
												</td>
												<td class="ai-auto-submit__row-status" data-state="idle">
													<div class="ai-auto-submit__row-progress">
														<span class="ai-auto-submit__row-icon" aria-hidden="true"></span>
														<div class="ai-auto-submit__row-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
															<div class="ai-auto-submit__row-fill"></div>
														</div>
													</div>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						<?php endif; ?>
					</div>

					<script type="application/json" id="ai-auto-products-json"><?php echo wp_json_encode( $products ); ?></script>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
