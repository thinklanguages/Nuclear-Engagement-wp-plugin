<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * File: admin/partials/nuclen-admin-generate.php
 * Implementation of changes required by WordPress.org guidelines.
 *
 * - Now includes a <p id="nuclen-credits-info"> in Step 2 to show “This will consume X credits.
 *   You have Y left.”
 * - All other code remains the same, except for that inserted line.
 *
 * @package NuclearEngagement\Admin
 */

// Retrieve the plugin settings
$settings = get_option( 'nuclear_engagement_settings', array() );

// The user-selected post types
$allowed_post_types = $settings['generation_post_types'] ?? array( 'post' );

$statuses   = get_post_stati( array( 'show_in_admin_status_list' => true ), 'objects' );
$categories = get_categories( array( 'hide_empty' => false ) );
$authors    = get_users( array( 'who' => 'authors' ) );

// We'll still fetch all public post types, but only show the allowed ones
$post_types = get_post_types( array( 'public' => true ), 'objects' );

$utils = new \NuclearEngagement\Utils();
$utils->display_nuclen_page_header();
?>

<div class="wrap nuclen-container">

    <div id="nuclen-progress-bar" class="nuclen-step-bar">
        <div id="nuclen-step-bar-1" class="nuclen-step-bar-step nuclen-step-todo"><?php esc_html_e( '1. Select', 'nuclear-engagement' ); ?></div>
        <div id="nuclen-step-bar-2" class="nuclen-step-bar-step nuclen-step-todo"><?php esc_html_e( '2. Confirm', 'nuclear-engagement' ); ?></div>
        <div id="nuclen-step-bar-3" class="nuclen-step-bar-step nuclen-step-todo"><?php esc_html_e( '3. Generate', 'nuclear-engagement' ); ?></div>
        <div id="nuclen-step-bar-4" class="nuclen-step-bar-step nuclen-step-todo"><?php esc_html_e( '4. Save', 'nuclear-engagement' ); ?></div>
    </div>

    <h1 class="nuclen-heading"><?php esc_html_e( 'Generate Content', 'nuclear-engagement' ); ?></h1>
<?php
        $generate_dir = plugin_dir_path( __FILE__ ) . 'generate/';
        require $generate_dir . 'filters.php';
        require $generate_dir . 'confirm.php';
        require $generate_dir . 'progress.php';
?>

</div>
