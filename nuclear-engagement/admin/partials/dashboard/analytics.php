<?php
declare(strict_types=1);
// File: admin/partials/dashboard/analytics.php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<!-- Analytics -->
<h2><?php esc_html_e( 'Analytics', 'nuclear-engagement' ); ?></h2>
<p>
    <?php
    printf(
        wp_kses(
            /* translators: %s is a link */
            __( 'Engagement analytics are available on the Nuclear Engagement web app (create a free account %s).', 'nuclear-engagement' ),
            [
                'a' => [ 'href' => [], 'target' => [] ],
            ]
        ),
        '<a href="https://app.nuclearengagement.com/signup" target="_blank">' . esc_html__( 'here', 'nuclear-engagement' ) . '</a>'
    );
    ?>
</p>
<button class="button button-secondary" onclick="window.open('https://app.nuclearengagement.com/sites', '_blank');">
    <?php esc_html_e( 'View Analytics', 'nuclear-engagement' ); ?>
</button>
