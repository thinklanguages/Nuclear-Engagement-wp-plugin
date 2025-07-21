<?php
/**
 * constants.php - Part of the Nuclear Engagement plugin.
 *
 * @package Nuclear_Engagement
 */

declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'MB_IN_BYTES' ) ) {
	define( 'MB_IN_BYTES', 1024 * 1024 );
}

/**
 * Plugin-wide numeric configuration.
 *
 * These constants centralize all tunable numeric values used across the
 * plugin. Keeping them in one place avoids "magic numbers" sprinkled in
 * the codebase and makes it easier to tweak behaviour without hunting
 * through multiple files.
 */

/** Maximum log file size in bytes. */
if ( ! defined( 'NUCLEN_LOG_FILE_MAX_SIZE' ) ) {
	define( 'NUCLEN_LOG_FILE_MAX_SIZE', MB_IN_BYTES );
}

/** Enable buffered logging. */
define( 'NUCLEN_BUFFER_LOGS', true );

/** Timeout for API requests in seconds. */
define( 'NUCLEN_API_TIMEOUT', 30 );

/** Initial delay before polling a new generation in seconds. */
define( 'NUCLEN_INITIAL_POLL_DELAY', 15 );

/** Maximum attempts when polling the API for generation results. */
define( 'NUCLEN_MAX_POLL_ATTEMPTS', 240 );

/** Lifetime of the activation redirect transient in seconds. */
define( 'NUCLEN_ACTIVATION_REDIRECT_TTL', 30 );

/** Delay between polling attempts in seconds. */
define( 'NUCLEN_POLL_RETRY_DELAY', MINUTE_IN_SECONDS );

/** Delay for manual generation polling in seconds. */
define( 'NUCLEN_GENERATION_POLL_DELAY', 30 );

/** Number of post IDs fetched per database query when generating. */
define( 'NUCLEN_POST_FETCH_CHUNK', 200 );

/** Default summary length when generating posts. */
define( 'NUCLEN_SUMMARY_LENGTH_DEFAULT', 30 );

/** Minimum words allowed for generated summaries. */
define( 'NUCLEN_SUMMARY_LENGTH_MIN', 20 );

/** Maximum words allowed for generated summaries. */
define( 'NUCLEN_SUMMARY_LENGTH_MAX', 50 );

/** Default number of items in generated summaries. */
define( 'NUCLEN_SUMMARY_ITEMS_DEFAULT', 3 );

/** Minimum items allowed in generated summaries. */
define( 'NUCLEN_SUMMARY_ITEMS_MIN', 3 );

/** Maximum items allowed in generated summaries. */
define( 'NUCLEN_SUMMARY_ITEMS_MAX', 7 );

/** Default scroll offset for the table of contents in pixels. */
define( 'NUCLEN_TOC_SCROLL_OFFSET_DEFAULT', 72 );

/**
 * Position of the plugin top-level menu.
 * WordPress core menus typically occupy positions below 25, so
 * 30 avoids most conflicts with other plugins.
 */
define( 'NUCLEN_ADMIN_MENU_POSITION', 30 );

/** Asset version for cache busting. */
if ( ! defined( 'NUCLEN_ASSET_VERSION' ) ) {
	define( 'NUCLEN_ASSET_VERSION', '20250720-2' );
}
