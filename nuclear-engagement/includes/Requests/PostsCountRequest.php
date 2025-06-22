<?php
declare(strict_types=1);
/**
 * File: includes/Requests/PostsCountRequest.php

 * Posts Count Request DTO
 *
 * @package NuclearEngagement\Requests
 */

namespace NuclearEngagement\Requests;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Data transfer object for posts count requests
 */
class PostsCountRequest {
    public string $postStatus = 'any';
    public int $categoryId = 0;
    public int $authorId = 0;
    public string $postType = '';
    public string $workflow = '';
    public bool $allowRegenerate = false;
    public bool $regenerateProtected = false;

    /**
     * Create from POST data
     *
     * @param array $post POST data
     * @return self
     */
    public static function fromPost(array $post): self {
        $request = new self();

        $unslashed = wp_unslash($post);

        $request->postStatus = sanitize_text_field($unslashed['nuclen_post_status'] ?? 'any');
        $request->categoryId = absint($unslashed['nuclen_category'] ?? 0);
        $request->authorId = absint($unslashed['nuclen_author'] ?? 0);
        $request->postType = sanitize_text_field($unslashed['nuclen_post_type'] ?? '');
        $request->workflow = sanitize_text_field($unslashed['nuclen_generate_workflow'] ?? '');
        $request->allowRegenerate = (bool) absint($unslashed['nuclen_allow_regenerate_data'] ?? 0);
        $request->regenerateProtected = (bool) absint($unslashed['nuclen_regenerate_protected_data'] ?? 0);

        return $request;
    }
}