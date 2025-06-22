<?php
declare(strict_types=1);
/**
 * File: includes/Services/PostsQueryService.php
 *
 * Posts Query Service
 *
 * @package NuclearEngagement\Services
 */

namespace NuclearEngagement\Services;

use NuclearEngagement\Requests\PostsCountRequest;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service for querying posts
 */
class PostsQueryService {
    /**
     * Build query args from request
     *
     * @param PostsCountRequest $request
     * @return array
     */
    public function buildQueryArgs(PostsCountRequest $request): array {
        $metaQuery = ['relation' => 'AND'];

        $queryArgs = [
            'post_type' => $request->postType,
            'posts_per_page' => -1,
            'post_status' => $request->postStatus,
            'fields' => 'ids',
        ];

        if ($request->categoryId) {
            $queryArgs['cat'] = $request->categoryId;
        }

        if ($request->authorId) {
            $queryArgs['author'] = $request->authorId;
        }

        // Skip existing data if not allowing regeneration
        if (!$request->allowRegenerate) {
            $metaKey = $request->workflow === 'quiz' ? 'nuclen-quiz-data' : 'nuclen-summary-data';
            $metaQuery[] = [
                'key' => $metaKey,
                'compare' => 'NOT EXISTS',
            ];
        }

        // Skip protected data if not allowed
        if (!$request->regenerateProtected) {
            $protectedKey = $request->workflow === 'quiz' ? 'nuclen_quiz_protected' : 'nuclen_summary_protected';
            $metaQuery[] = [
                'relation' => 'OR',
                [
                    'key' => $protectedKey,
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => $protectedKey,
                    'value' => '1',
                    'compare' => '!=',
                ],
            ];
        }

        // Only add meta_query if we have conditions
        if (count($metaQuery) > 1) {
            $queryArgs['meta_query'] = $metaQuery;
        }

        // Disable caching for performance during counts
        $queryArgs['update_post_meta_cache'] = false;
        $queryArgs['update_post_term_cache'] = false;
        $queryArgs['cache_results'] = false;

        return $queryArgs;
    }

    /**
     * Get posts count and IDs
     *
     * @param PostsCountRequest $request
     * @return array
     */
    public function getPostsCount(PostsCountRequest $request): array {
        $queryArgs = $this->buildQueryArgs($request);
        $query = new \WP_Query($queryArgs);

        return [
            'count' => $query->found_posts,
            'post_ids' => $query->posts ?: [],
        ];
    }
}
