<?php
/**
 * File: includes/Utils.php
 * Implementation of changes required by WordPress.org guidelines.
 * - Store log files and custom CSS in the standard uploads folder.
 * - No new style expansions needed here.
 *
 * @package NuclearEngagement
 */

namespace NuclearEngagement;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Utils {


	public function display_nuclen_page_header() {
		$image_url = plugin_dir_url( __DIR__ ) . 'assets/nuclear-engagement-logo.webp';
		if ( ! filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
			return;
		}
		$image_html = '<img height="40" width="40" src="' . esc_url( $image_url ) . '" alt="' . esc_attr__( 'Nuclear Engagement Logo', 'nuclear-engagement' ) . '" />';
		echo '<div id="nuclen-page-header">
                ' . wp_kses_post( $image_html ) . '
                <p><b>' . esc_html__( 'NUCLEAR ENGAGEMENT', 'nuclear-engagement' ) . '</b></p>
            </div>';
	}

	public static function nuclen_get_custom_css_info() {
		$upload_dir = wp_upload_dir();
		$custom_dir = $upload_dir['basedir'] . '/nuclear-engagement';

		if ( ! file_exists( $custom_dir ) ) {
			wp_mkdir_p( $custom_dir );
		}

		$base_css_file_name = 'nuclen-theme-custom.css';
		$custom_css_path    = $custom_dir . '/' . $base_css_file_name;

		// Get the stored version hash or generate a new one if the file exists
		$version = get_option('nuclen_custom_css_version', '');
		if (file_exists($custom_css_path)) {
			$file_mtime = filemtime($custom_css_path);
			$file_hash = md5_file($custom_css_path);
			$version = $file_mtime . '-' . substr($file_hash, 0, 8);
		}

		$custom_css_url = $upload_dir['baseurl'] . '/nuclear-engagement/' . $base_css_file_name . '?v=' . $version;

		return array(
			'dir'       => $custom_dir,
			'file_name' => $base_css_file_name,
			'path'      => $custom_css_path,
			'url'       => $custom_css_url,
		);
	}

        // Legacy query helpers were removed in favor of PostsQueryService.
}
