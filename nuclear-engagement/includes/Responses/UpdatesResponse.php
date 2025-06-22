<?php
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

    public bool   $success          = true;
    public ?int   $processed        = null;
    public ?int   $total            = null;
    public ?array $results          = null;
    public ?string $workflow        = null; // NEW
    public ?int   $remainingCredits = null;
    public ?string $message         = null;
    public ?int   $statusCode       = null;

    /**
     * Convert to array for JSON response.
     *
     * @return array
     */
    public function toArray(): array {
        $data = array( 'success' => $this->success );

        if ( null !== $this->processed ) {
            $data['processed'] = $this->processed;
        }
        if ( null !== $this->total ) {
            $data['total'] = $this->total;
        }
        if ( null !== $this->results ) {
            $data['results'] = $this->results;
        }
        if ( null !== $this->workflow ) {
            $data['workflow'] = $this->workflow;
        }
        if ( null !== $this->remainingCredits ) {
            $data['remaining_credits'] = $this->remainingCredits;
        }
        if ( null !== $this->message ) {
            $data['message'] = $this->message;
        }
        if ( null !== $this->statusCode ) {
            $data['status_code'] = $this->statusCode;
        }

        return $data;
    }
}
