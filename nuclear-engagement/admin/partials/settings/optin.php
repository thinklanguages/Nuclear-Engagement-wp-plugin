<?php
// File: admin/partials/settings/optin.php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Opt-In tab
 *
 * @package NuclearEngagement\Admin
 */
?>
<!-- OPT-IN TAB -->
<div id="optin" class="nuclen-tab-content nuclen-section" style="display:none;">
    <h2 class="nuclen-subheading"><?php esc_html_e( 'Email Opt-In Form', 'nuclear-engagement' ); ?> <span nuclen-tooltip="<?php esc_attr_e( 'You may need to clear page cache to see changes.', 'nuclear-engagement' ); ?>">🛈</span></h2>
    <p><?php esc_html_e( 'To collect email addresses, display an opt-in form when the quiz is completed.', 'nuclear-engagement' ); ?></p>

    <!-- Enable toggle -->
    <div class="nuclen-form-group nuclen-row">
        <label class="nuclen-label-col" for="enable_optin">
            <?php esc_html_e( 'Enable Opt-In', 'nuclear-engagement' ); ?>
            <span nuclen-tooltip="<?php esc_attr_e( 'Display an opt-in form at the end of the quiz to capture name + email.', 'nuclear-engagement' ); ?>">🛈</span>
        </label>
        <div class="nuclen-input-col">
            <input type="checkbox" name="enable_optin" id="enable_optin" value="1" <?php checked( $settings['enable_optin'], true ); ?> />
        </div>
    </div>

    <!-- Position -->
    <div class="nuclen-form-group nuclen-row">
        <label class="nuclen-label-col">
            <?php esc_html_e( 'Display Position', 'nuclear-engagement' ); ?>
        </label>
        <div class="nuclen-input-col">
            <label><input type="radio" name="nuclen_optin_position" value="with_results"  <?php checked( $settings['optin_position'], 'with_results' ); ?> /> <?php esc_html_e( 'With Results (results are displayed)', 'nuclear-engagement' ); ?></label><br/>
            <label><input type="radio" name="nuclen_optin_position" value="before_results" <?php checked( $settings['optin_position'], 'before_results' ); ?> /> <?php esc_html_e( 'Before Results (results are hidden)', 'nuclear-engagement' ); ?></label>
        </div>
    </div>

    <!-- Mandatory -->
    <div class="nuclen-form-group nuclen-row">
        <label class="nuclen-label-col" for="optin_mandatory">
            <?php esc_html_e( 'Make Opt-In Mandatory', 'nuclear-engagement' ); ?>
            <span nuclen-tooltip="<?php esc_attr_e( 'If unchecked, a “skip” link lets users view results without submitting.', 'nuclear-engagement' ); ?>">🛈</span>
        </label>
        <div class="nuclen-input-col">
            <input type="checkbox" name="optin_mandatory" id="optin_mandatory" value="1" <?php checked( $settings['optin_mandatory'], true ); ?> />
        </div>
    </div>

    <!-- Prompt text -->
    <div class="nuclen-form-group nuclen-row">
        <label class="nuclen-label-col" for="optin_prompt_text"><?php esc_html_e( 'Prompt Text Above Form', 'nuclear-engagement' ); ?></label>
        <div class="nuclen-input-col">
            <input type="text" class="nuclen-input" id="optin_prompt_text" name="optin_prompt_text" value="<?php echo esc_attr( $settings['optin_prompt_text'] ); ?>" />
        </div>
    </div>

    <!-- Button text -->
    <div class="nuclen-form-group nuclen-row">
        <label class="nuclen-label-col" for="optin_button_text"><?php esc_html_e( 'Submit Button Text', 'nuclear-engagement' ); ?></label>
        <div class="nuclen-input-col">
            <input type="text" class="nuclen-input" id="optin_button_text" name="optin_button_text" value="<?php echo esc_attr( $settings['optin_button_text'] ); ?>" />
        </div>
    </div>

    <h2 class="nuclen-subheading"><?php esc_html_e( 'Data submission', 'nuclear-engagement' ); ?></h2>
    <p><?php esc_html_e( 'Opt-in data is stored in database to be exported into a CSV file at any time. You can also send the data to your favorite ESP/CRM (ConvertKit, Mailchimp...) via a webhook (triggering an automation in Zapier, Make...).', 'nuclear-engagement' ); ?></p>

    <p>
        <?php
        printf(
            wp_kses(
                /* translators: 1: link to YouTube video */
                __( 'Learn how to set up an automation: %1$s', 'nuclear-engagement' ),
                array(
                    'a' => array(
                        'href'   => array(),
                        'target' => array(),
                        'rel'    => array(),
                    ),
                )
            ),
            '<a href="https://www.youtube.com/watch?v=z39FJEFNKVM&pp=ygUebnVjbGVhciBlbmdhZ2VtZW50IG9wdGluIGVtYWls" target="_blank" rel="noopener noreferrer">'
                . esc_html__( 'Tutorial', 'nuclear-engagement' )
                . '</a>'
        );
        ?>
    </p>

    <!-- Webhook URL -->
    <div class="nuclen-form-group nuclen-row">
        <label class="nuclen-label-col" for="optin_webhook">
            <?php esc_html_e( 'Webhook URL', 'nuclear-engagement' ); ?>
            <span nuclen-tooltip="<?php esc_attr_e( 'Paste the webhook from Make, Zapier, etc.', 'nuclear-engagement' ); ?>">🛈</span>
        </label>
        <div class="nuclen-input-col">
            <input type="url" name="optin_webhook" id="optin_webhook" class="nuclen-input" value="<?php echo esc_attr( $settings['optin_webhook'] ); ?>" />
        </div>
    </div>

    <!-- Export to CSV -->
    <div class="nuclen-form-group nuclen-row">
        <label class="nuclen-label-col"><?php esc_html_e( 'Export Opt-In Data', 'nuclear-engagement' ); ?></label>
        <div class="nuclen-input-col">

            <a href="<?php
                echo esc_url(
                    wp_nonce_url(
                        admin_url( 'admin-post.php?action=nuclen_export_optin' ),
                        'nuclen_export_optin'
                    )
                );
            ?>" class="button button-secondary" target="_blank" rel="noopener">
                <?php esc_html_e( 'Download CSV', 'nuclear-engagement' ); ?>
            </a>

        </div>
    </div>

</div><!-- /#optin -->
