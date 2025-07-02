<?php
declare(strict_types=1);
/**
 * File: inc/Services/Implementation/StructuredLogger.php
 *
 * Structured logger implementation.
 *
 * @package NuclearEngagement\Services\Implementation
 */

namespace NuclearEngagement\Services\Implementation;

use NuclearEngagement\Contracts\LoggerInterface;
use NuclearEngagement\Core\Environment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Structured logger with PSR-3 compatible interface.
 */
class StructuredLogger implements LoggerInterface {
	
	/** @var string */
	private string $log_level;
	
	/** @var array */
	private array $log_levels = array(
		'debug' => 100,
		'info' => 200,
		'warning' => 300,
		'error' => 400,
	);
	
	/** @var string */
	private string $log_file;
	
	/** @var int */
	private int $max_file_size;
	
	/** @var bool */
	private bool $enable_debug_logging;
	
	public function __construct() {
		$this->log_level = Environment::get_config( 'log_level', 'error' );
		$this->enable_debug_logging = Environment::get_config( 'enable_debug_logging', false );
		$this->max_file_size = defined( 'NUCLEN_LOG_FILE_MAX_SIZE' ) ? NUCLEN_LOG_FILE_MAX_SIZE : 10 * MB_IN_BYTES;
		
		$upload_dir = wp_upload_dir();
		$log_dir = $upload_dir['basedir'] . '/nuclear-engagement/logs';
		$this->log_file = $log_dir . '/nuclen-' . gmdate( 'Y-m-d' ) . '.log';
		
		$this->ensure_log_directory( $log_dir );
	}
	
	/**
	 * Log a message.
	 *
	 * @param string $level   Log level.
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 */
	public function log( string $level, string $message, array $context = array() ): void {
		if ( ! $this->should_log( $level ) ) {
			return;
		}
		
		$log_entry = $this->format_log_entry( $level, $message, $context );
		
		$this->write_to_file( $log_entry );
		
		// Also log to WordPress debug log if available
		if ( $this->enable_debug_logging && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( $log_entry );
		}
	}
	
	/**
	 * Log debug message.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 */
	public function debug( string $message, array $context = array() ): void {
		$this->log( 'debug', $message, $context );
	}
	
	/**
	 * Log info message.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 */
	public function info( string $message, array $context = array() ): void {
		$this->log( 'info', $message, $context );
	}
	
	/**
	 * Log warning message.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 */
	public function warning( string $message, array $context = array() ): void {
		$this->log( 'warning', $message, $context );
	}
	
	/**
	 * Log error message.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 */
	public function error( string $message, array $context = array() ): void {
		$this->log( 'error', $message, $context );
	}
	
	/**
	 * Log exception.
	 *
	 * @param \Throwable $exception Exception to log.
	 * @param array      $context   Additional context data.
	 */
	public function exception( \Throwable $exception, array $context = array() ): void {
		$context = array_merge( $context, array(
			'exception_class' => get_class( $exception ),
			'exception_file' => $exception->getFile(),
			'exception_line' => $exception->getLine(),
			'exception_trace' => $exception->getTraceAsString(),
		) );
		
		$this->error( $exception->getMessage(), $context );
	}
	
	/**
	 * Check if message should be logged based on level.
	 *
	 * @param string $level Log level.
	 * @return bool Whether to log.
	 */
	private function should_log( string $level ): bool {
		$level_value = $this->log_levels[ $level ] ?? 0;
		$min_level_value = $this->log_levels[ $this->log_level ] ?? 0;
		
		return $level_value >= $min_level_value;
	}
	
	/**
	 * Format log entry as JSON.
	 *
	 * @param string $level   Log level.
	 * @param string $message Log message.
	 * @param array  $context Context data.
	 * @return string Formatted log entry.
	 */
	private function format_log_entry( string $level, string $message, array $context ): string {
		$entry = array(
			'timestamp' => gmdate( 'c' ),
			'level' => strtoupper( $level ),
			'message' => $message,
			'context' => $context,
			'environment' => Environment::get_environment(),
			'memory_usage' => memory_get_usage( true ),
			'peak_memory' => memory_get_peak_usage( true ),
		);
		
		// Add request information if available
		if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
			$entry['request'] = array(
				'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
				'uri' => $_SERVER['REQUEST_URI'],
				'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
				'ip' => $this->get_client_ip(),
			);
		}
		
		// Add user information if available
		if ( function_exists( 'get_current_user_id' ) ) {
			$user_id = get_current_user_id();
			if ( $user_id ) {
				$entry['user_id'] = $user_id;
			}
		}
		
		return wp_json_encode( $entry, JSON_UNESCAPED_SLASHES ) . PHP_EOL;
	}
	
	/**
	 * Write log entry to file.
	 *
	 * @param string $log_entry Log entry.
	 */
	private function write_to_file( string $log_entry ): void {
		// Rotate log file if too large
		if ( file_exists( $this->log_file ) && filesize( $this->log_file ) > $this->max_file_size ) {
			$this->rotate_log_file();
		}
		
		// Write to file with locking
		$result = file_put_contents( $this->log_file, $log_entry, FILE_APPEND | LOCK_EX );
		
		if ( $result === false ) {
			// Fallback to error_log
			error_log( '[Nuclear Engagement] Failed to write to log file: ' . $this->log_file );
			error_log( $log_entry );
		}
	}
	
	/**
	 * Ensure log directory exists.
	 *
	 * @param string $log_dir Log directory path.
	 */
	private function ensure_log_directory( string $log_dir ): void {
		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}
		
		// Add .htaccess for security
		$htaccess_file = $log_dir . '/.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			file_put_contents( $htaccess_file, "Order deny,allow\nDeny from all\n" );
		}
		
		// Add index.php for security
		$index_file = $log_dir . '/index.php';
		if ( ! file_exists( $index_file ) ) {
			file_put_contents( $index_file, "<?php\n// Silence is golden.\n" );
		}
	}
	
	/**
	 * Rotate log file when it gets too large.
	 */
	private function rotate_log_file(): void {
		$rotated_file = $this->log_file . '.' . time();
		
		if ( rename( $this->log_file, $rotated_file ) ) {
			// Keep only last 5 rotated files
			$log_dir = dirname( $this->log_file );
			$base_name = basename( $this->log_file );
			$files = glob( $log_dir . '/' . $base_name . '.*' );
			
			if ( count( $files ) > 5 ) {
				// Sort by modification time and remove oldest
				usort( $files, function( $a, $b ) {
					return filemtime( $a ) - filemtime( $b );
				} );
				
				$files_to_remove = array_slice( $files, 0, count( $files ) - 5 );
				foreach ( $files_to_remove as $file ) {
					unlink( $file );
				}
			}
		}
	}
	
	/**
	 * Get client IP address.
	 *
	 * @return string Client IP.
	 */
	private function get_client_ip(): string {
		$ip_keys = array(
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR'
		);
		
		foreach ( $ip_keys as $key ) {
			if ( array_key_exists( $key, $_SERVER ) === true ) {
				foreach ( explode( ',', $_SERVER[ $key ] ) as $ip ) {
					$ip = trim( $ip );
					
					if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false ) {
						return $ip;
					}
				}
			}
		}
		
		return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
	}
}