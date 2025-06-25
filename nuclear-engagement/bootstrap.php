<?php
// nuclear-engagement/bootstrap.php
declare(strict_types=1);
use NuclearEngagement\SettingsRepository;
use NuclearEngagement\Defaults;
use NuclearEngagement\Activator;
use NuclearEngagement\Deactivator;
use NuclearEngagement\MetaRegistration;
use NuclearEngagement\AssetVersions;
use NuclearEngagement\Plugin;
use NuclearEngagement\InventoryCache;
use NuclearEngagement\Services\PostsQueryService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'NUCLEN_PLUGIN_DIR', plugin_dir_path( NUCLEN_PLUGIN_FILE ) );

if ( ! defined( 'NUCLEN_PLUGIN_VERSION' ) ) {
        if ( ! function_exists( 'get_file_data' ) ) {
                require_once ABSPATH . 'wp-includes/functions.php';
        }
        $data = get_file_data(
                NUCLEN_PLUGIN_FILE,
                array( 'Version' => 'Version' ),
                'plugin'
        );
        define( 'NUCLEN_PLUGIN_VERSION', $data['Version'] );
}

define( 'NUCLEN_ASSET_VERSION', '250625-4' );

$autoload = __DIR__ . '/vendor/autoload.php';
if ( ! file_exists( $autoload ) ) {
        $autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
}
if ( file_exists( $autoload ) ) {
        require_once $autoload;
} else {
        \NuclearEngagement\Services\LoggingService::log( 'Nuclear Engagement: vendor autoload not found.' );
        \NuclearEngagement\Services\LoggingService::notify_admin( 'Nuclear Engagement dependencies missing. Please run composer install.' );
        return;
}

// Register a minimal PSR-4 autoloader for plugin classes when Composer
// autoload rules are incomplete.
spl_autoload_register(
    static function ( $class ) {
        $prefix = 'NuclearEngagement\\';
        if ( 0 !== strpos( $class, $prefix ) ) {
            return;
        }

        $relative = str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) );

        $paths = array();

        // Direct mapping using the namespace structure.
        $paths[] = NUCLEN_PLUGIN_DIR . $relative . '.php';

        // Handle directories that are lowercase on disk (e.g. admin, front).
        $segments = explode( '/', $relative );
        if ( in_array( $segments[0], array( 'Admin', 'Front' ), true ) ) {
            $segments[0] = strtolower( $segments[0] );
            $paths[]     = NUCLEN_PLUGIN_DIR . implode( '/', $segments ) . '.php';

            if ( isset( $segments[1] ) ) {
                $paths[] = NUCLEN_PLUGIN_DIR . $segments[0] . '/traits/' . $segments[1] . '.php';
            }
        }

        // Classes living under includes/.
        $paths[] = NUCLEN_PLUGIN_DIR . 'includes/' . $relative . '.php';
        // Module classes under inc/.
        $paths[] = NUCLEN_PLUGIN_DIR . 'inc/' . $relative . '.php';
        // Core classes under inc/Core/.
        $paths[] = NUCLEN_PLUGIN_DIR . 'inc/Core/' . $relative . '.php';
        // Generic helpers under inc/Utils/.
        $paths[] = NUCLEN_PLUGIN_DIR . 'inc/Utils/' . $relative . '.php';

        foreach ( $paths as $file ) {
            if ( file_exists( $file ) ) {
                require_once $file;
                return;
            }
        }
    }
);

// Fallback for class maps missing from autoload.
if ( ! class_exists( \NuclearEngagement\AssetVersions::class ) ) {
    $asset_versions_path = NUCLEN_PLUGIN_DIR . 'inc/Core/AssetVersions.php';
    if ( file_exists( $asset_versions_path ) ) {
        require_once $asset_versions_path;
    }
}


if ( file_exists( NUCLEN_PLUGIN_DIR . 'inc/Core/constants.php' ) ) {
    require_once NUCLEN_PLUGIN_DIR . 'inc/Core/constants.php';
} else {
    \NuclearEngagement\Services\LoggingService::log( 'Nuclear Engagement: constants.php missing.' );
}

AssetVersions::init();

function nuclear_engagement_load_textdomain() {
    load_plugin_textdomain(
        'nuclear-engagement',
        false,
        dirname( plugin_basename( NUCLEN_PLUGIN_FILE ) ) . '/languages/'
    );
}
add_action( 'init', 'nuclear_engagement_load_textdomain' );

add_action(
    'init',
    function () {
        $defaults = array(
            'theme'            => 'bright',
            'font_size'        => '16',
            'font_color'       => '#000000',
            'bg_color'         => '#ffffff',
            'border_color'     => '#000000',
            'border_style'     => 'solid',
            'border_width'     => '1',
            'quiz_title'       => __( 'Test your knowledge', 'nuclear-engagement' ),
            'summary_title'    => __( 'Key Facts', 'nuclear-engagement' ),
            'show_attribution' => false,
            'display_summary'  => 'none',
            'display_quiz'     => 'none',
            'display_toc'      => 'manual',
        );

        SettingsRepository::get_instance( $defaults );
    },
    20
);

function nuclear_engagement_activate_plugin() {
    $defaults = Defaults::nuclen_get_default_settings();
    $settings = SettingsRepository::get_instance( $defaults );
    Activator::nuclen_activate( $settings );
}
register_activation_hook( NUCLEN_PLUGIN_FILE, 'nuclear_engagement_activate_plugin' );

function nuclear_engagement_deactivate_plugin() {
    $settings = SettingsRepository::get_instance();
    Deactivator::nuclen_deactivate( $settings );
}
register_deactivation_hook( NUCLEN_PLUGIN_FILE, 'nuclear_engagement_deactivate_plugin' );

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

function nuclen_update_migrate_post_meta() {
    if ( get_option( 'nuclen_meta_migration_done' ) ) {
        return;
    }

    global $wpdb;

    $check_error = static function () use ( $wpdb ) {
        if ( ! empty( $wpdb->last_error ) ) {
            \NuclearEngagement\Services\LoggingService::log( 'Meta migration error: ' . $wpdb->last_error );
            update_option( 'nuclen_meta_migration_error', $wpdb->last_error );
            return false;
        }
        return true;
    };

    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s",
            'nuclen-summary-data',
            'ne-summary-data'
        )
    );
    if ( ! $check_error() ) {
        return;
    }

    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s",
            'nuclen-quiz-data',
            'ne-quiz-data'
        )
    );
    if ( ! $check_error() ) {
        return;
    }

    delete_option( 'nuclen_meta_migration_error' );
    update_option( 'nuclen_meta_migration_done', true );
}
add_action( 'admin_init', 'nuclen_update_migrate_post_meta', 20 );

function nuclear_engagement_run_plugin() {
    MetaRegistration::init();
    $plugin = new Plugin();
    $plugin->nuclen_run();
}

function nuclear_engagement_init() {
    try {
        InventoryCache::register_hooks();
        PostsQueryService::register_hooks();
    } catch ( \Throwable $e ) {
        \NuclearEngagement\Services\LoggingService::log( 'Nuclear Engagement: Cache registration failed - ' . $e->getMessage() );
        add_action(
            'admin_notices',
            static function () {
                echo '<div class="error"><p>Nuclear Engagement: Cache system initialization failed.</p></div>';
            }
        );
    }

    nuclear_engagement_run_plugin();
}
add_action( 'plugins_loaded', 'nuclear_engagement_init' );
