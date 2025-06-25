<?php
declare(strict_types=1);
/**
 * File: includes/PendingSettingsTrait.php
 *
 * Provides helpers for managing pending settings changes.
 *
 * @package NuclearEngagement
 */

namespace NuclearEngagement;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait PendingSettingsTrait {
    /**
     * Remove a setting.
     */
    public function remove( string $key ): self {
        if ( isset( $this->pending[ $key ] ) ) {
            unset( $this->pending[ $key ] );
        }
        // Set to null to indicate removal
        $this->pending[ $key ] = null;
        return $this;
    }

    /**
     * Check if there are pending changes.
     */
    public function has_pending(): bool {
        return ! empty( $this->pending );
    }

    /**
     * Get all pending changes.
     */
    public function get_pending(): array {
        return $this->pending;
    }

    /**
     * Clear pending changes without saving.
     */
    public function clear_pending(): self {
        $this->pending = array();
        return $this;
    }
}
