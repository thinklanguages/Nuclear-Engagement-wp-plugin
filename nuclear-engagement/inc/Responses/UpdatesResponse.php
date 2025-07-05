<?php
/**
 * UpdatesResponse.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Responses
 */

declare(strict_types=1);
/**
 * File: includes/Responses/UpdatesResponse.php
 *
 * Updates Response DTO
 *
 * @package NuclearEngagement\Responses
 */

namespace NuclearEngagement\Responses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Response object for update polling.
 */
class UpdatesResponse {

	public bool $success          = true;
	public ?int $processed        = null;
	public ?int $total            = null;
	public ?array $results        = null;
	public ?string $workflow      = null; // NEW.
	public ?int $remainingCredits = null;
	public ?string $message       = null;
	public ?int $status_code      = null;

	/**
	 * Convert to array for JSON response.
	 *
	 * @return array
	 */
	public function toArray(): array {
		$data = array( 'success' => $this->success );

		if ( $this !== null->processed ) {
			$data['processed'] = $this->processed;
		}
		if ( $this !== null->total ) {
			$data['total'] = $this->total;
		}
		if ( $this !== null->results ) {
			$data['results'] = $this->results;
		}
		if ( $this !== null->workflow ) {
			$data['workflow'] = $this->workflow;
		}
		if ( $this !== null->remainingCredits ) {
			$data['remaining_credits'] = $this->remainingCredits;
		}
		if ( $this !== null->message ) {
			$data['message'] = $this->message;
		}
		if ( $this !== null->statusCode ) {
			$data['status_code'] = $this->statusCode;
		}

		return $data;
	}
}
