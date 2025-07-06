<?php
/**
 * Validator.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Validators
 */

declare(strict_types=1);
/**
 * File: inc/Validators/Validator.php
 *
 * Input validation implementation.
 *
 * @package NuclearEngagement\Validators
 */

namespace NuclearEngagement\Validators;

use NuclearEngagement\Contracts\ValidatorInterface;
use NuclearEngagement\Contracts\ValidationResult;
use NuclearEngagement\Validators\Rules\ValidationRules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Input validator implementation - simplified for better maintainability.
 */
class Validator implements ValidatorInterface {

	/** @var array */
	private array $custom_rules = array();

	/** @var ValidationRules */
	private ValidationRules $validation_rules;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->validation_rules = new ValidationRules();
	}

	/**
	 * Validate data against rules.
	 *
	 * @param array $data  Data to validate.
	 * @param array $rules Validation rules.
	 * @return ValidationResult Validation result.
	 */
	public function validate( array $data, array $rules ): ValidationResult {
		$errors         = array();
		$validated_data = array();

		foreach ( $rules as $field => $rule_string ) {
			$field_rules = $this->parse_rules( $rule_string );
			$value       = $data[ $field ] ?? null;

			$field_errors = $this->validate_field( $field, $value, $field_rules, $data );

			if ( ! empty( $field_errors ) ) {
				$errors[ $field ] = $field_errors;
			} else {
				$validated_data[ $field ] = $this->transform_value( $value, $field_rules );
			}
		}

		$is_valid = empty( $errors );

		return new ValidationResult( $is_valid, $errors, $validated_data );
	}

	/**
	 * Add custom validation rule.
	 *
	 * @param string   $name     Rule name.
	 * @param callable $callback Rule callback.
	 */
	public function add_rule( string $name, callable $callback ): void {
		$this->custom_rules[ $name ] = $callback;
	}

	/**
	 * Parse rule string into array of rules.
	 *
	 * @param string $rule_string Rule string.
	 * @return array Parsed rules.
	 */
	private function parse_rules( string $rule_string ): array {
		$rules      = array();
		$rule_parts = explode( '|', $rule_string );

		foreach ( $rule_parts as $rule_part ) {
			if ( strpos( $rule_part, ':' ) !== false ) {
				list( $rule_name, $rule_value ) = explode( ':', $rule_part, 2 );
				$rules[ $rule_name ]            = $rule_value;
			} else {
				$rules[ $rule_part ] = true;
			}
		}

		return $rules;
	}

	/**
	 * Validate single field.
	 *
	 * @param string $field       Field name.
	 * @param mixed  $value       Field value.
	 * @param array  $rules       Field rules.
	 * @param array  $all_data    All form data.
	 * @return array Field errors.
	 */
	private function validate_field( string $field, $value, array $rules, array $all_data ): array {
		$errors = array();

		// Required rule check.
		if ( isset( $rules['required'] ) && $this->is_empty( $value ) ) {
			/* translators: %s: placeholder description */
			$errors[] = sprintf( __( 'The %s field is required.', 'nuclear-engagement' ), $field );
			return $errors; // Stop validation if required and empty.
		}

		// Skip other validations if optional and empty.
		if ( ! isset( $rules['required'] ) && $this->is_empty( $value ) ) {
			return $errors;
		}

		// Apply validation rules.
		foreach ( $rules as $rule_name => $rule_value ) {
			if ( $rule_name === 'required' ) {
				continue; // Already handled.
			}

			$error = $this->apply_rule( $field, $value, $rule_name, $rule_value, $all_data );
			if ( $error ) {
				$errors[] = $error;
			}
		}

		return $errors;
	}

	/**
	 * Apply single validation rule - simplified using ValidationRules class.
	 *
	 * @param string $field      Field name.
	 * @param mixed  $value      Field value.
	 * @param string $rule_name  Rule name.
	 * @param mixed  $rule_value Rule value.
	 * @param array  $all_data   All form data.
	 * @return string|null Error message or null.
	 */
	private function apply_rule( string $field, $value, string $rule_name, $rule_value, array $all_data ): ?string {
		// Use dedicated validation rules class for better organization
		switch ( $rule_name ) {
			case 'string':
				return $this->validation_rules->validate_string( $value, $field );
			case 'integer':
				return $this->validation_rules->validate_integer( $value, $field );
			case 'numeric':
				return $this->validation_rules->validate_numeric( $value, $field );
			case 'boolean':
				return $this->validation_rules->validate_boolean( $value, $field );
			case 'array':
				return $this->validation_rules->validate_array( $value, $field );
			case 'email':
				return $this->validation_rules->validate_email( $value, $field );
			case 'url':
				return $this->validation_rules->validate_url( $value, $field );
			case 'min':
				return $this->validation_rules->validate_min( $value, (int) $rule_value, $field );
			case 'max':
				return $this->validation_rules->validate_max( $value, (int) $rule_value, $field );
			case 'in':
				return $this->validation_rules->validate_in( $value, $rule_value, $field );
			case 'regex':
				return $this->validation_rules->validate_regex( $value, $rule_value, $field );
			case 'post_exists':
				return $this->validation_rules->validate_post_exists( $value, $field );
			case 'user_exists':
				return $this->validation_rules->validate_user_exists( $value, $field );
			default:
				return $this->handle_custom_rule( $rule_name, $value, $rule_value, $field, $all_data );
		}
	}

	/**
	 * Handle custom validation rules.
	 *
	 * @param string $rule_name  Rule name.
	 * @param mixed  $value      Field value.
	 * @param mixed  $rule_value Rule value.
	 * @param string $field      Field name.
	 * @param array  $all_data   All form data.
	 * @return string|null Error message or null.
	 */
	private function handle_custom_rule( string $rule_name, $value, $rule_value, string $field, array $all_data ): ?string {
		if ( isset( $this->custom_rules[ $rule_name ] ) ) {
			$callback = $this->custom_rules[ $rule_name ];
			$result = $callback( $value, $rule_value, $field, $all_data );

			if ( $result !== true && is_string( $result ) ) {
				return $result;
			}
		}
		return null;
	}

	/**
	 * Check if value is empty.
	 *
	 * @param mixed $value Value to check.
	 * @return bool Whether value is empty.
	 */
	private function is_empty( $value ): bool {
		if ( $value === null || $value === '' ) {
			return true;
		}

		if ( is_array( $value ) && empty( $value ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Transform value based on rules.
	 *
	 * @param mixed $value Value to transform.
	 * @param array $rules Field rules.
	 * @return mixed Transformed value.
	 */
	private function transform_value( $value, array $rules ) {
		if ( isset( $rules['integer'] ) ) {
			return (int) $value;
		}

		if ( isset( $rules['numeric'] ) ) {
			return is_float( $value ) ? (float) $value : (int) $value;
		}

		if ( isset( $rules['boolean'] ) ) {
			return in_array( $value, array( '1', 1, 'true', true ), true );
		}

		if ( isset( $rules['string'] ) ) {
			return (string) $value;
		}

		return $value;
	}
}
