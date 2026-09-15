<?php
/**
 * One-time admin tool: map product_tag → Brand CPT on each product (ACF/meta lk_brand).
 *
 * Tools → Migrate product brands
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product ↔ brand migration (dry / test / full batch).
 */
final class Hello_Elementor_Child_Product_Brand_Migration {

	public const FIELD_NAME = 'lk_brand';

	public const MENU_SLUG = 'lk-migrate-product-brands';

	public const OFFSET_OPTION = 'lk_brand_migrate_offset';

	public const DEFAULT_BATCH = 100;

	public const TEST_LIMIT = 10;

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_tools_page' ) );
	}

	/**
	 * Tools submenu.
	 */
	public static function register_tools_page(): void {
		add_management_page(
			__( 'Migrate product brands', 'hello-elementor-child' ),
			__( 'Migrate product brands', 'hello-elementor-child' ),
			self::capability(),
			self::MENU_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Required capability.
	 */
	public static function capability(): string {
		return class_exists( 'WooCommerce' ) ? 'manage_woocommerce' : 'manage_options';
	}

	/**
	 * Admin page.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'hello-elementor-child' ) );
		}

		$log_lines = array();
		$summary   = null;

		if ( isset( $_POST['lk_brand_migrate_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['lk_brand_migrate_nonce'] ) ), 'lk_brand_migrate' ) ) {
			$mode = isset( $_POST['lk_migrate_mode'] ) ? sanitize_key( (string) wp_unslash( $_POST['lk_migrate_mode'] ) ) : '';

			if ( 'reset_offset' === $mode ) {
				delete_option( self::OFFSET_OPTION );
				$log_lines[] = __( 'Offset reset. Full run will start from the first product.', 'hello-elementor-child' );
			} elseif ( in_array( $mode, array( 'dry', 'test', 'full' ), true ) ) {
				$only_empty = ! empty( $_POST['lk_only_if_empty'] );
				$batch      = self::DEFAULT_BATCH;
				if ( 'test' === $mode ) {
					$batch = self::TEST_LIMIT;
				}

				$offset = 0;
				if ( 'full' === $mode ) {
					$offset = max( 0, (int) get_option( self::OFFSET_OPTION, 0 ) );
				}

				$result    = self::run_batch(
					array(
						'mode'       => $mode,
						'dry_run'    => ( 'dry' === $mode ),
						'only_empty' => $only_empty,
						'offset'     => $offset,
						'limit'      => $batch,
					)
				);
				$log_lines = $result['log'];
				$summary   = $result['summary'];

				if ( 'full' === $mode && ! $result['dry_run'] ) {
					if ( $result['summary']['done'] ) {
						delete_option( self::OFFSET_OPTION );
					} else {
						update_option( self::OFFSET_OPTION, (int) $result['summary']['next_offset'], false );
					}
				}
			}
		}

		$offset       = max( 0, (int) get_option( self::OFFSET_OPTION, 0 ) );
		$total        = self::count_products();
		$brand_count  = count( self::get_brand_map() );
		$field_label  = self::FIELD_NAME;

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Migrate product brands', 'hello-elementor-child' ); ?></h1>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: field name, 2: product count, 3: brand count */
						__( 'Sets ACF/meta field «%1$s» on each product from its product_tag (matched to Brand CPT by slug). Products: %2$d · Published brands: %3$d.', 'hello-elementor-child' ),
						$field_label,
						$total,
						$brand_count
					)
				);
				?>
			</p>
			<p class="description">
				<?php esc_html_e( 'Create an ACF Post Object field on products with field name «lk_brand» (label: برند) pointing to the brand post type before running a live migration.', 'hello-elementor-child' ); ?>
			</p>
			<?php if ( $offset > 0 ) : ?>
				<p>
					<strong><?php esc_html_e( 'Full migration progress:', 'hello-elementor-child' ); ?></strong>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %1$d offset, %2$d total */
							__( '%1$d of %2$d products processed. Click Full run again to continue.', 'hello-elementor-child' ),
							min( $offset, $total ),
							$total
						)
					);
					?>
				</p>
			<?php endif; ?>

			<form method="post" action="">
				<?php wp_nonce_field( 'lk_brand_migrate', 'lk_brand_migrate_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Options', 'hello-elementor-child' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="lk_only_if_empty" value="1" checked="checked" />
								<?php esc_html_e( 'Only update products where «lk_brand» is empty', 'hello-elementor-child' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" name="lk_migrate_mode" value="dry" class="button">
						<?php esc_html_e( 'Dry run (preview batch)', 'hello-elementor-child' ); ?>
					</button>
					<button type="submit" name="lk_migrate_mode" value="test" class="button button-secondary">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: product count */
								__( 'Test run (%d products)', 'hello-elementor-child' ),
								self::TEST_LIMIT
							)
						);
						?>
					</button>
					<button type="submit" name="lk_migrate_mode" value="full" class="button button-primary" onclick="return confirm('<?php echo esc_js( __( 'Migrate the next batch of products?', 'hello-elementor-child' ) ); ?>');">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: batch size */
								__( 'Full run (next %d)', 'hello-elementor-child' ),
								self::DEFAULT_BATCH
							)
						);
						?>
					</button>
					<button type="submit" name="lk_migrate_mode" value="reset_offset" class="button-link-delete">
						<?php esc_html_e( 'Reset full-run offset', 'hello-elementor-child' ); ?>
					</button>
				</p>
			</form>

			<?php if ( null !== $summary ) : ?>
				<h2><?php esc_html_e( 'Summary', 'hello-elementor-child' ); ?></h2>
				<ul style="list-style:disc;margin-right:1.5rem;">
					<li><?php echo esc_html( sprintf( __( 'Would update / updated: %d', 'hello-elementor-child' ), (int) $summary['updated'] ) ); ?></li>
					<li><?php echo esc_html( sprintf( __( 'Skipped (already set): %d', 'hello-elementor-child' ), (int) $summary['skipped_filled'] ) ); ?></li>
					<li><?php echo esc_html( sprintf( __( 'Skipped (no product_tag): %d', 'hello-elementor-child' ), (int) $summary['skipped_no_tag'] ) ); ?></li>
					<li><?php echo esc_html( sprintf( __( 'Skipped (no matching brand): %d', 'hello-elementor-child' ), (int) $summary['skipped_no_brand'] ) ); ?></li>
					<li><?php echo esc_html( sprintf( __( 'Multiple brand tags (used first match): %d', 'hello-elementor-child' ), (int) $summary['skipped_multi'] ) ); ?></li>
					<?php if ( ! empty( $summary['dry_run'] ) ) : ?>
						<li><strong><?php esc_html_e( 'Dry run — no data was saved.', 'hello-elementor-child' ); ?></strong></li>
					<?php elseif ( ! empty( $summary['done'] ) ) : ?>
						<li><strong><?php esc_html_e( 'Full migration complete.', 'hello-elementor-child' ); ?></strong></li>
					<?php elseif ( 'full' === ( $summary['mode'] ?? '' ) ) : ?>
						<li><strong><?php esc_html_e( 'Batch saved. Run Full run again for the next batch.', 'hello-elementor-child' ); ?></strong></li>
					<?php endif; ?>
				</ul>
			<?php endif; ?>

			<?php if ( array() !== $log_lines ) : ?>
				<h2><?php esc_html_e( 'Log', 'hello-elementor-child' ); ?></h2>
				<textarea readonly rows="18" class="large-text code" style="font-family:monospace;direction:ltr;"><?php echo esc_textarea( implode( "\n", $log_lines ) ); ?></textarea>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Count published products (parents + simple; variations excluded).
	 */
	public static function count_products(): int {
		$q = new WP_Query(
			array(
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'post_parent'            => 0,
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		return (int) $q->found_posts;
	}

	/**
	 * Published brand posts keyed by slug.
	 *
	 * @return array<string, int>
	 */
	public static function get_brand_map(): array {
		$posts = get_posts(
			array(
				'post_type'              => Hello_Elementor_Child_Brand_Cpt::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
			)
		);

		$map = array();
		foreach ( $posts as $id ) {
			$id = (int) $id;
			if ( $id <= 0 ) {
				continue;
			}
			$post = get_post( $id );
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$slug = sanitize_title( $post->post_name );
			if ( '' !== $slug ) {
				$map[ $slug ] = $id;
			}
		}

		return $map;
	}

	/**
	 * Read linked brand ID from product.
	 *
	 * @param int $product_id Product ID.
	 */
	public static function get_product_brand_id( int $product_id ): int {
		if ( $product_id <= 0 ) {
			return 0;
		}

		if ( function_exists( 'get_field' ) ) {
			$val = get_field( self::FIELD_NAME, $product_id );
			if ( $val instanceof WP_Post ) {
				return (int) $val->ID;
			}
			if ( is_numeric( $val ) ) {
				return (int) $val;
			}
		}

		return (int) get_post_meta( $product_id, self::FIELD_NAME, true );
	}

	/**
	 * Persist brand on product.
	 *
	 * @param int  $product_id Product ID.
	 * @param int  $brand_id   Brand post ID.
	 * @param bool $dry_run    Skip write.
	 */
	public static function set_product_brand_id( int $product_id, int $brand_id, bool $dry_run ): bool {
		if ( $dry_run || $product_id <= 0 || $brand_id <= 0 ) {
			return ! $dry_run;
		}

		if ( function_exists( 'update_field' ) ) {
			return (bool) update_field( self::FIELD_NAME, $brand_id, $product_id );
		}

		return (bool) update_post_meta( $product_id, self::FIELD_NAME, $brand_id );
	}

	/**
	 * Match product_tag term(s) to a brand post ID.
	 *
	 * @param int               $product_id Product ID.
	 * @param array<string,int> $brand_map  slug => brand ID.
	 * @return array{brand_id:int,tag_slug:string,tag_name:string,multiple:bool}
	 */
	public static function resolve_brand_for_product( int $product_id, array $brand_map ): array {
		$empty = array(
			'brand_id'  => 0,
			'tag_slug'  => '',
			'tag_name'  => '',
			'multiple'  => false,
		);

		if ( $product_id <= 0 || array() === $brand_map ) {
			return $empty;
		}

		$tags = get_the_terms( $product_id, 'product_tag' );
		if ( ! is_array( $tags ) || array() === $tags ) {
			return $empty;
		}

		$matches = array();
		foreach ( $tags as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}
			$slug = sanitize_title( urldecode( (string) $term->slug ) );
			if ( '' === $slug || ! isset( $brand_map[ $slug ] ) ) {
				continue;
			}
			$matches[] = array(
				'brand_id' => (int) $brand_map[ $slug ],
				'tag_slug' => $slug,
				'tag_name' => (string) $term->name,
			);
		}

		if ( array() === $matches ) {
			return $empty;
		}

		$first = $matches[0];
		return array(
			'brand_id' => (int) $first['brand_id'],
			'tag_slug' => (string) $first['tag_slug'],
			'tag_name' => (string) $first['tag_name'],
			'multiple' => count( $matches ) > 1,
		);
	}

	/**
	 * Run one migration batch.
	 *
	 * @param array<string, mixed> $args mode, dry_run, only_empty, offset, limit.
	 * @return array{log:array<int,string>,summary:array<string,mixed>,dry_run:bool}
	 */
	public static function run_batch( array $args ): array {
		$mode       = isset( $args['mode'] ) ? (string) $args['mode'] : 'dry';
		$dry_run    = ! empty( $args['dry_run'] );
		$only_empty = ! empty( $args['only_empty'] );
		$offset     = max( 0, (int) ( $args['offset'] ?? 0 ) );
		$limit      = max( 1, (int) ( $args['limit'] ?? self::DEFAULT_BATCH ) );

		@set_time_limit( 300 );

		$brand_map = self::get_brand_map();
		$log       = array();

		if ( array() === $brand_map ) {
			$log[] = __( 'No published brand posts found. Create brands first.', 'hello-elementor-child' );
			return array(
				'log'     => $log,
				'summary' => array(
					'updated'          => 0,
					'skipped_filled'   => 0,
					'skipped_no_tag'   => 0,
					'skipped_no_brand' => 0,
					'skipped_multi'    => 0,
					'dry_run'          => $dry_run,
					'mode'             => $mode,
					'done'             => true,
					'next_offset'      => $offset,
				),
				'dry_run' => $dry_run,
			);
		}

		$query = new WP_Query(
			array(
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'post_parent'            => 0,
				'posts_per_page'         => $limit,
				'offset'                 => $offset,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => true,
			)
		);

		$total    = (int) $query->found_posts;
		$ids      = array_map( 'intval', is_array( $query->posts ) ? $query->posts : array() );
		$summary  = array(
			'updated'          => 0,
			'skipped_filled'   => 0,
			'skipped_no_tag'   => 0,
			'skipped_no_brand' => 0,
			'skipped_multi'    => 0,
			'dry_run'          => $dry_run,
			'mode'             => $mode,
			'done'             => ( $offset + count( $ids ) ) >= $total,
			'next_offset'      => $offset + count( $ids ),
		);

		$prefix = $dry_run ? '[DRY] ' : '';

		foreach ( $ids as $product_id ) {
			$title = get_the_title( $product_id );
			if ( '' === $title ) {
				$title = '#' . $product_id;
			}

			if ( $only_empty && self::get_product_brand_id( $product_id ) > 0 ) {
				++$summary['skipped_filled'];
				$log[] = $prefix . sprintf( '⊘ #%d %s — already has brand', $product_id, $title );
				continue;
			}

			$match = self::resolve_brand_for_product( $product_id, $brand_map );
			if ( $match['brand_id'] <= 0 ) {
				$tags = get_the_terms( $product_id, 'product_tag' );
				if ( ! is_array( $tags ) || array() === $tags ) {
					++$summary['skipped_no_tag'];
					$log[] = $prefix . sprintf( '⊘ #%d %s — no product_tag', $product_id, $title );
				} else {
					++$summary['skipped_no_brand'];
					$tag_names = implode( ', ', wp_list_pluck( $tags, 'name' ) );
					$log[]     = $prefix . sprintf( '⊘ #%d %s — no brand match (tags: %s)', $product_id, $title, $tag_names );
				}
				continue;
			}

			$brand_title = get_the_title( (int) $match['brand_id'] );
			if ( $match['multiple'] ) {
				++$summary['skipped_multi'];
			}

			$saved = self::set_product_brand_id( $product_id, (int) $match['brand_id'], $dry_run );
			if ( $saved || $dry_run ) {
				++$summary['updated'];
				$multi_note = $match['multiple'] ? ' (first of multiple tag matches)' : '';
				$log[]      = $prefix . sprintf(
					'✓ #%d %s → brand «%s» (ID %d) via tag «%s»%s',
					$product_id,
					$title,
					$brand_title,
					(int) $match['brand_id'],
					$match['tag_name'],
					$multi_note
				);
			} else {
				$log[] = $prefix . sprintf( '✗ #%d %s — failed to save', $product_id, $title );
			}
		}

		$log[] = '';
		$log[] = sprintf(
			/* translators: 1: batch start, 2: batch end, 3: total */
			__( 'Batch products %1$d–%2$d of %3$d.', 'hello-elementor-child' ),
			$offset + 1,
			min( $offset + count( $ids ), $total ),
			$total
		);

		return array(
			'log'     => $log,
			'summary' => $summary,
			'dry_run' => $dry_run,
		);
	}
}
