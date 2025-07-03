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
		/** Current service instance. */
		private static ?self $instance = null;

		/** Admin notice service instance. */
		private AdminNoticeService $notices;

		/** Buffered log messages. */
		private array $buffer = array();

		/** Whether the shutdown hook has been registered. */
		private bool $shutdown_registered = false;

		public function __construct( AdminNoticeService $notices ) {
				$this->notices  = $notices;
				self::$instance = $this;
		}

		/** Retrieve the active instance, creating one if needed. */
		private static function instance(): self {
				if ( null === self::$instance ) {
						self::$instance = new self( new AdminNoticeService() );
				}
				return self::$instance;
		}

		/** Get directory, path and URL for the log file. */
		public static function get_log_file_info(): array {
				$instance   = self::instance();
				$upload_dir = wp_upload_dir();

		if ( ! empty( $upload_dir['error'] ) ) {
			error_log( '[Nuclear Engagement] ' . $upload_dir['error'] );
						$instance->add_admin_notice( 'Uploads directory unavailable. Using plugin directory for logs.' );
			$fallback_dir = rtrim( NUCLEN_PLUGIN_DIR, '/' ) . '/logs';
			return array(
				'dir'  => $fallback_dir,
				'path' => $fallback_dir . '/log.txt',
				'url'  => '',
			);
		}

				$log_folder = $upload_dir['basedir'] . '/nuclear-engagement';
				$log_file   = $log_folder . '/log.txt';
				$log_url    = $upload_dir['baseurl'] . '/nuclear-engagement/log.txt';

		return array(
			'dir'  => $log_folder,
			'path' => $log_file,
			'url'  => $log_url,
		);
	}

		/** Store an admin notice and ensure the hook is registered. */
		private function add_admin_notice( string $message ): void {
				$this->notices->add( $message );
		}

		/** Public helper to show an admin error notice. */
		public static function notify_admin( string $message ): void {
				$instance = self::instance();
				$instance->add_admin_notice( $message );
		}

		/** Debug level logging, only when WP_DEBUG is true. */
		public static function debug( string $message ): void {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						self::log( '[DEBUG] ' . $message );
				}
		}

		/** Log an exception including file and line. */
		public static function log_exception( \Throwable $e ): void {
				$msg = $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						$trace_lines = explode( "\n", $e->getTraceAsString() );
						$trace       = implode( ' | ', array_slice( $trace_lines, 0, 3 ) );
						$msg        .= ' Stack trace: ' . $trace;
				}
				self::log( $msg );
		}

	/** Fallback when writing to the log fails. */
	private function fallback( string $original, string $error ): void {
		$timestamp = gmdate( 'Y-m-d H:i:s' );
		$log_message = "[Nuclear Engagement] [{$timestamp}] {$original} - {$error}";
		
		// Try to use WordPress debug log first if available
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			if ( function_exists( 'wp_debug_log' ) ) {
				wp_debug_log( $log_message );
				return;
			}
		}
		
		// Fallback to system error_log with rate limiting
		$rate_limit_key = 'nuclen_error_log_' . md5( $error );
		$last_logged = get_transient( $rate_limit_key );
		
		if ( ! $last_logged ) {
			// Only log once per hour for the same error to prevent spam
			error_log( $log_message );
			set_transient( $rate_limit_key, time(), HOUR_IN_SECONDS );
			
			// Only show admin notice for critical errors, not routine log failures
			if ( strpos( $error, 'Failed to create log directory' ) !== false ) {
				$this->add_admin_notice( $error );
			}
		}
	}

		/** Determine if logging should be buffered. */
		private function use_buffer(): bool {
				$default = defined( 'NUCLEN_BUFFER_LOGS' ) ? (bool) NUCLEN_BUFFER_LOGS : true;
				return (bool) apply_filters( 'nuclen_enable_log_buffer', $default );
		}

		/** Flush buffered messages to the log file. */
		public static function flush(): void {
				$instance = self::instance();
				if ( empty( $instance->buffer ) ) {
						return;
				}

				$instance->write_messages( $instance->buffer );
				$instance->buffer = array();
		}

		/** Write one or more messages to the log. */
		private function write_messages( array $messages ): void {
				$info       = self::get_log_file_info();
		$log_folder = $info['dir'];
		$log_file   = $info['path'];
		$max_size   = defined( 'NUCLEN_LOG_FILE_MAX_SIZE' ) ? NUCLEN_LOG_FILE_MAX_SIZE : MB_IN_BYTES;

		if ( ! file_exists( $log_folder ) ) {
			if ( ! wp_mkdir_p( $log_folder ) ) {
				foreach ( $messages as $msg ) {
					$this->fallback( $msg, 'Failed to create log directory: ' . $log_folder );
				}
				return;
			}
		}

		if ( ! is_writable( $log_folder ) ) {
			foreach ( $messages as $msg ) {
				$this->fallback( $msg, 'Log directory not writable: ' . $log_folder );
			}
			return;
		}

		if ( file_exists( $log_file ) && ! is_writable( $log_file ) ) {
			foreach ( $messages as $msg ) {
				$this->fallback( $msg, 'Log file not writable: ' . $log_file );
			}
			return;
		}

		if ( file_exists( $log_file ) && filesize( $log_file ) > $max_size ) {
			$timestamped = $log_folder . '/log-' . gmdate( 'Y-m-d-His' ) . '.txt';
			$renamed     = rename( $log_file, $timestamped );
			if ( ! $renamed ) {
				foreach ( $messages as $msg ) {
					$this->fallback( $msg, 'Failed to rotate log file: ' . $timestamped );
				}
			}
		}

		$data = '';
		if ( ! file_exists( $log_file ) ) {
			$timestamp = gmdate( 'Y-m-d H:i:s' );
			$data     .= "[{$timestamp}] Log file created\n";
		}

		foreach ( $messages as $msg ) {
			$timestamp = gmdate( 'Y-m-d H:i:s' );
			$data     .= "[{$timestamp}] {$msg}\n";
		}

		if ( file_put_contents( $log_file, $data, FILE_APPEND | LOCK_EX ) === false ) {
			foreach ( $messages as $msg ) {
				$this->fallback( $msg, 'Failed to write to log file: ' . $log_file );
			}
		}
	}

		/** Append a message to the plugin log file. */
		public static function log( string $message ): void {
				if ( $message === '' ) {
						return;
				}

				$instance = self::instance();

				$message = wp_strip_all_tags( $message );
				if ( strlen( $message ) > 1000 ) {
						$message = substr( $message, 0, 1000 ) . '...';
				}

				if ( $instance->use_buffer() ) {
						$instance->buffer[] = $message;
						if ( ! $instance->shutdown_registered ) {
								register_shutdown_function( array( self::class, 'flush' ) );
								$instance->shutdown_registered = true;
						}
						return;
				}

				$instance->write_messages( array( $message ) );
		}

		/**
		 * Clean up old log files.
		 * 
		 * Removes log files older than 30 days by default.
		 */
		public static function cleanup_old_logs(): void {
			$instance = self::instance();
			$info = self::get_log_file_info();
			$log_folder = $info['dir'];
			
			if ( ! is_dir( $log_folder ) ) {
				return;
			}
			
			// Get the retention period (default: 30 days)
			$retention_days = defined( 'NUCLEN_LOG_RETENTION_DAYS' ) ? NUCLEN_LOG_RETENTION_DAYS : 30;
			$cutoff_time = time() - ( $retention_days * DAY_IN_SECONDS );
			
			// Find and delete old log files
			$log_files = glob( $log_folder . '/log*.txt' );
			if ( empty( $log_files ) ) {
				return;
			}
			
			foreach ( $log_files as $file ) {
				// Skip the current log file
				if ( $file === $info['path'] ) {
					continue;
				}
				
				// Check file modification time
				$file_time = filemtime( $file );
				if ( $file_time !== false && $file_time < $cutoff_time ) {
					// Delete old log file
					if ( ! @unlink( $file ) ) {
						self::debug( 'Failed to delete old log file: ' . basename( $file ) );
					} else {
						self::debug( 'Deleted old log file: ' . basename( $file ) );
					}
				}
			}
		}
}
