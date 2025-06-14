<?php
/**
 * File: includes/MetaRegistration.php
 *
 * Register meta keys for better query performance
 *
 * @package NuclearEngagement
 */

namespace NuclearEngagement;

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
        add_action( 'init', [ self::class, 'register_meta_keys' ], 5 );
    }

    /**
     * Register all plugin meta keys
     */
    public static function register_meta_keys(): void {
        // Get allowed post types from settings
        $settings = \NuclearEngagement\Container::getInstance()->get('settings');
        $post_types = $settings->get_array( 'generation_post_types', [ 'post' ] );

        // Register quiz data meta
        foreach ( $post_types as $post_type ) {
            register_post_meta( $post_type, 'nuclen-quiz-data', [
                'type' => 'string',
                'description' => 'Nuclear Engagement quiz data',
                'single' => true,
                'show_in_rest' => false,
                'sanitize_callback' => [ self::class, 'sanitize_quiz_data' ],
                'auth_callback' => [ self::class, 'auth_callback' ]
            ] );

            register_post_meta( $post_type, 'nuclen_quiz_protected', [
                'type' => 'boolean',
                'description' => 'Nuclear Engagement quiz protection flag',
                'single' => true,
                'show_in_rest' => false,
                'default' => false,
                'sanitize_callback' => 'rest_sanitize_boolean',
                'auth_callback' => [ self::class, 'auth_callback' ]
            ] );

            // Register summary data meta
            register_post_meta( $post_type, 'nuclen-summary-data', [
                'type' => 'string',
                'description' => 'Nuclear Engagement summary data',
                'single' => true,
                'show_in_rest' => false,
                'sanitize_callback' => [ self::class, 'sanitize_summary_data' ],
                'auth_callback' => [ self::class, 'auth_callback' ]
            ] );

            register_post_meta( $post_type, 'nuclen_summary_protected', [
                'type' => 'boolean',
                'description' => 'Nuclear Engagement summary protection flag',
                'single' => true,
                'show_in_rest' => false,
                'default' => false,
                'sanitize_callback' => 'rest_sanitize_boolean',
                'auth_callback' => [ self::class, 'auth_callback' ]
            ] );
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
        $sanitized = [
            'date' => '',
            'questions' => []
        ];

        if ( isset( $meta_value['date'] ) ) {
            $sanitized['date'] = sanitize_text_field( $meta_value['date'] );
        }

        if ( isset( $meta_value['questions'] ) && is_array( $meta_value['questions'] ) ) {
            foreach ( $meta_value['questions'] as $question ) {
                if ( is_array( $question ) ) {
                    $sanitized['questions'][] = [
                        'question' => wp_kses_post( $question['question'] ?? '' ),
                        'answers' => array_map( 'wp_kses_post', (array) ( $question['answers'] ?? [] ) ),
                        'explanation' => wp_kses_post( $question['explanation'] ?? '' )
                    ];
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

        $allowed_html = [
            'a' => ['href' => [], 'title' => [], 'target' => []],
            'br' => [],
            'em' => [],
            'strong' => [],
            'p' => [],
            'ul' => [],
            'ol' => [],
            'li' => [],
            'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [],
            'div' => ['class' => []],
            'span' => ['class' => []],
        ];

        return [
            'date' => sanitize_text_field( $meta_value['date'] ?? '' ),
            'summary' => wp_kses( $meta_value['summary'] ?? '', $allowed_html )
        ];
    }

    /**
     * Authorization callback for meta updates
     *
     * @param bool $allowed
     * @param string $meta_key
     * @param int $object_id
     * @param int $user_id
     * @param string $cap
     * @param array $caps
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