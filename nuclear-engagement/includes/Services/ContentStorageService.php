<?php
/**
 * File: includes/Services/ContentStorageService.php
 * 
 * Content Storage Service
 *
 * @package NuclearEngagement\Services
 */

namespace NuclearEngagement\Services;

use NuclearEngagement\SettingsRepository;
use NuclearEngagement\Utils;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service for storing generated content
 */
class ContentStorageService {
    /**
     * @var SettingsRepository
     */
    private SettingsRepository $settings;
    
    /**
     * @var Utils
     */
    private Utils $utils;
    
    /**
     * Constructor
     *
     * @param SettingsRepository $settings
     */
    public function __construct(SettingsRepository $settings) {
        $this->settings = $settings;
        $this->utils = new Utils();
    }
    
    /**
     * Store generation results
     *
     * @param array $results
     * @param string $workflowType
     */
    public function storeResults(array $results, string $workflowType): void {
        $updateLastModified = $this->settings->get_bool('update_last_modified', false);
        $dateNow = current_time('mysql');
        
        foreach ($results as $postIdString => $data) {
            $postId = (int) $postIdString;
            
            // Ensure date is set
            if (empty($data['date'])) {
                $data['date'] = $dateNow;
            }
            
            try {
                if ($workflowType === 'quiz') {
                    $this->storeQuizData($postId, $data);
                } else {
                    $this->storeSummaryData($postId, $data);
                }
                
                // Update post modified time if enabled
                if ($updateLastModified) {
                    $this->updatePostModifiedTime($postId);
                } else {
                    clean_post_cache($postId);
                }
                
\NuclearEngagement\Services\LoggingService::log("Stored {$workflowType} data for post {$postId}");
                
            } catch (\Exception $e) {
\NuclearEngagement\Services\LoggingService::log("Error storing {$workflowType} for post {$postId}: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Store quiz data
     *
     * @param int $postId
     * @param array $data
     * @throws \InvalidArgumentException On invalid data
     */
    public function storeQuizData(int $postId, array $data): void {
        if (empty($data['questions']) || !is_array($data['questions'])) {
            throw new \InvalidArgumentException("Invalid quiz data for post {$postId}");
        }
        
        $formatted = [
            'questions' => array_map(function($q) {
                return [
                    'question' => $q['question'] ?? '',
                    'answers' => $q['answers'] ?? [],
                    'explanation' => $q['explanation'] ?? '',
                ];
            }, $data['questions']),
            'date' => $data['date'] ?? current_time('mysql'),
        ];
        
        if (!update_post_meta($postId, 'nuclen-quiz-data', $formatted)) {
            throw new \RuntimeException("Failed to update quiz data for post {$postId}");
        }
    }
    
    /**
     * Store summary data
     *
     * @param int $postId
     * @param array $data
     * @throws \InvalidArgumentException On invalid data
     */
    public function storeSummaryData(int $postId, array $data): void {
        if (!isset($data['summary']) || !is_string($data['summary'])) {
            // Legacy support for 'content' key
            if (isset($data['content']) && is_string($data['content'])) {
                $data['summary'] = $data['content'];
            } else {
                throw new \InvalidArgumentException("Invalid summary data for post {$postId}");
            }
        }
        
        $allowedHtml = [
            'a' => ['href' => [], 'title' => [], 'target' => []],
            'br' => [],
            'em' => [],
            'strong' => [],
            'p' => [],
            'ul' => [],
            'ol' => [],
            'li' => [],
            'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [],
            'div' => ['class' => []],
            'span' => ['class' => []],
        ];
        
        $formatted = [
            'summary' => wp_kses($data['summary'], $allowedHtml),
            'date' => $data['date'] ?? current_time('mysql'),
        ];
        
        if (!update_post_meta($postId, 'nuclen-summary-data', $formatted)) {
            throw new \RuntimeException("Failed to update summary data for post {$postId}");
        }
    }
    
    /**
     * Update post modified time
     *
     * @param int $postId
     */
    private function updatePostModifiedTime(int $postId): void {
        $time = current_time('mysql');
        $result = wp_update_post([
            'ID' => $postId,
            'post_modified' => $time,
            'post_modified_gmt' => get_gmt_from_date($time),
        ]);
        
        if (is_wp_error($result)) {
\NuclearEngagement\Services\LoggingService::log("Failed to update modified time for post {$postId}: " . $result->get_error_message());
        }
        
        clean_post_cache($postId);
    }
}
