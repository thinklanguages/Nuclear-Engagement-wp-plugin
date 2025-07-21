<?php
/**
 * Shortcode debugger for troubleshooting rendering issues
 *
 * @package NuclearEngagement_Debug
 */

declare(strict_types=1);

namespace NuclearEngagement\Debug;

use NuclearEngagement\Modules\Quiz\Quiz_Service;
use NuclearEngagement\Modules\Summary\Summary_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Debug utility for shortcode rendering issues.
 *
 * This class provides debug shortcodes to help diagnose why content
 * might be rendering blank on the frontend.
 */
class ShortcodeDebugger {

	/**
	 * Register debug shortcodes.
	 */
	public static function register(): void {
		add_shortcode( 'nuclen_debug_quiz', array( __CLASS__, 'debug_quiz' ) );
		add_shortcode( 'nuclen_debug_summary', array( __CLASS__, 'debug_summary' ) );
		add_shortcode( 'nuclen_debug_assets', array( __CLASS__, 'debug_assets' ) );
	}

	/**
	 * Debug quiz data and rendering.
	 */
	public static function debug_quiz(): string {
		$post_id = get_the_ID();
		$output  = '<div style="background:#f0f0f0; padding:20px; margin:20px 0; border:2px solid #333;">';
		$output .= '<h3>🔍 Nuclear Engagement Quiz Debug</h3>';

		// Check post ID
		$output .= '<p><strong>Post ID:</strong> ' . ( $post_id ? $post_id : 'NONE' ) . '</p>';

		if ( ! $post_id ) {
			$output .= '<p style="color:red;">❌ No post ID found - shortcode must be used within a post/page</p>';
			$output .= '</div>';
			return $output;
		}

		// Get quiz data
		$quiz_service = new Quiz_Service();
		$quiz_data    = $quiz_service->get_quiz_data( $post_id );

		$output .= '<p><strong>Quiz Data Type:</strong> ' . gettype( $quiz_data ) . '</p>';

		if ( empty( $quiz_data ) ) {
			$output .= '<p style="color:red;">❌ No quiz data found in post meta</p>';

			// Check raw meta
			$raw_meta = get_post_meta( $post_id, 'nuclen-quiz-data', true );
			$output  .= '<p><strong>Raw Meta:</strong> ' . ( $raw_meta ? 'EXISTS' : 'EMPTY' ) . '</p>';
			if ( $raw_meta ) {
				$output .= '<pre>' . esc_html( substr( wp_json_encode( $raw_meta ), 0, 500 ) ) . '...</pre>';
			}
		} else {
			$output .= '<p style="color:green;">✅ Quiz data found</p>';

			// Check questions
			$questions = isset( $quiz_data['questions'] ) ? $quiz_data['questions'] : array();
			$output   .= '<p><strong>Number of questions:</strong> ' . count( $questions ) . '</p>';

			if ( ! empty( $questions ) ) {
				$output .= '<p><strong>First question:</strong> ' . esc_html( $questions[0]['question'] ?? 'NO QUESTION TEXT' ) . '</p>';
			}

			// Show structure
			$output .= '<details><summary>Quiz Data Structure</summary>';
			$output .= '<pre>' . esc_html( wp_json_encode( $quiz_data, JSON_PRETTY_PRINT ) ) . '</pre>';
			$output .= '</details>';
		}

		// Check JavaScript variables
		$output .= '<h4>JavaScript Variables:</h4>';
		$output .= '<div id="js-vars-check"></div>';
		$output .= '<script>
		document.addEventListener("DOMContentLoaded", function() {
			var jsVars = document.getElementById("js-vars-check");
			var html = "";
			
			// Check postQuizData
			if (typeof window.postQuizData !== "undefined") {
				html += "<p style=\"color:green;\">✅ postQuizData is defined (length: " + window.postQuizData.length + ")</p>";
			} else {
				html += "<p style=\"color:red;\">❌ postQuizData is NOT defined</p>";
			}
			
			// Check NuclenSettings
			if (typeof window.NuclenSettings !== "undefined") {
				html += "<p style=\"color:green;\">✅ NuclenSettings is defined</p>";
			} else {
				html += "<p style=\"color:red;\">❌ NuclenSettings is NOT defined</p>";
			}
			
			// Check quiz container
			var quizContainer = document.getElementById("nuclen-quiz-container");
			if (quizContainer) {
				html += "<p style=\"color:green;\">✅ Quiz container found in DOM</p>";
			} else {
				html += "<p style=\"color:orange;\">⚠️ Quiz container NOT found in DOM</p>";
			}
			
			jsVars.innerHTML = html;
		});
		</script>';

		$output .= '</div>';
		return $output;
	}

	/**
	 * Debug summary data and rendering.
	 */
	public static function debug_summary(): string {
		$post_id = get_the_ID();
		$output  = '<div style="background:#f0f0f0; padding:20px; margin:20px 0; border:2px solid #333;">';
		$output .= '<h3>🔍 Nuclear Engagement Summary Debug</h3>';

		// Check post ID
		$output .= '<p><strong>Post ID:</strong> ' . ( $post_id ? $post_id : 'NONE' ) . '</p>';

		if ( ! $post_id ) {
			$output .= '<p style="color:red;">❌ No post ID found</p>';
			$output .= '</div>';
			return $output;
		}

		// Get summary data
		$summary_data = get_post_meta( $post_id, Summary_Service::META_KEY, true );

		if ( empty( $summary_data ) ) {
			$output .= '<p style="color:red;">❌ No summary data found</p>';
		} else {
			$output .= '<p style="color:green;">✅ Summary data found</p>';
			$output .= '<p><strong>Summary text:</strong> ' . esc_html( substr( $summary_data['summary'] ?? '', 0, 100 ) ) . '...</p>';

			// Show items if present
			if ( ! empty( $summary_data['items'] ) ) {
				$output .= '<p><strong>Number of items:</strong> ' . count( $summary_data['items'] ) . '</p>';
			}
		}

		$output .= '</div>';
		return $output;
	}

	/**
	 * Debug asset loading.
	 */
	public static function debug_assets(): string {
		global $wp_scripts, $wp_styles;

		$output  = '<div style="background:#f0f0f0; padding:20px; margin:20px 0; border:2px solid #333;">';
		$output .= '<h3>🔍 Nuclear Engagement Assets Debug</h3>';

		// Check if assets are enqueued
		$js_handle  = 'nuclear-engagement-front';
		$css_handle = 'nuclear-engagement';

		$js_enqueued  = isset( $wp_scripts->registered[ $js_handle ] );
		$css_enqueued = isset( $wp_styles->registered[ $css_handle ] );

		$output .= '<p><strong>JavaScript:</strong> ';
		if ( $js_enqueued ) {
			$output .= '<span style="color:green;">✅ Registered</span>';
			if ( in_array( $js_handle, $wp_scripts->queue ) ) {
				$output .= ' <span style="color:green;">✅ Enqueued</span>';
			} else {
				$output .= ' <span style="color:red;">❌ Not in queue</span>';
			}
		} else {
			$output .= '<span style="color:red;">❌ Not registered</span>';
		}
		$output .= '</p>';

		$output .= '<p><strong>CSS:</strong> ';
		if ( $css_enqueued ) {
			$output .= '<span style="color:green;">✅ Registered</span>';
			if ( in_array( $css_handle, $wp_styles->queue ) ) {
				$output .= ' <span style="color:green;">✅ Enqueued</span>';
			} else {
				$output .= ' <span style="color:red;">❌ Not in queue</span>';
			}
		} else {
			$output .= '<span style="color:red;">❌ Not registered</span>';
		}
		$output .= '</p>';

		// Check constants
		$output   .= '<h4>Constants:</h4>';
		$constants = array(
			'NUCLEN_PLUGIN_DIR',
			'NUCLEN_PLUGIN_URL',
			'NUCLEN_PLUGIN_VERSION',
			'NUCLEN_ASSET_VERSION',
		);

		foreach ( $constants as $const ) {
			$output .= '<p><strong>' . $const . ':</strong> ';
			if ( defined( $const ) ) {
				$output .= '<span style="color:green;">✅</span> ' . esc_html( constant( $const ) );
			} else {
				$output .= '<span style="color:red;">❌ NOT DEFINED</span>';
			}
			$output .= '</p>';
		}

		$output .= '</div>';
		return $output;
	}
}
