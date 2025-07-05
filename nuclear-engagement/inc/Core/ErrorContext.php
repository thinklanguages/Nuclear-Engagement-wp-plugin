<?php
/**
 * ErrorContext.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Core
 */

declare(strict_types=1);

namespace NuclearEngagement\Core;

use NuclearEngagement\Core\Error\ErrorContext as NewErrorContext;
use NuclearEngagement\Core\Error\ErrorData;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Legacy compatibility wrapper for the refactored ErrorContext.
 *
 * @deprecated Use NuclearEngagement\Core\Error\ErrorContext instead
 * @package NuclearEngagement\Core
 */
final class ErrorContext {
	private NewErrorContext $new_context;

	public function __construct(
		string $error_id,
		string $message,
		string $severity = 'error',
		string $category = 'general',
		array $context = array(),
		string $stack_trace = '',
		?int $timestamp = null
	) {
		$error_data = new ErrorData(
			$error_id,
			$message,
			$severity,
			$category,
			$context,
			$stack_trace,
			null,
			$timestamp
		);

		$this->new_context = new NewErrorContext( $error_data );
	}

	public function getErrorId(): string {
		return $this->new_context->getErrorData()->error_id;
	}

	public function getMessage(): string {
		return $this->new_context->getErrorData()->message;
	}

	public function getSeverity(): string {
		return $this->new_context->getErrorData()->severity;
	}

	public function getCategory(): string {
		return $this->new_context->getErrorData()->category;
	}

	public function getContext(): array {
		return $this->new_context->getErrorData()->context;
	}

	public function toArray(): array {
		return $this->new_context->toArray();
	}

	public function handleError(): void {
		$this->new_context->handleError();
	}

	/**
	 * Get the new error context instance.
	 * For migration purposes.
	 */
	public function getNewContext(): NewErrorContext {
		return $this->new_context;
	}
}
