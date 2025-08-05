<?php

namespace NuclearEngagement\Exceptions;

class UserFriendlyException extends \Exception {
	protected int $status_code = 500;

	public function __construct( string $message = '', int $code = 0, int $status_code = 500, \Throwable $previous = null ) {
		parent::__construct( $message, $code, $previous );
		$this->status_code = $status_code;
	}

	public function getStatusCode(): int {
		return $this->status_code;
	}
}
