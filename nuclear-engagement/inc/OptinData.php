<?php
/**
 * OptinData.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement
 */

declare(strict_types=1);
/**
 * file: includes/OptinData.php
 * Class: NuclearEngagement\OptinData
 *
 * • Stores opt-in submissions
 * • AJAX insert for public users
 * • Secure CSV export for site admins
 *
 * @package NuclearEngagement
 */

namespace NuclearEngagement;

use NuclearEngagement\Services\LoggingService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OptinData {

	const TABLE_SLUG = 'nuclen_optins';

	/*
	---------------------------------------------------------------------
	 *  Bootstrap
	 * ------------------------------------------------------------------- */
	public static function init(): void {
		/* Table creation handled on plugin activation. */

		/* Save via AJAX – front-end */
		add_action( 'wp_ajax_nuclen_save_optin', array( self::class, 'handle_ajax' ) );
		add_action( 'wp_ajax_nopriv_nuclen_save_optin', array( self::class, 'handle_ajax' ) );

				// CSV export handled via OptinExportController.
	}

	/*
	---------------------------------------------------------------------
	 *  Helpers
	 * ------------------------------------------------------------------- */
	private static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SLUG;
	}
	/**
	 * Cached flag to avoid repeated SHOW TABLES queries.
	 *
	 * @var bool|null
	 */
	private static ?bool $table_exists_cache = null;

	/**
	 * Check whether the opt-in table already exists.
	 * Uses transient caching to minimize database queries.
	 */
	public static function table_exists(): bool {
		// First check in-memory cache
		if ( null !== self::$table_exists_cache ) {
			return self::$table_exists_cache;
		}

		// Check transient cache
		$cache_key     = 'nuclen_optin_table_exists';
		$cached_result = get_transient( $cache_key );

		if ( false !== $cached_result ) {
			self::$table_exists_cache = (bool) $cached_result;
			return self::$table_exists_cache;
		}

		// Only run the query if not cached
		global $wpdb;
		$table  = self::table_name();
		$exists = // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;

		// Cache the result
		self::$table_exists_cache = $exists;
		set_transient( $cache_key, $exists ? '1' : '0', DAY_IN_SECONDS );

		return self::$table_exists_cache;
	}

	/**
	 * Create the opt-in table if it doesn't already exist.
	 * Safe to run many times – dbDelta() is idempotent but skipped when not needed.
	 */
	public static function maybe_create_table(): bool {
		if ( self::table_exists() ) {
			return true;
		}

		global $wpdb;

		$charset = $wpdb->get_charset_collate();
		$table   = self::table_name();

		$sql = "
			CREATE TABLE {$table} (
				id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				submitted_at  DATETIME            NOT NULL,
				url           TEXT                NOT NULL,
				name          TEXT                NOT NULL,
				email         VARCHAR(255)        NOT NULL,
				PRIMARY KEY  (id),
				KEY email (email)
			) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$result = dbDelta( $sql );
		if ( ! empty( $wpdb->last_error ) ) {
			LoggingService::log( 'dbDelta error: ' . $wpdb->last_error );
			LoggingService::notify_admin( 'Nuclear Engagement table creation failed. Check logs.' );
			return false;
		}
		// dbDelta returns empty array when no changes needed

		self::$table_exists_cache = true;
		// Update transient cache when table is created
		set_transient( 'nuclen_optin_table_exists', '1', DAY_IN_SECONDS );
		return true;
	}

	/**
	 * Insert one submission.
	 *
	 * @return bool  True on success, false on failure.
	 */
	public static function insert( string $name, string $email, string $url ): bool {

		/* Validate email */
		if ( empty( $email ) || ! is_email( $email ) ) {
			return false;
		}

		global $wpdb;
		$ok = $wpdb->insert(
			self::table_name(),
			array(
				'submitted_at' => current_time( 'mysql', true ),
				'url'          => esc_url_raw( $url ),
				'name'         => sanitize_text_field( $name ),
				'email'        => sanitize_email( $email ),
			),
			array( '%s', '%s', '%s', '%s' )
		);

		if ( $ok === false ) {
			LoggingService::log( 'Insert error: ' . $wpdb->last_error );
		}

		return (bool) $ok;
	}

	/**
	 * Escape potential spreadsheet formulas in a CSV field.
	 *
	 * Spreadsheet applications may interpret values starting with =,+,-,@ as
	 * formulas. Prefix with a single quote so the value is treated as text.
	 */
	private static function escape_csv_field( string $value ): string {
		return preg_match( '/^[=+\-@]/', $value ) ? "'{$value}" : $value;
	}

	/*
	---------------------------------------------------------------------
	 *  AJAX insert
	 * ------------------------------------------------------------------- */
	public static function handle_ajax(): void {

		check_ajax_referer( 'nuclen_optin_nonce', 'nonce' );

		// Validate and sanitize input
		$name  = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$url   = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) );

		// Validate email (required)
		if ( empty( $email ) || ! is_email( $email ) ) {
			LoggingService::log( '[OptinData] Invalid email submitted: ' . $email );
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'nuclear-engagement' ) ), 400 );
			return;
		}

		// Validate name (required, max length)
		if ( empty( $name ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter your name.', 'nuclear-engagement' ) ), 400 );
			return;
		}
		
		if ( mb_strlen( $name ) > 100 ) {
			wp_send_json_error( array( 'message' => __( 'Name is too long. Please use less than 100 characters.', 'nuclear-engagement' ) ), 400 );
			return;
		}

		// Validate URL (must be from current site)
		if ( empty( $url ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid submission URL.', 'nuclear-engagement' ) ), 400 );
			return;
		}
		
		$site_url = get_site_url();
		if ( strpos( $url, $site_url ) !== 0 ) {
			LoggingService::log( '[OptinData] Invalid URL domain submitted: ' . $url );
			wp_send_json_error( array( 'message' => __( 'Invalid submission URL.', 'nuclear-engagement' ) ), 400 );
			return;
		}

		// Rate limiting - prevent spam submissions
		$rate_limit_key = 'nuclen_optin_' . md5( $email );
		if ( get_transient( $rate_limit_key ) ) {
			LoggingService::log( '[OptinData] Rate limit hit for email: ' . $email );
			wp_send_json_error( array( 'message' => __( 'Please wait a few seconds before submitting again.', 'nuclear-engagement' ) ), 429 );
			return;
		}
		set_transient( $rate_limit_key, true, 10 ); // 10 second cooldown

		// Attempt to insert
		if ( ! self::insert( $name, $email, $url ) ) {
			LoggingService::log( '[OptinData] Failed to insert optin for email: ' . $email );
			wp_send_json_error( array( 'message' => __( 'Unable to save your submission. Please try again later.', 'nuclear-engagement' ) ), 500 );
			return;
		}

		wp_send_json_success();
	}

	/*
	---------------------------------------------------------------------
	 *  CSV export  (admin-only)
	 * ------------------------------------------------------------------- */
	public static function handle_export(): void {

		$service = new \NuclearEngagement\Services\OptinExportService();
		$service->stream_csv();
	}
}
