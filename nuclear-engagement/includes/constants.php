<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Plugin-wide constants for numeric values. */

/** Maximum log file size in bytes. */
define( 'NUCLEN_LOG_FILE_MAX_SIZE', MB_IN_BYTES );

/** Timeout for API requests in seconds. */
define( 'NUCLEN_API_TIMEOUT', 30 );

/** Initial delay before polling a new generation in seconds. */
define( 'NUCLEN_INITIAL_POLL_DELAY', 15 );

/** Maximum attempts when polling the API for generation results. */
define( 'NUCLEN_MAX_POLL_ATTEMPTS', 10 );

/** Delay between polling attempts in seconds. */
define( 'NUCLEN_POLL_RETRY_DELAY', MINUTE_IN_SECONDS );

/** Delay for manual generation polling in seconds. */
define( 'NUCLEN_GENERATION_POLL_DELAY', 30 );

/** Default scroll offset for the table of contents in pixels. */
define( 'NUCLEN_TOC_SCROLL_OFFSET_DEFAULT', 72 );

/** Position of the plugin top-level menu. */
define( 'NUCLEN_ADMIN_MENU_POSITION', 30 );
