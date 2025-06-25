<?php

/**
 * File: includes/Services/RemoteApiService.php
 *
 * Remote API Service.
 *
 * @package NuclearEngagement\Services
 *
 * phpcs:disable WordPress.Files.FileName.NotHyphenatedLowercase, WordPress.Files.FileName.InvalidClassFileName
 */

declare(strict_types=1);

namespace NuclearEngagement\Services;

use NuclearEngagement\SettingsRepository;
use NuclearEngagement\Services\ApiException;
use NuclearEngagement\Services\Remote\RemoteRequest;
use NuclearEngagement\Services\Remote\ApiResponseHandler;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Service for communicating with Nuclear Engagement remote API
 */
class RemoteApiService {

    /** Cache group for API responses. */
    private const CACHE_GROUP = 'nuclen_remote';

    /** Short cache lifetime for update polling. */
    private const CACHE_TTL = 60; // 60 seconds

    /**
     * @var SettingsRepository
     */
    private SettingsRepository $settings;


    private RemoteRequest $request;

    private ApiResponseHandler $handler;

    /**
     * Constructor
     *
     * @param SettingsRepository $settings
     */
    public function __construct( SettingsRepository $settings, RemoteRequest $request, ApiResponseHandler $handler ) {
        $this->settings = $settings;
        $this->request  = $request;
        $this->handler  = $handler;
    }

    /**
     * Send posts to remote API for content generation
     *
     * @param array $data Data to send.
     * @return array Response data on success
     * @throws \RuntimeException On API errors
     */
    public function send_posts_to_generate( array $data ): array {
        $api_key       = $this->settings->get_string( 'api_key', '' );
        $generation_id = $data['generation_id'] ?? '';

        if ( empty( $api_key ) ) {
            throw new \RuntimeException( 'API key not configured' );
        }

        $cache_key = 'nuclen_update_' . $generation_id;
        $found     = false;
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP, false, $found );
        if ( ! $found ) {
            $cached = get_transient( $cache_key );
        }

        if ( is_array( $cached ) ) {
            return $cached;
        }

        $payload = array(
            'generation_id' => $generation_id,
            'api_key'       => $api_key,
            'siteUrl'       => get_site_url(),
            'posts'         => array_values(
                array_filter(
                    $data['posts'] ?? array(),
                    function ( $p ) {
                        return ! empty( $p['id'] ) && ! empty( $p['title'] ) && ! empty( $p['content'] );
                    }
                )
            ),
            'workflow'      => $data['workflow'] ?? array(),
        );

        \NuclearEngagement\Services\LoggingService::log( 'Sending generation request: ' . $generation_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        $response = $this->request->post( '/process-posts', $payload, $api_key );

        return $this->handler->handle( $response );
    }

    /**
     * Fetch generation updates from remote API
     *
     * @param string $generation_id Generation identifier.
     * @return array API response data.
     * @throws \RuntimeException On API errors
     */
    public function fetch_updates( string $generation_id ): array {
        $api_key = $this->settings->get_string( 'api_key', '' );

        if ( empty( $api_key ) ) {
            throw new \RuntimeException( 'API key not configured' );
        }

        $payload = array(
            'siteUrl'       => get_site_url(),
            'generation_id' => $generation_id,
        );

        if ( empty( $generation_id ) ) {
            \NuclearEngagement\Services\LoggingService::log( 'Fetching credits only (no generation_id)' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } else {
            \NuclearEngagement\Services\LoggingService::log( "Fetching updates for generation: {$generation_id}" ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        $response = $this->request->post( '/updates', $payload, $api_key );

        $data = $this->handler->handle( $response );

        $cache_key = 'nuclen_update_' . $generation_id;
        wp_cache_set( $cache_key, $data, self::CACHE_GROUP, self::CACHE_TTL );
        set_transient( $cache_key, $data, self::CACHE_TTL );

        return $data;
    }

}
