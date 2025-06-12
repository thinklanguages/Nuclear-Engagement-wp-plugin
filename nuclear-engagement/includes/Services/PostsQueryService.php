<?php
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
        
        // Skip posts that already have generated data when not allowing regeneration.
        // Earlier plugin versions stored meta using underscores instead of hyphens,
        // so we check both variants to ensure backwards compatibility.
        if (!$request->allowRegenerate) {
            $metaKeys = $request->workflow === 'quiz'
                ? ['nuclen-quiz-data', 'nuclen_quiz_data']
                : ['nuclen-summary-data', 'nuclen_summary_data'];

            $subQueries = [];
            foreach ($metaKeys as $key) {
                $subQueries[] = [
                    'key'     => $key,
                    'compare' => 'NOT EXISTS',
                ];
            }

            if (count($subQueries) === 1) {
                $metaQuery[] = $subQueries[0];
            } else {
                $metaQuery[] = array_merge(['relation' => 'AND'], $subQueries);
            }
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
        
        return $queryArgs;
    }
    
    /**
     * Get posts count and IDs
     *
     * @param PostsCountRequest $request
     * @return array
     */
    public function getPostsCount(PostsCountRequest $request): array {
        global $wpdb;

        $queryArgs = $this->buildQueryArgs($request);

        $where = $wpdb->prepare("{$wpdb->posts}.post_type = %s", $request->postType);

        if ($request->postStatus !== 'any') {
            $where .= $wpdb->prepare(" AND {$wpdb->posts}.post_status = %s", $request->postStatus);
        }

        if ($request->authorId) {
            $where .= $wpdb->prepare(" AND {$wpdb->posts}.post_author = %d", $request->authorId);
        }

        $join = '';

        if (!empty($queryArgs['cat'])) {
            $join .= " INNER JOIN {$wpdb->term_relationships} tr ON ({$wpdb->posts}.ID = tr.object_id)";
            $join .= " INNER JOIN {$wpdb->term_taxonomy} tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id)";
            $where .= $wpdb->prepare(" AND tt.taxonomy = 'category' AND tt.term_id = %d", $queryArgs['cat']);
        }

        if (!empty($queryArgs['meta_query'])) {
            $metaQuery = new \WP_Meta_Query($queryArgs['meta_query']);
            $metaSql = $metaQuery->get_sql('post', $wpdb->posts, 'ID');
            $join .= $metaSql['join'];
            $where .= $metaSql['where'];
        }

        $sqlIds = "SELECT DISTINCT {$wpdb->posts}.ID FROM {$wpdb->posts} {$join} WHERE {$where}";
        $postIds = $wpdb->get_col($sqlIds);

        $sqlCount = "SELECT COUNT(DISTINCT {$wpdb->posts}.ID) FROM {$wpdb->posts} {$join} WHERE {$where}";
        $count = (int) $wpdb->get_var($sqlCount);

        return [
            'count' => $count,
            'post_ids' => $postIds,
        ];
    }
}
