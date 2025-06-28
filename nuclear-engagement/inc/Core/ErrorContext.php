<?php
declare(strict_types=1);

namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Comprehensive error context container.
 *
 * @package NuclearEngagement\Core
 * @since 1.0.0
 */
final class ErrorContext {
	private string $error_id;
	private string $message;
	private string $severity;
	private string $category;
	private array $context;
	private string $stack_trace;
	private int $timestamp;
	private ?string $user_message = null;
	private ?string $admin_message = null;
	private array $suggested_actions = [];
	private bool $recovery_attempted = false;
	private bool $recovery_successful = false;
	private array $recovery_details = [];
	private bool $user_notified = false;
	private bool $admin_notified = false;
	private array $notifications_sent = [];
	private ?string $correlation_id = null;
	private array $related_errors = [];
	private array $mitigation_actions = [];
	private int $impact_score = 0;

	/**
	 * Constructor.
	 *
	 * @param string $error_id     Unique error identifier.
	 * @param string $message      Error message.
	 * @param string $severity     Error severity level.
	 * @param string $category     Error category.
	 * @param array  $context      Error context data.
	 * @param string $stack_trace  Stack trace information.
	 * @param int    $timestamp    Error timestamp.
	 */
	public function __construct(
		string $error_id,
		string $message,
		string $severity,
		string $category,
		array $context = [],
		string $stack_trace = '',
		int $timestamp = 0
	) {
		$this->error_id = $error_id;
		$this->message = $message;
		$this->severity = $severity;
		$this->category = $category;
		$this->context = $context;
		$this->stack_trace = $stack_trace;
		$this->timestamp = $timestamp ?: time();
		$this->correlation_id = $this->generate_correlation_id();
		$this->impact_score = $this->calculate_impact_score();
	}

	/**
	 * Get error ID.
	 *
	 * @return string Error ID.
	 */
	public function get_error_id(): string {
		return $this->error_id;
	}

	/**
	 * Get error message.
	 *
	 * @return string Error message.
	 */
	public function get_message(): string {
		return $this->message;
	}

	/**
	 * Get error severity.
	 *
	 * @return string Error severity.
	 */
	public function get_severity(): string {
		return $this->severity;
	}

	/**
	 * Get error category.
	 *
	 * @return string Error category.
	 */
	public function get_category(): string {
		return $this->category;
	}

	/**
	 * Get error context.
	 *
	 * @return array Error context.
	 */
	public function get_context(): array {
		return $this->context;
	}

	/**
	 * Get specific context value.
	 *
	 * @param string $key     Context key.
	 * @param mixed  $default Default value if key not found.
	 * @return mixed Context value.
	 */
	public function get_context_value( string $key, $default = null ) {
		return $this->context[$key] ?? $default;
	}

	/**
	 * Add context data.
	 *
	 * @param string $key   Context key.
	 * @param mixed  $value Context value.
	 */
	public function add_context( string $key, $value ): void {
		$this->context[$key] = $value;
	}

	/**
	 * Get stack trace.
	 *
	 * @return string Stack trace.
	 */
	public function get_stack_trace(): string {
		return $this->stack_trace;
	}

	/**
	 * Get timestamp.
	 *
	 * @return int Timestamp.
	 */
	public function get_timestamp(): int {
		return $this->timestamp;
	}

	/**
	 * Set user-friendly message.
	 *
	 * @param string $message User message.
	 */
	public function set_user_message( string $message ): void {
		$this->user_message = $message;
	}

	/**
	 * Get user-friendly message.
	 *
	 * @return string|null User message.
	 */
	public function get_user_message(): ?string {
		return $this->user_message;
	}

	/**
	 * Set admin message.
	 *
	 * @param string $message Admin message.
	 */
	public function set_admin_message( string $message ): void {
		$this->admin_message = $message;
	}

	/**
	 * Get admin message.
	 *
	 * @return string|null Admin message.
	 */
	public function get_admin_message(): ?string {
		return $this->admin_message;
	}

	/**
	 * Add suggested action.
	 *
	 * @param string $action    Action description.
	 * @param string $type      Action type (user, admin, automatic).
	 * @param array  $metadata  Action metadata.
	 */
	public function add_suggested_action( string $action, string $type = 'user', array $metadata = [] ): void {
		$this->suggested_actions[] = [
			'action' => $action,
			'type' => $type,
			'metadata' => $metadata,
			'added_at' => time(),
		];
	}

	/**
	 * Get suggested actions.
	 *
	 * @param string|null $type Filter by action type.
	 * @return array Suggested actions.
	 */
	public function get_suggested_actions( ?string $type = null ): array {
		if ( $type === null ) {
			return $this->suggested_actions;
		}

		return array_filter( $this->suggested_actions, function( $action ) use ( $type ) {
			return $action['type'] === $type;
		} );
	}

	/**
	 * Set recovery attempted status.
	 *
	 * @param bool $attempted Whether recovery was attempted.
	 */
	public function set_recovery_attempted( bool $attempted ): void {
		$this->recovery_attempted = $attempted;
	}

	/**
	 * Check if recovery was attempted.
	 *
	 * @return bool Whether recovery was attempted.
	 */
	public function was_recovery_attempted(): bool {
		return $this->recovery_attempted;
	}

	/**
	 * Set recovery successful status.
	 *
	 * @param bool $successful Whether recovery was successful.
	 */
	public function set_recovery_successful( bool $successful ): void {
		$this->recovery_successful = $successful;
	}

	/**
	 * Check if recovery was successful.
	 *
	 * @return bool Whether recovery was successful.
	 */
	public function was_recovery_successful(): bool {
		return $this->recovery_successful;
	}

	/**
	 * Add recovery details.
	 *
	 * @param string $step   Recovery step.
	 * @param mixed  $result Recovery result.
	 * @param string $notes  Additional notes.
	 */
	public function add_recovery_detail( string $step, $result, string $notes = '' ): void {
		$this->recovery_details[] = [
			'step' => $step,
			'result' => $result,
			'notes' => $notes,
			'timestamp' => time(),
		];
	}

	/**
	 * Get recovery details.
	 *
	 * @return array Recovery details.
	 */
	public function get_recovery_details(): array {
		return $this->recovery_details;
	}

	/**
	 * Set user notified status.
	 *
	 * @param bool $notified Whether user was notified.
	 */
	public function set_user_notified( bool $notified ): void {
		$this->user_notified = $notified;
	}

	/**
	 * Check if user was notified.
	 *
	 * @return bool Whether user was notified.
	 */
	public function was_user_notified(): bool {
		return $this->user_notified;
	}

	/**
	 * Set admin notified status.
	 *
	 * @param bool $notified Whether admin was notified.
	 */
	public function set_admin_notified( bool $notified ): void {
		$this->admin_notified = $notified;
	}

	/**
	 * Check if admin was notified.
	 *
	 * @return bool Whether admin was notified.
	 */
	public function was_admin_notified(): bool {
		return $this->admin_notified;
	}

	/**
	 * Add notification record.
	 *
	 * @param string $type      Notification type.
	 * @param string $recipient Notification recipient.
	 * @param bool   $success   Whether notification was successful.
	 * @param string $details   Additional details.
	 */
	public function add_notification( string $type, string $recipient, bool $success, string $details = '' ): void {
		$this->notifications_sent[] = [
			'type' => $type,
			'recipient' => $recipient,
			'success' => $success,
			'details' => $details,
			'timestamp' => time(),
		];
	}

	/**
	 * Get notification history.
	 *
	 * @return array Notification history.
	 */
	public function get_notifications(): array {
		return $this->notifications_sent;
	}

	/**
	 * Get correlation ID.
	 *
	 * @return string Correlation ID.
	 */
	public function get_correlation_id(): string {
		return $this->correlation_id;
	}

	/**
	 * Set correlation ID.
	 *
	 * @param string $correlation_id Correlation ID.
	 */
	public function set_correlation_id( string $correlation_id ): void {
		$this->correlation_id = $correlation_id;
	}

	/**
	 * Add related error.
	 *
	 * @param string $error_id Related error ID.
	 * @param string $relation Relationship type.
	 */
	public function add_related_error( string $error_id, string $relation = 'related' ): void {
		$this->related_errors[] = [
			'error_id' => $error_id,
			'relation' => $relation,
			'linked_at' => time(),
		];
	}

	/**
	 * Get related errors.
	 *
	 * @return array Related errors.
	 */
	public function get_related_errors(): array {
		return $this->related_errors;
	}

	/**
	 * Add mitigation action.
	 *
	 * @param string $action     Mitigation action taken.
	 * @param bool   $automated  Whether action was automated.
	 * @param array  $parameters Action parameters.
	 */
	public function add_mitigation_action( string $action, bool $automated = false, array $parameters = [] ): void {
		$this->mitigation_actions[] = [
			'action' => $action,
			'automated' => $automated,
			'parameters' => $parameters,
			'timestamp' => time(),
		];
	}

	/**
	 * Get mitigation actions.
	 *
	 * @return array Mitigation actions.
	 */
	public function get_mitigation_actions(): array {
		return $this->mitigation_actions;
	}

	/**
	 * Get impact score.
	 *
	 * @return int Impact score (0-100).
	 */
	public function get_impact_score(): int {
		return $this->impact_score;
	}

	/**
	 * Check if error is critical.
	 *
	 * @return bool Whether error is critical.
	 */
	public function is_critical(): bool {
		return $this->severity === ErrorManager::SEVERITY_CRITICAL;
	}

	/**
	 * Check if error is security-related.
	 *
	 * @return bool Whether error is security-related.
	 */
	public function is_security_related(): bool {
		return $this->category === ErrorManager::CATEGORY_SECURITY;
	}

	/**
	 * Get error age in seconds.
	 *
	 * @return int Error age.
	 */
	public function get_age(): int {
		return time() - $this->timestamp;
	}

	/**
	 * Check if error requires immediate attention.
	 *
	 * @return bool Whether error requires immediate attention.
	 */
	public function requires_immediate_attention(): bool {
		return $this->is_critical() || 
			   $this->is_security_related() || 
			   $this->impact_score >= 80;
	}

	/**
	 * Serialize error context to array.
	 *
	 * @param bool $include_sensitive Whether to include sensitive data.
	 * @return array Serialized context.
	 */
	public function to_array( bool $include_sensitive = false ): array {
		$data = [
			'error_id' => $this->error_id,
			'message' => $this->message,
			'severity' => $this->severity,
			'category' => $this->category,
			'timestamp' => $this->timestamp,
			'correlation_id' => $this->correlation_id,
			'impact_score' => $this->impact_score,
			'user_message' => $this->user_message,
			'admin_message' => $this->admin_message,
			'suggested_actions' => $this->suggested_actions,
			'recovery_attempted' => $this->recovery_attempted,
			'recovery_successful' => $this->recovery_successful,
			'recovery_details' => $this->recovery_details,
			'notifications_sent' => $this->notifications_sent,
			'related_errors' => $this->related_errors,
			'mitigation_actions' => $this->mitigation_actions,
		];

		if ( $include_sensitive ) {
			$data['context'] = $this->context;
			$data['stack_trace'] = $this->stack_trace;
		} else {
			// Include only non-sensitive context
			$data['context'] = $this->get_safe_context();
		}

		return $data;
	}

	/**
	 * Generate correlation ID for related errors.
	 *
	 * @return string Correlation ID.
	 */
	private function generate_correlation_id(): string {
		$user_id = $this->context['user_context']['user_id'] ?? 0;
		$request_uri = $this->context['request_context']['request_uri'] ?? '';
		$category = $this->category;
		
		return md5( $user_id . $request_uri . $category . date( 'Y-m-d-H' ) );
	}

	/**
	 * Calculate impact score based on error characteristics.
	 *
	 * @return int Impact score (0-100).
	 */
	private function calculate_impact_score(): int {
		$score = 0;

		// Base score by severity
		switch ( $this->severity ) {
			case ErrorManager::SEVERITY_CRITICAL:
				$score += 50;
				break;
			case ErrorManager::SEVERITY_HIGH:
				$score += 35;
				break;
			case ErrorManager::SEVERITY_MEDIUM:
				$score += 20;
				break;
			case ErrorManager::SEVERITY_LOW:
				$score += 5;
				break;
		}

		// Additional score by category
		switch ( $this->category ) {
			case ErrorManager::CATEGORY_SECURITY:
				$score += 30;
				break;
			case ErrorManager::CATEGORY_DATABASE:
				$score += 25;
				break;
			case ErrorManager::CATEGORY_AUTHENTICATION:
				$score += 20;
				break;
			case ErrorManager::CATEGORY_EXTERNAL_API:
				$score += 15;
				break;
		}

		// User context impact
		if ( $this->context['user_context']['is_admin'] ?? false ) {
			$score += 10;
		}

		// Request context impact
		if ( $this->context['request_context']['is_ajax'] ?? false ) {
			$score += 5;
		}

		return min( 100, $score );
	}

	/**
	 * Get context data safe for logging/display.
	 *
	 * @return array Safe context data.
	 */
	private function get_safe_context(): array {
		$safe_keys = [
			'timestamp',
			'error_type',
			'file',
			'line',
			'user_context' => [ 'user_id', 'is_admin', 'is_logged_in' ],
			'request_context' => [ 'request_method', 'is_ajax', 'is_rest', 'is_admin' ],
			'system_context' => [ 'php_version', 'memory_usage', 'memory_peak' ],
			'wordpress_context' => [ 'wp_version', 'wp_debug', 'multisite' ],
		];

		$safe_context = [];
		foreach ( $safe_keys as $key => $subkeys ) {
			if ( is_array( $subkeys ) ) {
				if ( isset( $this->context[$key] ) ) {
					$safe_context[$key] = [];
					foreach ( $subkeys as $subkey ) {
						if ( isset( $this->context[$key][$subkey] ) ) {
							$safe_context[$key][$subkey] = $this->context[$key][$subkey];
						}
					}
				}
			} else {
				if ( isset( $this->context[$subkeys] ) ) {
					$safe_context[$subkeys] = $this->context[$subkeys];
				}
			}
		}

		return $safe_context;
	}
}