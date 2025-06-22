<?php
use NuclearEngagement\SettingsRepository;
use NuclearEngagement\Defaults;
use NuclearEngagement\Activator;
use NuclearEngagement\Deactivator;
use NuclearEngagement\MetaRegistration;
use NuclearEngagement\AssetVersions;
use NuclearEngagement\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

define('NUCLEN_PLUGIN_DIR', plugin_dir_path(NUCLEN_PLUGIN_FILE));

if ( ! defined( 'NUCLEN_PLUGIN_VERSION' ) ) {
    if ( ! function_exists( 'get_plugin_data' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $data = get_plugin_data( NUCLEN_PLUGIN_FILE );
    define( 'NUCLEN_PLUGIN_VERSION', $data['Version'] );
}

define('NUCLEN_ASSET_VERSION', '250613-30');

require_once NUCLEN_PLUGIN_DIR . 'includes/autoload.php';
require_once NUCLEN_PLUGIN_DIR . 'includes/constants.php';

AssetVersions::init();

function nuclear_engagement_load_textdomain() {
    load_plugin_textdomain(
        'nuclear-engagement',
        false,
        dirname(plugin_basename(NUCLEN_PLUGIN_FILE)) . '/languages/'
    );
}
add_action('init', 'nuclear_engagement_load_textdomain');

add_action('init', function () {
    $defaults = [
        'theme' => 'bright',
        'font_size' => '16',
        'font_color' => '#000000',
        'bg_color' => '#ffffff',
        'border_color' => '#000000',
        'border_style' => 'solid',
        'border_width' => '1',
        'quiz_title' => __('Test your knowledge', 'nuclear-engagement'),
        'summary_title' => __('Key Facts', 'nuclear-engagement'),
        'show_attribution' => false,
        'display_summary' => 'none',
        'display_quiz' => 'none',
        'display_toc' => 'manual',
    ];

    SettingsRepository::get_instance($defaults);
}, 20);

function nuclear_engagement_activate_plugin() {
    $defaults = Defaults::nuclen_get_default_settings();
    $settings = SettingsRepository::get_instance($defaults);
    Activator::nuclen_activate($settings);
}
register_activation_hook(NUCLEN_PLUGIN_FILE, 'nuclear_engagement_activate_plugin');

function nuclear_engagement_deactivate_plugin() {
    $settings = SettingsRepository::get_instance();
    Deactivator::nuclen_deactivate($settings);
}
register_deactivation_hook(NUCLEN_PLUGIN_FILE, 'nuclear_engagement_deactivate_plugin');

function nuclear_engagement_redirect_on_activation() {
    if (get_transient('nuclen_plugin_activation_redirect')) {
        delete_transient('nuclen_plugin_activation_redirect');
        if (is_admin() && !(defined('DOING_AJAX') && DOING_AJAX)) {
            wp_safe_redirect(admin_url('admin.php?page=nuclear-engagement-setup'));
            exit;
        }
    }
}
add_action('admin_init', 'nuclear_engagement_redirect_on_activation');

function nuclen_update_migrate_post_meta() {
    if (get_option('nuclen_meta_migration_done')) {
        return;
    }

    global $wpdb;

    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s",
            'nuclen-summary-data',
            'ne-summary-data'
        )
    );
    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s",
            'nuclen-quiz-data',
            'ne-quiz-data'
        )
    );

    update_option('nuclen_meta_migration_done', true);
}
add_action('admin_init', 'nuclen_update_migrate_post_meta', 20);

function nuclear_engagement_run_plugin() {
    MetaRegistration::init();
    $plugin = new Plugin();
    $plugin->nuclen_run();
}

nuclear_engagement_run_plugin();
