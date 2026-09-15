<?php
/**
 * Enhanced brand single template — hero, stats, lk_brand products, content sections.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enhanced brand CPT single (opt-in per brand post).
 */
final class Hello_Elementor_Child_Brand_Enhanced_Single {

	public const META_KEY = '_lk_use_enhanced_brand';

	public const SCOPE_TAXONOMY = 'lk_brand';

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_post_meta' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_box' ) );
		add_action( 'save_post_' . Hello_Elementor_Child_Brand_Cpt::POST_TYPE, array( __CLASS__, 'save_meta_box' ), 10, 2 );
		add_action( 'acf/init', array( __CLASS__, 'register_acf_fields' ) );
	}

	/**
	 * Whether enhanced template is enabled for a brand.
	 *
	 * @param int|WP_Post|null $brand Brand post or ID.
	 */
	public static function is_enabled_for( $brand = null ): bool {
		$post = self::resolve_brand_post( $brand );
		if ( ! $post instanceof WP_Post ) {
			return false;
		}
		return 'yes' === get_post_meta( (int) $post->ID, self::META_KEY, true );
	}

	/**
	 * Active enhanced brand on current request.
	 */
	public static function is_active(): bool {
		if ( ! class_exists( 'Hello_Elementor_Child_Custom_Brand_Single' )
			|| ! Hello_Elementor_Child_Custom_Brand_Single::is_enabled()
		) {
			return false;
		}
		$brand = Hello_Elementor_Child_Custom_Brand_Single::get_brand_post();
		return self::is_enabled_for( $brand );
	}

	/**
	 * Brand post ID for product scoping.
	 */
	public static function get_scope_brand_id(): int {
		if ( ! self::is_active() ) {
			return 0;
		}
		$brand = Hello_Elementor_Child_Custom_Brand_Single::get_brand_post();
		return $brand instanceof WP_Post ? (int) $brand->ID : 0;
	}

	/**
	 * @param int|WP_Post|null $brand Brand.
	 */
	private static function resolve_brand_post( $brand = null ): ?WP_Post {
		if ( $brand instanceof WP_Post ) {
			return Hello_Elementor_Child_Brand_Cpt::POST_TYPE === $brand->post_type ? $brand : null;
		}
		if ( is_numeric( $brand ) && (int) $brand > 0 ) {
			$post = get_post( (int) $brand );
			return ( $post instanceof WP_Post && Hello_Elementor_Child_Brand_Cpt::POST_TYPE === $post->post_type ) ? $post : null;
		}
		return Hello_Elementor_Child_Custom_Brand_Single::get_brand_post();
	}

	/**
	 * Register toggle meta.
	 */
	public static function register_post_meta(): void {
		register_post_meta(
			Hello_Elementor_Child_Brand_Cpt::POST_TYPE,
			self::META_KEY,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'auth_callback'     => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'sanitize_callback' => static function ( $value ): string {
					return 'yes' === $value ? 'yes' : '';
				},
			)
		);
	}

	/**
	 * Meta box on brand edit.
	 */
	public static function register_meta_box(): void {
		add_meta_box(
			'lk_enhanced_brand_template',
			__( 'تمپلیت برند', 'hello-elementor-child' ),
			array( __CLASS__, 'render_meta_box' ),
			Hello_Elementor_Child_Brand_Cpt::POST_TYPE,
			'side',
			'high'
		);
	}

	/**
	 * @param WP_Post $post Brand post.
	 */
	public static function render_meta_box( WP_Post $post ): void {
		$enabled = self::is_enabled_for( $post );
		wp_nonce_field( 'lk_enhanced_brand_save', 'lk_enhanced_brand_nonce' );
		?>
		<label for="<?php echo esc_attr( self::META_KEY ); ?>" style="display:flex;gap:0.5rem;align-items:flex-start;cursor:pointer;">
			<input type="checkbox" name="<?php echo esc_attr( self::META_KEY ); ?>" id="<?php echo esc_attr( self::META_KEY ); ?>" value="yes" <?php checked( $enabled ); ?> style="margin-top:0.2rem;">
			<span>
				<strong><?php esc_html_e( 'تمپلیت پیشرفته برند', 'hello-elementor-child' ); ?></strong>
				<br>
				<span class="description"><?php esc_html_e( 'چیدمان جدید با هیرو، آمار، محصولات از فیلد برند و بخش‌های محتوایی.', 'hello-elementor-child' ); ?></span>
			</span>
		</label>
		<?php
	}

	/**
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function save_meta_box( int $post_id, WP_Post $post ): void {
		if ( Hello_Elementor_Child_Brand_Cpt::POST_TYPE !== $post->post_type ) {
			return;
		}
		if ( ! isset( $_POST['lk_enhanced_brand_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['lk_enhanced_brand_nonce'] ) ), 'lk_enhanced_brand_save' )
		) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$value = isset( $_POST[ self::META_KEY ] ) && 'yes' === $_POST[ self::META_KEY ] ? 'yes' : '';
		update_post_meta( $post_id, self::META_KEY, $value );
	}

	/**
	 * ACF field group for enhanced brand content.
	 */
	public static function register_acf_fields(): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group(
			array(
				'key'                   => 'group_lk_brand_enhanced',
				'title'                 => __( 'محتوای تمپلیت پیشرفته برند', 'hello-elementor-child' ),
				'fields'                => array(
					array(
						'key'   => 'field_lk_brand_hero_image',
						'label' => __( 'تصویر هیرو بنر', 'hello-elementor-child' ),
						'name'  => 'brand_hero_image',
						'type'  => 'image',
						'return_format' => 'array',
						'preview_size'  => 'medium',
					),
					array(
						'key'   => 'field_lk_brand_catalog',
						'label' => __( 'کاتالوگ', 'hello-elementor-child' ),
						'name'  => 'brand_catalog',
						'type'  => 'file',
						'return_format' => 'array',
					),
					array(
						'key'   => 'field_lk_brand_website',
						'label' => __( 'سایت رسمی برند', 'hello-elementor-child' ),
						'name'  => 'brand_website',
						'type'  => 'url',
					),
					array(
						'key'   => 'field_lk_brand_year',
						'label' => __( 'سال تاسیس', 'hello-elementor-child' ),
						'name'  => 'brand_established_year',
						'type'  => 'text',
					),
					array(
						'key'   => 'field_lk_brand_country',
						'label' => __( 'کشور سازنده', 'hello-elementor-child' ),
						'name'  => 'brand_country',
						'type'  => 'text',
					),
					array(
						'key'   => 'field_lk_brand_activity',
						'label' => __( 'حوزه فعالیت', 'hello-elementor-child' ),
						'name'  => 'brand_activity_field',
						'type'  => 'text',
					),
					array(
						'key'   => 'field_lk_brand_about_image',
						'label' => __( 'تصویر بخش درباره برند', 'hello-elementor-child' ),
						'name'  => 'brand_about_image',
						'type'  => 'image',
						'return_format' => 'array',
						'preview_size'  => 'medium',
					),
					array(
						'key'        => 'field_lk_brand_why_buy',
						'label'      => __( 'چرا از لوک آزما بخریم', 'hello-elementor-child' ),
						'name'       => 'brand_why_buy',
						'type'       => 'repeater',
						'layout'     => 'block',
						'sub_fields' => array(
							array(
								'key'   => 'field_lk_brand_why_title',
								'label' => __( 'عنوان', 'hello-elementor-child' ),
								'name'  => 'title',
								'type'  => 'text',
							),
							array(
								'key'   => 'field_lk_brand_why_text',
								'label' => __( 'متن', 'hello-elementor-child' ),
								'name'  => 'text',
								'type'  => 'textarea',
								'rows'  => 3,
							),
						),
					),
					array(
						'key'        => 'field_lk_brand_faq',
						'label'      => __( 'سوالات متداول', 'hello-elementor-child' ),
						'name'       => 'brand_faq',
						'type'       => 'repeater',
						'layout'     => 'block',
						'sub_fields' => array(
							array(
								'key'   => 'field_lk_brand_faq_q',
								'label' => __( 'سوال', 'hello-elementor-child' ),
								'name'  => 'question',
								'type'  => 'text',
							),
							array(
								'key'   => 'field_lk_brand_faq_a',
								'label' => __( 'پاسخ', 'hello-elementor-child' ),
								'name'  => 'answer',
								'type'  => 'textarea',
								'rows'  => 4,
							),
						),
					),
					array(
						'key'           => 'field_lk_brand_articles',
						'label'         => __( 'مقالات مرتبط', 'hello-elementor-child' ),
						'name'          => 'brand_related_articles',
						'type'          => 'relationship',
						'post_type'     => array( 'post' ),
						'filters'       => array( 'search' ),
						'return_format' => 'object',
						'max'           => 12,
					),
					array(
						'key'           => 'field_lk_brand_similar',
						'label'         => __( 'برندهای مشابه', 'hello-elementor-child' ),
						'name'          => 'brand_similar_brands',
						'type'          => 'relationship',
						'post_type'     => array( Hello_Elementor_Child_Brand_Cpt::POST_TYPE ),
						'filters'       => array( 'search' ),
						'return_format' => 'object',
						'max'           => 12,
					),
				),
				'location'              => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => Hello_Elementor_Child_Brand_Cpt::POST_TYPE,
						),
					),
				),
				'menu_order'            => 5,
				'position'              => 'normal',
				'style'                 => 'default',
				'label_placement'       => 'top',
				'instruction_placement' => 'label',
				'active'                => true,
			)
		);
	}

	/**
	 * Scope products query to lk_brand meta.
	 *
	 * @param array<string, mixed> $args     Query args.
	 * @param int                  $brand_id Brand post ID.
	 * @return array<string, mixed>
	 */
	public static function apply_product_scope( array $args, int $brand_id ): array {
		if ( $brand_id <= 0 ) {
			$args['post__in'] = array( 0 );
			return $args;
		}

		$meta_query = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : array();
		$meta_query[] = array(
			'key'     => Hello_Elementor_Child_Product_Brand_Migration::FIELD_NAME,
			'value'   => $brand_id,
			'compare' => '=',
			'type'    => 'NUMERIC',
		);
		$args['meta_query'] = $meta_query;

		return $args;
	}

	/**
	 * Apply orderby from request.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<string, mixed>
	 */
	public static function apply_orderby( array $args ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( (string) wp_unslash( $_GET['orderby'] ) ) : 'menu_order';

		switch ( $orderby ) {
			case 'date':
				$args['orderby'] = 'date';
				$args['order']   = 'DESC';
				break;
			case 'title':
				$args['orderby'] = 'title';
				$args['order']   = 'ASC';
				break;
			case 'price':
				$args['meta_key'] = '_price';
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'ASC';
				break;
			case 'price-desc':
				$args['meta_key'] = '_price';
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'DESC';
				break;
			default:
				$args['orderby'] = 'menu_order title';
				$args['order']   = 'ASC';
				break;
		}

		return $args;
	}

	/**
	 * @param WP_Post $brand Brand post.
	 * @return array<string, mixed>
	 */
	public static function build_template_data( WP_Post $brand ): array {
		$brand_id = (int) $brand->ID;
		$acf      = static function ( string $key ) use ( $brand_id ) {
			if ( ! function_exists( 'get_field' ) ) {
				return null;
			}
			return get_field( $key, $brand_id );
		};

		$english_name = '';
		foreach ( array( 'en-brand', 'english_name', 'en_name', 'en-tag' ) as $en_key ) {
			$val = $acf( $en_key );
			if ( is_scalar( $val ) && '' !== trim( (string) $val ) ) {
				$english_name = trim( (string) $val );
				break;
			}
		}

		$hero_image = self::normalize_image( $acf( 'brand_hero_image' ) );

		$logo = null;
		$thumb_id = (int) get_post_thumbnail_id( $brand_id );
		if ( $thumb_id > 0 ) {
			$logo_url = wp_get_attachment_image_url( $thumb_id, 'medium' );
			if ( is_string( $logo_url ) && '' !== $logo_url ) {
				$logo = array(
					'url' => $logo_url,
					'alt' => $brand->post_title,
				);
			}
		}

		$about_image = self::normalize_image( $acf( 'brand_about_image' ) );
		if ( ! $about_image ) {
			$about_image = array(
				'url' => content_url( 'uploads/2023/08/man-in-lab.webp' ),
				'alt' => $brand->post_title,
			);
		}

		$catalog     = $acf( 'brand_catalog' );
		$catalog_url = '';
		if ( is_array( $catalog ) && ! empty( $catalog['url'] ) ) {
			$catalog_url = (string) $catalog['url'];
		} elseif ( is_numeric( $catalog ) ) {
			$catalog_url = (string) wp_get_attachment_url( (int) $catalog );
		}

		$website = trim( (string) ( $acf( 'brand_website' ) ?? '' ) );
		if ( '' !== $website && ! preg_match( '#^https?://#i', $website ) ) {
			$website = 'https://' . $website;
		}

		$excerpt = self::first_paragraph_from_content( (string) $brand->post_content );
		if ( '' === $excerpt ) {
			$excerpt = self::first_sentence_from_text( (string) $brand->post_excerpt );
		}

		$about_parsed = self::parse_about_teaser( (string) $brand->post_content );
		if ( '' === trim( (string) ( $about_parsed['teaser_html'] ?? '' ) ) && '' !== trim( (string) $brand->post_content ) ) {
			$about_parsed['teaser_html'] = wp_kses_post( apply_filters( 'the_content', (string) $brand->post_content ) );
			$about_parsed['has_more']    = false;
			$about_parsed['rest_html']   = '';
		}

		$paged    = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
		$per_page = Hello_Elementor_Child_Custom_Brand_Single::get_per_page();

		$args = array(
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'posts_per_page'      => $per_page,
			'paged'               => $paged,
			'ignore_sticky_posts' => true,
		);
		$args = self::apply_product_scope( $args, $brand_id );
		$args = self::apply_orderby( $args );
		if ( class_exists( 'Hello_Elementor_Child_Archive_Product_Filter' ) ) {
			$args = Hello_Elementor_Child_Archive_Product_Filter::apply_fragments_to_args( $args, null, true );
		}

		$query = new WP_Query( $args );

		$products_html = '';
		$pagination    = '';
		$filters_html  = '';
		if ( class_exists( 'Hello_Elementor_Child_Archive_Product_Filter' ) ) {
			$products_html = Hello_Elementor_Child_Archive_Product_Filter::render_products_grid_html( $query, 'shop' );
			$pagination    = Hello_Elementor_Child_Archive_Product_Filter::render_pagination_html(
				$query,
				$brand_id,
				$paged,
				self::SCOPE_TAXONOMY
			);
			$filters_html = Hello_Elementor_Child_Archive_Product_Filter::render_filters_html(
				array(
					'scope_brand_id'    => $brand_id,
					'panels_open'       => true,
					'max_visible_items' => 3,
				)
			);
		}

		$why_buy = self::normalize_why_buy( $acf( 'brand_why_buy' ) );
		$faqs    = self::normalize_faq( $acf( 'brand_faq' ) );
		$articles = self::normalize_articles( $acf( 'brand_related_articles' ) );
		$similar  = self::normalize_similar_brands( $acf( 'brand_similar_brands' ), $brand_id );

		$stats = array(
			array(
				'icon'  => 'products',
				'label' => __( 'تعداد محصولات', 'hello-elementor-child' ),
				'value' => sprintf(
					/* translators: %d: product count */
					__( '%d محصول', 'hello-elementor-child' ),
					(int) $query->found_posts
				),
			),
			array(
				'icon'  => 'calendar',
				'label' => __( 'سال تاسیس', 'hello-elementor-child' ),
				'value' => trim( (string) ( $acf( 'brand_established_year' ) ?? '' ) ),
			),
			array(
				'icon'  => 'globe',
				'label' => __( 'کشور سازنده', 'hello-elementor-child' ),
				'value' => trim( (string) ( $acf( 'brand_country' ) ?? '' ) ),
			),
			array(
				'icon'  => 'flask',
				'label' => __( 'حوزه فعالیت', 'hello-elementor-child' ),
				'value' => trim( (string) ( $acf( 'brand_activity_field' ) ?? '' ) ),
			),
		);
		$stats = array_values(
			array_filter(
				$stats,
				static function ( array $item ): bool {
					return '' !== trim( (string) ( $item['value'] ?? '' ) );
				}
			)
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_orderby = isset( $_GET['orderby'] ) ? sanitize_key( (string) wp_unslash( $_GET['orderby'] ) ) : 'menu_order';

		return array(
			'id'              => $brand_id,
			'name'            => $brand->post_title,
			'title'           => sprintf(
				/* translators: %s: brand name */
				__( 'محصولات برند %s', 'hello-elementor-child' ),
				$brand->post_title
			),
			'english_name'    => $english_name,
			'excerpt'         => $excerpt,
			'logo'            => $logo,
			'hero_image'      => $hero_image,
			'icons_url'       => HELLO_ELEMENTOR_CHILD_URI . 'assets/icons/brand-enhanced/',
			'catalog_url'     => $catalog_url,
			'website_url'     => $website,
			'stats'           => $stats,
			'count'           => (int) $query->found_posts,
			'breadcrumb_html' => function_exists( 'hello_elementor_child_get_breadcrumb_html' )
				? hello_elementor_child_get_breadcrumb_html()
				: '',
			'filters_html'    => $filters_html,
			'products_html'   => $products_html,
			'pagination_html' => $pagination,
			'why_buy'         => $why_buy,
			'why_buy_title'   => sprintf(
				/* translators: %s: brand name */
				__( 'چرا محصولات %s را از لوک آزما بخریم؟', 'hello-elementor-child' ),
				$brand->post_title
			),
			'about_title'     => __( 'درباره برند', 'hello-elementor-child' ),
			'about_teaser'    => (string) ( $about_parsed['teaser_html'] ?? '' ),
			'about_rest'      => (string) ( $about_parsed['rest_html'] ?? '' ),
			'about_has_more'  => ! empty( $about_parsed['has_more'] ),
			'about_image'     => $about_image,
			'faq_title'       => sprintf(
				/* translators: %s: brand name */
				__( 'سوالات متداول درباره %s', 'hello-elementor-child' ),
				$brand->post_title
			),
			'faqs'            => $faqs,
			'articles_title'  => __( 'مقالات مرتبط', 'hello-elementor-child' ),
			'articles'        => $articles,
			'similar_title'   => __( 'برندهای مشابه', 'hello-elementor-child' ),
			'similar_brands'  => $similar,
			'contact_url'     => self::get_contact_url(),
			'contact_label'   => __( 'تماس با ما', 'hello-elementor-child' ),
			'cta_title'       => __( 'محصول مورد نظر خود را پیدا نکردید؟', 'hello-elementor-child' ),
			'cta_text'        => __( 'کارشناسان ما آماده راهنمایی شما در انتخاب و تامین مواد شیمیایی هستند.', 'hello-elementor-child' ),
			'orderby'         => $current_orderby,
			'orderby_options' => array(
				array( 'value' => 'menu_order', 'label' => __( 'محبوب‌ترین', 'hello-elementor-child' ) ),
				array( 'value' => 'date', 'label' => __( 'جدیدترین', 'hello-elementor-child' ) ),
				array( 'value' => 'title', 'label' => __( 'نام محصول', 'hello-elementor-child' ) ),
				array( 'value' => 'price', 'label' => __( 'ارزان‌ترین', 'hello-elementor-child' ) ),
				array( 'value' => 'price-desc', 'label' => __( 'گران‌ترین', 'hello-elementor-child' ) ),
			),
		);
	}

	/**
	 * Contact page URL (light contact template).
	 */
	public static function get_contact_url(): string {
		if ( class_exists( 'Hello_Elementor_Child_Light_Contact_Template' ) ) {
			$pages = get_posts(
				array(
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'meta_key'       => '_wp_page_template',
					'meta_value'     => Hello_Elementor_Child_Light_Contact_Template::TEMPLATE_FILE,
				)
			);
			if ( ! empty( $pages[0] ) && $pages[0] instanceof WP_Post ) {
				$url = get_permalink( $pages[0] );
				if ( is_string( $url ) && '' !== $url ) {
					return $url;
				}
			}
		}
		$page = get_page_by_path( 'contact' );
		if ( $page instanceof WP_Post ) {
			$url = get_permalink( $page );
			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}
		return home_url( '/contact/' );
	}

	/**
	 * @param mixed $image ACF image.
	 * @return array{url:string,alt:string}|null
	 */
	private static function normalize_image( $image ): ?array {
		if ( is_array( $image ) && ! empty( $image['url'] ) ) {
			$alt = isset( $image['alt'] ) ? (string) $image['alt'] : '';
			return array(
				'url' => (string) $image['url'],
				'alt' => $alt,
			);
		}
		if ( is_numeric( $image ) && (int) $image > 0 ) {
			$url = wp_get_attachment_image_url( (int) $image, 'large' );
			if ( is_string( $url ) && '' !== $url ) {
				return array(
					'url' => $url,
					'alt' => '',
				);
			}
		}
		return null;
	}

	/**
	 * First sentence of brand body content (for hero excerpt).
	 *
	 * @param string $content Post content.
	 */
	private static function first_paragraph_from_content( string $content ): string {
		$content = trim( $content );
		if ( '' === $content ) {
			return '';
		}

		$html = apply_filters( 'the_content', $content );
		if ( preg_match( '/<p\b[^>]*>.*?<\/p>/is', $html, $match ) ) {
			return self::first_sentence_from_text( wp_strip_all_tags( $match[0] ) );
		}

		$blocks = preg_split( "/\n\s*\n/u", trim( wp_strip_all_tags( $content ) ) );
		if ( is_array( $blocks ) && ! empty( $blocks[0] ) ) {
			return self::first_sentence_from_text( (string) $blocks[0] );
		}

		return self::first_sentence_from_text( wp_strip_all_tags( $content ) );
	}

	/**
	 * Plain text up to and including the first period.
	 *
	 * @param string $text Source text.
	 */
	private static function first_sentence_from_text( string $text ): string {
		$text = self::normalize_plain_text( $text );
		if ( '' === $text ) {
			return '';
		}

		$dot_pos = function_exists( 'mb_strpos' )
			? mb_strpos( $text, '.' )
			: strpos( $text, '.' );

		if ( false !== $dot_pos && $dot_pos >= 0 ) {
			$sentence = function_exists( 'mb_substr' )
				? mb_substr( $text, 0, (int) $dot_pos + 1 )
				: substr( $text, 0, (int) $dot_pos + 1 );
			return trim( (string) $sentence );
		}

		return $text;
	}

	/**
	 * @param string $text Plain text.
	 */
	private static function normalize_plain_text( string $text ): string {
		$text = trim( preg_replace( '/\s+/u', ' ', $text ) );
		return is_string( $text ) ? $text : '';
	}

	/**
	 * First paragraph + first list; rest for "مشاهده بیشتر".
	 *
	 * @param string $content Post content.
	 * @return array{teaser_html:string,rest_html:string,has_more:bool}
	 */
	public static function parse_about_teaser( string $content ): array {
		$content = trim( $content );
		$out     = array(
			'teaser_html' => '',
			'rest_html'   => '',
			'has_more'    => false,
		);
		if ( '' === $content ) {
			return $out;
		}

		$html = apply_filters( 'the_content', $content );
		if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
			return $out;
		}

		$first_p   = '';
		$first_list = '';
		if ( preg_match( '/<p\b[^>]*>.*?<\/p>/is', $html, $p_match ) ) {
			$first_p = $p_match[0];
		}
		if ( preg_match( '/<(ul|ol)\b[^>]*>.*?<\/\1>/is', $html, $list_match ) ) {
			$first_list = $list_match[0];
		}

		$teaser_parts = array_filter( array( $first_p, $first_list ) );
		$teaser_html  = wp_kses_post( implode( "\n", $teaser_parts ) );
		$rest_html    = $html;

		foreach ( $teaser_parts as $part ) {
			$pos = strpos( $rest_html, $part );
			if ( false !== $pos ) {
				$rest_html = substr( $rest_html, 0, $pos ) . substr( $rest_html, $pos + strlen( $part ) );
			}
		}
		$rest_html = trim( $rest_html );
		$rest_html = wp_kses_post( $rest_html );

		$has_more = '' !== trim( wp_strip_all_tags( $rest_html ) );

		$out['teaser_html'] = $teaser_html;
		$out['rest_html']   = $rest_html;
		$out['has_more']    = $has_more;

		return $out;
	}

	/**
	 * @param mixed $rows Repeater rows.
	 * @return array<int, array{icon:string,title:string,text:string}>
	 */
	private static function normalize_why_buy( $rows ): array {
		$items = array();
		if ( is_array( $rows ) ) {
			$icons = array( 'shield', 'truck', 'consult', 'invoice' );
			$i     = 0;
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$title = trim( (string) ( $row['title'] ?? '' ) );
				$text  = trim( (string) ( $row['text'] ?? '' ) );
				if ( '' === $title && '' === $text ) {
					continue;
				}
				$items[] = array(
					'icon'  => $icons[ $i % count( $icons ) ],
					'title' => $title,
					'text'  => $text,
				);
				++$i;
			}
		}

		if ( array() !== $items ) {
			return $items;
		}

		return array(
			array(
				'icon'  => 'shield',
				'title' => __( 'تضمین اصالت', 'hello-elementor-child' ),
				'text'  => __( 'مواد شیمیایی با برگه آنالیز و تامین از منابع معتبر.', 'hello-elementor-child' ),
			),
			array(
				'icon'  => 'truck',
				'title' => __( 'ارسال سریع', 'hello-elementor-child' ),
				'text'  => __( 'ارسال مطمئن و سریع به سراسر کشور.', 'hello-elementor-child' ),
			),
			array(
				'icon'  => 'consult',
				'title' => __( 'مشاوره تخصصی', 'hello-elementor-child' ),
				'text'  => __( 'راهنمایی کارشناسان در انتخاب گرید و بسته‌بندی مناسب.', 'hello-elementor-child' ),
			),
			array(
				'icon'  => 'invoice',
				'title' => __( 'فاکتور رسمی', 'hello-elementor-child' ),
				'text'  => __( 'صدور فاکتور رسمی برای خریدهای سازمانی.', 'hello-elementor-child' ),
			),
		);
	}

	/**
	 * @param mixed $rows FAQ repeater.
	 * @return array<int, array{question:string,answer:string}>
	 */
	private static function normalize_faq( $rows ): array {
		$items = array();
		if ( ! is_array( $rows ) ) {
			return $items;
		}
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$q = trim( (string) ( $row['question'] ?? '' ) );
			$a = trim( (string) ( $row['answer'] ?? '' ) );
			if ( '' === $q ) {
				continue;
			}
			$items[] = array(
				'question' => $q,
				'answer'   => $a,
			);
		}
		return $items;
	}

	/**
	 * @param mixed $posts Related posts.
	 * @return array<int, array{title:string,url:string,image:string,date:string}>
	 */
	private static function normalize_articles( $posts ): array {
		$items = array();
		if ( ! is_array( $posts ) ) {
			return $items;
		}
		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$url = get_permalink( $post );
			if ( ! is_string( $url ) || '' === $url ) {
				continue;
			}
			$image = get_the_post_thumbnail_url( $post, 'medium' );
			$items[] = array(
				'title' => get_the_title( $post ),
				'url'   => $url,
				'image' => is_string( $image ) ? $image : '',
				'date'  => get_the_date( '', $post ),
			);
		}
		return $items;
	}

	/**
	 * @param mixed $brands Brand posts.
	 * @param int   $self_id Current brand ID.
	 * @return array<int, array{title:string,url:string,image:string}>
	 */
	private static function normalize_similar_brands( $brands, int $self_id ): array {
		$items = array();
		if ( ! is_array( $brands ) ) {
			return $items;
		}
		foreach ( $brands as $brand ) {
			if ( ! $brand instanceof WP_Post ) {
				continue;
			}
			if ( (int) $brand->ID === $self_id ) {
				continue;
			}
			$url = get_permalink( $brand );
			if ( ! is_string( $url ) || '' === $url ) {
				continue;
			}
			$image = get_the_post_thumbnail_url( $brand, 'thumbnail' );
			$items[] = array(
				'title' => get_the_title( $brand ),
				'url'   => $url,
				'image' => is_string( $image ) ? $image : '',
			);
		}
		return $items;
	}

	/**
	 * Enqueue enhanced brand assets.
	 */
	public static function enqueue_assets(): void {
		$css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/single-brand-enhanced.css';
		if ( file_exists( $css ) ) {
			wp_enqueue_style(
				'lk-brand-enhanced',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/css/single-brand-enhanced.css',
				array( 'lk-light-product' ),
				(string) filemtime( $css )
			);
		}

		$js = HELLO_ELEMENTOR_CHILD_PATH . 'assets/js/single-brand-enhanced.js';
		if ( file_exists( $js ) ) {
			wp_enqueue_script(
				'lk-brand-enhanced',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/js/single-brand-enhanced.js',
				array(),
				(string) filemtime( $js ),
				true
			);
		}
	}
}
