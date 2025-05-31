<?php
/**
 * File: includes/Responses/UpdatesResponse.php
 
 * Updates Response DTO
 *
 * @package NuclearEngagement\Responses
 */

namespace NuclearEngagement\Responses;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Response object for update polling
 */
class UpdatesResponse {
    /**
     * @var bool Success status
     */
    public bool $success = true;
    
    /**
     * @var int|null Number of processed items
     */
    public ?int $processed = null;
    
    /**
     * @var int|null Total items
     */
    public ?int $total = null;
    
    /**
     * @var array|null Results data
     */
    public ?array $results = null;
    
    /**
     * @var int|null Remaining credits
     */
    public ?int $remainingCredits = null;
    
    /**
     * @var string|null Message
     */
    public ?string $message = null;
    
    /**
     * Convert to array for JSON response
     *
     * @return array
     */
    public function toArray(): array {
        $data = ['success' => $this->success];
        
        if ($this->processed !== null) {
            $data['processed'] = $this->processed;
        }
        
        if ($this->total !== null) {
            $data['total'] = $this->total;
        }
        
        if ($this->results !== null) {
            $data['results'] = $this->results;
        }
        
        if ($this->remainingCredits !== null) {
            $data['remaining_credits'] = $this->remainingCredits;
        }
        
        if ($this->message !== null) {
            $data['message'] = $this->message;
        }
        
        return $data;
    }
}
