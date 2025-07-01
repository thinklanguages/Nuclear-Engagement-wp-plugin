<?php
/**
	* File: includes/Utils.php
	*
	* Utility helpers used throughout the plugin.
	*
	* Implementation of changes required by WordPress.org guidelines.
	* - Store log files and custom CSS in the standard uploads folder.
	* - No new style expansions needed here.
	*
	* @package NuclearEngagement
	*/
declare(strict_types=1);

namespace NuclearEngagement\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
	* Generic helper utilities for the plugin.
	*/
class Utils {

	/**
	 * Output a standard page header used across admin screens.
	 *
	 * @return void
	 */

       public function display_nuclen_page_header(): void {
		// Validate plugin constants to prevent path traversal
		if ( ! defined( 'NUCLEN_PLUGIN_URL' ) || ! defined( 'NUCLEN_PLUGIN_DIR' ) ) {
			return;
		}
		
		// Construct and validate image URL
		$image_url = NUCLEN_PLUGIN_URL . 'assets/images/nuclear-engagement-logo.webp';
		
		// Validate that the image URL starts with the plugin URL (no path traversal)
		if ( strpos( $image_url, NUCLEN_PLUGIN_URL ) !== 0 ) {
			return;
		}
		
		// Validate template path to prevent directory traversal
		$template_path = NUCLEN_PLUGIN_DIR . 'templates/admin/page-header.php';
		$real_plugin_dir = realpath( NUCLEN_PLUGIN_DIR );
		$real_template_path = realpath( $template_path );
		
		// Ensure template is within plugin directory and exists
		if ( ! $real_template_path || 
			 ! $real_plugin_dir || 
			 strpos( $real_template_path, $real_plugin_dir ) !== 0 ||
			 ! file_exists( $real_template_path ) ) {
			return;
		}
		
		// Load template safely
		load_template(
			$real_template_path,
			true,
			array( 'image_url' => esc_url( $image_url ) )
		);
	}

	/**
	 * Retrieve paths and URLs for the custom CSS file.
	 *
	 * @return array<string,string> Information about the custom CSS file.
	 */
	public static function nuclen_get_custom_css_info(): array {
		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			\NuclearEngagement\Services\LoggingService::log( 'wp_upload_dir error: ' . $upload_dir['error'] );
			\NuclearEngagement\Services\LoggingService::notify_admin( 'Uploads directory unavailable.' );
			return array();
		}

		$custom_dir = $upload_dir['basedir'] . '/nuclear-engagement';

		if ( ! file_exists( $custom_dir ) ) {
			if ( ! wp_mkdir_p( $custom_dir ) ) {
				\NuclearEngagement\Services\LoggingService::log( 'Failed creating custom CSS directory: ' . $custom_dir );
				\NuclearEngagement\Services\LoggingService::notify_admin( 'Failed creating custom CSS directory.' );
				return array();
			}
		}

		$base_css_file_name = 'nuclen-theme-custom.css';
		$custom_css_path    = $custom_dir . '/' . $base_css_file_name;

		// Get the stored version hash or generate a new one if the file exists.
		$version = get_option( 'nuclen_custom_css_version', '' );
		if ( '' === $version && file_exists( $custom_css_path ) ) {
			$file_mtime = filemtime( $custom_css_path );
			$file_hash  = md5_file( $custom_css_path );
			$version    = $file_mtime . '-' . substr( $file_hash, 0, 8 );
		}

		$custom_css_url = $upload_dir['baseurl'] . '/nuclear-engagement/' . $base_css_file_name . '?v=' . $version;

		return array(
			'dir'       => $custom_dir,
			'file_name' => $base_css_file_name,
			'path'      => $custom_css_path,
			'url'       => $custom_css_url,
		);
	}
}
