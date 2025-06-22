<?php
declare(strict_types=1);
/**
 * File: front/traits/assets-trait.php
 *
 * Trait: AssetsTrait
 *
 * Handles front-end CSS & JS enqueues and localisation.
 *
 * Host class must expose `$plugin_name` and `nuclen_get_settings_repository()`.
 *
 * @package NuclearEngagement\Front
 */

namespace NuclearEngagement\Front;

use NuclearEngagement\AssetVersions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait AssetsTrait {

    /**
     * Determine if front-end assets should load on the current request.
     *
     * @return bool
     */
    private function should_load_assets(): bool {
        if ( is_admin() || ! is_singular() ) {
            return false;
        }

        $post_id = get_the_ID();
        if ( ! $post_id ) {
            return false;
        }

        $post    = get_post( $post_id );
        $content = $post ? $post->post_content : '';

        if ( has_shortcode( $content, 'nuclear_engagement_quiz' ) || has_shortcode( $content, 'nuclear_engagement_summary' ) ) {
            return true;
        }

        $settings_repo   = $this->nuclen_get_settings_repository();
        $display_quiz    = $settings_repo->get( 'display_quiz', 'manual' );
        $display_summary = $settings_repo->get( 'display_summary', 'manual' );

        if ( $display_quiz !== 'manual' && $display_quiz !== 'none' ) {
            $quiz_meta = maybe_unserialize( get_post_meta( $post_id, 'nuclen-quiz-data', true ) );
            if ( is_array( $quiz_meta ) && ! empty( $quiz_meta['questions'] ) ) {
                return true;
            }
        }

        if ( $display_summary !== 'manual' && $display_summary !== 'none' ) {
            $summary_meta = get_post_meta( $post_id, 'nuclen-summary-data', true );
            if ( is_array( $summary_meta ) && ! empty( trim( $summary_meta['summary'] ?? '' ) ) ) {
                return true;
            }
        }

        return false;
    }

    /* ────────────────────────────
       STYLES
    ──────────────────────────── */
    public function wp_enqueue_styles() {
        if ( ! $this->should_load_assets() ) {
            return;
        }

        /* Base CSS */
        wp_enqueue_style(
            $this->plugin_name,
            plugin_dir_url( __FILE__ ) . '../css/nuclen-front.css',
            array(),
            AssetVersions::get( 'front_css' ),
            'all'
        );

        /* Theme CSS (bright / dark / custom / none) */
        $settings_repo = $this->nuclen_get_settings_repository();
        $theme_choice = $settings_repo->get( 'theme', 'bright' );

        if ( $theme_choice === 'none' ) {
            return;
        }

        if ( $theme_choice === 'bright' ) {
            $theme_url = plugin_dir_url( __FILE__ ) . '../css/nuclen-theme-bright.css';
            $theme_v   = AssetVersions::get( 'theme_bright_css' );
        } elseif ( $theme_choice === 'dark' ) {
            $theme_url = plugin_dir_url( __FILE__ ) . '../css/nuclen-theme-dark.css';
            $theme_v   = AssetVersions::get( 'theme_dark_css' );
        } elseif ( $theme_choice === 'custom' ) {
            $css_info  = \NuclearEngagement\Utils::nuclen_get_custom_css_info();
            $theme_url = $css_info['url'];
            $theme_v   = get_option( 'nuclen_custom_css_version', AssetVersions::get( 'theme_bright_css' ) );
        } else {
            $theme_url = plugin_dir_url( __FILE__ ) . '../css/nuclen-theme-bright.css';
            $theme_v   = AssetVersions::get( 'theme_bright_css' );
        }

        wp_enqueue_style(
            $this->plugin_name . '-theme',
            $theme_url,
            array(),
            $theme_v,
            'all'
        );
    }

    /* ────────────────────────────
       SCRIPTS
    ──────────────────────────── */
    public function wp_enqueue_scripts() {
        if ( ! $this->should_load_assets() ) {
            return;
        }

        /* Main bundle */
        wp_enqueue_script(
            $this->plugin_name . '-front',
            plugin_dir_url( dirname( __FILE__ ) ) . 'js/nuclen-front.js',
            array(),
            AssetVersions::get( 'front_js' ),
            true
        );

        $settings_repo = $this->nuclen_get_settings_repository();

        /* ───── Inline scalars (booleans & strings) ───── */
        $inline_js = '';
        $inline_js .= 'var NuclenOptinEnabled  = ' . ( $settings_repo->get( 'enable_optin', false ) ? 'true' : 'false' ) . ";\n";

        $raw_mandatory   = $settings_repo->get( 'optin_mandatory', false );
        $optin_mandatory = ( $raw_mandatory === true || $raw_mandatory === 1 || $raw_mandatory === '1' );
        $inline_js .= 'var NuclenOptinMandatory = ' . ( $optin_mandatory ? 'true' : 'false' ) . ";\n";

        $inline_js .= 'var NuclenOptinPosition = '        . json_encode( $settings_repo->get( 'optin_position', 'with_results' ) ) . ";\n";
        $inline_js .= 'var NuclenOptinPromptText = '      . json_encode( $settings_repo->get( 'optin_prompt_text', 'Please enter your details to view your score:' ) ) . ";\n";
        $inline_js .= 'var NuclenOptinButtonText = '      . json_encode( $settings_repo->get( 'optin_button_text', 'Submit' ) ) . ";\n";
        $inline_js .= 'var NuclenOptinWebhook = '         . json_encode( $settings_repo->get( 'optin_webhook', '' ) ) . ";\n";
        $inline_js .= 'var NuclenOptinSuccessMessage = '  . json_encode( $settings_repo->get( 'optin_success_message', '' ) ) . ";\n";
        $inline_js .= 'var NuclenCustomQuizHtmlAfter = '  . json_encode( $settings_repo->get( 'custom_quiz_html_after', '' ) ) . ";\n";

        wp_add_inline_script( $this->plugin_name . '-front', $inline_js, 'before' );

        /* ► NEW ◄ – endpoint & nonce for AJAX opt-in storage */
        wp_localize_script(
            $this->plugin_name . '-front',
            'NuclenOptinAjax',
            array(
                'url'   => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'nuclen_optin_nonce' ),
            )
        );

        /* Per-post quiz data */
        $post_id   = get_the_ID();
        $quiz_meta = maybe_unserialize( get_post_meta( $post_id, 'nuclen-quiz-data', true ) );
        $questions = ( is_array( $quiz_meta ) && isset( $quiz_meta['questions'] ) ) ? $quiz_meta['questions'] : array();
        wp_localize_script( $this->plugin_name . '-front', 'postQuizData', $questions );

        /* Numeric settings */
        wp_localize_script(
            $this->plugin_name . '-front',
            'NuclenSettings',
            array(
                'questions_per_quiz'   => $settings_repo->get_int( 'questions_per_quiz', 10 ),
                'answers_per_question' => $settings_repo->get_int( 'answers_per_question', 4 ),
            )
        );

        /* Translatable strings for the quiz */
        $ne_strings = array(
            'retake_test'   => $settings_repo->get( 'quiz_label_retake_test', __( 'Retake Test', 'nuclear-engagement' ) ),
            'your_score'    => $settings_repo->get( 'quiz_label_your_score', __( 'Your Score', 'nuclear-engagement' ) ),
            'perfect'       => $settings_repo->get( 'quiz_label_perfect', __( 'Perfect!', 'nuclear-engagement' ) ),
            'well_done'     => $settings_repo->get( 'quiz_label_well_done', __( 'Well done!', 'nuclear-engagement' ) ),
            'retake_prompt' => $settings_repo->get( 'quiz_label_retake_prompt', __( 'Why not retake the test?', 'nuclear-engagement' ) ),
            'correct'       => $settings_repo->get( 'quiz_label_correct', __( 'Correct:', 'nuclear-engagement' ) ),
            'your_answer'   => $settings_repo->get( 'quiz_label_your_answer', __( 'Your answer:', 'nuclear-engagement' ) ),
        );
        wp_localize_script( $this->plugin_name . '-front', 'NuclenStrings', $ne_strings );
    }
}
