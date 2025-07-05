<?php
/**
 * BaseException.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Exceptions
 */

declare(strict_types=1);
/**
 * File: inc/Exceptions/BaseException.php
 *
 * Base exception for the plugin.
 *
 * @package NuclearEngagement\Exceptions
 */

namespace NuclearEngagement\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base exception class for Nuclear Engagement plugin.
 */
abstract class BaseException extends \Exception {

	/** @var array */
	protected array $context = array();

	/** @var string */
	protected string $error_code = '';

	public function __construct( string $message = '', int $code = 0, ?\Throwable $previous = null, array $context = array() ) {
		parent::__construct( $message, $code, $previous );
		$this->context = $context;
	}

	/**
	 * Get exception context.
	 *
	 * @return array Context data.
	 */
	public function get_context(): array {
		return $this->context;
	}

	/**
	 * Set exception context.
	 *
	 * @param array $context Context data.
	 */
	public function set_context( array $context ): void {
		$this->context = $context;
	}

	/**
	 * Add context item.
	 *
	 * @param string $key   Context key.
	 * @param mixed  $value Context value.
	 */
	public function add_context( string $key, $value ): void {
		$this->context[ $key ] = $value;
	}

	/**
	 * Get error code.
	 *
	 * @return string Error code.
	 */
	public function get_error_code(): string {
		return $this->error_code;
	}

	/**
	 * Set error code.
	 *
	 * @param string $error_code Error code.
	 */
	public function set_error_code( string $error_code ): void {
		$this->error_code = $error_code;
	}

	/**
	 * Get user-friendly message.
	 *
	 * @return string User-friendly message.
	 */
	public function get_user_message(): string {
		return $this->getMessage();
	}

	/**
	 * Convert to array for logging.
	 *
	 * @return array Exception data.
	 */
	public function to_array(): array {
		return array(
			'class'      => get_class( $this ),
			'message'    => $this->getMessage(),
			'code'       => $this->getCode(),
			'error_code' => $this->error_code,
			'file'       => $this->getFile(),
			'line'       => $this->getLine(),
			'context'    => $this->context,
			'trace'      => $this->getTraceAsString(),
		);
	}
}
