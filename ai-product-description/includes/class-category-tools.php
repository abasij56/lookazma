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
				'actions'      => array(
					'generateSeo'  => AI_Product_Desc_Ajax::CAT_SEO_GENERATE,
					'saveSeo'      => AI_Product_Desc_Ajax::CAT_SEO_SAVE,
					'generateDesc' => AI_Product_Desc_Ajax::CAT_DESC_GENERATE,
					'saveDesc'     => AI_Product_Desc_Ajax::CAT_DESC_SAVE,
				),
				'i18n'         => array(
					'notConfigured' => __( 'تنظیمات AI کامل نیست. از منوی «توضیحات محصول AI ← تنظیمات» پر کنید.', 'ai-product-description' ),
					'loading'       => __( 'در حال تولید...', 'ai-product-description' ),
					'saving'        => __( 'در حال ذخیره...', 'ai-product-description' ),
					'error'         => __( 'خطا در ارتباط با هوش مصنوعی.', 'ai-product-description' ),
					'saveError'     => __( 'ذخیره انجام نشد.', 'ai-product-description' ),
					'emptyTitle'    => __( 'عنوان SEO ندارد', 'ai-product-description' ),
					'emptyMeta'     => __( 'توضیح متا ندارد', 'ai-product-description' ),
					'emptyDesc'     => __( 'توضیحات ندارد', 'ai-product-description' ),
					'needPreview'   => __( 'ابتدا پیش‌نمایش را بسازید.', 'ai-product-description' ),
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

		$seo   = self::get_yoast_seo( (int) $term->term_id );
		$desc  = (string) $term->description;
		$title = trim( (string) $seo['title'] );
		$meta  = trim( (string) $seo['metadesc'] );
		?>
		<div class="ai-cat-tools" id="ai-cat-tools" data-term-id="<?php echo esc_attr( (string) $term->term_id ); ?>">
			<hr>
			<h2><?php esc_html_e( 'ابزار هوش مصنوعی دسته', 'ai-product-description' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'مقدار فعلی را ببینید، با AI پیشنهاد بگیرید و پس از مقایسه ثبت کنید. اگر مقدار فعلی خالی باشد، از صفر تولید می‌شود.', 'ai-product-description' ); ?>
			</p>

			<div class="ai-cat-tools__card" id="ai-cat-seo-card">
				<h3><?php esc_html_e( 'اصلاح سئو دسته', 'ai-product-description' ); ?></h3>

				<div class="ai-cat-tools__compare">
					<div class="ai-cat-tools__col">
						<strong><?php esc_html_e( 'مقدار فعلی', 'ai-product-description' ); ?></strong>
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
		</div>
		<?php
	}

	/**
	 * Render a full WordPress visual HTML editor.
	 *
	 * @param string $editor_id Unique editor id.
	 * @param string $content   Initial HTML/text.
	 * @param string $title     Accessibility title.
	 */
	private static function render_html_editor( string $editor_id, string $content, string $title ): void {
		$content = trim( $content );
		if ( '' !== $content && false === strpos( $content, '<' ) ) {
			$content = wpautop( $content );
		}

		wp_editor(
			$content,
			$editor_id,
			array(
				'textarea_name' => $editor_id,
				'textarea_rows' => 14,
				'media_buttons' => true,
				'drag_drop_upload' => true,
				'teeny'         => false,
				'tinymce'       => array(
					'wpautop'       => true,
					'toolbar1'      => 'formatselect,bold,italic,underline,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,wp_more,spellchecker,fullscreen,wp_adv',
					'toolbar2'      => 'strikethrough,hr,forecolor,pastetext,removeformat,charmap,outdent,indent,undo,redo,wp_help',
					'content_css'   => false,
				),
				'quicktags'     => array(
					'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code,close',
				),
				'editor_class'  => 'ai-cat-html-editor',
				'editor_height' => 280,
			)
		);
		echo '<p class="screen-reader-text">' . esc_html( $title ) . '</p>';
	}

	/**
	 * Read Yoast SEO title/metadesc for a term.
	 *
	 * @param int $term_id Term ID.
	 * @return array{title: string, metadesc: string}
	 */
	public static function get_yoast_seo( int $term_id ): array {
		$title = '';
		$desc  = '';

		if ( class_exists( 'WPSEO_Taxonomy_Meta' ) ) {
			$title = (string) WPSEO_Taxonomy_Meta::get_term_meta( $term_id, self::TAXONOMY, 'title' );
			$desc  = (string) WPSEO_Taxonomy_Meta::get_term_meta( $term_id, self::TAXONOMY, 'desc' );
		} else {
			$all = get_option( 'wpseo_taxonomy_meta', array() );
			if ( isset( $all[ self::TAXONOMY ][ $term_id ] ) && is_array( $all[ self::TAXONOMY ][ $term_id ] ) ) {
				$row   = $all[ self::TAXONOMY ][ $term_id ];
				$title = isset( $row['wpseo_title'] ) ? (string) $row['wpseo_title'] : '';
				$desc  = isset( $row['wpseo_desc'] ) ? (string) $row['wpseo_desc'] : '';
			}
		}

		return array(
			'title'    => $title,
			'metadesc' => $desc,
		);
	}

	/**
	 * Write Yoast SEO title/metadesc for a term.
	 *
	 * @param int    $term_id Term ID.
	 * @param string $title   SEO title.
	 * @param string $metadesc Meta description.
	 * @return bool
	 */
	public static function set_yoast_seo( int $term_id, string $title, string $metadesc ): bool {
		if ( class_exists( 'WPSEO_Taxonomy_Meta' ) ) {
			if ( method_exists( 'WPSEO_Taxonomy_Meta', 'set_value' ) ) {
				WPSEO_Taxonomy_Meta::set_value( $term_id, self::TAXONOMY, 'title', $title );
				WPSEO_Taxonomy_Meta::set_value( $term_id, self::TAXONOMY, 'desc', $metadesc );
				return true;
			}

			if ( method_exists( 'WPSEO_Taxonomy_Meta', 'set_values' ) ) {
				WPSEO_Taxonomy_Meta::set_values(
					$term_id,
					self::TAXONOMY,
					array(
						'wpseo_title' => $title,
						'wpseo_desc'  => $metadesc,
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

		$all[ self::TAXONOMY ][ $term_id ]['wpseo_title'] = $title;
		$all[ self::TAXONOMY ][ $term_id ]['wpseo_desc']  = $metadesc;

		return (bool) update_option( 'wpseo_taxonomy_meta', $all );
	}

	/**
	 * Collect category context for AI prompts.
	 *
	 * @param WP_Term $term Term.
	 * @return array{name: string, slug: string, description: string, parent_name: string, seo_title: string, seo_metadesc: string}
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
		);
	}
}
