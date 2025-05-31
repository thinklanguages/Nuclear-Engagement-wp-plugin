<?php
/**
 * File: admin/Controller/Ajax/PostsCountController.php
 
 * Posts Count Controller
 *
 * @package NuclearEngagement\Admin\Controller\Ajax
 */

namespace NuclearEngagement\Admin\Controller\Ajax;

use NuclearEngagement\Requests\PostsCountRequest;
use NuclearEngagement\Services\PostsQueryService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Controller for getting posts count
 */
class PostsCountController {
    /**
     * @var PostsQueryService
     */
    private PostsQueryService $service;
    
    /**
     * Constructor
     *
     * @param PostsQueryService $service
     */
    public function __construct(PostsQueryService $service) {
        $this->service = $service;
    }
    
    /**
     * Handle posts count request
     */
    public function handle(): void {
        try {
            check_ajax_referer('nuclen_admin_ajax_nonce', 'security');
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Not allowed']);
            }
            
            // Parse request
            $request = PostsCountRequest::fromPost($_POST);
            
            // Get posts
            $result = $this->service->getPostsCount($request);
            
            wp_send_json_success($result);
            
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}