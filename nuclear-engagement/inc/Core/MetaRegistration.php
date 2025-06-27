<?php
declare(strict_types=1);
/**
 * File: includes/MetaRegistration.php
 *
 * Register meta keys for better query performance
 *
 * @package NuclearEngagement
 */

namespace NuclearEngagement\Core;

use function NuclearEngagement\nuclen_settings_array;
use NuclearEngagement\Modules\Summary\Summary_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MetaRegistration
 *
 * Handles registration of post meta keys for improved query performance
 */
class MetaRegistration {

	/**
	 * Initialize meta registration
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'register_meta_keys' ), 5 );
	}

	/**
	 * Register all plugin meta keys
	 */
	public static function register_meta_keys(): void {
		// Get allowed post types from settings
		$post_types = nuclen_settings_array( 'generation_post_types', array( 'post' ) );

		// Register quiz data meta
		foreach ( $post_types as $post_type ) {
			register_post_meta(
				$post_type,
				'nuclen-quiz-data',
				array(
					'type'              => 'string',
					'description'       => 'Nuclear Engagement quiz data',
					'single'            => true,
					'show_in_rest'      => false,
					'sanitize_callback' => array( self::class, 'sanitize_quiz_data' ),
					'auth_callback'     => array( self::class, 'auth_callback' ),
				)
			);

			register_post_meta(
				$post_type,
				'nuclen_quiz_protected',
				array(
					'type'              => 'boolean',
					'description'       => 'Nuclear Engagement quiz protection flag',
					'single'            => true,
					'show_in_rest'      => false,
					'default'           => false,
					'sanitize_callback' => 'rest_sanitize_boolean',
					'auth_callback'     => array( self::class, 'auth_callback' ),
				)
			);

			// Register summary data meta
			register_post_meta(
				$post_type,
				Summary_Service::META_KEY,
				array(
					'type'              => 'string',
					'description'       => 'Nuclear Engagement summary data',
					'single'            => true,
					'show_in_rest'      => false,
					'sanitize_callback' => array( self::class, 'sanitize_summary_data' ),
					'auth_callback'     => array( self::class, 'auth_callback' ),
				)
			);

			register_post_meta(
				$post_type,
				Summary_Service::PROTECTED_KEY,
				array(
					'type'              => 'boolean',
					'description'       => 'Nuclear Engagement summary protection flag',
					'single'            => true,
					'show_in_rest'      => false,
					'default'           => false,
					'sanitize_callback' => 'rest_sanitize_boolean',
					'auth_callback'     => array( self::class, 'auth_callback' ),
				)
			);
		}
	}

	/**
	 * Sanitize quiz data before saving
	 *
	 * @param mixed $meta_value
	 * @return mixed
	 */
	public static function sanitize_quiz_data( $meta_value ) {
		if ( ! is_array( $meta_value ) ) {
			return '';
		}

		// Ensure required structure
		$sanitized = array(
			'date'      => '',
			'questions' => array(),
		);

		if ( isset( $meta_value['date'] ) ) {
			$sanitized['date'] = sanitize_text_field( $meta_value['date'] );
		}

		if ( isset( $meta_value['questions'] ) && is_array( $meta_value['questions'] ) ) {
			foreach ( $meta_value['questions'] as $question ) {
				if ( is_array( $question ) ) {
					$sanitized['questions'][] = array(
						'question'    => wp_kses_post( $question['question'] ?? '' ),
						'answers'     => array_map( 'wp_kses_post', (array) ( $question['answers'] ?? array() ) ),
						'explanation' => wp_kses_post( $question['explanation'] ?? '' ),
					);
				}
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize summary data before saving
	 *
	 * @param mixed $meta_value
	 * @return mixed
	 */
	public static function sanitize_summary_data( $meta_value ) {
		if ( ! is_array( $meta_value ) ) {
			return '';
		}

		$allowed_html = array(
			'a'      => array(
				'href'   => array(),
				'title'  => array(),
				'target' => array(),
			),
			'br'     => array(),
			'em'     => array(),
			'strong' => array(),
			'p'      => array(),
			'ul'     => array(),
			'ol'     => array(),
			'li'     => array(),
			'h1'     => array(),
			'h2'     => array(),
			'h3'     => array(),
			'h4'     => array(),
			'div'    => array( 'class' => array() ),
			'span'   => array( 'class' => array() ),
		);

		return array(
			'date'    => sanitize_text_field( $meta_value['date'] ?? '' ),
			'summary' => wp_kses( $meta_value['summary'] ?? '', $allowed_html ),
		);
	}

	/**
	 * Authorization callback for meta updates
	 *
	 * @param bool   $allowed
	 * @param string $meta_key
	 * @param int    $object_id
	 * @param int    $user_id
	 * @param string $cap
	 * @param array  $caps
	 * @return bool
	 */
	public static function auth_callback( $allowed, $meta_key, $object_id, $user_id, $cap, $caps ) {
		// Check if user can edit this post
		if ( ! user_can( $user_id, 'edit_post', $object_id ) ) {
			return false;
		}

		return $allowed;
	}
}
