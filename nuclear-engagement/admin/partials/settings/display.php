<?php
// File: admin/partials/settings/display.php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Display tab
 *
 * @package NuclearEngagement\Admin
 */
?>
<!-- DISPLAY TAB -->
<div id="display" class="nuclen-tab-content nuclen-section" style="display:none;">
<?php
    $display_dir = plugin_dir_path( __FILE__ ) . 'display/';
    require $display_dir . 'counts.php';
    require $display_dir . 'custom-quiz.php';
    require $display_dir . 'labels.php';
    require $display_dir . 'titles.php';
    require $display_dir . 'attribution.php';
    require $display_dir . 'toc.php';
?>
</div><!-- /#display -->
