<?php
/**
 * ErrorData.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Core_Error
 */

declare(strict_types=1);

namespace NuclearEngagement\Core\Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Immutable error data container.
 *
 * @package NuclearEngagement\Core\Error
 */
final class ErrorData {
	public readonly string $error_id;
	public readonly string $message;
	public readonly string $severity;
	public readonly string $category;
	public readonly array $context;
	public readonly string $stack_trace;
	public readonly int $timestamp;
	public readonly ?string $correlation_id;

	public function __construct(
		string $error_id,
		string $message,
		string $severity,
		string $category,
		array $context = array(),
		string $stack_trace = '',
		?string $correlation_id = null,
		?int $timestamp = null
	) {
		$this->error_id       = $error_id;
		$this->message        = $message;
		$this->severity       = $severity;
		$this->category       = $category;
		$this->context        = $context;
		$this->stack_trace    = $stack_trace;
		$this->correlation_id = $correlation_id;
		$this->timestamp      = $timestamp ?? time();
	}

	public function toArray(): array {
		return array(
			'error_id'       => $this->error_id,
			'message'        => $this->message,
			'severity'       => $this->severity,
			'category'       => $this->category,
			'context'        => $this->context,
			'stack_trace'    => $this->stack_trace,
			'timestamp'      => $this->timestamp,
			'correlation_id' => $this->correlation_id,
		);
	}
}
