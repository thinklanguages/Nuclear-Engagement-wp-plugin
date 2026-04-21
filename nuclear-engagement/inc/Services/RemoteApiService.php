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
			$api_exception = $this->normalize_api_exception(
				$e,
				array(
					'endpoint'      => '/process-posts',
					'generation_id' => $generation_id,
					'post_count'    => count( $payload['posts'] ?? array() ),
				)
			);
			$this->record_circuit_breaker_failure( $api_exception );
			throw $api_exception;
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

		// Check cache first for completed generations only.
		// Use the server's explicit `completed` boolean — `processed >= total`
		// is trivially true for the not_found stub (0 >= 0) and would cache a
		// spurious "complete" result.
		$cache_key = 'nuclen_update_' . $generation_id;
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( is_array( $cached ) && ! empty( $cached['completed'] ) ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'[INFO] Returning cached completed response | GenID: %s | Status: %s',
					$generation_id,
					isset( $cached['status'] ) ? (string) $cached['status'] : 'unknown'
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

			// Only cache if we have valid data.
			if ( is_array( $data ) ) {
				$is_not_found = isset( $data['status'] ) && 'not_found' === $data['status'];
				if ( ! $is_not_found ) {
					wp_cache_set( $cache_key, $data, self::CACHE_GROUP, self::CACHE_TTL );
				}
				// Longer-lived transient only for truly terminal responses — gated
				// on the server's `completed` boolean, not processed/total math.
				if ( ! empty( $data['completed'] ) && ! $is_not_found ) {
					set_transient( $cache_key, $data, 300 );
				}
			}

			return $data;
		} catch ( \Throwable $e ) {
			$api_exception = $this->normalize_api_exception(
				$e,
				array(
					'endpoint'      => '/updates',
					'generation_id' => $generation_id,
				)
			);
			$this->record_circuit_breaker_failure( $api_exception );
			throw $api_exception;
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
		} catch ( \Throwable $e ) {
			$api_exception = $this->normalize_api_exception(
				$e,
				array(
					'endpoint' => '/updates',
					'purpose'  => 'credits_only',
				)
			);
			$this->record_circuit_breaker_failure( $api_exception );
			throw $api_exception;
		}
	}

	/**
	 * Cancel an in-flight generation on the remote server.
	 *
	 * Wraps POST /api/cancel-generation. The endpoint is idempotent: if the
	 * generation has already reached a terminal state it returns
	 * refunded_credits: 0 with the existing status.
	 *
	 * @param string $generation_id Parent generation identifier.
	 * @return array Either the parsed server response on success, or
	 *               [ 'success' => false, 'error' => string ] on failure.
	 */
	public function cancel_generation( string $generation_id ): array {
		$api_key = $this->settings->get_string( 'api_key', '' );

		if ( empty( $api_key ) ) {
			\NuclearEngagement\Services\LoggingService::log(
				'[RemoteApiService::cancel_generation] API key not configured',
				'error'
			);
			return array(
				'success' => false,
				'error'   => __( 'API key not configured. Please configure it in the plugin settings.', 'nuclear-engagement' ),
			);
		}

		if ( empty( $generation_id ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Missing generation_id.', 'nuclear-engagement' ),
			);
		}

		// Check circuit breaker.
		if ( ! $this->circuit_breaker->is_request_allowed() ) {
			$status = $this->circuit_breaker->get_status();
			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'[RemoteApiService::cancel_generation] Circuit breaker open; retry in %ds',
					$status['time_until_retry']
				),
				'warning'
			);
			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: %d seconds */
					__( 'API temporarily unavailable. Retry in %d seconds.', 'nuclear-engagement' ),
					$status['time_until_retry']
				),
			);
		}

		$payload = array(
			'api_key'       => $api_key,
			'siteUrl'       => get_site_url(),
			'generation_id' => $generation_id,
		);

		\NuclearEngagement\Services\LoggingService::log(
			sprintf(
				'[RemoteApiService::cancel_generation] Sending cancel request | GenID: %s',
				$generation_id
			)
		);

		try {
			$response = $this->request->post( '/cancel-generation', $payload, $api_key );
			$result   = $this->handler->handle( $response );

			$this->circuit_breaker->record_success();

			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'[RemoteApiService::cancel_generation] SUCCESS | GenID: %s | Status: %s | Refunded: %d',
					$generation_id,
					is_array( $result ) ? ( $result['status'] ?? 'unknown' ) : 'unknown',
					is_array( $result ) ? (int) ( $result['refunded_credits'] ?? 0 ) : 0
				)
			);

			return is_array( $result ) ? $result : array( 'success' => true );
		} catch ( \Throwable $e ) {
			$api_exception = $this->normalize_api_exception(
				$e,
				array(
					'endpoint'      => '/cancel-generation',
					'generation_id' => $generation_id,
				)
			);
			$this->record_circuit_breaker_failure( $api_exception );

			\NuclearEngagement\Services\LoggingService::log(
				sprintf(
					'[RemoteApiService::cancel_generation] ERROR | GenID: %s | %s',
					$generation_id,
					$api_exception->getMessage()
				),
				'error'
			);

			return array(
				'success' => false,
				'error'   => $api_exception->get_user_message(),
			);
		}
	}

	/**
	 * Normalize arbitrary throwables into the plugin API exception type.
	 *
	 * @param \Throwable $e       Source exception.
	 * @param array      $context Additional context to attach.
	 * @return CustomApiException
	 */
	private function normalize_api_exception( \Throwable $e, array $context = array() ): CustomApiException {
		$api_exception = $e instanceof CustomApiException ? $e : CustomApiException::fromThrowable( $e );

		if ( ! empty( $context ) ) {
			$api_exception->set_context( array_merge( $api_exception->get_context(), $context ) );
		}

		return $api_exception;
	}

	/**
	 * Only transient failures should contribute to opening the circuit breaker.
	 *
	 * @param CustomApiException $api_exception Normalized API exception.
	 */
	private function record_circuit_breaker_failure( CustomApiException $api_exception ): void {
		if ( $api_exception->is_retryable() ) {
			$this->circuit_breaker->record_failure();
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
