<?php
declare(strict_types=1);
/**
 * File: includes/Requests/UpdatesRequest.php

 * Updates Request DTO
 *
 * @package NuclearEngagement\Requests
 */

namespace NuclearEngagement\Requests;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data transfer object for update polling requests
 */
class UpdatesRequest {
	/**
	 * @var string Generation ID to check
	 */
	public string $generationId = '';

	/**
	 * Create from POST data
	 *
	 * @param array $post POST data
	 * @return self
	 */
	public static function fromPost( array $post ): self {
		$request			   = new self();
		$request->generationId = isset( $post['generation_id'] )
			? sanitize_text_field( wp_unslash( $post['generation_id'] ) )
			: '';
		return $request;
	}
}
