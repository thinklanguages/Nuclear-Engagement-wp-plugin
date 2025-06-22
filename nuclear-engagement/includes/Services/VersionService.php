<?php
declare(strict_types=1);

namespace NuclearEngagement\Services;

use NuclearEngagement\AssetVersions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provides access to asset version strings.
 */
final class VersionService {
    /**
     * Retrieve a version string for the given asset key.
     */
    public function get( string $key ): string {
        return AssetVersions::get( $key );
    }
}
