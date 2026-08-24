<?php
/**
 * Product category AI SEO + description tools on edit screen.
 *
 * @package AI_Product_Description
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders UI under product category edit form and helpers for Yoast term SEO.
 */
final class AI_Product_Desc_Category_Tools {

	public const TAXONOMY = 'product_cat';

	/**
	 * Hook into admin.
	 */
	public static function init(): void {
		add_action( self::TAXONOMY . '_edit_form', array( __CLASS__, 'render_edit_panel' ), 20, 1 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Load assets only on product category screens.
	 *
	 * @param string $hook Admin hook.
	 */
	public static function enqueue_assets( string $hook ): void {
		if ( 'term.php' !== $hook && 'edit-tags.php' !== $hook ) {
			return;
		}

		$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( self::TAXONOMY !== $taxonomy ) {
			return;
		}

		wp_enqueue_editor();
		wp_enqueue_media();

		wp_enqueue_style(
			'ai-product-desc-category',
			AI_PRODUCT_DESC_URL . 'assets/css/admin-category.css',
			array(),
			AI_PRODUCT_DESC_VERSION
		);

		wp_enqueue_script(
			'ai-product-desc-category',
			AI_PRODUCT_DESC_URL . 'assets/js/admin-category.js',
			array( 'jquery', 'editor' ),
			AI_PRODUCT_DESC_VERSION,
			true
		);

		$provider = AI_Product_Desc_Settings::get_active_provider_config();
		$configured = '' !== trim( $provider['api_key'] )
			&& '' !== trim( $provider['base_url'] )
			&& '' !== trim( $provider['model'] );

		wp_localize_script(
			'ai-product-desc-category',
			'aiProductDescCategory',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( AI_Product_Desc_Ajax::NONCE_ACTION ),
				'isConfigured' => $configured ? 1 : 0,
				'specFields'   => self::get_acf_spec_fields(),
				'actions'      => array(
					'generateSeo'   => AI_Product_Desc_Ajax::CAT_SEO_GENERATE,
					'saveSeo'       => AI_Product_Desc_Ajax::CAT_SEO_SAVE,
					'generateDesc'  => AI_Product_Desc_Ajax::CAT_DESC_GENERATE,
					'saveDesc'      => AI_Product_Desc_Ajax::CAT_DESC_SAVE,
					'generateSpecs' => AI_Product_Desc_Ajax::CAT_SPECS_GENERATE,
					'saveSpecs'     => AI_Product_Desc_Ajax::CAT_SPECS_SAVE,
				),
				'i18n'         => array(
					'notConfigured'  => __( 'تنظیمات AI کامل نیست. از منوی «توضیحات محصول AI ← تنظیمات» پر کنید.', 'ai-product-description' ),
					'loading'        => __( 'در حال تولید دو مرحله‌ای (ممکن است ۱–۳ دقیقه طول بکشد)...', 'ai-product-description' ),
					'loadingDesc'    => __( 'مرحله ۱: سوال‌وجواب تخصصی… سپس ساخت مقاله…', 'ai-product-description' ),
					'saving'         => __( 'در حال ذخیره...', 'ai-product-description' ),
					'error'          => __( 'خطا در ارتباط با هوش مصنوعی.', 'ai-product-description' ),
					'saveError'      => __( 'ذخیره انجام نشد.', 'ai-product-description' ),
					'emptyTitle'     => __( 'عنوان SEO ندارد', 'ai-product-description' ),
					'emptyMeta'      => __( 'توضیح متا ندارد', 'ai-product-description' ),
					'emptyFocuskw'   => __( 'کلمه کلیدی کانونی ندارد', 'ai-product-description' ),
					'emptyDesc'      => __( 'توضیحات ندارد', 'ai-product-description' ),
					'emptyValue'     => __( 'خالی', 'ai-product-description' ),
					'needPreview'    => __( 'ابتدا پیش‌نمایش را بسازید.', 'ai-product-description' ),
					'confirmSeo'     => __( 'سئوی فعلی دسته با پیشنهاد AI جایگزین شود؟', 'ai-product-description' ),
					'confirmDesc'    => __( 'توضیحات فعلی دسته با پیشنهاد AI جایگزین شود؟', 'ai-product-description' ),
					'confirmSpecs'   => __( 'مشخصات فنی فعلی دسته با پیشنهاد AI جایگزین شود؟', 'ai-product-description' ),
					'notChemical'    => __( 'این دسته ماده شیمیایی تکی تشخیص داده نشد؛ مقادیر پیشنهادی N/A هستند.', 'ai-product-description' ),
				),
			)
		);
	}

	/**
	 * Panel under category edit form.
	 *
	 * @param WP_Term $term Current term.
	 */
	public static function render_edit_panel( $term ): void {
		if ( ! current_user_can( 'manage_options' ) || ! $term instanceof WP_Term ) {
			return;
		}

		$seo     = self::get_yoast_seo( (int) $term->term_id );
		$desc    = self::prepare_editor_content( (string) $term->description );
		$title   = trim( (string) $seo['title'] );
		$meta    = trim( (string) $seo['metadesc'] );
		$focuskw = trim( (string) $seo['focuskw'] );
		$specs   = self::get_acf_specs( (int) $term->term_id );

		// Pass prepared HTML to JS so TinyMCE can load it correctly in Visual mode.
		wp_add_inline_script(
			'ai-product-desc-category',
			'window.aiProductDescCategory = window.aiProductDescCategory || {};'
			. 'window.aiProductDescCategory.currentDescHtml = '
			. wp_json_encode( $desc )
			. ';'
			. 'window.aiProductDescCategory.currentSpecs = '
			. wp_json_encode( $specs )
			. ';',
			'before'
		);
		?>
		<div class="ai-cat-tools" id="ai-cat-tools" data-term-id="<?php echo esc_attr( (string) $term->term_id ); ?>">
			<hr>
			<h2><?php esc_html_e( 'ابزار هوش مصنوعی دسته', 'ai-product-description' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'مقدار فعلی را ببینید، با AI پیشنهاد بگیرید و پس از مقایسه ثبت کنید. توضیح دسته در دو مرحله ساخته می‌شود: ابتدا ۵۰ سوال‌وجواب تخصصی کوتاه، سپس مقاله HTML ساختاریافته (معرفی، کاربردها، گرید، ایمنی، FAQ و دعوت به خرید).', 'ai-product-description' ); ?>
			</p>

			<div class="ai-cat-tools__card" id="ai-cat-seo-card">
				<h3><?php esc_html_e( 'اصلاح سئو دسته', 'ai-product-description' ); ?></h3>

				<div class="ai-cat-tools__compare">
					<div class="ai-cat-tools__col">
						<strong><?php esc_html_e( 'مقدار فعلی', 'ai-product-description' ); ?></strong>
						<p>
							<span class="ai-cat-tools__label"><?php esc_html_e( 'کلمه کلیدی کانونی:', 'ai-product-description' ); ?></span>
							<span id="ai-cat-current-seo-focuskw" class="<?php echo '' === $focuskw ? 'is-empty' : ''; ?>">
								<?php echo '' === $focuskw ? esc_html__( 'کلمه کلیدی کانونی ندارد', 'ai-product-description' ) : esc_html( $focuskw ); ?>
							</span>
						</p>
						<p>
							<span class="ai-cat-tools__label"><?php esc_html_e( 'عنوان SEO:', 'ai-product-description' ); ?></span>
							<span id="ai-cat-current-seo-title" class="<?php echo '' === $title ? 'is-empty' : ''; ?>">
								<?php echo '' === $title ? esc_html__( 'عنوان SEO ندارد', 'ai-product-description' ) : esc_html( $title ); ?>
							</span>
						</p>
						<p>
							<span class="ai-cat-tools__label"><?php esc_html_e( 'توضیح متا:', 'ai-product-description' ); ?></span>
							<span id="ai-cat-current-seo-meta" class="<?php echo '' === $meta ? 'is-empty' : ''; ?>">
								<?php echo '' === $meta ? esc_html__( 'توضیح متا ندارد', 'ai-product-description' ) : esc_html( $meta ); ?>
							</span>
						</p>
					</div>
					<div class="ai-cat-tools__col">
						<strong><?php esc_html_e( 'پیش‌نمایش AI', 'ai-product-description' ); ?></strong>
						<p>
							<span class="ai-cat-tools__label"><?php esc_html_e( 'کلمه کلیدی کانونی:', 'ai-product-description' ); ?></span>
							<span id="ai-cat-preview-seo-focuskw" class="is-empty"><?php esc_html_e( 'هنوز پیشنهادی ساخته نشده', 'ai-product-description' ); ?></span>
						</p>
						<p>
							<span class="ai-cat-tools__label"><?php esc_html_e( 'عنوان SEO:', 'ai-product-description' ); ?></span>
							<span id="ai-cat-preview-seo-title" class="is-empty"><?php esc_html_e( 'هنوز پیشنهادی ساخته نشده', 'ai-product-description' ); ?></span>
						</p>
						<p>
							<span class="ai-cat-tools__label"><?php esc_html_e( 'توضیح متا:', 'ai-product-description' ); ?></span>
							<span id="ai-cat-preview-seo-meta" class="is-empty"><?php esc_html_e( 'هنوز پیشنهادی ساخته نشده', 'ai-product-description' ); ?></span>
						</p>
					</div>
				</div>

				<p class="ai-cat-tools__actions">
					<button type="button" class="button button-secondary" id="ai-cat-generate-seo">
						<?php esc_html_e( 'اصلاح سئو دسته', 'ai-product-description' ); ?>
					</button>
					<button type="button" class="button button-primary" id="ai-cat-save-seo" disabled>
						<?php esc_html_e( 'ثبت سئو در یوست', 'ai-product-description' ); ?>
					</button>
					<span id="ai-cat-seo-status" class="ai-cat-tools__status" hidden></span>
				</p>
			</div>

			<div class="ai-cat-tools__card" id="ai-cat-desc-card">
				<h3><?php esc_html_e( 'اصلاح توضیحات دسته', 'ai-product-description' ); ?></h3>

				<div class="ai-cat-tools__compare">
					<div class="ai-cat-tools__col ai-cat-tools__col--editor">
						<strong><?php esc_html_e( 'مقدار فعلی', 'ai-product-description' ); ?></strong>
						<?php
						self::render_html_editor(
							'ai_cat_current_desc',
							$desc,
							__( 'توضیحات فعلی دسته', 'ai-product-description' )
						);
						?>
					</div>
					<div class="ai-cat-tools__col ai-cat-tools__col--editor">
						<strong><?php esc_html_e( 'پیش‌نمایش AI', 'ai-product-description' ); ?></strong>
						<?php
						self::render_html_editor(
							'ai_cat_preview_desc',
							'',
							__( 'پیشنهاد هوش مصنوعی', 'ai-product-description' )
						);
						?>
					</div>
				</div>

				<div class="ai-cat-tools__col ai-cat-tools__col--editor ai-cat-tools__stage1">
					<strong><?php esc_html_e( 'پیش‌نمایش مرحله اول AI', 'ai-product-description' ); ?></strong>
					<p class="description">
						<?php esc_html_e( 'خروجی خام سوال و جواب تخصصی (مرحله اول). فقط برای بررسی است و با «ثبت توضیحات دسته» ذخیره نمی‌شود.', 'ai-product-description' ); ?>
					</p>
					<?php
					self::render_html_editor(
						'ai_cat_stage1_desc',
						'',
						__( 'پیش‌نمایش مرحله اول AI', 'ai-product-description' )
					);
					?>
				</div>

				<p class="ai-cat-tools__actions">
					<button type="button" class="button button-secondary" id="ai-cat-generate-desc">
						<?php esc_html_e( 'اصلاح توضیحات دسته', 'ai-product-description' ); ?>
					</button>
					<button type="button" class="button button-primary" id="ai-cat-save-desc" disabled>
						<?php esc_html_e( 'ثبت توضیحات دسته', 'ai-product-description' ); ?>
					</button>
					<span id="ai-cat-desc-status" class="ai-cat-tools__status" hidden></span>
				</p>
			</div>

			<div class="ai-cat-tools__card" id="ai-cat-specs-card">
				<h3><?php esc_html_e( 'مشخصات فنی دسته (ACF)', 'ai-product-description' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'مقادیر فعلی فیلدهای ACF را ببینید، با AI پیشنهاد بگیرید و پس از تأیید جایگزین کنید. فایل MSDS با AI پر نمی‌شود.', 'ai-product-description' ); ?>
				</p>

				<div class="ai-cat-tools__compare ai-cat-tools__compare--specs">
					<div class="ai-cat-tools__col">
						<strong><?php esc_html_e( 'مقدار فعلی', 'ai-product-description' ); ?></strong>
						<table class="ai-cat-specs-table widefat striped">
							<tbody id="ai-cat-current-specs-body">
								<?php foreach ( self::get_acf_spec_fields() as $field_name => $field_label ) : ?>
									<?php
									$value = isset( $specs[ $field_name ] ) ? trim( (string) $specs[ $field_name ] ) : '';
									?>
									<tr data-field="<?php echo esc_attr( $field_name ); ?>">
										<th scope="row"><?php echo esc_html( $field_label ); ?></th>
										<td class="<?php echo '' === $value ? 'is-empty' : ''; ?>">
											<?php echo '' === $value ? esc_html__( 'خالی', 'ai-product-description' ) : esc_html( $value ); ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<div class="ai-cat-tools__col">
						<strong><?php esc_html_e( 'پیش‌نمایش AI', 'ai-product-description' ); ?></strong>
						<p id="ai-cat-specs-hint" class="description" hidden></p>
						<table class="ai-cat-specs-table widefat striped">
							<tbody id="ai-cat-preview-specs-body">
								<?php foreach ( self::get_acf_spec_fields() as $field_name => $field_label ) : ?>
									<tr data-field="<?php echo esc_attr( $field_name ); ?>">
										<th scope="row"><?php echo esc_html( $field_label ); ?></th>
										<td class="is-empty"><?php esc_html_e( 'هنوز پیشنهادی ساخته نشده', 'ai-product-description' ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>

				<p class="ai-cat-tools__actions">
					<button type="button" class="button button-secondary" id="ai-cat-generate-specs">
						<?php esc_html_e( 'پیشنهاد مشخصات با AI', 'ai-product-description' ); ?>
					</button>
					<button type="button" class="button button-primary" id="ai-cat-save-specs" disabled>
						<?php esc_html_e( 'ثبت مشخصات در ACF', 'ai-product-description' ); ?>
					</button>
					<span id="ai-cat-specs-status" class="ai-cat-tools__status" hidden></span>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * ACF text/textarea fields for product category technical specs.
	 *
	 * @return array<string, string> Field name => label.
	 */
	public static function get_acf_spec_fields(): array {
		return array(
			'شکل_ظاهری_دسته'     => __( 'شکل ظاهری', 'ai-product-description' ),
			'مترادف'               => __( 'مترادف', 'ai-product-description' ),
			'cas_no-cat'          => __( 'Cas No.', 'ai-product-description' ),
			'en-cat'              => __( 'نام انگلیسی دسته', 'ai-product-description' ),
			'فرمول_شیمیایی_دسته' => __( 'فرمول شیمیایی', 'ai-product-description' ),
			'وزن_مولوکول'         => __( 'وزن مولکولی', 'ai-product-description' ),
			'نقطه_ذوب'            => __( 'نقطه ذوب', 'ai-product-description' ),
			'نقطه_جوش'            => __( 'نقطه جوش', 'ai-product-description' ),
			'نقطه_اشتعال'         => __( 'نقطه اشتعال', 'ai-product-description' ),
			'چگالی'               => __( 'چگالی', 'ai-product-description' ),
			'ویسکوزیته'           => __( 'ویسکوزیته', 'ai-product-description' ),
			'فشار_بخار'           => __( 'فشار بخار', 'ai-product-description' ),
			'حلالیت در آب'        => __( 'حلالیت در آب', 'ai-product-description' ),
			'حلالیت_دسته'         => __( 'حلالیت', 'ai-product-description' ),
			'توضیحات'             => __( 'توضیحات (ACF)', 'ai-product-description' ),
		);
	}

	/**
	 * Read ACF technical specs for a product category term.
	 *
	 * @param int $term_id Term ID.
	 * @return array<string, string>
	 */
	public static function get_acf_specs( int $term_id ): array {
		$acf_id = self::TAXONOMY . '_' . $term_id;
		$out    = array();

		foreach ( array_keys( self::get_acf_spec_fields() ) as $name ) {
			$value = '';

			if ( function_exists( 'get_field' ) ) {
				$raw = get_field( $name, $acf_id );
				if ( is_scalar( $raw ) ) {
					$value = trim( (string) $raw );
				}
			}

			if ( '' === $value ) {
				$meta = get_term_meta( $term_id, $name, true );
				if ( is_scalar( $meta ) ) {
					$value = trim( (string) $meta );
				}
			}

			$out[ $name ] = $value;
		}

		return $out;
	}

	/**
	 * Write ACF technical specs for a product category term.
	 *
	 * @param int                  $term_id Term ID.
	 * @param array<string, string> $specs   Field name => value.
	 * @return bool
	 */
	public static function set_acf_specs( int $term_id, array $specs ): bool {
		$acf_id  = self::TAXONOMY . '_' . $term_id;
		$allowed = array_keys( self::get_acf_spec_fields() );

		foreach ( $allowed as $name ) {
			if ( ! array_key_exists( $name, $specs ) ) {
				continue;
			}

			$value = sanitize_textarea_field( (string) $specs[ $name ] );

			if ( function_exists( 'update_field' ) ) {
				update_field( $name, $value, $acf_id );
			}

			// update_term_meta returns false when value is unchanged; that is OK.
			update_term_meta( $term_id, $name, $value );
		}

		return true;
	}

	/**
	 * Normalize term description for TinyMCE Visual mode.
	 *
	 * @param string $content Raw term description.
	 * @return string
	 */
	private static function prepare_editor_content( string $content ): string {
		$content = trim( $content );
		if ( '' === $content ) {
			return '';
		}

		// If HTML was stored as entities (&lt;div&gt;...), decode for the visual editor.
		if ( false !== strpos( $content, '&lt;' ) || false !== strpos( $content, '&amp;lt;' ) ) {
			$content = html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			// Decode twice if double-escaped.
			if ( false !== strpos( $content, '&lt;' ) ) {
				$content = html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			}
		}

		// Plain text without HTML tags → paragraphs.
		if ( false === strpos( $content, '<' ) ) {
			$content = wpautop( $content );
		}

		return $content;
	}

	/**
	 * Render a full WordPress visual HTML editor.
	 *
	 * @param string $editor_id Unique editor id.
	 * @param string $content   Initial HTML/text.
	 * @param string $title     Accessibility title.
	 */
	private static function render_html_editor( string $editor_id, string $content, string $title ): void {
		$content = self::prepare_editor_content( $content );

		wp_editor(
			$content,
			$editor_id,
			array(
				'textarea_name'    => $editor_id,
				'textarea_rows'    => 14,
				'media_buttons'    => true,
				'drag_drop_upload' => true,
				'teeny'            => false,
				'default_editor'   => 'tinymce',
				'tinymce'          => true,
				'quicktags'        => true,
				'editor_class'     => 'ai-cat-html-editor',
				'editor_height'    => 280,
			)
		);
		echo '<p class="screen-reader-text">' . esc_html( $title ) . '</p>';
	}

	/**
	 * Read Yoast SEO title/metadesc/focus keyphrase for a term.
	 *
	 * @param int $term_id Term ID.
	 * @return array{title: string, metadesc: string, focuskw: string}
	 */
	public static function get_yoast_seo( int $term_id ): array {
		$title   = '';
		$desc    = '';
		$focuskw = '';

		if ( class_exists( 'WPSEO_Taxonomy_Meta' ) ) {
			$title   = (string) WPSEO_Taxonomy_Meta::get_term_meta( $term_id, self::TAXONOMY, 'title' );
			$desc    = (string) WPSEO_Taxonomy_Meta::get_term_meta( $term_id, self::TAXONOMY, 'desc' );
			$focuskw = (string) WPSEO_Taxonomy_Meta::get_term_meta( $term_id, self::TAXONOMY, 'focuskw' );
		} else {
			$all = get_option( 'wpseo_taxonomy_meta', array() );
			if ( isset( $all[ self::TAXONOMY ][ $term_id ] ) && is_array( $all[ self::TAXONOMY ][ $term_id ] ) ) {
				$row     = $all[ self::TAXONOMY ][ $term_id ];
				$title   = isset( $row['wpseo_title'] ) ? (string) $row['wpseo_title'] : '';
				$desc    = isset( $row['wpseo_desc'] ) ? (string) $row['wpseo_desc'] : '';
				$focuskw = isset( $row['wpseo_focuskw'] ) ? (string) $row['wpseo_focuskw'] : '';
			}
		}

		return array(
			'title'    => $title,
			'metadesc' => $desc,
			'focuskw'  => $focuskw,
		);
	}

	/**
	 * Write Yoast SEO title/metadesc/focus keyphrase for a term.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $title    SEO title.
	 * @param string $metadesc Meta description.
	 * @param string $focuskw  Focus keyphrase.
	 * @return bool
	 */
	public static function set_yoast_seo( int $term_id, string $title, string $metadesc, string $focuskw = '' ): bool {
		if ( class_exists( 'WPSEO_Taxonomy_Meta' ) ) {
			if ( method_exists( 'WPSEO_Taxonomy_Meta', 'set_value' ) ) {
				WPSEO_Taxonomy_Meta::set_value( $term_id, self::TAXONOMY, 'title', $title );
				WPSEO_Taxonomy_Meta::set_value( $term_id, self::TAXONOMY, 'desc', $metadesc );
				WPSEO_Taxonomy_Meta::set_value( $term_id, self::TAXONOMY, 'focuskw', $focuskw );
				return true;
			}

			if ( method_exists( 'WPSEO_Taxonomy_Meta', 'set_values' ) ) {
				WPSEO_Taxonomy_Meta::set_values(
					$term_id,
					self::TAXONOMY,
					array(
						'wpseo_title'   => $title,
						'wpseo_desc'    => $metadesc,
						'wpseo_focuskw' => $focuskw,
					)
				);
				return true;
			}
		}

		$all = get_option( 'wpseo_taxonomy_meta', array() );
		if ( ! is_array( $all ) ) {
			$all = array();
		}
		if ( ! isset( $all[ self::TAXONOMY ] ) || ! is_array( $all[ self::TAXONOMY ] ) ) {
			$all[ self::TAXONOMY ] = array();
		}
		if ( ! isset( $all[ self::TAXONOMY ][ $term_id ] ) || ! is_array( $all[ self::TAXONOMY ][ $term_id ] ) ) {
			$all[ self::TAXONOMY ][ $term_id ] = array();
		}

		$all[ self::TAXONOMY ][ $term_id ]['wpseo_title']   = $title;
		$all[ self::TAXONOMY ][ $term_id ]['wpseo_desc']    = $metadesc;
		$all[ self::TAXONOMY ][ $term_id ]['wpseo_focuskw'] = $focuskw;

		return (bool) update_option( 'wpseo_taxonomy_meta', $all );
	}

	/**
	 * Collect category context for AI prompts.
	 *
	 * @param WP_Term $term Term.
	 * @return array{name: string, slug: string, description: string, parent_name: string, seo_title: string, seo_metadesc: string, seo_focuskw: string}
	 */
	public static function get_category_context( WP_Term $term ): array {
		$parent_name = '';
		if ( $term->parent ) {
			$parent = get_term( (int) $term->parent, self::TAXONOMY );
			if ( $parent instanceof WP_Term && ! is_wp_error( $parent ) ) {
				$parent_name = $parent->name;
			}
		}

		$seo = self::get_yoast_seo( (int) $term->term_id );

		return array(
			'name'         => $term->name,
			'slug'         => $term->slug,
			'description'  => (string) $term->description,
			'parent_name'  => $parent_name,
			'seo_title'    => $seo['title'],
			'seo_metadesc' => $seo['metadesc'],
			'seo_focuskw'  => $seo['focuskw'],
		);
	}
}
