<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Plugin Name:       Nuclear Engagement
 * Plugin URI:        https://www.nuclearengagement.com
 * Description:       Bulk generate engaging content for your blog posts with AI in one click.
 * Version:           1.1
 * Author:            Stefano Lodola
 * Requires at least: 5.6
 * Tested up to:      6.8
 * Requires PHP:      7.4
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       nuclear-engagement
 * Domain Path:       /
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

use NuclearEngagement\SettingsRepository;

define('NUCLEN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NUCLEN_PLUGIN_VERSION', '1.1');
define('NUCLEN_ASSET_VERSION', '250613-30');

// Load plugin textdomain
function nuclear_engagement_load_textdomain() {
    load_plugin_textdomain(
        'nuclear-engagement',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
}
add_action('init', 'nuclear_engagement_load_textdomain');

// Initialize SettingsRepository with default values
add_action('init', function() {
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
    
    // Initialize the SettingsRepository with defaults
    SettingsRepository::get_instance($defaults);
}, 20); // Higher priority to ensure translations are loaded first

/**
 * Optimized autoloader for plugin classes with class map caching
 */
spl_autoload_register(function ($class) {
    static $classMap = null;
    static $dynamicPrefixes = null;
    
    $prefix = 'NuclearEngagement\\';
    
    // Early exit if not our namespace
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    
    // Initialize class map on first use
    if ($classMap === null) {
        $classMap = [
            // Core classes
            'NuclearEngagement\\Plugin' => '/includes/Plugin.php',
            'NuclearEngagement\\Loader' => '/includes/Loader.php',
            'NuclearEngagement\\Utils' => '/includes/Utils.php',
            'NuclearEngagement\\Activator' => '/includes/Activator.php',
            'NuclearEngagement\\Deactivator' => '/includes/Deactivator.php',
            'NuclearEngagement\\SettingsRepository' => '/includes/SettingsRepository.php',
            'NuclearEngagement\\Container' => '/includes/Container.php',
            'NuclearEngagement\\Defaults' => '/includes/Defaults.php',
            'NuclearEngagement\\OptinData' => '/includes/OptinData.php',
            'NuclearEngagement\\MetaRegistration' => '/includes/MetaRegistration.php',

            // Admin classes
            'NuclearEngagement\\Admin\\Admin' => '/admin/Admin.php',
            'NuclearEngagement\\Admin\\Dashboard' => '/admin/Dashboard.php',
            'NuclearEngagement\\Admin\\Onboarding' => '/admin/Onboarding.php',
            'NuclearEngagement\\Admin\\Settings' => '/admin/Settings.php',
            'NuclearEngagement\\Admin\\Setup' => '/admin/Setup.php',
            
            // Admin Controllers
            'NuclearEngagement\\Admin\\Controller\\Ajax\\GenerateController' => '/admin/Controller/Ajax/GenerateController.php',
            'NuclearEngagement\\Admin\\Controller\\Ajax\\UpdatesController' => '/admin/Controller/Ajax/UpdatesController.php',
            'NuclearEngagement\\Admin\\Controller\\Ajax\\PointerController' => '/admin/Controller/Ajax/PointerController.php',
            'NuclearEngagement\\Admin\\Controller\\Ajax\\PostsCountController' => '/admin/Controller/Ajax/PostsCountController.php',
            'NuclearEngagement\\Admin\\Controller\\Ajax\\BaseController' => '/admin/Controller/Ajax/BaseController.php',
            
            // Front classes
            'NuclearEngagement\\Front\\FrontClass' => '/front/FrontClass.php',
            'NuclearEngagement\\Front\\Controller\\Rest\\ContentController' => '/front/Controller/Rest/ContentController.php',
            
            // Services
            'NuclearEngagement\\Services\\GenerationService' => '/includes/Services/GenerationService.php',
            'NuclearEngagement\\Services\\RemoteApiService' => '/includes/Services/RemoteApiService.php',
            'NuclearEngagement\\Services\\ContentStorageService' => '/includes/Services/ContentStorageService.php',
            'NuclearEngagement\\Services\\PointerService' => '/includes/Services/PointerService.php',
            'NuclearEngagement\\Services\\PostsQueryService' => '/includes/Services/PostsQueryService.php',
            'NuclearEngagement\\Services\\AutoGenerationService' => '/includes/Services/AutoGenerationService.php',
            
            // Requests/Responses
            'NuclearEngagement\\Requests\\ContentRequest' => '/includes/Requests/ContentRequest.php',
            'NuclearEngagement\\Requests\\GenerateRequest' => '/includes/Requests/GenerateRequest.php',
            'NuclearEngagement\\Requests\\PostsCountRequest' => '/includes/Requests/PostsCountRequest.php',
            'NuclearEngagement\\Requests\\UpdatesRequest' => '/includes/Requests/UpdatesRequest.php',
            'NuclearEngagement\\Responses\\GenerationResponse' => '/includes/Responses/GenerationResponse.php',
            'NuclearEngagement\\Responses\\UpdatesResponse' => '/includes/Responses/UpdatesResponse.php',

            // Admin traits
            'NuclearEngagement\\Admin\\Admin_Ajax' => '/admin/trait-admin-ajax.php',
            'NuclearEngagement\\Admin\\Admin_Assets' => '/admin/trait-admin-assets.php',
            'NuclearEngagement\\Admin\\Admin_AutoGenerate' => '/admin/trait-admin-autogenerate.php',
            'NuclearEngagement\\Admin\\Admin_Menu' => '/admin/trait-admin-menu.php',
            'NuclearEngagement\\Admin\\Admin_Quiz_Metabox' => '/admin/trait-admin-metabox-quiz.php',
            'NuclearEngagement\\Admin\\Admin_Summary_Metabox' => '/admin/trait-admin-metabox-summary.php',
            'NuclearEngagement\\Admin\\Admin_Metaboxes' => '/admin/trait-admin-metaboxes.php',
            'NuclearEngagement\\Admin\\SettingsPageCustomCSSTrait' => '/admin/trait-settings-custom-css.php',
            'NuclearEngagement\\Admin\\SettingsPageLoadTrait' => '/admin/trait-settings-page-load.php',
            'NuclearEngagement\\Admin\\SettingsPageSaveTrait' => '/admin/trait-settings-page-save.php',
            'NuclearEngagement\\Admin\\SettingsSanitizeCoreTrait' => '/admin/trait-settings-sanitize-core.php',
            'NuclearEngagement\\Admin\\SettingsSanitizeGeneralTrait' => '/admin/trait-settings-sanitize-general.php',
            'NuclearEngagement\\Admin\\SettingsSanitizeOptinTrait' => '/admin/trait-settings-sanitize-optin.php',
            'NuclearEngagement\\Admin\\SettingsSanitizeStyleTrait' => '/admin/trait-settings-sanitize-style.php',
            'NuclearEngagement\\Admin\\SettingsColorPickerTrait' => '/admin/SettingsColorPickerTrait.php',
            'NuclearEngagement\\Admin\\SettingsPageTrait' => '/admin/SettingsPageTrait.php',
            'NuclearEngagement\\Admin\\SettingsSanitizeTrait' => '/admin/SettingsSanitizeTrait.php',
            'NuclearEngagement\\Admin\\SetupHandlersTrait' => '/admin/SetupHandlersTrait.php',

            // Front traits
            'NuclearEngagement\\Front\\AssetsTrait' => '/front/traits/AssetsTrait.php',
            'NuclearEngagement\\Front\\RestTrait' => '/front/traits/RestTrait.php',
            'NuclearEngagement\\Front\\ShortcodesTrait' => '/front/traits/ShortcodesTrait.php',
        ];
        
        // Dynamic prefixes for trait loading
        $dynamicPrefixes = [
            'Admin\\' => '/admin/',
            'Front\\' => '/front/',
            'Services\\' => '/includes/Services/',
            'Requests\\' => '/includes/Requests/',
            'Responses\\' => '/includes/Responses/',
        ];
    }
    
    // Check if we have an exact match in the class map
    if (isset($classMap[$class])) {
        $file = __DIR__ . $classMap[$class];
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
    
    // Fall back to dynamic loading for classes not in the map
    $relative_class = substr($class, strlen($prefix));
    
    // Determine subpath based on namespace structure
    $subpath = '/includes/';
    foreach ($dynamicPrefixes as $nsPrefix => $path) {
        if (strpos($relative_class, $nsPrefix) === 0) {
            $subpath = $path;
            $relative_class = substr($relative_class, strlen($nsPrefix));
            break;
        }
    }
    
    $file = __DIR__ . $subpath . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

/* ──────────────────────────────────────────────────────────
 * Activation / deactivation hooks
 * ────────────────────────────────────────────────────────── */
function nuclear_engagement_activate_plugin() {
    // Load default settings from central Defaults class
    $defaults = NuclearEngagement\Defaults::nuclen_get_default_settings();

    // Initialize SettingsRepository with defaults
    $settings = SettingsRepository::get_instance($defaults);
    
    // Run activation
    NuclearEngagement\Activator::nuclen_activate($settings);
}
register_activation_hook(__FILE__, 'nuclear_engagement_activate_plugin');

function nuclear_engagement_deactivate_plugin() {
    // Get existing instance or create with empty defaults
    $settings = SettingsRepository::get_instance();
    NuclearEngagement\Deactivator::nuclen_deactivate($settings);
}
register_deactivation_hook(__FILE__, 'nuclear_engagement_deactivate_plugin');

/**
 * Redirect to Setup screen right after activation.
 */
function nuclear_engagement_redirect_on_activation() {
	if ( get_transient( 'nuclen_plugin_activation_redirect' ) ) {
		delete_transient( 'nuclen_plugin_activation_redirect' );
		if ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=nuclear-engagement-setup' ) );
			exit;
		}
	}
}
add_action( 'admin_init', 'nuclear_engagement_redirect_on_activation' );

/* ──────────────────────────────────────────────────────────
 * ❷ Old meta‑key migration (unchanged)
 * ────────────────────────────────────────────────────────── */
/**
 * Updates all post‑meta keys from the legacy "ne‑*" names to "nuclen‑*".
 */
function nuclen_update_migrate_post_meta() {

	if ( get_option( 'nuclen_meta_migration_done' ) ) {
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

	update_option( 'nuclen_meta_migration_done', true );
}
add_action( 'admin_init', 'nuclen_update_migrate_post_meta', 20 );

/* ──────────────────────────────────────────────────────────
 * ❸ Run the plugin
 * ────────────────────────────────────────────────────────── */
function nuclear_engagement_run_plugin() {
	// Initialize meta registration
	NuclearEngagement\MetaRegistration::init();
	
	$plugin = new NuclearEngagement\Plugin();
	$plugin->nuclen_run();
}
nuclear_engagement_run_plugin();