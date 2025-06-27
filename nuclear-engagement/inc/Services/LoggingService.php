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

	/** Retrieve the active instance. */
	private static function instance(): self {
	    if ( null === self::$instance ) {
	        throw new \RuntimeException( 'LoggingService not initialized' );
	    }
	    return self::$instance;
	}

	/** Forward static calls to the current instance. */
	public static function __callStatic( string $name, array $arguments ) {
	    $instance = self::instance();
	    if ( ! method_exists( $instance, $name ) ) {
	        throw new \BadMethodCallException( "Method {$name} does not exist" );
	    }
	    return $instance->$name( ...$arguments );
	}

	/** Get directory, path and URL for the log file. */
	public function get_log_file_info(): array {
	    $upload_dir = wp_upload_dir();

	    if ( ! empty( $upload_dir['error'] ) ) {
	        error_log( '[Nuclear Engagement] ' . $upload_dir['error'] );
	        $this->add_admin_notice( 'Uploads directory unavailable. Using plugin directory for logs.' );
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
	public function notify_admin( string $message ): void {
	    $this->add_admin_notice( $message );
	}

	/** Debug level logging, only when WP_DEBUG is true. */
	public function debug( string $message ): void {
	    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	        $this->log( '[DEBUG] ' . $message );
	    }
	}

	/** Log an exception including file and line. */
	public function log_exception( \Throwable $e ): void {
	    $msg = $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
	    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	        $trace_lines = explode( "\n", $e->getTraceAsString() );
	        $trace       = implode( ' | ', array_slice( $trace_lines, 0, 3 ) );
	        $msg        .= ' Stack trace: ' . $trace;
	    }
	    $this->log( $msg );
	}

	/** Fallback when writing to the log fails. */
	private function fallback( string $original, string $error ): void {
	    $timestamp = gmdate( 'Y-m-d H:i:s' );
	    error_log( "[Nuclear Engagement] [{$timestamp}] {$original} - {$error}" );
	    $this->add_admin_notice( $error );
	}

	/** Determine if logging should be buffered. */
	private function use_buffer(): bool {
	    $default = defined( 'NUCLEN_BUFFER_LOGS' ) ? (bool) NUCLEN_BUFFER_LOGS : true;
	    return (bool) apply_filters( 'nuclen_enable_log_buffer', $default );
	}

	/** Flush buffered messages to the log file. */
	public function flush(): void {
	    if ( empty( $this->buffer ) ) {
	        return;
	    }

	    $this->write_messages( $this->buffer );
	    $this->buffer = array();
	}

	/** Write one or more messages to the log. */
	private function write_messages( array $messages ): void {
	    $info       = $this->get_log_file_info();
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
	public function log( string $message ): void {
	    if ( $message === '' ) {
	        return;
	    }

	    $message = wp_strip_all_tags( $message );
	    if ( strlen( $message ) > 1000 ) {
	        $message = substr( $message, 0, 1000 ) . '...';
	    }

	    if ( $this->use_buffer() ) {
	        $this->buffer[] = $message;
	        if ( ! $this->shutdown_registered ) {
	            register_shutdown_function( array( self::class, 'flush' ) );
	            $this->shutdown_registered = true;
	        }
	        return;
	    }

	    $this->write_messages( array( $message ) );
	}
}
