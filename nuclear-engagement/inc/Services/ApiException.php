<?php
/**
 * ApiException.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Services
 */

declare(strict_types=1);
/**
 * File: includes/Services/ApiException.php
 *
 * Exception thrown when the remote API returns an error.
 */

namespace NuclearEngagement\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ApiException extends \RuntimeException {
	private ?string $error_code;

	public function __construct( string $message, int $code = 500, ?string $error_code = null ) {
		parent::__construct( $message, $code );
		$this->errorCode = $error_code;
	}

	public function getErrorCode(): ?string {
		return $this->errorCode;
	}
}
