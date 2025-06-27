<?php
declare(strict_types=1);

namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Core\Defaults;
use NuclearEngagement\Core\Installer;
use NuclearEngagement\Core\MetaRegistration;
use NuclearEngagement\Core\AssetVersions;
use NuclearEngagement\Core\Plugin;
use NuclearEngagement\Core\InventoryCache;
use NuclearEngagement\Services\PostsQueryService;

/**
 * Bootstraps the plugin.
 */
final class Bootloader {
	/**
	 * Initialize plugin loading.
	 */
	public static function init(): void {
		self::define_constants();
		self::register_autoloaders();
		self::load_helpers();
		self::register_hooks();
	}

	/**
	 * Define core constants.
	 */
	private static function define_constants(): void {
		if ( ! defined( 'NUCLEN_PLUGIN_DIR' ) ) {
			define( 'NUCLEN_PLUGIN_DIR', plugin_dir_path( NUCLEN_PLUGIN_FILE ) );
		}

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

		if ( ! defined( 'NUCLEN_ASSET_VERSION' ) ) {
			define( 'NUCLEN_ASSET_VERSION', '250627-1' );
		}
	}

	/**
	 * Register Composer and fallback autoloaders.
	 */
	private static function register_autoloaders(): void {
		$autoload = __DIR__ . '/../../vendor/autoload.php';
		if ( ! file_exists( $autoload ) ) {
			$autoload = dirname( __DIR__, 2 ) . '/vendor/autoload.php';
		}
		if ( file_exists( $autoload ) ) {
			require_once $autoload;
		} else {
			$logging = __DIR__ . '/../Services/LoggingService.php';
			if ( file_exists( $logging ) ) {
				require_once $logging;
			}
			\NuclearEngagement\Services\LoggingService::log( 'Nuclear Engagement: vendor autoload not found.' );
			\NuclearEngagement\Services\LoggingService::notify_admin( 'Nuclear Engagement dependencies missing. Please run composer install.' );
			return;
		}

		spl_autoload_register(
			static function ( $class ) {
				$prefix = 'NuclearEngagement\\';
				if ( 0 !== strpos( $class, $prefix ) ) {
					return;
				}

				$relative = str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) );

				$paths	 = array();
				$paths[] = NUCLEN_PLUGIN_DIR . $relative . '.php';

				$segments = explode( '/', $relative );
				if ( in_array( $segments[0], array( 'Admin', 'Front' ), true ) ) {
					$segments[0] = strtolower( $segments[0] );
					$paths[]	 = NUCLEN_PLUGIN_DIR . implode( '/', $segments ) . '.php';

					if ( isset( $segments[1] ) ) {
						$paths[] = NUCLEN_PLUGIN_DIR . $segments[0] . '/traits/' . $segments[1] . '.php';
					}
				}

				$paths[] = NUCLEN_PLUGIN_DIR . 'inc/' . $relative . '.php';
				$paths[] = NUCLEN_PLUGIN_DIR . 'inc/Core/' . $relative . '.php';

				foreach ( $paths as $file ) {
					if ( file_exists( $file ) ) {
						require_once $file;
						return;
					}
				}
			}
		);

		if ( ! class_exists( AssetVersions::class ) ) {
			$asset_versions_path = NUCLEN_PLUGIN_DIR . 'inc/Core/AssetVersions.php';
			if ( file_exists( $asset_versions_path ) ) {
				require_once $asset_versions_path;
			}
		}
	}

	/**
	 * Load helper files and constants.
	 */
	private static function load_helpers(): void {
		require_once NUCLEN_PLUGIN_DIR . 'inc/Helpers/settings-functions.php';

		if ( file_exists( NUCLEN_PLUGIN_DIR . 'inc/Core/constants.php' ) ) {
			require_once NUCLEN_PLUGIN_DIR . 'inc/Core/constants.php';
		} else {
			\NuclearEngagement\Services\LoggingService::log( 'Nuclear Engagement: constants.php missing.' );
		}

		AssetVersions::init();
	}

	/**
	 * Register WordPress hooks.
	 */
	private static function register_hooks(): void {
		add_action( 'init', 'nuclear_engagement_load_textdomain' );

		add_action(
			'init',
			static function () {
				$defaults = array(
					'theme'			   => 'bright',
					'font_size'		   => '16',
					'font_color'	   => '#000000',
					'bg_color'		   => '#ffffff',
					'border_color'	   => '#000000',
					'border_style'	   => 'solid',
					'border_width'	   => '1',
					'quiz_title'	   => __( 'Test your knowledge', 'nuclear-engagement' ),
					'summary_title'	   => __( 'Key Facts', 'nuclear-engagement' ),
					'show_attribution' => false,
					'display_summary'  => 'none',
					'display_quiz'	   => 'none',
					'display_toc'	   => 'manual',
				);

				SettingsRepository::get_instance( $defaults );
			},
			20
		);

		$installer = new Installer();
		\register_activation_hook( NUCLEN_PLUGIN_FILE, array( $installer, 'activate' ) );
		\register_deactivation_hook( NUCLEN_PLUGIN_FILE, array( $installer, 'deactivate' ) );

		add_action( 'admin_init', 'nuclear_engagement_redirect_on_activation' );
		add_action( 'admin_init', array( $installer, 'migrate_post_meta' ), 20 );

		add_action( 'plugins_loaded', 'nuclear_engagement_init' );
	}
}
