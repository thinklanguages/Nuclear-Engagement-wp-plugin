<?php
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

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait AssetsTrait {

    /* ────────────────────────────
       STYLES
    ──────────────────────────── */
    public function wp_enqueue_styles() {

        /* Base CSS */
        wp_enqueue_style(
            $this->plugin_name,
            plugin_dir_url( __FILE__ ) . '../css/nuclen-front.css',
            array(),
            filemtime( plugin_dir_path( __FILE__ ) . '../css/nuclen-front.css' ),
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
        } elseif ( $theme_choice === 'dark' ) {
            $theme_url = plugin_dir_url( __FILE__ ) . '../css/nuclen-theme-dark.css';
        } elseif ( $theme_choice === 'custom' ) {
            $css_info  = \NuclearEngagement\Utils::nuclen_get_custom_css_info();
            $theme_url = $css_info['url'];
        } else {
            $theme_url = plugin_dir_url( __FILE__ ) . '../css/nuclen-theme-bright.css';
        }

        wp_enqueue_style(
            $this->plugin_name . '-theme',
            $theme_url,
            array(),
            filemtime( str_replace( content_url(), WP_CONTENT_DIR, $theme_url ) ),
            'all'
        );
    }

    /* ────────────────────────────
       SCRIPTS
    ──────────────────────────── */
    public function wp_enqueue_scripts() {

        /* Main bundle */
        wp_enqueue_script(
            $this->plugin_name . '-front',
            plugin_dir_url( dirname( __FILE__ ) ) . 'js/nuclen-front.js',
            array(),
            NUCLEN_ASSET_VERSION,
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
    }
}
