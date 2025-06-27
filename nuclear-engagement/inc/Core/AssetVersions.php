<?php
declare(strict_types=1);
namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles versioning for compiled plugin assets.
 */
final class AssetVersions {
	private const OPTION        = 'nuclen_asset_versions';
	private const PLUGIN_OPTION = 'nuclen_asset_versions_build';

	/**
	 * Recompute asset versions when the stored plugin version differs.
	 */
	public static function init(): void {
		$stored = get_option( self::PLUGIN_OPTION );
		if ( $stored !== NUCLEN_PLUGIN_VERSION ) {
			self::update_versions();
		}
	}

	/**
	 * Compute and store asset version strings.
	 */
	public static function update_versions(): void {
		update_option( self::OPTION, self::compute() );
		update_option( self::PLUGIN_OPTION, NUCLEN_PLUGIN_VERSION );
	}

	/**
	 * Retrieve a version string for the given key.
	 */
	public static function get( string $key ): string {
		$versions = get_option( self::OPTION, array() );
		return isset( $versions[ $key ] ) ? $versions[ $key ] : NUCLEN_ASSET_VERSION;
	}

	/**
	 * Build the version map based on file modification times.
	 */
	private static function compute(): array {
		$files = array(
			'admin_css'       => NUCLEN_PLUGIN_DIR . 'admin/css/nuclen-admin.css',
			'admin_dashboard' => NUCLEN_PLUGIN_DIR . 'admin/css/nuclen-admin-dashboard.css',
			'admin_js'        => NUCLEN_PLUGIN_DIR . 'admin/js/nuclen-admin.js',
			'onboarding_js'   => NUCLEN_PLUGIN_DIR . 'admin/js/onboarding-pointers.js',
			'front_css'       => NUCLEN_PLUGIN_DIR . 'front/css/nuclen-front.css',
			'front_js'        => NUCLEN_PLUGIN_DIR . 'front/js/nuclen-front.js',
			'toc_admin_css'   => NUCLEN_PLUGIN_DIR . 'inc/Modules/TOC/assets/css/nuclen-toc-admin.css',
			'toc_admin_js'    => NUCLEN_PLUGIN_DIR . 'inc/Modules/TOC/assets/js/nuclen-toc-admin.js',
			'toc_front_css'   => NUCLEN_PLUGIN_DIR . 'inc/Modules/TOC/assets/css/nuclen-toc-front.css',
			'toc_front_js'    => NUCLEN_PLUGIN_DIR . 'inc/Modules/TOC/assets/js/nuclen-toc-front.js',
		);

		$versions = array();
		foreach ( $files as $key => $path ) {
			$versions[ $key ] = file_exists( $path ) ? (string) filemtime( $path ) : (string) time();
		}
		return $versions;
	}
}
