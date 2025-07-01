<?php
declare(strict_types=1);
/**
 * File: inc/Contracts/ValidatorInterface.php
 *
 * Validator interface for input validation.
 *
 * @package NuclearEngagement\Contracts
 */

namespace NuclearEngagement\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defines validation contract for input validation.
 */
interface ValidatorInterface {
	
	/**
	 * Validate data against rules.
	 *
	 * @param array $data  Data to validate.
	 * @param array $rules Validation rules.
	 * @return ValidationResult Validation result.
	 */
	public function validate( array $data, array $rules ): ValidationResult;
	
	/**
	 * Add custom validation rule.
	 *
	 * @param string   $name     Rule name.
	 * @param callable $callback Rule callback.
	 */
	public function add_rule( string $name, callable $callback ): void;
}

/**
 * Validation result class.
 */
class ValidationResult {
	
	/** @var bool */
	private bool $is_valid;
	
	/** @var array */
	private array $errors;
	
	/** @var array */
	private array $validated_data;
	
	public function __construct( bool $is_valid, array $errors = array(), array $validated_data = array() ) {
		$this->is_valid = $is_valid;
		$this->errors = $errors;
		$this->validated_data = $validated_data;
	}
	
	public function is_valid(): bool {
		return $this->is_valid;
	}
	
	public function get_errors(): array {
		return $this->errors;
	}
	
	public function get_validated_data(): array {
		return $this->validated_data;
	}
	
	public function has_errors(): bool {
		return ! empty( $this->errors );
	}
	
	public function get_first_error(): ?string {
		return $this->errors[0] ?? null;
	}
}