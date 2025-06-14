<?php
/**
 * File: includes/Services/PointerService.php
 *
 * Pointer Service
 *
 * @package NuclearEngagement\Services
 */

namespace NuclearEngagement\Services;

if (!defined('ABSPATH')) {
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
     * @param int $userId
     * @throws \InvalidArgumentException On empty pointer ID
     */
    public function dismissPointer(string $pointerId, int $userId): void {
        if (empty($pointerId)) {
            throw new \InvalidArgumentException('No pointer ID provided');
        }

        update_user_meta($userId, 'nuclen_pointer_dismissed_' . $pointerId, true);
    }

    /**
     * Get undismissed pointers for a user
     *
     * @param array $pointers All pointers
     * @param int $userId
     * @return array Undismissed pointers
     */
    public function getUndismissedPointers(array $pointers, int $userId): array {
        $undismissed = [];

        foreach ($pointers as $pointer) {
            if (!isset($pointer['id'])) {
                continue;
            }

            $dismissed = get_user_meta($userId, 'nuclen_pointer_dismissed_' . $pointer['id'], true);
            if (!$dismissed) {
                $undismissed[] = $pointer;
            }
        }

        return $undismissed;
    }
}
