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
use NuclearEngagement\Services\LoggingService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Service for querying posts
 */
class PostsQueryService {
        /** Cache group for query results. */
        private const CACHE_GROUP = 'nuclen_posts_query';

        /** Cache lifetime in seconds. */
        private const CACHE_TTL = 10 * MINUTE_IN_SECONDS; // 10 minutes.

        /** Option name storing cache version. */
        private const VERSION_OPTION = 'nuclen_posts_query_version';

        /**
         * Register hooks to invalidate caches when posts or terms change.
         */
        public static function register_hooks(): void {
                $cb = array( self::class, 'clear_cache' );

                foreach ( array(
                        'save_post',
                        'delete_post',
                        'deleted_post',
                        'trashed_post',
                        'untrashed_post',
                        'transition_post_status',
                        'clean_post_cache',
                ) as $hook ) {
                        add_action( $hook, $cb );
                }

                foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) {
                        add_action( $hook, $cb );
                }

                foreach ( array(
                        'create_term',
                        'created_term',
                        'edit_term',
                        'edited_term',
                        'delete_term',
                        'deleted_term',
                        'set_object_terms',
                        'added_term_relationship',
                        'deleted_term_relationships',
                        'edited_terms',
                ) as $hook ) {
                        add_action( $hook, $cb );
                }

                add_action( 'switch_blog', $cb );
        }

        /**
         * Clear all cached query results.
         */
        public static function clear_cache(): void {
                $version = (int) get_option( self::VERSION_OPTION, 1 );
                update_option( self::VERSION_OPTION, $version + 1, false );

                if ( function_exists( 'wp_cache_flush_group' ) ) {
                        wp_cache_flush_group( self::CACHE_GROUP );
                } else {
                        wp_cache_flush();
                }
        }

        /**
         * Get current cache version.
         */
        private function get_cache_version(): int {
                return (int) get_option( self::VERSION_OPTION, 1 );
        }

        /**
         * Generate a cache key for the given request.
         */
        private function getCacheKey( PostsCountRequest $request ): string {
                $data = array(
                        $request->postType,
                        $request->postStatus,
                        $request->categoryId,
                        $request->authorId,
                        $request->allowRegenerate ? 1 : 0,
                        $request->regenerateProtected ? 1 : 0,
                        $request->workflow,
                        $this->get_cache_version(),
                        get_current_blog_id(),
                );

                return md5( wp_json_encode( $data ) );
        }

    /**
     * Build query args from request
     *
     * @param PostsCountRequest $request
     * @return array
     */
    public function buildQueryArgs( PostsCountRequest $request ): array {
        $metaQuery = array( 'relation' => 'AND' );

        $queryArgs = array(
            'post_type'      => $request->postType,
            'posts_per_page' => -1,
            'post_status'    => $request->postStatus,
            'fields'         => 'ids',
        );

        if ( $request->categoryId ) {
            $queryArgs['cat'] = $request->categoryId;
        }

        if ( $request->authorId ) {
            $queryArgs['author'] = $request->authorId;
        }

        // Skip existing data if not allowing regeneration
        if ( ! $request->allowRegenerate ) {
            $metaKey     = $request->workflow === 'quiz' ? 'nuclen-quiz-data' : 'nuclen-summary-data';
            $metaQuery[] = array(
                'key'     => $metaKey,
                'compare' => 'NOT EXISTS',
            );
        }

        // Skip protected data if not allowed
        if ( ! $request->regenerateProtected ) {
            $protectedKey = $request->workflow === 'quiz' ? 'nuclen_quiz_protected' : 'nuclen_summary_protected';
            $metaQuery[]  = array(
                'relation' => 'OR',
                array(
                    'key'     => $protectedKey,
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key'     => $protectedKey,
                    'value'   => '1',
                    'compare' => '!=',
                ),
            );
        }

        // Only add meta_query if we have conditions
        if ( count( $metaQuery ) > 1 ) {
            $queryArgs['meta_query'] = $metaQuery;
        }

        // Disable caching for performance during counts
        $queryArgs['update_post_meta_cache'] = false;
        $queryArgs['update_post_term_cache'] = false;
        $queryArgs['cache_results']          = false;

        return $queryArgs;
    }

    /**
     * Get posts count and IDs
     *
     * @param PostsCountRequest $request
     * @return array
     */
       public function getPostsCount( PostsCountRequest $request ): array {
               $cache_key      = $this->getCacheKey( $request );
               $transient_key  = 'nuclen_pq_' . $cache_key;
               $found          = false;
               $cached         = wp_cache_get( $cache_key, self::CACHE_GROUP, false, $found );
               if ( ! $found ) {
                       $cached = get_transient( $transient_key );
               }

               if ( is_array( $cached ) ) {
                       return $cached;
               }

               global $wpdb;

               $joins  = array();
               $wheres = array();

               $wheres[] = $wpdb->prepare( 'p.post_type = %s', $request->postType );

               if ( 'any' !== $request->postStatus ) {
                       $wheres[] = $wpdb->prepare( 'p.post_status = %s', $request->postStatus );
               }

               if ( $request->authorId ) {
                       $wheres[] = $wpdb->prepare( 'p.post_author = %d', $request->authorId );
               }

               if ( $request->categoryId ) {
                       $joins[]  = "JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID";
                       $joins[]  = "JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'category'";
                       $wheres[] = $wpdb->prepare( 'tt.term_id = %d', $request->categoryId );
               }

               if ( ! $request->allowRegenerate ) {
                       $meta_key = $request->workflow === 'quiz' ? 'nuclen-quiz-data' : 'nuclen-summary-data';
                       $joins[]  = $wpdb->prepare( "LEFT JOIN {$wpdb->postmeta} pm_exist ON pm_exist.post_id = p.ID AND pm_exist.meta_key = %s", $meta_key );
                       $wheres[] = 'pm_exist.meta_id IS NULL';
               }

               if ( ! $request->regenerateProtected ) {
                       $prot_key = $request->workflow === 'quiz' ? 'nuclen_quiz_protected' : 'nuclen_summary_protected';
                       $joins[]  = $wpdb->prepare( "LEFT JOIN {$wpdb->postmeta} pm_prot ON pm_prot.post_id = p.ID AND pm_prot.meta_key = %s", $prot_key );
                       $wheres[] = "(pm_prot.meta_id IS NULL OR pm_prot.meta_value != '1')";
               }

               $sql  = "FROM {$wpdb->posts} p " . implode( ' ', $joins );
               if ( $wheres ) {
                       $sql .= ' WHERE ' . implode( ' AND ', $wheres );
               }

               $count = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT p.ID) $sql" );

               $post_ids = array();
               $limit    = 1000;
               $offset   = 0;

               do {
                       $query = $wpdb->prepare(
                               "SELECT DISTINCT p.ID $sql ORDER BY p.ID ASC LIMIT %d OFFSET %d",
                               $limit,
                               $offset
                       );
                       $batch      = $wpdb->get_col( $query );
                       $post_ids   = array_merge( $post_ids, $batch );
                       $offset    += $limit;
               } while ( count( $batch ) === $limit );

               if ( $wpdb->last_error ) {
                       LoggingService::log( 'Posts query error: ' . $wpdb->last_error );
               }

               $result = array(
                       'count'    => $count,
                       'post_ids' => $post_ids,
               );

               wp_cache_set( $cache_key, $result, self::CACHE_GROUP, self::CACHE_TTL );
               set_transient( $transient_key, $result, self::CACHE_TTL );

               return $result;
       }
}
