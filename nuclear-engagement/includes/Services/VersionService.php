<?php
/**
 * File: includes/Services/VersionService.php
 *
 * Provides version management functionality for assets.
 *
 * @package    NuclearEngagement
 * @subpackage Services
 * @since      1.0.0
 */

declare( strict_types = 1 );

namespace NuclearEngagement\Services;

use NuclearEngagement\AssetVersions;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages asset versioning for the plugin.
 *
 * This service provides a consistent way to manage and retrieve version strings
 * for various assets (CSS, JS, etc.) to handle browser caching effectively.
 *
 * @since 1.0.0
 */
final class VersionService {

	/**
	 * Retrieve a version string for the given asset key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The unique identifier for the asset (e.g., 'admin-css', 'frontend-js').
	 * @return string The version string for the given asset key, or empty string if not found.
	 *
	 * @throws \InvalidArgumentException If the provided key is empty.
	 */
	public function get( string $key ): string {
		if ( '' === trim( $key ) ) {
			throw new \InvalidArgumentException( 'Asset key cannot be empty.' );
		}

		/**
		 * Filters the version string for a given asset.
		 *
		 * @since 1.0.0
		 *
		 * @param string $version The version string for the asset.
		 * @param string $key     The asset key being requested.
		 */
		return (string) apply_filters( 
			'nuclen_asset_version', 
			AssetVersions::get( $key ), 
			$key 
		);
	}
}
