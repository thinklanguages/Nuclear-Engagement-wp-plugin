<?php
namespace NuclearEngagement;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Centralized error handling utility.
 */
final class ErrorHandler {
    /**
     * Log a simple message using the plugin logging facility.
     */
    public static function log(string $message): void {
        $utils = new Utils();
        $utils->nuclen_log($message);
    }

    /**
     * Log an exception with optional context information.
     */
    public static function exception(\Throwable $e, string $context = ''): void {
        $message = $context ? $context . ': ' . $e->getMessage() : $e->getMessage();
        self::log($message);
    }
}
