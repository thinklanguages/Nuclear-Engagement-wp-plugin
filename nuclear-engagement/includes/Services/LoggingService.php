<?php
/**
 * File: includes/Services/LoggingService.php
 *
 * Handles plugin log file storage and writes.
 *
 * @package NuclearEngagement\Services
 */

namespace NuclearEngagement\Services;

if (!defined('ABSPATH')) {
    exit;
}

class LoggingService {
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
            wp_mkdir_p($log_folder);
        }
        if (file_exists($log_file) && filesize($log_file) > $max_size) {
            $timestamped = $log_folder . '/log-' . gmdate('Y-m-d-His') . '.txt';
            @rename($log_file, $timestamped);
        }

        if (!file_exists($log_file)) {
            $timestamp = gmdate('Y-m-d H:i:s');
            if (file_put_contents($log_file, "[$timestamp] Log file created\n", FILE_APPEND | LOCK_EX) === false) {
                return;
            }
        }

        $timestamp = gmdate('Y-m-d H:i:s');
        if (file_put_contents($log_file, "[$timestamp] {$message}\n", FILE_APPEND | LOCK_EX) === false) {
            error_log('Failed to write to log file: ' . $log_file);
        }
    }
}

