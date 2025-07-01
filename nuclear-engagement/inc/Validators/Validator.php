<?php
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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Input validator implementation.
 */
class Validator implements ValidatorInterface {
	
	/** @var array */
	private array $custom_rules = array();
	
	/**
	 * Validate data against rules.
	 *
	 * @param array $data  Data to validate.
	 * @param array $rules Validation rules.
	 * @return ValidationResult Validation result.
	 */
	public function validate( array $data, array $rules ): ValidationResult {
		$errors = array();
		$validated_data = array();
		
		foreach ( $rules as $field => $rule_string ) {
			$field_rules = $this->parse_rules( $rule_string );
			$value = $data[ $field ] ?? null;
			
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
		$rules = array();
		$rule_parts = explode( '|', $rule_string );
		
		foreach ( $rule_parts as $rule_part ) {
			if ( strpos( $rule_part, ':' ) !== false ) {
				list( $rule_name, $rule_value ) = explode( ':', $rule_part, 2 );
				$rules[ $rule_name ] = $rule_value;
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
		
		// Required rule check
		if ( isset( $rules['required'] ) && $this->is_empty( $value ) ) {
			$errors[] = sprintf( __( 'The %s field is required.', 'nuclear-engagement' ), $field );
			return $errors; // Stop validation if required and empty
		}
		
		// Skip other validations if optional and empty
		if ( ! isset( $rules['required'] ) && $this->is_empty( $value ) ) {
			return $errors;
		}
		
		// Apply validation rules
		foreach ( $rules as $rule_name => $rule_value ) {
			if ( $rule_name === 'required' ) {
				continue; // Already handled
			}
			
			$error = $this->apply_rule( $field, $value, $rule_name, $rule_value, $all_data );
			if ( $error ) {
				$errors[] = $error;
			}
		}
		
		return $errors;
	}
	
	/**
	 * Apply single validation rule.
	 *
	 * @param string $field      Field name.
	 * @param mixed  $value      Field value.
	 * @param string $rule_name  Rule name.
	 * @param mixed  $rule_value Rule value.
	 * @param array  $all_data   All form data.
	 * @return string|null Error message or null.
	 */
	private function apply_rule( string $field, $value, string $rule_name, $rule_value, array $all_data ): ?string {
		switch ( $rule_name ) {
			case 'string':
				if ( ! is_string( $value ) ) {
					return sprintf( __( 'The %s field must be a string.', 'nuclear-engagement' ), $field );
				}
				break;
				
			case 'integer':
				if ( ! is_numeric( $value ) || (int) $value != $value ) {
					return sprintf( __( 'The %s field must be an integer.', 'nuclear-engagement' ), $field );
				}
				break;
				
			case 'numeric':
				if ( ! is_numeric( $value ) ) {
					return sprintf( __( 'The %s field must be numeric.', 'nuclear-engagement' ), $field );
				}
				break;
				
			case 'boolean':
				if ( ! is_bool( $value ) && ! in_array( $value, array( '0', '1', 0, 1, 'true', 'false' ), true ) ) {
					return sprintf( __( 'The %s field must be a boolean.', 'nuclear-engagement' ), $field );
				}
				break;
				
			case 'array':
				if ( ! is_array( $value ) ) {
					return sprintf( __( 'The %s field must be an array.', 'nuclear-engagement' ), $field );
				}
				break;
				
			case 'email':
				if ( ! is_email( $value ) ) {
					return sprintf( __( 'The %s field must be a valid email.', 'nuclear-engagement' ), $field );
				}
				break;
				
			case 'url':
				if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
					return sprintf( __( 'The %s field must be a valid URL.', 'nuclear-engagement' ), $field );
				}
				break;
				
			case 'min':
				$min = (int) $rule_value;
				if ( is_string( $value ) && strlen( $value ) < $min ) {
					return sprintf( __( 'The %s field must be at least %d characters.', 'nuclear-engagement' ), $field, $min );
				}
				if ( is_numeric( $value ) && $value < $min ) {
					return sprintf( __( 'The %s field must be at least %d.', 'nuclear-engagement' ), $field, $min );
				}
				if ( is_array( $value ) && count( $value ) < $min ) {
					return sprintf( __( 'The %s field must have at least %d items.', 'nuclear-engagement' ), $field, $min );
				}
				break;
				
			case 'max':
				$max = (int) $rule_value;
				if ( is_string( $value ) && strlen( $value ) > $max ) {
					return sprintf( __( 'The %s field must not exceed %d characters.', 'nuclear-engagement' ), $field, $max );
				}
				if ( is_numeric( $value ) && $value > $max ) {
					return sprintf( __( 'The %s field must not exceed %d.', 'nuclear-engagement' ), $field, $max );
				}
				if ( is_array( $value ) && count( $value ) > $max ) {
					return sprintf( __( 'The %s field must not have more than %d items.', 'nuclear-engagement' ), $field, $max );
				}
				break;
				
			case 'in':
				$allowed_values = explode( ',', $rule_value );
				if ( ! in_array( $value, $allowed_values, true ) ) {
					return sprintf( __( 'The %s field must be one of: %s.', 'nuclear-engagement' ), $field, implode( ', ', $allowed_values ) );
				}
				break;
				
			case 'not_in':
				$forbidden_values = explode( ',', $rule_value );
				if ( in_array( $value, $forbidden_values, true ) ) {
					return sprintf( __( 'The %s field must not be one of: %s.', 'nuclear-engagement' ), $field, implode( ', ', $forbidden_values ) );
				}
				break;
				
			case 'regex':
				if ( ! preg_match( $rule_value, $value ) ) {
					return sprintf( __( 'The %s field format is invalid.', 'nuclear-engagement' ), $field );
				}
				break;
				
			case 'post_exists':
				if ( ! get_post( $value ) ) {
					return sprintf( __( 'The selected %s is invalid.', 'nuclear-engagement' ), $field );
				}
				break;
				
			case 'user_exists':
				if ( ! get_user_by( 'id', $value ) ) {
					return sprintf( __( 'The selected %s is invalid.', 'nuclear-engagement' ), $field );
				}
				break;
				
			default:
				// Check custom rules
				if ( isset( $this->custom_rules[ $rule_name ] ) ) {
					$callback = $this->custom_rules[ $rule_name ];
					$result = $callback( $value, $rule_value, $field, $all_data );
					
					if ( $result !== true && is_string( $result ) ) {
						return $result;
					}
				}
				break;
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