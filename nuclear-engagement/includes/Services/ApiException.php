<?php
declare(strict_types=1);
/**
 * File: includes/Services/ApiException.php
 *
 * Exception thrown when the remote API returns an error.
 */

namespace NuclearEngagement\Services;

if (!defined('ABSPATH')) {
    exit;
}

class ApiException extends \RuntimeException {
    private ?string $errorCode;

    public function __construct(string $message, int $code = 0, ?string $errorCode = null) {
        parent::__construct($message, $code);
        $this->errorCode = $errorCode;
    }

    public function getErrorCode(): ?string {
        return $this->errorCode;
    }
}
