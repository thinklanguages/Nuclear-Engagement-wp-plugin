<?php
/**
 * ConcurrencyException.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Exceptions
 */

declare(strict_types=1);

namespace NuclearEngagement\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exception for concurrency and locking errors
 */
class ConcurrencyException extends BaseException {

	/** @var string */
	private string $resource_id = '';

	/** @var int */
	private int $retry_after_seconds = 0;

	public function __construct(
		string $message,
		string $resource_id = '',
		int $retry_after_seconds = 0,
		int $code = 409,
		?\Throwable $previous = null
	) {
		$this->resource_id         = $resource_id;
		$this->retry_after_seconds = $retry_after_seconds;

		$context = array(
			'resource_id' => $resource_id,
			'retry_after' => $retry_after_seconds,
		);

		parent::__construct( $message, $code, $previous, $context );
		$this->error_code = 'CONCURRENCY_ERROR';
	}

	/**
	 * Create for lock acquisition failure
	 */
	public static function lockFailed( string $resource_id, int $retry_after = 5 ): self {
		return new self(
			sprintf( 'Failed to acquire lock for resource: %s', $resource_id ),
			$resource_id,
			$retry_after
		);
	}

	/**
	 * Create for resource already in use
	 */
	public static function resourceBusy( string $resource_id, string $details = '' ): self {
		$message = sprintf( 'Resource %s is currently being processed', $resource_id );
		if ( $details ) {
			$message .= ': ' . $details;
		}

		return new self( $message, $resource_id, 10 );
	}

	/**
	 * Get resource ID
	 */
	public function get_resource_id(): string {
		return $this->resource_id;
	}

	/**
	 * Get retry after seconds
	 */
	public function get_retry_after_seconds(): int {
		return $this->retry_after_seconds;
	}

	/**
	 * Get user-friendly message
	 */
	public function get_user_message(): string {
		return __( 'Another operation is currently in progress. Please try again in a few moments.', 'nuclear-engagement' );
	}
}
