<?php
/**
 * Bootstrap.php - Part of the Nuclear Engagement plugin.
 *
 * @package Nuclear_Engagement
 */

// Nuclear-engagement/bootstrap.php.
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Prevent multiple bootstrap attempts.
if ( defined( 'NUCLEN_BOOTSTRAP_LOADED' ) ) {
	return;
}
define( 'NUCLEN_BOOTSTRAP_LOADED', true );

require_once __DIR__ . '/inc/Core/PluginBootstrap.php';
require_once __DIR__ . '/inc/Core/CompatibilityAutoloader.php';

// Register compatibility autoloader to handle src/ vs nuclear-engagement/inc/ duplication.
\NuclearEngagement\Core\CompatibilityAutoloader::register();

/**
 * Define a bootstrap error handler.
 *
 * @param \Throwable $e The exception that was thrown.
 */
function nuclen_handle_bootstrap_error( \Throwable $e ): void {
	// Log the error with full context.
	$error_message = sprintf(
		'[Nuclear Engagement] Bootstrap Error: %s in %s on line %d',
		$e->getMessage(),
		$e->getFile(),
		$e->getLine()
	);

	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	error_log( $error_message );

	// Store error in option for persistent notification.
	update_option(
		'nuclen_bootstrap_error',
		array(
			'message' => $e->getMessage(),
			'file'    => $e->getFile(),
			'line'    => $e->getLine(),
			'time'    => current_time( 'mysql' ),
			'trace'   => $e->getTraceAsString(),
		)
	);

	// Show admin notice.
	if ( is_admin() ) {
		add_action(
			'admin_notices',
			function () use ( $e ) {
				$is_debug = defined( 'WP_DEBUG' ) && WP_DEBUG;

				echo '<div class="notice notice-error is-dismissible"><p>';
				echo '<strong>' . esc_html__( 'Nuclear Engagement Plugin Error:', 'nuclear-engagement' ) . '</strong> ';
				echo esc_html__( 'The plugin could not be loaded due to an error.', 'nuclear-engagement' ) . '<br>';

				if ( current_user_can( 'manage_options' ) ) {
					printf(
						/* translators: %s: Error message */
						esc_html__( 'Error: %s', 'nuclear-engagement' ),
						esc_html( $e->getMessage() )
					);

					if ( $is_debug ) {
						printf(
							'<br><small>%s:%d</small>',
							esc_html( basename( $e->getFile() ) ),
							intval( $e->getLine() )
						);
					}
				}

				echo '</p></div>';
			}
		);
	}
}

try {
	// Check critical requirements first.
	if ( ! defined( 'NUCLEN_PLUGIN_FILE' ) ) {
		throw new \RuntimeException( 'NUCLEN_PLUGIN_FILE constant is not defined. The plugin may not be loaded correctly.' );
	}

	// Initialize the plugin.
	$bootstrap = \NuclearEngagement\Core\PluginBootstrap::getInstance();
	$bootstrap->init();

} catch ( \Throwable $e ) {
	nuclen_handle_bootstrap_error( $e );
}
