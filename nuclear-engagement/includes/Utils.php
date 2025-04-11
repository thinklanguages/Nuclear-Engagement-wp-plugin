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

	public static function nuclen_get_log_file_info() {
		$upload_dir = wp_upload_dir();

		$log_folder = $upload_dir['basedir'] . '/nuclear-engagement';
		$log_file   = $log_folder . '/log.txt';
		$log_url    = $upload_dir['baseurl'] . '/nuclear-engagement/log.txt';

		return array(
			'dir'  => $log_folder,
			'path' => $log_file,
			'url'  => $log_url,
		);
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
			if ( file_put_contents( $log_file, "[$timestamp] Log file created\n", FILE_APPEND ) === false ) {
				return;
			}
		}

		$timestamp = gmdate( 'Y-m-d H:i:s' );
		file_put_contents( $log_file, "[$timestamp] $message\n", FILE_APPEND );
	}

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
		$version            = file_exists( $custom_css_path ) ? filemtime( $custom_css_path ) : time();

		$custom_css_url = $upload_dir['baseurl'] . '/nuclear-engagement/' . $base_css_file_name . '?v=' . $version;

		return array(
			'dir'       => $custom_dir,
			'file_name' => $base_css_file_name,
			'path'      => $custom_css_path,
			'url'       => $custom_css_url,
		);
	}

	public function nuclen_build_generation_query_args() {
		$unslashed_post = wp_unslash( $_POST );

		$post_status           = sanitize_text_field( $unslashed_post['nuclen_post_status'] ?? 'any' );
		$category_id           = absint( $unslashed_post['nuclen_category'] ?? 0 );
		$author_id             = absint( $unslashed_post['nuclen_author'] ?? 0 );
		$post_type             = sanitize_text_field( $unslashed_post['nuclen_post_type'] ?? '' );
		$workflow              = sanitize_text_field( $unslashed_post['nuclen_generate_workflow'] ?? '' );
		$allow_regen           = absint( $unslashed_post['nuclen_allow_regenerate_data'] ?? 0 );
		$allow_protected_regen = absint( $unslashed_post['nuclen_regenerate_protected_data'] ?? 0 );

		$meta_query = array( 'relation' => 'AND' );

		$query_args = array(
			'post_type'      => $post_type,
			'posts_per_page' => -1,
			'post_status'    => $post_status,
		);

		if ( $category_id ) {
			$query_args['cat'] = (int) $category_id;
		}

		if ( $author_id ) {
			$query_args['author'] = (int) $author_id;
		}

		// Skip existing data if !allow_regen
		if ( ! $allow_regen ) {
			if ( $workflow === 'quiz' ) {
				$meta_query[] = array(
					'key'     => 'nuclen-quiz-data',
					'compare' => 'NOT EXISTS',
				);
			} else {
				$meta_query[] = array(
					'key'     => 'nuclen-summary-data',
					'compare' => 'NOT EXISTS',
				);
			}
		}

		// Skip protected data if not allowed
		if ( ! $allow_protected_regen ) {
			if ( $workflow === 'quiz' ) {
				$meta_query[] = array(
					'relation' => 'OR',
					array(
						'key'     => 'nuclen_quiz_protected',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => 'nuclen_quiz_protected',
						'value'   => '1',
						'compare' => '!=',
					),
				);
			} else {
				$meta_query[] = array(
					'relation' => 'OR',
					array(
						'key'     => 'nuclen_summary_protected',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => 'nuclen_summary_protected',
						'value'   => '1',
						'compare' => '!=',
					),
				);
			}
		}

		if ( count( $meta_query ) > 1 || ( count( $meta_query ) === 1 && ! empty( $meta_query[0]['key'] ) ) ) {
			$query_args['meta_query'] = $meta_query;
		}

		return $query_args;
	}

	public static function nuclen_get_post_data_from_id_for_generation( $post_id ) {
		$post = get_post( $post_id );
		if ( $post ) {
			return array(
				'id'      => $post_id,
				'title'   => get_the_title( $post_id ),
				'content' => wp_strip_all_tags( $post->post_content ),
			);
		}
		return null;
	}
}
