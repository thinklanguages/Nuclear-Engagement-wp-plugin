<?php
/**
 * ResponseUtils.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Utils
 */

declare(strict_types=1);

namespace NuclearEngagement\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ResponseUtils {
	public static function json_success( array $data = array(), string $message = '' ): void {
		wp_send_json_success(
			array(
				'data'      => $data,
				'message'   => $message,
				'timestamp' => current_time( 'timestamp' ),
			)
		);
	}

	public static function json_error( string $message, array $data = array(), int $status_code = 400 ): void {
		status_header( $status_code );
		wp_send_json_error(
			array(
				'message'   => $message,
				'data'      => $data,
				'timestamp' => current_time( 'timestamp' ),
			)
		);
	}

	public static function redirect_with_message( string $page, string $message, bool $is_error = false ): void {
		$key = $is_error ? 'nuclen_error' : 'nuclen_success';

		wp_redirect(
			add_query_arg(
				array(
					'page'     => $page,
					$key       => urlencode( $message ),
					'_wpnonce' => wp_create_nonce( $page ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function get_admin_notice( string $type = 'info' ): ?string {
		$message = null;
		$class   = 'notice notice-' . $type;

		if ( isset( $_GET['nuclen_success'] ) ) {
			$message = sanitize_text_field( wp_unslash( $_GET['nuclen_success'] ) );
			$class   = 'notice notice-success';
		} elseif ( isset( $_GET['nuclen_error'] ) ) {
			$message = sanitize_text_field( wp_unslash( $_GET['nuclen_error'] ) );
			$class   = 'notice notice-error';
		}

		if ( $message && wp_verify_nonce( $_GET['_wpnonce'] ?? '', $_GET['page'] ?? '' ) ) {
			return sprintf( '<div class="%s is-dismissible"><p>%s</p></div>', esc_attr( $class ), esc_html( $message ) );
		}

		return null;
	}
}
