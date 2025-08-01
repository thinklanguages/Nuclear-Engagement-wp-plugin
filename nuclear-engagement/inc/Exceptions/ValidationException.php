<?php
/**
 * ValidationException.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Exceptions
 */

declare(strict_types=1);
/**
 * File: inc/Exceptions/ValidationException.php
 *
 * Validation exception class.
 *
 * @package NuclearEngagement\Exceptions
 */

namespace NuclearEngagement\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exception thrown when validation fails.
 */
class ValidationException extends BaseException {

	/** @var array */
	private array $validation_errors = array();

	public function __construct( array $validation_errors, string $message = '', int $code = 400, ?\Throwable $previous = null ) {
		$this->validation_errors = $validation_errors;

		if ( empty( $message ) ) {
			$message = 'Validation failed';
		}

		parent::__construct( $message, $code, $previous, array( 'validation_errors' => $validation_errors ) );
		$this->error_code = 'VALIDATION_FAILED';
	}

	/**
	 * Get validation errors.
	 *
	 * @return array Validation errors.
	 */
	public function get_validation_errors(): array {
		return $this->validation_errors;
	}

	/**
	 * Get first validation error.
	 *
	 * @return string|null First validation error.
	 */
	public function get_first_error(): ?string {
		return $this->validation_errors[0] ?? null;
	}

	/**
	 * Check if has validation errors.
	 *
	 * @return bool Whether has validation errors.
	 */
	public function has_errors(): bool {
		return ! empty( $this->validation_errors );
	}

	/**
	 * Get user-friendly message.
	 *
	 * @return string User-friendly message.
	 */
	public function get_user_message(): string {
		// If we have a message, use it
		if ( ! empty( $this->getMessage() ) ) {
			return $this->getMessage();
		}
		
		// Otherwise, try to build a message from validation errors
		if ( ! empty( $this->validation_errors ) ) {
			$messages = array();
			foreach ( $this->validation_errors as $key => $value ) {
				if ( is_string( $value ) ) {
					$messages[] = $value;
				} elseif ( $key === 'empty_content' && $value === true ) {
					$messages[] = 'This post appears to be empty. No content can be generated.';
				}
			}
			
			if ( ! empty( $messages ) ) {
				return implode( ', ', $messages );
			}
		}

		return 'Validation failed';
	}
}
