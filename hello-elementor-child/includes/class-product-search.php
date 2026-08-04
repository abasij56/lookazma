<?php
/**
 * Limit front-end product search to title + SKU (ignore long description content).
 *
 * Fixes FiboSearch Enter → WP/Elementor results showing unrelated products
 * that only mention the query inside AI-generated descriptions.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product search query adjustments.
 */
final class Hello_Elementor_Child_Product_Search {

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_filter( 'posts_search', array( __CLASS__, 'limit_product_search_sql' ), 500, 2 );
	}

	/**
	 * Whether this query is a front-end product search.
	 *
	 * @param WP_Query $query Query.
	 */
	private static function is_product_search_query( WP_Query $query ): bool {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return false;
		}

		$s = $query->get( 's' );
		if ( ! is_string( $s ) || '' === trim( $s ) ) {
			return false;
		}

		$post_type = $query->get( 'post_type' );

		if ( empty( $post_type ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return isset( $_GET['post_type'] ) && 'product' === $_GET['post_type'];
		}

		if ( is_string( $post_type ) ) {
			return 'product' === $post_type;
		}

		if ( is_array( $post_type ) ) {
			$post_type = array_values( array_filter( $post_type ) );
			return array( 'product' ) === $post_type
				|| ( in_array( 'product', $post_type, true ) && ! in_array( 'post', $post_type, true ) && ! in_array( 'page', $post_type, true ) );
		}

		return false;
	}

	/**
	 * Resolve search terms the same way WP would.
	 *
	 * @param WP_Query $query Query.
	 * @return array<int, string>
	 */
	private static function get_search_terms( WP_Query $query ): array {
		$terms = $query->get( 'search_terms' );
		if ( is_array( $terms ) && array() !== $terms ) {
			return array_values(
				array_filter(
					array_map(
						static function ( $term ) {
							return is_string( $term ) ? trim( $term ) : '';
						},
						$terms
					)
				)
			);
		}

		$s = trim( (string) $query->get( 's' ) );
		if ( '' === $s ) {
			return array();
		}

		// Mirror WP: quoted phrase as one term; otherwise split on whitespace.
		if ( preg_match( '/^"([^"]+)"$/', $s, $m ) ) {
			return array( $m[1] );
		}

		$parts = preg_split( '/\s+/u', $s ) ?: array();
		return array_values( array_filter( array_map( 'trim', $parts ) ) );
	}

	/**
	 * Replace default search SQL (title/content/excerpt) with title + SKU only.
	 *
	 * @param string   $search Search SQL fragment.
	 * @param WP_Query $query  Query.
	 * @return string
	 */
	public static function limit_product_search_sql( $search, $query ): string {
		global $wpdb;

		if ( ! $query instanceof WP_Query || ! self::is_product_search_query( $query ) ) {
			return is_string( $search ) ? $search : '';
		}

		$terms = self::get_search_terms( $query );
		if ( array() === $terms ) {
			return is_string( $search ) ? $search : '';
		}

		$parts = array();
		foreach ( $terms as $term ) {
			$like    = '%' . $wpdb->esc_like( $term ) . '%';
			$parts[] = $wpdb->prepare(
				"({$wpdb->posts}.post_title LIKE %s OR EXISTS (
					SELECT 1 FROM {$wpdb->postmeta} pm
					WHERE pm.post_id = {$wpdb->posts}.ID
					AND pm.meta_key = '_sku'
					AND pm.meta_value LIKE %s
				))",
				$like,
				$like
			);
		}

		$sql = ' AND (' . implode( ' AND ', $parts ) . ') ';
		if ( ! is_user_logged_in() ) {
			$sql .= " AND ({$wpdb->posts}.post_password = '') ";
		}

		return $sql;
	}
}
