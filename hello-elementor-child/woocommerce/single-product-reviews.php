<?php
/**
 * Light product reviews — login required, card layout for comments + nested replies.
 *
 * @package HelloElementorChild
 * @see woocommerce/templates/single-product-reviews.php
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
	return;
}

if ( ! comments_open() ) {
	return;
}

$count   = (int) $product->get_review_count();
$average = (float) $product->get_average_rating();
$login_url = function_exists( 'wc_get_page_permalink' ) ? (string) wc_get_page_permalink( 'myaccount' ) : '';
if ( '' === $login_url ) {
	$login_url = wp_login_url( get_permalink( $product->get_id() ) );
} else {
	$login_url = add_query_arg(
		'redirect',
		rawurlencode( (string) get_permalink( $product->get_id() ) ),
		$login_url
	);
}

/**
 * Average = sum(ratings) / count(ratings) from approved top-level reviews.
 *
 * @param int $product_id Product ID.
 * @return array{count:int,average:float}
 */
$lk_compute_avg = static function ( int $product_id ): array {
	$comments = get_comments(
		array(
			'post_id'      => $product_id,
			'status'       => 'approve',
			'parent'       => 0,
			'type'         => '',
			'number'       => 0,
		)
	);

	$sum   = 0.0;
	$rated = 0;
	foreach ( $comments as $c ) {
		$r = (float) get_comment_meta( (int) $c->comment_ID, 'rating', true );
		if ( $r > 0 ) {
			$sum += $r;
			$rated++;
		}
	}

	return array(
		'count'   => $rated,
		'average' => $rated > 0 ? ( $sum / $rated ) : 0.0,
	);
};

$lk_avg_pack = $lk_compute_avg( (int) $product->get_id() );
if ( $lk_avg_pack['count'] > 0 ) {
	// Prefer live sum÷count over possibly stale product meta.
	$average = (float) $lk_avg_pack['average'];
}

if ( ! function_exists( 'hello_elementor_child_lk_star_svg' ) ) {
	/**
	 * Inline star SVG (currentColor).
	 */
	function hello_elementor_child_lk_star_svg(): string {
		return '<svg class="lk-star-svg" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2.5l2.85 5.78 6.38.93-4.61 4.5 1.09 6.35L12 16.98l-5.71 3.08 1.09-6.35-4.61-4.5 6.38-.93L12 2.5z"/></svg>';
	}
}

if ( ! function_exists( 'hello_elementor_child_lk_stars_html' ) ) {
	/**
	 * Five SVG stars, filled RTL (star 1 on the right).
	 *
	 * @param float $rating Rating 0–5.
	 */
	function hello_elementor_child_lk_stars_html( $rating ): string {
		$rating = max( 0.0, min( 5.0, (float) $rating ) );
		$full   = (int) floor( $rating + 1e-9 );
		$frac   = $rating - $full;
		$half   = ( $frac >= 0.25 && $frac < 0.75 && $full < 5 );
		if ( $frac >= 0.75 && $full < 5 ) {
			$full++;
			$half = false;
		}

		$label = sprintf(
			/* translators: %s: rating number */
			__( '%s از ۵', 'hello-elementor-child' ),
			number_format_i18n( $rating, ( abs( $rating - round( $rating ) ) < 0.05 ) ? 0 : 1 )
		);

		$svg  = hello_elementor_child_lk_star_svg();
		$html = '<span class="lk-stars" role="img" aria-label="' . esc_attr( $label ) . '">';

		for ( $i = 1; $i <= 5; $i++ ) {
			$state = 'empty';
			if ( $i <= $full ) {
				$state = 'full';
			} elseif ( $half && $i === $full + 1 ) {
				$state = 'half';
			}
			$html .= '<span class="lk-stars__icon is-' . esc_attr( $state ) . '">' . $svg . '</span>';
		}

		$html .= '</span>';
		return $html;
	}
}

if ( ! function_exists( 'hello_elementor_child_lk_avg_badge_html' ) ) {
	/**
	 * Compact average badge: e.g. 4.3★
	 *
	 * @param float $average Average rating.
	 */
	function hello_elementor_child_lk_avg_badge_html( $average ): string {
		$average = max( 0.0, min( 5.0, (float) $average ) );
		$decimals = ( abs( $average - round( $average ) ) < 0.05 ) ? 0 : 1;
		$num      = number_format_i18n( $average, $decimals );

		return '<span class="lk-reviews__avg">'
			. '<span class="lk-reviews__avg-num">' . esc_html( $num ) . '</span>'
			. '<span class="lk-reviews__avg-star" aria-hidden="true">' . hello_elementor_child_lk_star_svg() . '</span>'
			. '</span>';
	}
}

/**
 * Recursively render a review and its replies.
 *
 * @param WP_Comment          $comment Comment.
 * @param array<int, WP_Comment> $by_parent Map parent_id => children.
 * @param bool                $is_reply Whether this node is a reply.
 */
$lk_render_review = function ( $comment, array $by_parent, $is_reply ) use ( &$lk_render_review ) {
	$rating  = (float) get_comment_meta( (int) $comment->comment_ID, 'rating', true );
	$classes = array( 'lk-review' );
	if ( $is_reply ) {
		$classes[] = 'lk-review--reply';
	}

	?>
	<li <?php comment_class( $classes, $comment ); ?> id="li-comment-<?php echo esc_attr( (string) $comment->comment_ID ); ?>">
		<article id="comment-<?php echo esc_attr( (string) $comment->comment_ID ); ?>" class="lk-review__card comment_container">
			<header class="lk-review__head">
				<span class="lk-review__author"><?php echo esc_html( get_comment_author( $comment ) ); ?></span>
				<?php if ( ! $is_reply && $rating > 0 && function_exists( 'wc_review_ratings_enabled' ) && wc_review_ratings_enabled() ) : ?>
					<?php echo hello_elementor_child_lk_stars_html( $rating ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endif; ?>
				<time class="lk-review__date" datetime="<?php echo esc_attr( get_comment_date( 'c', $comment ) ); ?>">
					<?php echo esc_html( get_comment_date( '', $comment ) ); ?>
				</time>
			</header>
			<div class="lk-review__body description">
				<?php echo wp_kses_post( wpautop( get_comment_text( $comment ) ) ); ?>
			</div>
		</article>
		<?php
		$cid = (int) $comment->comment_ID;
		if ( ! empty( $by_parent[ $cid ] ) ) :
			?>
			<ol class="children">
				<?php
				foreach ( $by_parent[ $cid ] as $child ) {
					$lk_render_review( $child, $by_parent, true );
				}
				?>
			</ol>
		<?php endif; ?>
	</li>
	<?php
};

$lk_comments = get_comments(
	array(
		'post_id' => (int) $product->get_id(),
		'status'  => 'approve',
		'orderby' => 'comment_date_gmt',
		'order'   => 'desc',
		'type'    => '',
	)
);

$lk_by_parent = array();
$lk_top_level = array();
foreach ( $lk_comments as $lk_c ) {
	$parent = (int) $lk_c->comment_parent;
	if ( $parent > 0 ) {
		if ( ! isset( $lk_by_parent[ $parent ] ) ) {
			$lk_by_parent[ $parent ] = array();
		}
		$lk_by_parent[ $parent ][] = $lk_c;
	} else {
		$lk_top_level[] = $lk_c;
	}
}
?>
<div id="reviews" class="woocommerce-Reviews lk-reviews">
	<div id="comments" class="lk-reviews__list-wrap">
		<div class="lk-reviews__summary">
			<p class="lk-reviews__stat lk-reviews__stat--count">
				<span class="lk-reviews__stat-label"><?php esc_html_e( 'تعداد نظرات:', 'hello-elementor-child' ); ?></span>
				<span class="lk-reviews__stat-value"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
			</p>
			<div class="lk-reviews__stat lk-reviews__stat--rating">
				<span class="lk-reviews__stat-label"><?php esc_html_e( 'امتیاز محصول:', 'hello-elementor-child' ); ?></span>
				<?php if ( $count > 0 && function_exists( 'wc_review_ratings_enabled' ) && wc_review_ratings_enabled() && $average > 0 ) : ?>
					<?php echo hello_elementor_child_lk_avg_badge_html( $average ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php else : ?>
					<span class="lk-reviews__stat-value lk-reviews__stat-value--muted"><?php esc_html_e( '—', 'hello-elementor-child' ); ?></span>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( ! empty( $lk_top_level ) ) : ?>
			<ol class="commentlist lk-reviews__list">
				<?php
				foreach ( $lk_top_level as $lk_top ) {
					$lk_render_review( $lk_top, $lk_by_parent, false );
				}
				?>
			</ol>
		<?php else : ?>
			<p class="lk-reviews__empty"><?php esc_html_e( 'هنوز نظری ثبت نشده است.', 'hello-elementor-child' ); ?></p>
		<?php endif; ?>
	</div>

	<div id="review_form_wrapper" class="lk-reviews__form-wrap">
		<div id="review_form" class="lk-reviews__form">
			<?php if ( 'yes' === get_option( 'woocommerce_review_rating_verification_required' ) && ! wc_customer_bought_product( '', get_current_user_id(), $product->get_id() ) ) : ?>
				<p class="woocommerce-verification-required lk-reviews__notice">
					<?php esc_html_e( 'فقط خریداران این محصول می‌توانند نظر ثبت کنند.', 'hello-elementor-child' ); ?>
				</p>
			<?php elseif ( ! is_user_logged_in() ) : ?>
				<div class="lk-reviews__login">
					<p class="lk-reviews__login-text"><?php esc_html_e( 'برای ثبت نظر ابتدا وارد شوید.', 'hello-elementor-child' ); ?></p>
					<a class="lk-reviews__login-btn" href="<?php echo esc_url( $login_url ); ?>">
						<?php esc_html_e( 'ورود به حساب', 'hello-elementor-child' ); ?>
					</a>
				</div>
			<?php else : ?>
				<?php
				$comment_form = array(
					'title_reply'          => have_comments()
						? esc_html__( 'نوشتن نظر', 'hello-elementor-child' )
						: esc_html__( 'اولین نظر را بنویسید', 'hello-elementor-child' ),
					'title_reply_to'       => esc_html__( 'پاسخ به %s', 'hello-elementor-child' ),
					'title_reply_before'   => '<span id="reply-title" class="comment-reply-title lk-reviews__form-title">',
					'title_reply_after'    => '</span>',
					'comment_notes_before' => '',
					'comment_notes_after'  => '',
					'label_submit'         => esc_html__( 'ارسال نظر', 'hello-elementor-child' ),
					'logged_in_as'         => '',
					'comment_field'        => '',
					'fields'               => array(),
				);

				if ( wc_review_ratings_enabled() ) {
					$required = wc_review_ratings_required() ? '&nbsp;<span class="required">*</span>' : '';
					$comment_form['comment_field'] = '<div class="comment-form-rating lk-reviews__rating"><label for="rating">' . esc_html__( 'امتیاز', 'hello-elementor-child' ) . $required . '</label><select name="rating" id="rating" required>
						<option value="">' . esc_html__( 'امتیاز…', 'hello-elementor-child' ) . '</option>
						<option value="1">' . esc_html__( 'خیلی ضعیف', 'hello-elementor-child' ) . '</option>
						<option value="2">' . esc_html__( 'ضعیف', 'hello-elementor-child' ) . '</option>
						<option value="3">' . esc_html__( 'متوسط', 'hello-elementor-child' ) . '</option>
						<option value="4">' . esc_html__( 'خوب', 'hello-elementor-child' ) . '</option>
						<option value="5">' . esc_html__( 'عالی', 'hello-elementor-child' ) . '</option>
					</select></div>';
				}

				$comment_form['comment_field'] .= '<p class="comment-form-comment lk-reviews__comment"><label for="comment">' . esc_html__( 'متن نظر', 'hello-elementor-child' ) . '&nbsp;<span class="required">*</span></label><textarea id="comment" name="comment" cols="45" rows="6" required></textarea></p>';

				comment_form( apply_filters( 'woocommerce_product_review_comment_form_args', $comment_form ) );
				?>
			<?php endif; ?>
		</div>
	</div>
</div>
