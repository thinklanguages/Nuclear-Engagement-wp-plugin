<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * admin/partials/nuclen-dashboard-page.php
 *
 * This file contains the HTML markup for the Nuclear Engagement Admin Dashboard.
 * We'll fix the “No credits info returned.” by referencing `data.data.remaining_credits`.
 */

use function NuclearEngagement\nuclen_settings_bool;

// Fetch plugin setup info to decide if we show credits
$fully_setup = ( nuclen_settings_bool( 'connected', false ) && nuclen_settings_bool( 'wp_app_pass_created', false ) );

$utils = new \NuclearEngagement\Utils();
$utils->display_nuclen_page_header();
?>
<div class="wrap nuclen-container">
    <h1 class="nuclen-heading"><?php esc_html_e( 'Dashboard', 'nuclear-engagement' ); ?></h1>
    <?php
        $dash_dir = plugin_dir_path( __FILE__ ) . 'dashboard/';
        require $dash_dir . 'inventory.php';
        require $dash_dir . 'analytics.php';
        require $dash_dir . 'scheduled.php';
        require $dash_dir . 'credits.php';
    ?>
</div><!-- .wrap .nuclen-container -->
