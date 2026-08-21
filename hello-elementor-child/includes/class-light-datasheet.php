<?php
/**
 * Light product datasheet viewer — same-origin stream (avoids 403 hotlink) + URLs for modal.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves ACF/meta datasheet files and streams them for view/download.
 */
final class Hello_Elementor_Child_Light_Datasheet {

	public const ACTION = 'lk_datasheet';

	/**
	 * Register AJAX endpoints.
	 */
	public static function init(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'stream' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( __CLASS__, 'stream' ) );
	}

	/**
	 * @param int $product_id Product ID.
	 */
	public static function nonce_action( int $product_id ): string {
		return 'lk_datasheet_' . $product_id;
	}

	/**
	 * View + download URLs for a product datasheet (empty strings when none).
	 *
	 * @param int $product_id Product ID.
	 * @return array{url:string,view_url:string,download_url:string,mime:string,attachment_id:int}
	 */
	public static function get_product_datasheet( int $product_id ): array {
		$empty = array(
			'url'           => '',
			'view_url'      => '',
			'download_url'  => '',
			'mime'          => '',
			'attachment_id' => 0,
		);

		if ( $product_id <= 0 ) {
			return $empty;
		}

		$resolved = self::resolve( $product_id );
		if ( $resolved['attachment_id'] <= 0 && '' === $resolved['url'] ) {
			return $empty;
		}

		$nonce = wp_create_nonce( self::nonce_action( $product_id ) );
		$base  = admin_url( 'admin-ajax.php' );

		$view = add_query_arg(
			array(
				'action'     => self::ACTION,
				'product_id' => $product_id,
				'mode'       => 'view',
				'nonce'      => $nonce,
			),
			$base
		);

		$download = add_query_arg(
			array(
				'action'     => self::ACTION,
				'product_id' => $product_id,
				'mode'       => 'download',
				'nonce'      => $nonce,
			),
			$base
		);

		return array(
			'url'           => $resolved['url'],
			'view_url'      => $view,
			'download_url'  => $download,
			'mime'          => $resolved['mime'],
			'attachment_id' => $resolved['attachment_id'],
		);
	}

	/**
	 * Stream datasheet file (view inline or download).
	 */
	public static function stream(): void {
		$product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$mode       = isset( $_GET['mode'] ) ? sanitize_key( (string) $_GET['mode'] ) : 'view'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$nonce      = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['nonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $product_id <= 0 || ! wp_verify_nonce( $nonce, self::nonce_action( $product_id ) ) ) {
			status_header( 403 );
			wp_die( esc_html__( 'دسترسی غیرمجاز.', 'hello-elementor-child' ), 403 );
		}

		if ( ! in_array( $mode, array( 'view', 'download' ), true ) ) {
			$mode = 'view';
		}

		$resolved = self::resolve( $product_id );
		$path     = '';
		$mime     = $resolved['mime'];
		$filename = 'datasheet';

		if ( $resolved['attachment_id'] > 0 ) {
			$path = (string) get_attached_file( $resolved['attachment_id'] );
			if ( '' === $mime ) {
				$mime = (string) get_post_mime_type( $resolved['attachment_id'] );
			}
			$attached_name = basename( $path );
			if ( '' !== $attached_name ) {
				$filename = $attached_name;
			}
		}

		if ( ( '' === $path || ! is_readable( $path ) ) && '' !== $resolved['url'] ) {
			// Last resort: remote fetch only for same-host URLs.
			$host = wp_parse_url( home_url(), PHP_URL_HOST );
			$file_host = wp_parse_url( $resolved['url'], PHP_URL_HOST );
			if ( $host && $file_host && strtolower( (string) $host ) === strtolower( (string) $file_host ) ) {
				$response = wp_remote_get(
					$resolved['url'],
					array(
						'timeout'     => 30,
						'redirection' => 3,
					)
				);
				if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
					$body = wp_remote_retrieve_body( $response );
					if ( '' !== $body ) {
						if ( '' === $mime ) {
							$mime = (string) wp_remote_retrieve_header( $response, 'content-type' );
						}
						if ( '' === $mime ) {
							$mime = 'application/octet-stream';
						}
						nocache_headers();
						header( 'Content-Type: ' . $mime );
						header(
							'Content-Disposition: ' . ( 'download' === $mode ? 'attachment' : 'inline' )
							. '; filename="' . rawurlencode( $filename ) . '"'
						);
						header( 'Content-Length: ' . (string) strlen( $body ) );
						header( 'X-Content-Type-Options: nosniff' );
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo $body;
						exit;
					}
				}
			}
		}

		if ( '' === $path || ! is_readable( $path ) ) {
			status_header( 404 );
			wp_die( esc_html__( 'فایل پیدا نشد.', 'hello-elementor-child' ), 404 );
		}

		if ( '' === $mime ) {
			$mime = 'application/octet-stream';
			if ( function_exists( 'mime_content_type' ) ) {
				$detected = mime_content_type( $path );
				if ( is_string( $detected ) && '' !== $detected ) {
					$mime = $detected;
				}
			}
		}

		$size = filesize( $path );
		nocache_headers();
		header( 'Content-Type: ' . $mime );
		header(
			'Content-Disposition: ' . ( 'download' === $mode ? 'attachment' : 'inline' )
			. '; filename="' . rawurlencode( $filename ) . '"'
		);
		if ( false !== $size ) {
			header( 'Content-Length: ' . (string) $size );
		}
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Accept-Ranges: none' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		readfile( $path );
		exit;
	}

	/**
	 * @param int $product_id Product ID.
	 * @return array{url:string,attachment_id:int,mime:string}
	 */
	public static function resolve( int $product_id ): array {
		$result = array(
			'url'           => '',
			'attachment_id' => 0,
			'mime'          => '',
		);

		$keys = array(
			'coa',
			'COA',
			'coa_file',
			'برگه مشخصات فنی',
			'برگه_مشخصات_فنی',
			'برگه مشخصات',
			'برگه_مشخصات',
			'دانلود برگه مشخصات فنی',
			'دانلود_برگه_مشخصات_فنی',
			'datasheet',
			'data_sheet',
			'tds',
			'tds_file',
			'pdf',
			'technical_datasheet',
			'فایل_مشخصات',
			'datasheet_file',
		);

		foreach ( $keys as $sheet_key ) {
			$raw = function_exists( 'get_field' ) ? get_field( $sheet_key, $product_id, false ) : get_post_meta( $product_id, $sheet_key, true );
			if ( null === $raw || false === $raw || '' === $raw ) {
				$raw = get_post_meta( $product_id, $sheet_key, true );
			}
			$parsed = self::parse_media_value( $raw );
			if ( $parsed['attachment_id'] > 0 || '' !== $parsed['url'] ) {
				return self::enrich_mime( $parsed );
			}
		}

		if ( function_exists( 'acf_get_field_objects' ) ) {
			$fields = acf_get_field_objects( $product_id, false );
			if ( is_array( $fields ) ) {
				foreach ( $fields as $field ) {
					if ( ! is_array( $field ) ) {
						continue;
					}
					$name  = isset( $field['name'] ) ? (string) $field['name'] : '';
					$label = isset( $field['label'] ) ? (string) $field['label'] : '';
					$type  = isset( $field['type'] ) ? (string) $field['type'] : '';
					if ( ! self::is_datasheet_field( $name, $label, $type ) ) {
						continue;
					}
					$parsed = self::parse_media_value( $field['value'] ?? null );
					if ( $parsed['attachment_id'] > 0 || '' !== $parsed['url'] ) {
						return self::enrich_mime( $parsed );
					}
				}
			}
		}

		foreach ( array_keys( (array) get_post_meta( $product_id ) ) as $meta_key ) {
			$meta_key = (string) $meta_key;
			if ( str_starts_with( $meta_key, '_' ) ) {
				continue;
			}
			if (
				! preg_match( '/^(coa|coa_file)$/i', $meta_key )
				&& ! preg_match( '/datasheet|data_sheet|tds|برگه/u', $meta_key )
			) {
				continue;
			}
			$parsed = self::parse_media_value( get_post_meta( $product_id, $meta_key, true ) );
			if ( $parsed['attachment_id'] > 0 || '' !== $parsed['url'] ) {
				return self::enrich_mime( $parsed );
			}
		}

		return $result;
	}

	/**
	 * @param array{url:string,attachment_id:int,mime:string} $parsed Parsed media.
	 * @return array{url:string,attachment_id:int,mime:string}
	 */
	private static function enrich_mime( array $parsed ): array {
		if ( '' === $parsed['mime'] && $parsed['attachment_id'] > 0 ) {
			$parsed['mime'] = (string) get_post_mime_type( $parsed['attachment_id'] );
		}
		if ( '' === $parsed['url'] && $parsed['attachment_id'] > 0 ) {
			$url = wp_get_attachment_url( $parsed['attachment_id'] );
			$parsed['url'] = $url ? (string) $url : '';
		}
		return $parsed;
	}

	/**
	 * @param mixed $value Raw field value.
	 * @return array{url:string,attachment_id:int,mime:string}
	 */
	private static function parse_media_value( $value ): array {
		$out = array(
			'url'           => '',
			'attachment_id' => 0,
			'mime'          => '',
		);

		if ( null === $value || false === $value || '' === $value ) {
			return $out;
		}

		if ( is_array( $value ) ) {
			if ( isset( $value['mime_type'] ) ) {
				$out['mime'] = (string) $value['mime_type'];
			} elseif ( isset( $value['type'] ) ) {
				$out['mime'] = (string) $value['type'];
			}

			$id = 0;
			if ( isset( $value['ID'] ) ) {
				$id = (int) $value['ID'];
			} elseif ( isset( $value['id'] ) ) {
				$id = (int) $value['id'];
			}
			if ( $id > 0 ) {
				$out['attachment_id'] = $id;
			}

			if ( isset( $value['url'] ) && '' !== (string) $value['url'] ) {
				$out['url'] = (string) $value['url'];
			} elseif ( $id > 0 ) {
				$url = wp_get_attachment_url( $id );
				$out['url'] = $url ? (string) $url : '';
			}

			return $out;
		}

		if ( is_numeric( $value ) && (int) $value > 0 ) {
			$id  = (int) $value;
			$url = wp_get_attachment_url( $id );
			$out['attachment_id'] = $id;
			$out['url']           = $url ? (string) $url : '';
			return $out;
		}

		if ( ! is_scalar( $value ) ) {
			return $out;
		}

		$candidate = trim( (string) $value );
		if ( '' === $candidate ) {
			return $out;
		}

		if ( preg_match( '#^https?://#i', $candidate ) || str_starts_with( $candidate, '//' ) ) {
			$out['url'] = $candidate;
			$att_id     = attachment_url_to_postid( $candidate );
			if ( $att_id > 0 ) {
				$out['attachment_id'] = $att_id;
			}
			return $out;
		}

		if ( str_starts_with( $candidate, '/' ) ) {
			$out['url'] = home_url( $candidate );
			$att_id     = attachment_url_to_postid( $out['url'] );
			if ( $att_id > 0 ) {
				$out['attachment_id'] = $att_id;
			}
			return $out;
		}

		return $out;
	}

	/**
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @param string $type  Field type.
	 */
	private static function is_datasheet_field( string $name, string $label, string $type ): bool {
		$label = trim( $label );
		$name  = trim( $name );

		if ( in_array( $label, array( 'COA', 'coa', 'برگه مشخصات فنی', 'دانلود برگه مشخصات فنی', 'برگه مشخصات' ), true ) ) {
			return true;
		}

		if ( '' !== $label && false !== mb_stripos( $label, 'مشخصات فنی' ) ) {
			if ( false !== mb_stripos( $label, 'ایمنی' ) || false !== mb_stripos( $label, 'msds' ) ) {
				return false;
			}
			return true;
		}

		if ( '' !== $label && false !== mb_stripos( $label, 'برگه مشخصات' ) ) {
			return true;
		}

		if ( '' !== $label && preg_match( '/\bcoa\b/i', $label ) ) {
			return true;
		}

		if ( preg_match( '/^(coa|coa_file)$/i', $name ) || preg_match( '/datasheet|data_sheet|tds/i', $name ) ) {
			return true;
		}

		if ( preg_match( '/برگه.*مشخصات/u', $name ) ) {
			return true;
		}

		return in_array( $type, array( 'file', 'url', 'image', 'link' ), true )
			&& '' !== $label
			&& false !== mb_stripos( $label, 'برگه' );
	}
}
