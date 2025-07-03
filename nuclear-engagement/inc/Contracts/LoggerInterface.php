<?php
declare(strict_types=1);
/**
 * File: inc/Contracts/LoggerInterface.php
 *
 * Logger interface for dependency injection.
 *
 * @package NuclearEngagement\Contracts
 */

namespace NuclearEngagement\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defines logging contract for the plugin.
 */
interface LoggerInterface {
	
	/**
	 * Log a message.
	 *
	 * @param string $level   Log level (debug, info, warning, error).
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 */
	public function log( string $level, string $message, array $context = array() ): void;
	
	/**
	 * Log debug message.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 */
	public function debug( string $message, array $context = array() ): void;
	
	/**
	 * Log info message.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 */
	public function info( string $message, array $context = array() ): void;
	
	/**
	 * Log warning message.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 */
	public function warning( string $message, array $context = array() ): void;
	
	/**
	 * Log error message.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 */
	public function error( string $message, array $context = array() ): void;
	
	/**
	 * Log exception.
	 *
	 * @param \Throwable $exception Exception to log.
	 * @param array      $context   Additional context data.
	 */
	public function exception( \Throwable $exception, array $context = array() ): void;
}