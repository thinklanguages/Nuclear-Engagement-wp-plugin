<?php
declare(strict_types=1);
/**
 * File: includes/Services/LoggingService.php
 *
 * Handles plugin log file storage and writes.
 *
 * @package NuclearEngagement\Services
 */

namespace NuclearEngagement\Services;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LoggingService {
    /**
     * @var array<string>
     */
    private static array $admin_notices = [];

    /**
     * Get directory, path and URL for the log file.
     */
    public static function get_log_file_info(): array {
        $upload_dir = wp_upload_dir();

        $log_folder = $upload_dir['basedir'] . '/nuclear-engagement';
        $log_file   = $log_folder . '/log.txt';
        $log_url    = $upload_dir['baseurl'] . '/nuclear-engagement/log.txt';

        return [
            'dir'  => $log_folder,
            'path' => $log_file,
            'url'  => $log_url,
        ];
    }

    /**
     * Store an admin notice and ensure the hook is registered.
     */
    private static function add_admin_notice(string $message): void {
        self::$admin_notices[] = $message;
        if (count(self::$admin_notices) === 1) {
            add_action('admin_notices', [self::class, 'render_admin_notices']);
        }
    }

    /**
     * Output stored admin notices.
     */
    public static function render_admin_notices(): void {
        foreach (self::$admin_notices as $notice) {
            echo '<div class="notice notice-error"><p>' . esc_html($notice) . '</p></div>';
        }
    }

    /**
     * Fallback when writing to the log fails.
     */
    private static function fallback(string $original, string $error): void {
        error_log($original);
        self::add_admin_notice($error);
    }

    /**
     * Append a message to the plugin log file.
     */
    public static function log(string $message): void {
        if ($message === '') {
            return;
        }

        $info       = self::get_log_file_info();
        $log_folder = $info['dir'];
        $log_file   = $info['path'];
        $max_size   = NUCLEN_LOG_FILE_MAX_SIZE; // 1 MB

        if (!file_exists($log_folder)) {
            if (!wp_mkdir_p($log_folder)) {
                self::fallback($message, 'Failed to create log directory: ' . $log_folder);
                return;
            }
        }

        if (!is_writable($log_folder)) {
            self::fallback($message, 'Log directory not writable: ' . $log_folder);
            return;
        }

        if (file_exists($log_file) && !is_writable($log_file)) {
            self::fallback($message, 'Log file not writable: ' . $log_file);
            return;
        }
        if (file_exists($log_file) && filesize($log_file) > $max_size) {
            $timestamped = $log_folder . '/log-' . gmdate('Y-m-d-His') . '.txt';
            @rename($log_file, $timestamped);
        }

        if (!file_exists($log_file)) {
            $timestamp = gmdate('Y-m-d H:i:s');
            if (file_put_contents($log_file, "[$timestamp] Log file created\n", FILE_APPEND | LOCK_EX) === false) {
                self::fallback($message, 'Failed to create log file: ' . $log_file);
                return;
            }
        }

        $timestamp = gmdate('Y-m-d H:i:s');
        if (file_put_contents($log_file, "[$timestamp] {$message}\n", FILE_APPEND | LOCK_EX) === false) {
            self::fallback($message, 'Failed to write to log file: ' . $log_file);
        }
    }
}

