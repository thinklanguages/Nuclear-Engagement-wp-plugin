<?php
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

    /* ---------------------------------------------------------------------
     *  Bootstrap
     * ------------------------------------------------------------------- */
    public static function init(): void {
        /* Table creation handled on plugin activation. */

        /* Save via AJAX – front-end */
        add_action( 'wp_ajax_nuclen_save_optin',          [ self::class, 'handle_ajax' ] );
        add_action( 'wp_ajax_nopriv_nuclen_save_optin',   [ self::class, 'handle_ajax' ] );

                // CSV export handled via OptinExportController
    }

    /* ---------------------------------------------------------------------
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
     */
    public static function table_exists(): bool {
        if ( null !== self::$table_exists_cache ) {
            return self::$table_exists_cache;
        }

        global $wpdb;
        $table = self::table_name();
        self::$table_exists_cache = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) === $table;
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
        if ( empty( $result ) ) {
            LoggingService::log( 'dbDelta executed with no changes.' );
        }

        self::$table_exists_cache = true;
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
            [
                'submitted_at' => current_time( 'mysql', true ),
                'url'          => esc_url_raw( $url ),
                'name'         => sanitize_text_field( $name ),
                'email'        => sanitize_email( $email ),
            ],
            [ '%s', '%s', '%s', '%s' ]
        );

        if ( false === $ok ) {
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
        return preg_match('/^[=+\-@]/', $value) ? "'{$value}" : $value;
    }

    /* ---------------------------------------------------------------------
     *  AJAX insert
     * ------------------------------------------------------------------- */
    public static function handle_ajax(): void {

        check_ajax_referer( 'nuclen_optin_nonce', 'nonce' );

        $name  = sanitize_text_field( wp_unslash( $_POST['name']  ?? '' ) );
        $email = sanitize_email(      wp_unslash( $_POST['email'] ?? '' ) );
        $url   = esc_url_raw(        wp_unslash( $_POST['url']   ?? '' ) );

        if ( empty( $email ) || ! is_email( $email ) ) {
            wp_send_json_error( [ 'message' => 'Please enter a valid email address.' ], 400 );
        }

        if ( ! self::insert( $name, $email, $url ) ) {
            wp_send_json_error( [ 'message' => 'Unable to save your submission. Please try again later.' ], 500 );
        }

        wp_send_json_success();
    }

    /* ---------------------------------------------------------------------
     *  CSV export  (admin-only)
     * ------------------------------------------------------------------- */
    public static function handle_export(): void {

        /* Permission check */
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions.', 'nuclear-engagement' ), 403 );
        }

        /* Nonce check – accept from GET or POST */
        $nonce = $_REQUEST['_wpnonce'] ?? '';
        if ( ! wp_verify_nonce( $nonce, 'nuclen_export_optin' ) ) {
            wp_die( __( 'Invalid nonce.', 'nuclear-engagement' ), 400 );
        }

        global $wpdb;
        $rows = $wpdb->get_results(
            'SELECT submitted_at AS datetime,
                    url,
                    name,
                    email
               FROM ' . self::table_name() . '
               ORDER BY submitted_at DESC',
            ARRAY_A
        );

        /* Output */
        if ( ob_get_length() ) {
            ob_end_clean();
        }
        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=nuclen_optins_' . gmdate( 'Y-m-d' ) . '.csv' );

        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, [ 'datetime', 'url', 'name', 'email' ] );   // headings
                foreach ( $rows as $r ) {
                        // Prevent formula injection when opened in spreadsheet apps.
                        $r['name']  = self::escape_csv_field( $r['name'] );
                        $r['email'] = self::escape_csv_field( $r['email'] );
                        $r['url']   = self::escape_csv_field( $r['url'] );
                        fputcsv( $out, $r );
                }
        fclose( $out );
        exit;
    }
}

