<?php
/**
 * Light category template for all product category archives.
 *
 * Native Lookazma chrome + shop cards + category specs tabs.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Switches every product category archive to the Timber light template.
 */
final class Hello_Elementor_Child_Custom_Category_Archive {

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_filter( 'timber/locations', array( __CLASS__, 'add_timber_locations' ) );

		add_action( 'template_redirect', array( __CLASS__, 'force_light_template' ), 1 );
		add_filter( 'template_include', array( __CLASS__, 'maybe_use_custom_template' ), PHP_INT_MAX );
		add_filter( 'elementor/theme/need_override_location', array( __CLASS__, 'disable_elementor_locations' ), PHP_INT_MAX, 2 );
		add_filter( 'elementor/theme/get_location_templates', array( __CLASS__, 'remove_popup_templates' ), PHP_INT_MAX, 2 );
		add_filter( 'elementor/theme/get_location_templates/popup', array( __CLASS__, 'deny_popup_templates' ), PHP_INT_MAX );
		add_filter( 'jet-theme-core/template-include', array( __CLASS__, 'disable_jet_theme_core_template' ), PHP_INT_MAX );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_light_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'dequeue_listing_assets' ), 100 );
		add_action( 'wp_print_styles', array( __CLASS__, 'dequeue_listing_assets' ), 100 );
		add_action( 'wp_print_scripts', array( __CLASS__, 'dequeue_listing_assets' ), 100 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
	}

	/**
	 * Add Timber views path.
	 *
	 * @param array<int, string> $locations Locations.
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
	 * Whether the light category template is active for the current (or given) term.
	 *
	 * @param int|null $term_id Optional product_cat term ID (e.g. AJAX filters).
	 */
	public static function is_enabled( ?int $term_id = null ): bool {
		if ( null === $term_id ) {
			return function_exists( 'is_product_category' ) && is_product_category();
		}

		if ( $term_id <= 0 ) {
			return false;
		}

		$term = get_term( $term_id, 'product_cat' );
		return $term instanceof WP_Term && ! is_wp_error( $term );
	}

	/**
	 * Hard takeover before Elementor/Jet archive templates.
	 */
	public static function force_light_template(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		if ( ! self::is_enabled() ) {
			return;
		}

		$custom = self::get_light_template_path();
		if ( ! file_exists( $custom ) ) {
			return;
		}

		status_header( 200 );
		include $custom;
		exit;
	}

	/**
	 * Fallback template_include swap.
	 *
	 * @param string $template Current template.
	 */
	public static function maybe_use_custom_template( string $template ): string {
		if ( ! self::is_enabled() ) {
			return $template;
		}
		$custom = self::get_light_template_path();
		return file_exists( $custom ) ? $custom : $template;
	}

	/**
	 * Block Elementor header/footer/archive/popup on this template.
	 *
	 * @param bool   $need_override Whether override is needed.
	 * @param string $location      Location name.
	 */
	public static function disable_elementor_locations( bool $need_override, string $location ): bool {
		if ( ! self::is_enabled() ) {
			return $need_override;
		}
		if ( in_array( $location, array( 'header', 'footer', 'archive', 'product_archive', 'product-archive', 'popup' ), true ) ) {
			return false;
		}
		return $need_override;
	}

	/**
	 * Strip popup location templates.
	 *
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
	 * Empty popup location documents.
	 *
	 * @param mixed $templates Templates.
	 * @return mixed
	 */
	public static function deny_popup_templates( $templates ) {
		return self::is_enabled() ? array() : $templates;
	}

	/**
	 * Prevent JetThemeCore from swapping the PHP template.
	 *
	 * @param mixed $template Current template.
	 * @return mixed
	 */
	public static function disable_jet_theme_core_template( $template ) {
		if ( ! self::is_enabled() ) {
			return $template;
		}
		$custom = self::get_light_template_path();
		return file_exists( $custom ) ? $custom : $template;
	}

	/**
	 * Light template PHP path.
	 */
	public static function get_light_template_path(): string {
		return HELLO_ELEMENTOR_CHILD_PATH . 'woocommerce/taxonomy-product_cat-light.php';
	}

	/**
	 * Front-end assets.
	 */
	public static function enqueue_light_assets(): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		if ( class_exists( 'Hello_Elementor_Child_Archive_Product_Filter' ) ) {
			Hello_Elementor_Child_Archive_Product_Filter::enqueue_assets();
		}

		$card_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-product-shop-cards.css';
		if ( file_exists( $card_css ) ) {
			wp_enqueue_style(
				'lk-archive-shop-cards',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-product-shop-cards.css',
				array( HELLO_ELEMENTOR_CHILD_VAZIRMATN_HANDLE ),
				(string) filemtime( $card_css )
			);
		}

		$light_deps = array( HELLO_ELEMENTOR_CHILD_VAZIRMATN_HANDLE, 'lk-archive-filters' );
		if ( wp_style_is( 'lk-archive-shop-cards', 'registered' ) || wp_style_is( 'lk-archive-shop-cards', 'enqueued' ) ) {
			$light_deps[] = 'lk-archive-shop-cards';
		}

		wp_enqueue_style(
			'lk-light-product',
			HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-light-product.css',
			$light_deps,
			HELLO_ELEMENTOR_CHILD_VERSION
		);

		$cat_css = HELLO_ELEMENTOR_CHILD_PATH . 'assets/css/archive-light-category.css';
		if ( file_exists( $cat_css ) ) {
			wp_enqueue_style(
				'lk-light-category',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/css/archive-light-category.css',
				array( 'lk-light-product' ),
				(string) filemtime( $cat_css )
			);
		}

		wp_add_inline_style( 'lk-light-product', 'body.lk-light-category{--lk-lpt-cols:4;}' );

		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'wc-add-to-cart' );
		wp_enqueue_script( 'wc-cart-fragments' );
		wp_enqueue_script( 'woocommerce' );

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

		$tabs_js = HELLO_ELEMENTOR_CHILD_PATH . 'assets/js/light-category-tabs.js';
		if ( file_exists( $tabs_js ) ) {
			wp_enqueue_script(
				'lk-light-category-tabs',
				HELLO_ELEMENTOR_CHILD_URI . 'assets/js/light-category-tabs.js',
				array(),
				(string) filemtime( $tabs_js ),
				true
			);
		}
	}

	/**
	 * Drop Elementor/Jet chrome + listing assets.
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
			'jet-woo-builder-frontend-font',
			'jet-smart-filters',
			'jet-engine-frontend',
			'lk-archive-product-light',
		);
		$script_handles = array(
			'elementor-frontend',
			'elementor-webpack-runtime',
			'elementor-frontend-modules',
			'elementor-pro-frontend',
			'webpack-pro',
			'pro-elements-handlers',
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
	 * Body classes.
	 *
	 * @param array<int, string> $classes Classes.
	 * @return array<int, string>
	 */
	public static function body_class( array $classes ): array {
		if ( self::is_enabled() ) {
			$classes[] = 'lk-light-product';
			$classes[] = 'lk-light-category';
			$classes[] = 'lk-shop-cards-archive';
			$classes[] = 'lz-chrome';
		}
		return array_values( array_unique( $classes ) );
	}

	/**
	 * Products per page for light category.
	 */
	public static function get_per_page(): int {
		return 12;
	}

	/**
	 * Pick the richest category description HTML (term + ACF).
	 *
	 * @param int    $term_id Term ID.
	 * @param string $acf_id  ACF id (product_cat_{id}).
	 */
	public static function resolve_category_description_html( int $term_id, string $acf_id = '' ): string {
		$candidates = array();

		$raw = get_term_field( 'description', $term_id, 'product_cat', 'raw' );
		if ( is_string( $raw ) && '' !== trim( wp_strip_all_tags( $raw ) ) ) {
			$candidates[] = $raw;
			$candidates[] = wpautop( $raw );
		}

		$filtered = term_description( $term_id, 'product_cat' );
		if ( is_string( $filtered ) && '' !== trim( wp_strip_all_tags( $filtered ) ) ) {
			$candidates[] = $filtered;
		}

		$acf_keys = array(
			'توضیحات',
			'توضیحات_دسته',
			'description',
			'cat_description',
			'category_description',
			'category_content',
			'محتوا',
			'متن_دسته',
		);
		foreach ( $acf_keys as $key ) {
			if ( function_exists( 'get_field' ) && '' !== $acf_id ) {
				foreach ( array( true, false ) as $format ) {
					$val = get_field( $key, $acf_id, $format );
					if ( is_string( $val ) && '' !== trim( wp_strip_all_tags( $val ) ) ) {
						$candidates[] = $val;
					}
				}
			}
			$meta = get_term_meta( $term_id, $key, true );
			if ( is_string( $meta ) && '' !== trim( wp_strip_all_tags( $meta ) ) ) {
				$candidates[] = $meta;
			}
		}

		$best       = '';
		$best_score = -1;
		foreach ( $candidates as $html ) {
			$html  = trim( (string) $html );
			$score = self::count_section_headings( $html );
			$len   = strlen( $html );
			if ( $score > $best_score || ( $score === $best_score && $len > strlen( $best ) ) ) {
				$best       = $html;
				$best_score = $score;
			}
		}

		return $best;
	}

	/**
	 * Parse category description into H1 + nested heading TOC (h2 → h3 children).
	 *
	 * @param string $html Description HTML.
	 * @return array<string, mixed>
	 */
	public static function parse_description_sections( string $html ): array {
		$html = trim( $html );
		$out  = array(
			'full_html' => '',
			'excerpt'   => '',
			'h1_label'  => '',
			'sections'  => array(),
			'has_specs' => false,
		);

		if ( '' === $html ) {
			return $out;
		}

		$out['has_specs'] = true;
		$out['excerpt']   = self::excerpt_from_html( $html );

		if ( preg_match( '/<h1\b[^>]*>(.*?)<\/h1>/is', $html, $h1_match ) ) {
			$out['h1_label'] = trim( wp_strip_all_tags( (string) $h1_match[1] ) );
		}
		if ( '' === $out['h1_label'] ) {
			$out['h1_label'] = __( 'همه مطالب', 'hello-elementor-child' );
		}

		$headings  = self::find_section_headings( $html );
		$count     = count( $headings );
		$annotated = $html;
		for ( $i = $count - 1; $i >= 0; $i-- ) {
			$id           = 'lk-cat-sec-' . ( $i + 1 );
			$offset       = (int) $headings[ $i ]['offset'];
			$heading_html = (string) $headings[ $i ]['html'];
			$with_id      = self::inject_heading_id( $heading_html, $id );
			$annotated    = substr( $annotated, 0, $offset ) . $with_id . substr( $annotated, $offset + strlen( $heading_html ) );
			$headings[ $i ]['id'] = $id;
		}

		$out['full_html'] = wp_kses_post( $annotated );

		$flat = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$label = trim( (string) $headings[ $i ]['label'] );
			if ( '' === $label ) {
				$label = sprintf(
					/* translators: %d section number */
					__( 'بخش %d', 'hello-elementor-child' ),
					$i + 1
				);
			}

			$flat[] = array(
				'id'       => (string) ( $headings[ $i ]['id'] ?? ( 'lk-cat-sec-' . ( $i + 1 ) ) ),
				'label'    => $label,
				'level'    => (int) ( $headings[ $i ]['level'] ?? 2 ),
				'children' => array(),
			);
		}

		$out['sections'] = self::nest_heading_sections( $flat );

		return $out;
	}

	/**
	 * Nest flat h2/h3 items: h3s become children of the preceding h2.
	 *
	 * @param array<int, array{id:string,label:string,level:int,children:array}> $flat Flat headings.
	 * @return array<int, array{id:string,label:string,level:int,children:array}>
	 */
	private static function nest_heading_sections( array $flat ): array {
		$nested     = array();
		$current_h2 = null;

		foreach ( $flat as $item ) {
			$level = (int) ( $item['level'] ?? 2 );
			if ( $level <= 2 ) {
				$item['level']    = 2;
				$item['children'] = array();
				$nested[]         = $item;
				$current_h2       = count( $nested ) - 1;
				continue;
			}

			$child = array(
				'id'    => (string) $item['id'],
				'label' => (string) $item['label'],
				'level' => $level,
			);

			if ( null === $current_h2 ) {
				$item['level']    = 2;
				$item['children'] = array();
				$nested[]         = $item;
				continue;
			}

			$nested[ $current_h2 ]['children'][] = $child;
		}

		return $nested;
	}

	/**
	 * Add or replace id on a heading opening tag.
	 *
	 * @param string $heading_html Heading markup.
	 * @param string $id           Anchor id.
	 */
	private static function inject_heading_id( string $heading_html, string $id ): string {
		$id = preg_replace( '/[^a-zA-Z0-9_-]/', '', $id );
		if ( ! is_string( $id ) || '' === $id ) {
			return $heading_html;
		}
		if ( preg_match( '/\sid="/i', $heading_html ) ) {
			$replaced = preg_replace( '/\sid="[^"]*"/i', ' id="' . $id . '"', $heading_html, 1 );
			return is_string( $replaced ) ? $replaced : $heading_html;
		}
		$replaced = preg_replace( '/^<([a-zA-Z0-9]+)\b/i', '<$1 id="' . $id . '"', $heading_html, 1 );
		return is_string( $replaced ) ? $replaced : $heading_html;
	}

	/**
	 * Count detectable section headings.
	 *
	 * @param string $html HTML.
	 */
	private static function count_section_headings( string $html ): int {
		return count( self::find_section_headings( $html ) );
	}

	/**
	 * Detect heading level from markup (h2→2, h3→3, fallbacks→2).
	 *
	 * @param string $html Heading HTML.
	 */
	private static function detect_heading_level( string $html ): int {
		if ( preg_match( '/^<h([2-4])\b/i', $html, $m ) ) {
			return (int) $m[1];
		}
		return 2;
	}

	/**
	 * Find H2/H3/Elementor/strong headings with offsets.
	 *
	 * @param string $html HTML.
	 * @return array<int, array{html:string,offset:int,label:string,level:int}>
	 */
	private static function find_section_headings( string $html ): array {
		$real_patterns = array(
			'/<h[2-4]\b[^>]*>.*?<\/h[2-4]>/is',
			'/<(div|span|p|h[1-6])\b[^>]*class="[^"]*elementor-heading-title[^"]*"[^>]*>.*?<\/\1>/is',
		);
		$fallback_patterns = array(
			'/<p\b[^>]*>\s*<(strong|b)\b[^>]*>.{2,90}<\/\1>\s*<\/p>/is',
		);

		$real = self::match_heading_patterns( $html, $real_patterns );
		$hits = $real;
		if ( count( $real ) < 1 ) {
			$hits = self::match_heading_patterns( $html, $fallback_patterns );
		}

		if ( array() === $hits ) {
			return array();
		}

		usort(
			$hits,
			static function ( array $a, array $b ): int {
				return $a['offset'] <=> $b['offset'];
			}
		);

		$deduped  = array();
		$last_end = -1;
		foreach ( $hits as $hit ) {
			if ( $hit['offset'] < $last_end ) {
				continue;
			}
			$label = trim( wp_strip_all_tags( $hit['html'] ) );
			if ( '' === $label ) {
				continue;
			}
			$hit['label'] = $label;
			$hit['level'] = self::detect_heading_level( (string) $hit['html'] );
			$deduped[]    = $hit;
			$last_end     = $hit['offset'] + strlen( $hit['html'] );
		}

		return $deduped;
	}

	/**
	 * Run heading regexes.
	 *
	 * @param string             $html     HTML.
	 * @param array<int, string> $patterns Patterns.
	 * @return array<int, array{html:string,offset:int,label:string}>
	 */
	private static function match_heading_patterns( string $html, array $patterns ): array {
		$hits = array();
		foreach ( $patterns as $pattern ) {
			if ( ! preg_match_all( $pattern, $html, $matches, PREG_OFFSET_CAPTURE ) ) {
				continue;
			}
			foreach ( $matches[0] as $match ) {
				$tag = strtolower( (string) $match[0] );
				if ( (bool) preg_match( '/^<h1\b/i', $tag ) ) {
					continue;
				}
				$hits[] = array(
					'html'   => (string) $match[0],
					'offset' => (int) $match[1],
					'label'  => '',
				);
			}
		}
		return $hits;
	}

	/**
	 * First readable paragraph from HTML.
	 *
	 * @param string $html HTML.
	 */
	private static function excerpt_from_html( string $html ): string {
		if ( preg_match( '/<p\b[^>]*>(.*?)<\/p>/is', $html, $match ) ) {
			$text = trim( wp_strip_all_tags( (string) $match[1] ) );
			if ( '' !== $text ) {
				return $text;
			}
		}
		$text = trim( wp_strip_all_tags( $html ) );
		$text = preg_replace( '/\s+/u', ' ', $text );
		if ( ! is_string( $text ) || '' === $text ) {
			return '';
		}
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > 180 ) {
			return mb_substr( $text, 0, 180 ) . '…';
		}
		if ( strlen( $text ) > 180 ) {
			return substr( $text, 0, 180 ) . '…';
		}
		return $text;
	}
}
