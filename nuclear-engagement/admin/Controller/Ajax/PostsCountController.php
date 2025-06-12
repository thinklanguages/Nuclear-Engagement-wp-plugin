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
use NuclearEngagement\Includes\BaseAjaxController;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Controller for getting posts count
 */
class PostsCountController extends BaseAjaxController {
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
            if ( ! $this->verify_request( 'nuclen_admin_ajax_nonce' ) ) {
                return;
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