<?php
/**
 * GenerationResponse.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Responses
 */

declare(strict_types=1);
/**
 * File: includes/Responses/GenerationResponse.php
 *
 * Generation Response DTO
 *
 * @package NuclearEngagement\Responses
 */

namespace NuclearEngagement\Responses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Response object for generation requests
 */
class GenerationResponse {
	/**
	 * @var string Generation ID for tracking
	 */
	public string $generationId;

	/**
	 * @var array Generated results
	 */
	public array $results = array();

	/**
	 * @var bool Success status
	 */
	public bool $success = true;

	/**
	 * @var string|null Error message if any
	 */
	public ?string $error = null;

	/**
	 * @var string|null Error code if any
	 */
	public ?string $error_code = null;

	/**
	 * @var int|null HTTP status code from remote API
	 */
	public ?int $status_code = null;

	/**
	 * Convert to array for JSON response
	 *
	 * @return array
	 */
	public function toArray(): array {
		$data = array(
			'generation_id' => $this->generationId,
			'results'       => $this->results,
			'success'       => $this->success,
		);

		if ( $this->error !== null ) {
			$data['error'] = $this->error;
		}

		if ( $this->errorCode !== null ) {
			$data['error_code'] = $this->errorCode;
		}

		if ( $this->statusCode !== null ) {
			$data['status_code'] = $this->statusCode;
		}

		return $data;
	}
}
