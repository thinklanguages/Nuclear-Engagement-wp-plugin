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

use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Services\ApiException;
use NuclearEngagement\Services\Remote\RemoteRequest;
use NuclearEngagement\Services\Remote\ApiResponseHandler;
use NuclearEngagement\Exceptions\ApiException as CustomApiException;
use NuclearEngagement\Exceptions\ValidationException;
use NuclearEngagement\Core\BaseService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for communicating with Nuclear Engagement remote API
 */
class RemoteApiService extends BaseService {

	/** Cache group for API responses. */
	private const CACHE_GROUP = 'nuclen_remote';

	/** Short cache lifetime for update polling. */
	private const CACHE_TTL = 5; // 5 seconds to match frontend polling interval.

	/**
	 * @var SettingsRepository
	 */
	private SettingsRepository $settings;


	private RemoteRequest $request;

	private ApiResponseHandler $handler;

	/**
	 * @var CircuitBreaker
	 */
	private CircuitBreaker $circuit_breaker;

	/**
	 * Constructor
	 *
	 * @param SettingsRepository $settings
	 * @param RemoteRequest      $request
	 * @param ApiResponseHandler $handler
	 * @param CircuitBreaker     $circuit_breaker
	 */
	public function __construct( SettingsRepository $settings, RemoteRequest $request, ApiResponseHandler $handler, CircuitBreaker $circuit_breaker ) {
		parent::__construct();

		$this->settings        = $settings;
		$this->request         = $request;
		$this->handler         = $handler;
		$this->circuit_breaker = $circuit_breaker;

		// Set service-specific cache TTL
		$this->cache_ttl = self::CACHE_TTL;
	}

	/**
	 * Send posts to remote API for content generation
	 *
	 * @param array $data Data to send.
	 * @return array Response data on success
	 * @throws CustomApiException On API errors
	 * @throws ValidationException On invalid input
	 */
	public function send_posts_to_generate( array $data ): array {
		$api_key       = $this->settings->get_string( 'api_key', '' );
		$generation_id = $data['generation_id'] ?? '';

		if ( empty( $api_key ) ) {
			throw new ValidationException(
				array( 'API key not configured. Please configure it in the plugin settings.' ),
				'API key not configured'
			);
		}

		// Check circuit breaker
		if ( ! $this->circuit_breaker->is_request_allowed() ) {
			$status = $this->circuit_breaker->get_status();
			throw CustomApiException::serviceUnavailable(
				sprintf(
					'API temporarily unavailable. Circuit breaker is open. Retry in %d seconds.',
					$status['time_until_retry']
				),
				$status['time_until_retry']
			);
		}

		// Don't use cache for initial generation request

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

		\NuclearEngagement\Services\LoggingService::log(
			sprintf(
				'[INFO] Sending API request | GenID: %s | Posts: %d | Workflow: %s',
				$generation_id,
				count( $payload['posts'] ),
				$data['workflow']['type'] ?? 'unknown'
			)
		);

		try {
			$response = $this->request->post( '/process-posts', $payload, $api_key );
			$result   = $this->handler->handle( $response );

			// Record success
			$this->circuit_breaker->record_success();

			return $result;
		} catch ( \Throwable $e ) {
			// Record failure
			$this->circuit_breaker->record_failure();

			// Convert to custom exception if not already
			if ( ! $e instanceof CustomApiException ) {
				$api_exception = CustomApiException::fromThrowable( $e );
				$api_exception->set_context(
					array(
						'endpoint'      => '/process-posts',
						'generation_id' => $generation_id,
						'post_count'    => count( $payload['posts'] ?? array() ),
					)
				);
				throw $api_exception;
			}

			throw $e;
		}
	}

	/**
	 * Fetch generation updates from remote API
	 *
	 * @param string $generation_id Generation identifier.
	 * @return array API response data.
	 * @throws CustomApiException On API errors
	 * @throws ValidationException On invalid input
	 */
	public function fetch_updates( string $generation_id ): array {
		$api_key = $this->settings->get_string( 'api_key', '' );

		if ( empty( $api_key ) ) {
			throw new ValidationException(
				array( 'API key not configured. Please configure it in the plugin settings.' ),
				'API key not configured'
			);
		}

		// Check circuit breaker
		if ( ! $this->circuit_breaker->is_request_allowed() ) {
			$status = $this->circuit_breaker->get_status();
			throw CustomApiException::serviceUnavailable(
				sprintf(
					'API temporarily unavailable. Circuit breaker is open. Retry in %d seconds.',
					$status['time_until_retry']
				),
				$status['time_until_retry']
			);
		}

		// Check cache first for completed generations only
		$cache_key = 'nuclen_update_' . $generation_id;
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		// If we have cached data and generation is complete, return it
		if ( is_array( $cached ) && isset( $cached['processed'] ) && isset( $cached['total'] )
			&& $cached['processed'] >= $cached['total'] ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'[INFO] Returning cached data | GenID: %s | Processed: %d/%d',
					$generation_id,
					$cached['processed'],
					$cached['total']
				)
			);
			return $cached;
		}

		$payload = array(
			'siteUrl'       => get_site_url(),
			'generation_id' => $generation_id,
		);

		if ( empty( $generation_id ) ) {
			\NuclearEngagement\Services\LoggingService::log( '[CREDIT CHECK] Fetching credits only | No generation ID provided' );
		} elseif ( strpos( $generation_id, 'gen_auto_' ) === 0 ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( '[CREDIT CHECK FALLBACK] Using updates API for credit check | Dummy GenID: %s', $generation_id )
			);
		} else {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( '[GENERATION UPDATE] Fetching generation status | GenID: %s', $generation_id )
			);
		}

		try {
			$response = $this->request->post( '/updates', $payload, $api_key );
			$data     = $this->handler->handle( $response );

			// Record success
			$this->circuit_breaker->record_success();

			// Only cache if we have valid data
			if ( is_array( $data ) ) {
				wp_cache_set( $cache_key, $data, self::CACHE_GROUP, self::CACHE_TTL );
				// Only set transient for completed generations
				if ( isset( $data['processed'] ) && isset( $data['total'] ) && $data['processed'] >= $data['total'] ) {
					set_transient( $cache_key, $data, 300 ); // 5 minutes for completed generations
				}
			}

			return $data;
		} catch ( \Throwable $e ) {
			// Record failure
			$this->circuit_breaker->record_failure();

			// Convert to custom exception if not already
			if ( ! $e instanceof CustomApiException ) {
				$api_exception = CustomApiException::fromThrowable( $e );
				$api_exception->set_context(
					array(
						'endpoint'      => '/updates',
						'generation_id' => $generation_id,
					)
				);
				throw $api_exception;
			}

			throw $e;
		}
	}

	/**
	 * Fetch only credits information without triggering generation updates.
	 *
	 * @return array API response with credits information.
	 * @throws CustomApiException On API errors
	 * @throws ValidationException On invalid input
	 */
	public function fetch_credits_only(): array {
		$api_key = $this->settings->get_string( 'api_key', '' );

		if ( empty( $api_key ) ) {
			throw new ValidationException(
				array( 'API key not configured. Please configure it in the plugin settings.' ),
				'API key not configured'
			);
		}

		// Check circuit breaker
		if ( ! $this->circuit_breaker->is_request_allowed() ) {
			$status = $this->circuit_breaker->get_status();
			throw CustomApiException::serviceUnavailable(
				sprintf(
					'API temporarily unavailable. Circuit breaker is open. Retry in %d seconds.',
					$status['time_until_retry']
				),
				$status['time_until_retry']
			);
		}

		// Check cache first
		$cache_key = 'nuclen_credits_only';
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( is_array( $cached ) && isset( $cached['remaining_credits'] ) ) {
			return $cached;
		}

		$payload = array(
			'siteUrl'      => get_site_url(),
			'credits_only' => true,
		);

		\NuclearEngagement\Services\LoggingService::debug( 'Fetching credits only from API' );

		try {
			$response = $this->request->post( '/updates', $payload, $api_key );
			$data     = $this->handler->handle( $response );
			$this->circuit_breaker->record_success();

			// Cache the credits for 30 seconds
			if ( isset( $data['remaining_credits'] ) ) {
				wp_cache_set( $cache_key, $data, self::CACHE_GROUP, 30 );
			}

			return $data;
		} catch ( \Exception $e ) {
			$this->circuit_breaker->record_failure();
			throw $e;
		}
	}

	/**
	 * Get service name for logging and caching.
	 *
	 * @return string Service name.
	 */
	protected function get_service_name(): string {
		return 'remote_api_service';
	}
}
