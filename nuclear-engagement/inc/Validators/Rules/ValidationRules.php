<?php
/**
 * ValidationRules.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Validators_Rules
 */

declare(strict_types=1);

namespace NuclearEngagement\Validators\Rules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validation rules separated for better maintainability
 */
class ValidationRules {
	
	/**
	 * Validate string type.
	 *
	 * @param mixed $value Value to validate.
	 * @param string $field Field name.
	 * @return string|null Error message or null.
	 */
	public function validate_string( $value, string $field ): ?string {
		if ( ! is_string( $value ) ) {
			return sprintf( __( 'The %s field must be a string.', 'nuclear-engagement' ), $field );
		}
		return null;
	}

	/**
	 * Validate integer type.
	 *
	 * @param mixed $value Value to validate.
	 * @param string $field Field name.
	 * @return string|null Error message or null.
	 */
	public function validate_integer( $value, string $field ): ?string {
		if ( ! is_numeric( $value ) || (int) $value != $value ) {
			return sprintf( __( 'The %s field must be an integer.', 'nuclear-engagement' ), $field );
		}
		return null;
	}

	/**
	 * Validate numeric type.
	 *
	 * @param mixed $value Value to validate.
	 * @param string $field Field name.
	 * @return string|null Error message or null.
	 */
	public function validate_numeric( $value, string $field ): ?string {
		if ( ! is_numeric( $value ) ) {
			return sprintf( __( 'The %s field must be numeric.', 'nuclear-engagement' ), $field );
		}
		return null;
	}

	/**
	 * Validate boolean type.
	 *
	 * @param mixed $value Value to validate.
	 * @param string $field Field name.
	 * @return string|null Error message or null.
	 */
	public function validate_boolean( $value, string $field ): ?string {
		if ( ! is_bool( $value ) && ! in_array( $value, array( '0', '1', 0, 1, 'true', 'false' ), true ) ) {
			return sprintf( __( 'The %s field must be a boolean.', 'nuclear-engagement' ), $field );
		}
		return null;
	}

	/**
	 * Validate array type.
	 *
	 * @param mixed $value Value to validate.
	 * @param string $field Field name.
	 * @return string|null Error message or null.
	 */
	public function validate_array( $value, string $field ): ?string {
		if ( ! is_array( $value ) ) {
			return sprintf( __( 'The %s field must be an array.', 'nuclear-engagement' ), $field );
		}
		return null;
	}

	/**
	 * Validate email format.
	 *
	 * @param mixed $value Value to validate.
	 * @param string $field Field name.
	 * @return string|null Error message or null.
	 */
	public function validate_email( $value, string $field ): ?string {
		if ( ! is_email( $value ) ) {
			return sprintf( __( 'The %s field must be a valid email.', 'nuclear-engagement' ), $field );
		}
		return null;
	}

	/**
	 * Validate URL format.
	 *
	 * @param mixed $value Value to validate.
	 * @param string $field Field name.
	 * @return string|null Error message or null.
	 */
	public function validate_url( $value, string $field ): ?string {
		if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
			return sprintf( __( 'The %s field must be a valid URL.', 'nuclear-engagement' ), $field );
		}
		return null;
	}

	/**
	 * Validate minimum constraint.
	 *
	 * @param mixed $value Value to validate.
	 * @param int $min Minimum value.
	 * @param string $field Field name.
	 * @return string|null Error message or null.
	 */
	public function validate_min( $value, int $min, string $field ): ?string {
		if ( is_string( $value ) && strlen( $value ) < $min ) {
			return sprintf( __( 'The %1$s field must be at least %2$d characters.', 'nuclear-engagement' ), $field, $min );
		}
		if ( is_numeric( $value ) && $value < $min ) {
			return sprintf( __( 'The %1$s field must be at least %2$d.', 'nuclear-engagement' ), $field, $min );
		}
		if ( is_array( $value ) && count( $value ) < $min ) {
			return sprintf( __( 'The %1$s field must have at least %2$d items.', 'nuclear-engagement' ), $field, $min );
		}
		return null;
	}

	/**
	 * Validate maximum constraint.
	 *
	 * @param mixed $value Value to validate.
	 * @param int $max Maximum value.
	 * @param string $field Field name.
	 * @return string|null Error message or null.
	 */
	public function validate_max( $value, int $max, string $field ): ?string {
		if ( is_string( $value ) && strlen( $value ) > $max ) {
			return sprintf( __( 'The %1$s field must not exceed %2$d characters.', 'nuclear-engagement' ), $field, $max );
		}
		if ( is_numeric( $value ) && $value > $max ) {
			return sprintf( __( 'The %1$s field must not exceed %2$d.', 'nuclear-engagement' ), $field, $max );
		}
		if ( is_array( $value ) && count( $value ) > $max ) {
			return sprintf( __( 'The %1$s field must not have more than %2$d items.', 'nuclear-engagement' ), $field, $max );
		}
		return null;
	}

	/**
	 * Validate value is in allowed list.
	 *
	 * @param mixed $value Value to validate.
	 * @param string $allowed_values Comma-separated allowed values.
	 * @param string $field Field name.
	 * @return string|null Error message or null.
	 */
	public function validate_in( $value, string $allowed_values, string $field ): ?string {
		$allowed = explode( ',', $allowed_values );
		if ( ! in_array( $value, $allowed, true ) ) {
			return sprintf( __( 'The %1$s field must be one of: %2$s.', 'nuclear-engagement' ), $field, implode( ', ', $allowed ) );
		}
		return null;
	}

	/**
	 * Validate regex pattern.
	 *
	 * @param mixed $value Value to validate.
	 * @param string $pattern Regex pattern.
	 * @param string $field Field name.
	 * @return string|null Error message or null.
	 */
	public function validate_regex( $value, string $pattern, string $field ): ?string {
		if ( ! preg_match( $pattern, $value ) ) {
			return sprintf( __( 'The %s field format is invalid.', 'nuclear-engagement' ), $field );
		}
		return null;
	}

	/**
	 * Validate post exists.
	 *
	 * @param mixed $value Value to validate.
	 * @param string $field Field name.
	 * @return string|null Error message or null.
	 */
	public function validate_post_exists( $value, string $field ): ?string {
		if ( ! get_post( $value ) ) {
			return sprintf( __( 'The selected %s is invalid.', 'nuclear-engagement' ), $field );
		}
		return null;
	}

	/**
	 * Validate user exists.
	 *
	 * @param mixed $value Value to validate.
	 * @param string $field Field name.
	 * @return string|null Error message or null.
	 */
	public function validate_user_exists( $value, string $field ): ?string {
		if ( ! get_user_by( 'id', $value ) ) {
			return sprintf( __( 'The selected %s is invalid.', 'nuclear-engagement' ), $field );
		}
		return null;
	}
}