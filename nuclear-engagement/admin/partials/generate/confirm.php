    <!-- Step 2 Section (hidden by default) -->
    <div id="nuclen-step-2" class="nuclen-section nuclen-hidden">
        <h2 class="nuclen-subheading"><?php esc_html_e( '2. Confirm and Generate', 'nuclear-engagement' ); ?></h2>

        <!-- New line to display credit info -->
        <p id="nuclen-credits-info" style="font-weight: bold; margin-bottom: 1em;"></p>

        <p id="nuclen-posts-count"></p>
        <form id="nuclen-generate-form" method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
            <?php
            wp_nonce_field( 'nuclen_admin_ajax_nonce', 'security' );
            ?>
            <input type="hidden" name="action" value="nuclen_trigger_generation" />
            <!-- Hidden fields to preserve user selections -->
            <input type="hidden" name="nuclen_selected_post_ids" id="nuclen_selected_post_ids" />
            <input type="hidden" name="nuclen_selected_generate_workflow" id="nuclen_selected_generate_workflow" />
            <input type="hidden" name="nuclen_selected_summary_format" id="nuclen_selected_summary_format" />
            <input type="hidden" name="nuclen_selected_summary_length" id="nuclen_selected_summary_length" />
            <input type="hidden" name="nuclen_selected_summary_number_of_items" id="nuclen_selected_summary_number_of_items" />
            <input type="hidden" name="nuclen_selected_post_status" id="nuclen_selected_post_status" />
            <input type="hidden" name="nuclen_selected_post_type" id="nuclen_selected_post_type" />

            <button type="submit" id="nuclen-submit-btn" class="button button-primary nuclen-button-primary">
                <?php esc_html_e( 'Generate Content', 'nuclear-engagement' ); ?>
            </button>
            <button type="button" id="nuclen-go-back-btn" class="button">
                <?php esc_html_e( 'Back to post selection', 'nuclear-engagement' ); ?>
            </button>
        </form>
    </div>
