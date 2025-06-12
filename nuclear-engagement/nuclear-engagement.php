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
 * Requires at least: 6.5
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
use NuclearEngagement\ErrorHandler;

define('NUCLEN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NUCLEN_PLUGIN_VERSION', '1.1');
define('NUCLEN_ASSET_VERSION', '250612-1');

// Load plugin textdomain
function nuclear_engagement_load_textdomain() {
    load_plugin_textdomain(
        'nuclear-engagement',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
}
add_action('plugins_loaded', 'nuclear_engagement_load_textdomain');

// Initialize SettingsRepository with default values
add_action('plugins_loaded', function() {
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
            'NuclearEngagement\\ErrorHandler' => '/includes/ErrorHandler.php',
            'NuclearEngagement\\Activator' => '/includes/Activator.php',
            'NuclearEngagement\\Deactivator' => '/includes/Deactivator.php',
            'NuclearEngagement\\SettingsRepository' => '/includes/SettingsRepository.php',
            'NuclearEngagement\\Container' => '/includes/Container.php',
            'NuclearEngagement\\Defaults' => '/includes/Defaults.php',
            'NuclearEngagement\\OptinData' => '/includes/OptinData.php',
            'NuclearEngagement\\MetaRegistration' => '/includes/MetaRegistration.php',
            'NuclearEngagement\\Includes\\BaseAjaxController' => '/includes/BaseAjaxController.php',
            
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

    // Ensure opt-in table exists so inserts can skip this check
    NuclearEngagement\OptinData::maybe_create_table();

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
 * ❶ MIGRATION: Convert legacy WP Application Password → new plugin password
 * ────────────────────────────────────────────────────────── */
function nuclen_migrate_app_password() {

	// Already migrated?
	if ( get_option( 'nuclen_app_pass_migration_done' ) ) {
		return;
	}

	// Only run when an admin‑area page loads and the current user can manage options.
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$app_setup = get_option(
		'nuclear_engagement_setup',
		array(
			'api_key'             => '',
			'connected'           => false,
			'wp_app_pass_created' => false,
			'plugin_password'     => '',
		)
	);

	// We need to migrate if the old flag is set, but no plugin_password yet.
	if ( ! empty( $app_setup['wp_app_pass_created'] ) && ! empty( $app_setup['plugin_password'] ) ) {
		update_option( 'nuclen_app_pass_migration_done', true ); // nothing to do
		return;
	}

	/* — Generate fresh plugin‑side password & UUID — */
	$plugin_password = wp_generate_password( 32, false, false );
	$uuid            = wp_generate_uuid4();

	/* — Pick a user_login to send (current admin, else first admin) — */
	$current_user = wp_get_current_user();
	if ( ! $current_user || 0 === $current_user->ID ) {
		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => array( 'user_login', 'ID' ),
			)
		);
		$current_user = $admins ? $admins[0] : (object) array( 'user_login' => 'admin' );
	}

	/* — Send the new creds to the SaaS, preserving the expected keys — */
	$payload = array(
		'appApiKey'     => $app_setup['api_key'],
		'siteUrl'       => get_site_url(),
		'wpUserLogin'   => $current_user->user_login,
		'wpAppPassword' => $plugin_password,
		'wpAppPassUuid' => $uuid,
	);

	$response = wp_remote_post(
		'https://app.nuclearengagement.com/api/store-wp-creds',
		array(
			'method'  => 'POST',
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( $payload ),
			'timeout' => 30,
			'reject_unsafe_urls' => true,
			'user-agent' => 'NuclearEngagement/' . NUCLEN_PLUGIN_VERSION,
		)
	);

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
                // Log but still store locally – user can re‑try from Setup page if needed.
                ErrorHandler::log( '[Nuclear Engagement] App‑password migration failed to contact SaaS: ' . ( is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_response_code( $response ) ) );
        }

	/* — Persist new password & UUID — */
	$app_setup['plugin_password']  = $plugin_password;
	$app_setup['wp_app_pass_uuid'] = $uuid;
	update_option( 'nuclear_engagement_setup', $app_setup );

	/* — Optionally remove the old WP Application Password entry — */
	if ( class_exists( 'WP_Application_Passwords' ) && ! empty( $app_setup['wp_app_pass_uuid'] ) ) {
		$users = get_users(
			array(
				'fields' => array( 'ID' ),
				'number' => 50, // small – we just need to find and delete once
			)
		);
		foreach ( $users as $u ) {
			$apps = \WP_Application_Passwords::get_user_application_passwords( $u->ID );
			foreach ( $apps as $ap ) {
				if ( isset( $ap['uuid'] ) && $ap['uuid'] === $app_setup['wp_app_pass_uuid'] ) {
					\WP_Application_Passwords::delete_application_password( $u->ID, $ap['item_id'] );
					break 2; // done
				}
			}
		}
	}

	update_option( 'nuclen_app_pass_migration_done', true );
}
add_action( 'admin_init', 'nuclen_migrate_app_password', 9 ); // run early in admin_init

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

/**
 * Render callback for the quiz block.
 *
 * Mirrors the [nuclear_engagement_quiz] shortcode.
 *
 * @return string
 */
function nuclear_engagement_render_quiz_block() {
       return do_shortcode( '[nuclear_engagement_quiz]' );
}

/**
 * Register the front-end quiz block.
 */
function nuclear_engagement_register_quiz_block() {
       if ( ! function_exists( 'register_block_type' ) ) {
               return;
       }

       register_block_type(
               __DIR__ . '/front/block.json',
               array(
                       'style'          => array( 'nuclear-engagement', 'nuclear-engagement-theme' ),
                       'script'         => 'nuclear-engagement-front',
                       'render_callback' => 'nuclear_engagement_render_quiz_block',
               )
       );
}
add_action( 'init', 'nuclear_engagement_register_quiz_block' );

/**
 * Render callback for the summary block.
 *
 * Mirrors the [nuclear_engagement_summary] shortcode.
 *
 * @return string
 */
function nuclear_engagement_render_summary_block() {
       return do_shortcode( '[nuclear_engagement_summary]' );
}

/**
 * Register the front-end summary block.
 */
function nuclear_engagement_register_summary_block() {
       if ( ! function_exists( 'register_block_type' ) ) {
               return;
       }

       register_block_type(
               __DIR__ . '/front/summary-block.json',
               array(
                       'style'          => array( 'nuclear-engagement', 'nuclear-engagement-theme' ),
                       'script'         => 'nuclear-engagement-front',
                       'render_callback' => 'nuclear_engagement_render_summary_block',
               )
       );
}
add_action( 'init', 'nuclear_engagement_register_summary_block' );

/**
 * Render callback for the table of contents block.
 *
 * Mirrors the [nuclear_engagement_toc] shortcode.
 *
 * @return string
 */
function nuclear_engagement_render_toc_block() {
       return do_shortcode( '[nuclear_engagement_toc]' );
}

/**
 * Register the table of contents block.
 */
function nuclear_engagement_register_toc_block() {
       if ( ! function_exists( 'register_block_type' ) ) {
               return;
       }

       register_block_type(
               __DIR__ . '/modules/toc/block.json',
               array(
                       'style'          => 'nuclen-toc-front',
                       'script'         => 'nuclen-toc-front',
                       'render_callback' => 'nuclear_engagement_render_toc_block',
               )
       );
}
add_action( 'init', 'nuclear_engagement_register_toc_block' );

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