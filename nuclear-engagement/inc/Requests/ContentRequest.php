<?php
/**
 * ContentRequest.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Requests
 */

declare(strict_types=1);
/**
 * File: includes/Requests/ContentRequest.php
 *
 * Content Request DTO
 *
 * @package NuclearEngagement\Requests
 */

namespace NuclearEngagement\Requests;

use NuclearEngagement\Exceptions\ValidationException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data transfer object for content receive requests
 */
class ContentRequest {
	/**
	 * @var string Workflow type (quiz|summary)
	 */
	public string $workflow = '';

	/**
	 * @var array Results data from remote API
	 */
	public array $results = array();

	/**
	 * Create from JSON data
	 *
	 * @param array $data JSON data
	 * @return self
	 * @throws \NuclearEngagement\Exceptions\ValidationException On validation errors
	 */
	public static function fromJson( array $data ): self {
		$request = new self();

		if ( empty( $data['workflow'] ) ) {
			throw new ValidationException(
				array( 'request_keys' => array_keys( $data ) ),
				'No workflow found in request'
			);
		}

		if ( empty( $data['results'] ) || ! is_array( $data['results'] ) ) {
			throw new ValidationException(
				array( 'request_keys' => array_keys( $data ) ),
				'No results data found in request'
			);
		}

		$request->workflow = sanitize_text_field( $data['workflow'] );

		// Validate workflow.
		if ( ! in_array( $request->workflow, array( 'quiz', 'summary' ), true ) ) {
			throw new ValidationException(
				array(
					'workflow'      => $request->workflow,
					'allowed_types' => array( 'quiz', 'summary' ),
				),
				'Invalid workflow type'
			);
		}

		$request->results = $data['results'];

		return $request;
	}
}
