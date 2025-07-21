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
	public ?string $errorCode = null;

	/**
	 * @var int|null HTTP status code from remote API
	 */
	public ?int $statusCode = null;

	/**
	 * @var string|null Success message
	 */
	public ?string $message = null;

	/**
	 * @var int|null Total number of posts
	 */
	public ?int $totalPosts = null;

	/**
	 * @var int|null Total number of batches
	 */
	public ?int $totalBatches = null;

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

		if ( $this->message !== null ) {
			$data['message'] = $this->message;
		}

		if ( $this->totalPosts !== null ) {
			$data['total_posts'] = $this->totalPosts;
		}

		if ( $this->totalBatches !== null ) {
			$data['total_batches'] = $this->totalBatches;
		}

		return $data;
	}
}
