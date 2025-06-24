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
        private static array $admin_notices = array();

        /**
         * @var array<string> Buffered log messages.
         */
        private static array $buffer = array();

        /**
         * Whether the shutdown hook has been registered.
         */
        private static bool $shutdown_registered = false;

	/**
	 * Get directory, path and URL for the log file.
	 */
	public static function get_log_file_info(): array {
		$upload_dir = wp_upload_dir();

		$log_folder = $upload_dir['basedir'] . '/nuclear-engagement';
		$log_file   = $log_folder . '/log.txt';
		$log_url    = $upload_dir['baseurl'] . '/nuclear-engagement/log.txt';

		return array(
			'dir'  => $log_folder,
			'path' => $log_file,
			'url'  => $log_url,
		);
	}

	/**
	 * Store an admin notice and ensure the hook is registered.
	 */
	private static function add_admin_notice( string $message ): void {
		self::$admin_notices[] = $message;
		if ( count( self::$admin_notices ) === 1 ) {
			add_action( 'admin_notices', array( self::class, 'render_admin_notices' ) );
		}
	}

	/**
	 * Public helper to show an admin error notice.
	 */
	public static function notify_admin( string $message ): void {
		self::add_admin_notice( $message );
	}

	/**
	 * Debug level logging, only when WP_DEBUG is true.
	 */
	public static function debug( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			self::log( '[DEBUG] ' . $message );
		}
	}

	/**
	 * Output stored admin notices.
	 */
	public static function render_admin_notices(): void {
		foreach ( self::$admin_notices as $notice ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $notice ) . '</p></div>';
		}
	}

	/**
	 * Fallback when writing to the log fails.
	 */
        private static function fallback( string $original, string $error ): void {
                $timestamp = gmdate( 'Y-m-d H:i:s' );
                error_log( "[Nuclear Engagement] [$timestamp] {$original} - {$error}" );
                self::add_admin_notice( $error );
        }

        /**
         * Determine if logging should be buffered.
         */
        private static function use_buffer(): bool {
                $default = defined( 'NUCLEN_BUFFER_LOGS' ) ? (bool) NUCLEN_BUFFER_LOGS : true;

                /**
                 * Filters whether logging should buffer messages until shutdown.
                 *
                 * @param bool $use_buffer Current setting.
                 */
                return (bool) apply_filters( 'nuclen_enable_log_buffer', $default );
        }

        /**
         * Flush buffered messages to the log file.
         */
        public static function flush(): void {
                if ( empty( self::$buffer ) ) {
                        return;
                }

                self::write_messages( self::$buffer );
                self::$buffer = array();
        }

        /**
         * Write one or more messages to the log.
         *
         * @param array<string> $messages Messages to write.
         */
        private static function write_messages( array $messages ): void {
                $info       = self::get_log_file_info();
                $log_folder = $info['dir'];
                $log_file   = $info['path'];
                $max_size   = defined( 'NUCLEN_LOG_FILE_MAX_SIZE' ) ? NUCLEN_LOG_FILE_MAX_SIZE : MB_IN_BYTES;

                if ( ! file_exists( $log_folder ) ) {
                        if ( ! wp_mkdir_p( $log_folder ) ) {
                                foreach ( $messages as $msg ) {
                                        self::fallback( $msg, 'Failed to create log directory: ' . $log_folder );
                                }
                                return;
                        }
                }

                if ( ! is_writable( $log_folder ) ) {
                        foreach ( $messages as $msg ) {
                                self::fallback( $msg, 'Log directory not writable: ' . $log_folder );
                        }
                        return;
                }

                if ( file_exists( $log_file ) && ! is_writable( $log_file ) ) {
                        foreach ( $messages as $msg ) {
                                self::fallback( $msg, 'Log file not writable: ' . $log_file );
                        }
                        return;
                }

                if ( file_exists( $log_file ) && filesize( $log_file ) > $max_size ) {
                        $timestamped = $log_folder . '/log-' . gmdate( 'Y-m-d-His' ) . '.txt';
                        $renamed     = rename( $log_file, $timestamped );
                        if ( ! $renamed ) {
                                foreach ( $messages as $msg ) {
                                        self::fallback( $msg, 'Failed to rotate log file: ' . $timestamped );
                                }
                        }
                }

                $data = '';
                if ( ! file_exists( $log_file ) ) {
                        $timestamp = gmdate( 'Y-m-d H:i:s' );
                        $data     .= "[$timestamp] Log file created\n";
                }

                foreach ( $messages as $msg ) {
                        $timestamp = gmdate( 'Y-m-d H:i:s' );
                        $data     .= "[$timestamp] {$msg}\n";
                }

                if ( file_put_contents( $log_file, $data, FILE_APPEND | LOCK_EX ) === false ) {
                        foreach ( $messages as $msg ) {
                                self::fallback( $msg, 'Failed to write to log file: ' . $log_file );
                        }
                }
        }

	/**
	 * Append a message to the plugin log file.
	 */
        public static function log( string $message ): void {
                if ( $message === '' ) {
                        return;
                }

                // Strip any HTML and limit length to avoid leaking sensitive data
                $message = wp_strip_all_tags( $message );
                if ( strlen( $message ) > 1000 ) {
                        $message = substr( $message, 0, 1000 ) . '...';
                }

                if ( self::use_buffer() ) {
                        self::$buffer[] = $message;
                        if ( ! self::$shutdown_registered ) {
                                register_shutdown_function( array( self::class, 'flush' ) );
                                self::$shutdown_registered = true;
                        }
                        return;
                }

                self::write_messages( array( $message ) );
        }
}
