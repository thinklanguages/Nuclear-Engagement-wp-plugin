<?php
/**
 * PointerService.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Services
 */

declare(strict_types=1);
/**
 * File: includes/Services/PointerService.php
 *
 * Pointer Service
 *
 * @package NuclearEngagement\Services
 */

namespace NuclearEngagement\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for managing admin pointers
 */
class PointerService {
	/**
	 * Dismiss a pointer for a user
	 *
	 * @param string $pointerId
	 * @param int    $user_id
	 * @throws \InvalidArgumentException On empty pointer ID
	 */
	public function dismissPointer( string $pointerId, int $user_id ): void {
		if ( empty( $pointerId ) ) {
			throw new \InvalidArgumentException( 'No pointer ID provided' );
		}

		update_user_meta( $user_id, 'nuclen_pointer_dismissed_' . $pointerId, true );
	}

	/**
	 * Get undismissed pointers for a user
	 *
	 * @param array $pointers All pointers
	 * @param int   $user_id
	 * @return array Undismissed pointers
	 */
	public function getUndismissedPointers( array $pointers, int $user_id ): array {
		$undismissed = array();

		foreach ( $pointers as $pointer ) {
			if ( ! isset( $pointer['id'] ) ) {
				continue;
			}

			$dismissed = get_user_meta( $user_id, 'nuclen_pointer_dismissed_' . $pointer['id'], true );
			if ( ! $dismissed ) {
				$undismissed[] = $pointer;
			}
		}

		return $undismissed;
	}
}
