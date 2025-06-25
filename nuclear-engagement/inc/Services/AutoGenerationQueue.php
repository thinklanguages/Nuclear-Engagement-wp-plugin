<?php
declare(strict_types=1);
/**
 * File: includes/Services/AutoGenerationQueue.php
 *
 * Handles queue management for auto generation.
 */

namespace NuclearEngagement\Services;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AutoGenerationQueue {
    /** Option name storing the queued post IDs. */
    private const QUEUE_OPTION = 'nuclen_autogen_queue';

    /** Maximum number of posts to send in one batch. */
    private const BATCH_SIZE = 5;


    private RemoteApiService $remote_api;
    private ContentStorageService $content_storage;
    private PostDataFetcher $fetcher;

    public function __construct( RemoteApiService $remote_api, ContentStorageService $content_storage, ?PostDataFetcher $fetcher = null ) {
        $this->remote_api      = $remote_api;
        $this->content_storage = $content_storage;
        $this->fetcher         = $fetcher ?: new PostDataFetcher();
    }

    /**
     * Add a post to the pending generation queue and schedule processing.
     */
    public function queue_post( int $post_id, string $workflow_type ): void {
        $queue = get_option( self::QUEUE_OPTION, array() );
        if ( ! isset( $queue[ $workflow_type ] ) ) {
            $queue[ $workflow_type ] = array();
        }
        if ( ! in_array( $post_id, $queue[ $workflow_type ], true ) ) {
            $queue[ $workflow_type ][] = $post_id;
            update_option( self::QUEUE_OPTION, $queue, 'no' );
        }
        if ( ! wp_next_scheduled( AutoGenerationService::QUEUE_HOOK ) ) {
            $scheduled = wp_schedule_single_event( time(), AutoGenerationService::QUEUE_HOOK, array() );
            if ( false === $scheduled ) {
                \NuclearEngagement\Services\LoggingService::log( 'Failed to schedule event ' . AutoGenerationService::QUEUE_HOOK );
                \NuclearEngagement\Services\LoggingService::notify_admin(
                    sprintf( __( 'Failed to schedule event %s', 'nuclear-engagement' ), AutoGenerationService::QUEUE_HOOK )
                );
            }
        }
    }

    /**
     * Process queued posts in batches.
     */
    public function process_queue(): void {
        $queue = get_option( self::QUEUE_OPTION, array() );
        if ( empty( $queue ) ) {
            return;
        }

        $generations = get_option( 'nuclen_active_generations', array() );

        foreach ( $queue as $workflow_type => $ids ) {
            $ids = array_map( 'absint', $ids );
            while ( ! empty( $ids ) ) {
                $batch     = array_splice( $ids, 0, self::BATCH_SIZE );
                $post_data = array();

                $posts = $this->fetcher->fetch( $batch );

                foreach ( $posts as $post ) {
                    $pid        = (int) $post->ID;
                    $post_data[] = array(
                        'id'      => $pid,
                        'title'   => $post->post_title,
                        'content' => wp_strip_all_tags( $post->post_content ),
                    );
                }

                if ( empty( $post_data ) ) {
                    continue;
                }

                $workflow = array(
                    'type'                    => $workflow_type,
                    'summary_format'          => 'paragraph',
                    'summary_length'          => NUCLEN_SUMMARY_LENGTH_DEFAULT,
                    'summary_number_of_items' => NUCLEN_SUMMARY_ITEMS_DEFAULT,
                );

                $generation_id = 'gen_' . uniqid( 'auto_', true );

                try {
                    $this->remote_api->send_posts_to_generate(
                        array(
                            'posts'         => $post_data,
                            'workflow'      => $workflow,
                            'generation_id' => $generation_id,
                        )
                    );
                } catch ( \Throwable $e ) {
                    \NuclearEngagement\Services\LoggingService::log( 'Failed to start generation: ' . $e->getMessage() );
                    \NuclearEngagement\Services\LoggingService::notify_admin( 'Auto-generation failed: ' . $e->getMessage() );
                    continue;
                }

                $next_poll                    = time() + NUCLEN_INITIAL_POLL_DELAY + mt_rand( 1, 5 );
                $generations[ $generation_id ] = array(
                    'started_at'    => current_time( 'mysql' ),
                    'post_ids'      => $batch,
                    'next_poll'     => $next_poll,
                    'attempt'       => 1,
                    'workflow_type' => $workflow_type,
                );

                $scheduled = wp_schedule_single_event( $next_poll, 'nuclen_poll_generation', array( $generation_id, $workflow_type, $batch, 1 ) );
                if ( false === $scheduled ) {
                    \NuclearEngagement\Services\LoggingService::log( 'Failed to schedule event nuclen_poll_generation for generation ' . $generation_id );
                    \NuclearEngagement\Services\LoggingService::notify_admin(
                        sprintf( __( 'Failed to schedule event nuclen_poll_generation for generation %s', 'nuclear-engagement' ), $generation_id )
                    );
                }
            }
            $queue[ $workflow_type ] = $ids;
        }

        update_option( 'nuclen_active_generations', $generations, 'no' );

        foreach ( $queue as $type => $ids ) {
            if ( empty( $ids ) ) {
                unset( $queue[ $type ] );
            }
        }

        if ( empty( $queue ) ) {
            delete_option( self::QUEUE_OPTION );
        } else {
            update_option( self::QUEUE_OPTION, $queue, 'no' );
            $scheduled = wp_schedule_single_event( time() + 1, AutoGenerationService::QUEUE_HOOK, array() );
            if ( false === $scheduled ) {
                \NuclearEngagement\Services\LoggingService::log( 'Failed to schedule event ' . AutoGenerationService::QUEUE_HOOK );
                \NuclearEngagement\Services\LoggingService::notify_admin(
                    sprintf( __( 'Failed to schedule event %s', 'nuclear-engagement' ), AutoGenerationService::QUEUE_HOOK )
                );
            }
        }
    }
}
