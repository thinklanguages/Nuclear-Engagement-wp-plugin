<?php
declare(strict_types=1);
/**
 * File: admin/Traits/SettingsSanitizeOptinTrait.php
 *
 * Sanitises only the Opt-In section.
 *
 * @package NuclearEngagement\Admin
 */

namespace NuclearEngagement\Admin\Traits;

trait SettingsSanitizeOptinTrait {

    /**
     * Sanitise Opt-In-related options.
     *
     * @param array $input Raw settings.
     * @return array       Clean Opt-In keys.
     */
    private function nuclen_sanitize_optin( array $input ): array {

        $allowed_optin_positions = array( 'with_results', 'before_results' );
        $optin_position          = isset( $input['optin_position'] )
            ? sanitize_text_field( $input['optin_position'] )
            : 'with_results';
        if ( ! in_array( $optin_position, $allowed_optin_positions, true ) ) {
            $optin_position = 'with_results';
        }

        $optin_mandatory = isset( $input['optin_mandatory'] ) ? (bool) $input['optin_mandatory'] : false;
        $enable_optin    = isset( $input['enable_optin'] ) ? (bool) $input['enable_optin'] : false;
        $optin_webhook   = isset( $input['optin_webhook'] ) ? esc_url_raw( trim( $input['optin_webhook'] ) ) : '';

        $optin_prompt_text = isset( $input['optin_prompt_text'] )
            ? sanitize_text_field( $input['optin_prompt_text'] )
            : 'Please enter your details to view your score:';

        $optin_button_text = isset( $input['optin_button_text'] )
            ? sanitize_text_field( $input['optin_button_text'] )
            : 'Submit';

        $optin_success_message = isset( $input['optin_success_message'] )
            ? sanitize_text_field( $input['optin_success_message'] )
            : 'Thank you, your submission was successful!';

        return array(
            /* opt-in */
            'enable_optin'          => $enable_optin,
            'optin_webhook'         => $optin_webhook,
            'optin_success_message' => $optin_success_message,
            'optin_position'        => $optin_position,
            'optin_mandatory'       => $optin_mandatory,
            'optin_prompt_text'     => $optin_prompt_text,
            'optin_button_text'     => $optin_button_text,
        );
    }
}
