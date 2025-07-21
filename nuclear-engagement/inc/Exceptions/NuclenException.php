<?php
declare(strict_types=1);

namespace NuclearEngagement\Exceptions;

/**
 * Base exception class for all Nuclear Engagement exceptions
 */
class NuclenException extends \Exception {
	protected array $context      = array();
	protected string $userMessage = '';
	protected bool $shouldLog     = true;
	protected string $severity    = 'error';

	public function __construct(
		string $message = '',
		int $code = 0,
		?\Throwable $previous = null,
		array $context = array()
	) {
		parent::__construct( $message, $code, $previous );
		$this->context     = $context;
		$this->userMessage = $this->getDefaultUserMessage();
	}

	public function getContext(): array {
		return $this->context;
	}

	public function withContext( array $context ): self {
		$this->context = array_merge( $this->context, $context );
		return $this;
	}

	public function getUserMessage(): string {
		return $this->userMessage ?: $this->getDefaultUserMessage();
	}

	public function setUserMessage( string $message ): self {
		$this->userMessage = $message;
		return $this;
	}

	protected function getDefaultUserMessage(): string {
		return __( 'An error occurred. Please try again later.', 'nuclear-engagement' );
	}

	public function shouldLog(): bool {
		return $this->shouldLog;
	}

	public function getSeverity(): string {
		return $this->severity;
	}

	public function toArray(): array {
		return array(
			'type'         => get_class( $this ),
			'message'      => $this->getMessage(),
			'code'         => $this->getCode(),
			'file'         => $this->getFile(),
			'line'         => $this->getLine(),
			'context'      => $this->context,
			'user_message' => $this->getUserMessage(),
			'severity'     => $this->severity,
			'trace'        => $this->getTraceAsString(),
		);
	}
}
