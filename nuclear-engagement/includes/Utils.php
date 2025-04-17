<?php
namespace NuclearEngagement;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Utils for the free plugin (we've removed remote AI calls).
 */
class Utils {

	public static function nuclen_get_log_file_info() {
		$upload_dir = wp_upload_dir();
		$log_folder = $upload_dir['basedir'] . '/nuclear-engagement';
		$log_file   = $log_folder . '/log.txt';
		$log_url    = $upload_dir['baseurl'] . '/nuclear-engagement/log.txt';

		return [
			'dir'  => $log_folder,
			'path' => $log_file,
			'url'  => $log_url,
		];
	}

	public function nuclen_log( $message ) {
		if ( empty( $message ) ) {
			return;
		}
		$info       = self::nuclen_get_log_file_info();
		$log_folder = $info['dir'];
		$log_file   = $info['path'];

		if ( ! file_exists( $log_folder ) ) {
			wp_mkdir_p( $log_folder );
		}
		if ( ! file_exists( $log_file ) ) {
			$timestamp = gmdate( 'Y-m-d H:i:s' );
			@file_put_contents( $log_file, "[$timestamp] Log file created\n", FILE_APPEND );
		}

		$timestamp = gmdate( 'Y-m-d H:i:s' );
		@file_put_contents( $log_file, "[$timestamp] $message\n", FILE_APPEND );
	}

	public function display_nuclen_page_header() {
		$image_url = plugin_dir_url( __DIR__ ) . 'assets/nuclear-engagement-logo.webp';
		if ( ! filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
			return;
		}
		?>
		<div id="nuclen-page-header">
			<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php esc_attr_e( 'Nuclear Engagement Logo', 'nuclear-engagement' ); ?>" width="40" height="40"/>
			<p><b><?php esc_html_e( 'NUCLEAR ENGAGEMENT', 'nuclear-engagement' ); ?></b></p>
		</div>
		<?php
	}

	public static function nuclen_get_custom_css_info() {
		$upload_dir = wp_upload_dir();
		$custom_dir = $upload_dir['basedir'] . '/nuclear-engagement';

		if ( ! file_exists( $custom_dir ) ) {
			wp_mkdir_p( $custom_dir );
		}

		$base_css_file_name = 'nuclen-theme-custom.css';
		$custom_css_path    = $custom_dir . '/' . $base_css_file_name;
		$version            = file_exists( $custom_css_path ) ? filemtime( $custom_css_path ) : time();

		$custom_css_url = $upload_dir['baseurl'] . '/nuclear-engagement/' . $base_css_file_name . '?v=' . $version;

		return [
			'dir'       => $custom_dir,
			'file_name' => $base_css_file_name,
			'path'      => $custom_css_path,
			'url'       => $custom_css_url,
		];
	}
}
