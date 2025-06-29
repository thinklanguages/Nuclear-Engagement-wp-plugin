<?php
declare(strict_types=1);

namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lazy loading system for services and assets.
 *
 * @package NuclearEngagement\Core
 * @since 1.0.0
 */
final class LazyLoader {
	/**
	 * Lazy loading configurations.
	 *
	 * @var array<string, array{trigger: string, priority: int, condition: callable|null}>
	 */
	private static array $lazy_configs = [];

	/**
	 * Already loaded services.
	 *
	 * @var array<string, bool>
	 */
	private static array $loaded_services = [];

	/**
	 * Deferred loading queue.
	 *
	 * @var array<string, array{service: string, callback: callable, priority: int}>
	 */
	private static array $deferred_queue = [];

	/**
	 * Loading conditions cache.
	 *
	 * @var array<string, bool>
	 */
	private static array $condition_cache = [];

	/**
	 * Initialize lazy loader.
	 */
	public static function init(): void {
		// Set up default lazy loading configurations
		self::setup_default_configs();

		// Hook into WordPress lifecycle for lazy loading triggers
		add_action( 'init', [ self::class, 'process_init_triggers' ], 5 );
		add_action( 'wp', [ self::class, 'process_wp_triggers' ], 5 );
		add_action( 'admin_init', [ self::class, 'process_admin_triggers' ], 5 );
		add_action( 'wp_enqueue_scripts', [ self::class, 'process_frontend_triggers' ], 5 );
		add_action( 'admin_enqueue_scripts', [ self::class, 'process_admin_asset_triggers' ], 5 );

		// Process deferred queue
		add_action( 'wp_footer', [ self::class, 'process_deferred_queue' ], 1 );
		add_action( 'admin_footer', [ self::class, 'process_deferred_queue' ], 1 );
	}

	/**
	 * Register a service for lazy loading.
	 *
	 * @param string        $service_id Service identifier.
	 * @param callable      $loader     Function to load the service.
	 * @param string        $trigger    When to load (init, wp, admin_init, etc.).
	 * @param callable|null $condition  Optional condition to check before loading.
	 * @param int           $priority   Loading priority.
	 */
	public static function register( string $service_id, callable $loader, string $trigger = 'init', ?callable $condition = null, int $priority = 10 ): void {
		self::$lazy_configs[$service_id] = [
			'loader'    => $loader,
			'trigger'   => $trigger,
			'condition' => $condition,
			'priority'  => $priority,
		];
	}

	/**
	 * Load a service immediately if not already loaded.
	 *
	 * @param string $service_id Service identifier.
	 * @return bool Whether service was loaded.
	 */
	public static function load_now( string $service_id ): bool {
		if ( isset( self::$loaded_services[$service_id] ) ) {
			return true;
		}

		if ( ! isset( self::$lazy_configs[$service_id] ) ) {
			return false;
		}

		$config = self::$lazy_configs[$service_id];
		
		// Check condition if specified
		if ( $config['condition'] && ! self::check_condition( $service_id, $config['condition'] ) ) {
			return false;
		}

		PerformanceMonitor::start( "lazy_load_{$service_id}" );

		try {
			call_user_func( $config['loader'] );
			self::$loaded_services[$service_id] = true;
			
			PerformanceMonitor::stop( "lazy_load_{$service_id}" );
			return true;
		} catch ( \Throwable $e ) {
			PerformanceMonitor::stop( "lazy_load_{$service_id}" );
			
			ErrorRecovery::addErrorContext(
				"Failed to lazy load service: {$service_id}",
				[
					'service' => $service_id,
					'error'   => $e->getMessage(),
					'file'    => $e->getFile(),
					'line'    => $e->getLine(),
				],
				'error'
			);
			
			return false;
		}
	}

	/**
	 * Defer service loading to a later point.
	 *
	 * @param string   $service_id Service identifier.
	 * @param callable $loader     Loader function.
	 * @param int      $priority   Priority in deferred queue.
	 */
	public static function defer( string $service_id, callable $loader, int $priority = 10 ): void {
		self::$deferred_queue[] = [
			'service'  => $service_id,
			'callback' => $loader,
			'priority' => $priority,
		];
	}

	/**
	 * Check if a service is loaded.
	 *
	 * @param string $service_id Service identifier.
	 * @return bool Whether service is loaded.
	 */
	public static function is_loaded( string $service_id ): bool {
		return isset( self::$loaded_services[$service_id] );
	}

	/**
	 * Get loading statistics.
	 *
	 * @return array{loaded: array, pending: array, deferred: int}
	 */
	public static function get_stats(): array {
		return [
			'loaded'   => array_keys( self::$loaded_services ),
			'pending'  => array_keys( array_diff_key( self::$lazy_configs, self::$loaded_services ) ),
			'deferred' => count( self::$deferred_queue ),
		];
	}

	/**
	 * Process services that should load on 'init'.
	 */
	public static function process_init_triggers(): void {
		self::process_trigger( 'init' );
	}

	/**
	 * Process services that should load on 'wp'.
	 */
	public static function process_wp_triggers(): void {
		self::process_trigger( 'wp' );
	}

	/**
	 * Process services that should load on 'admin_init'.
	 */
	public static function process_admin_triggers(): void {
		if ( is_admin() ) {
			self::process_trigger( 'admin_init' );
		}
	}

	/**
	 * Process services that should load when frontend scripts are enqueued.
	 */
	public static function process_frontend_triggers(): void {
		if ( ! is_admin() ) {
			self::process_trigger( 'frontend_scripts' );
		}
	}

	/**
	 * Process services that should load when admin scripts are enqueued.
	 */
	public static function process_admin_asset_triggers(): void {
		if ( is_admin() ) {
			self::process_trigger( 'admin_scripts' );
		}
	}

	/**
	 * Process the deferred loading queue.
	 */
	public static function process_deferred_queue(): void {
		if ( empty( self::$deferred_queue ) ) {
			return;
		}

		// Sort by priority
		usort( self::$deferred_queue, function( $a, $b ) {
			return $a['priority'] <=> $b['priority'];
		} );

		foreach ( self::$deferred_queue as $item ) {
			if ( ! isset( self::$loaded_services[ $item['service'] ] ) ) {
				PerformanceMonitor::start( "deferred_load_{$item['service']}" );
				
				try {
					call_user_func( $item['callback'] );
					self::$loaded_services[ $item['service'] ] = true;
				} catch ( \Throwable $e ) {
					ErrorRecovery::addErrorContext(
						"Failed to load deferred service: {$item['service']}",
						[
							'service' => $item['service'],
							'error'   => $e->getMessage(),
						],
						'error'
					);
				}
				
				PerformanceMonitor::stop( "deferred_load_{$item['service']}" );
			}
		}

		self::$deferred_queue = [];
	}

	/**
	 * Setup default lazy loading configurations.
	 */
	private static function setup_default_configs(): void {
		// Admin-only services
		self::register(
			'dashboard_widgets',
			function() {
				// Load dashboard widgets only when needed
				if ( class_exists( 'NuclearEngagement\\Admin\\DashboardWidgets' ) ) {
					ServiceContainer::resolve( 'NuclearEngagement\\Admin\\DashboardWidgets' )->register();
				}
			},
			'admin_init',
			function() {
				return is_admin() && self::is_dashboard_page();
			}
		);

		// Frontend-only services
		self::register(
			'frontend_assets',
			function() {
				// Load frontend assets only when needed
				if ( class_exists( 'NuclearEngagement\\Frontend\\Assets' ) ) {
					ServiceContainer::resolve( 'NuclearEngagement\\Frontend\\Assets' )->enqueue();
				}
			},
			'frontend_scripts',
			function() {
				return ! is_admin() && self::should_load_frontend_assets();
			}
		);

		// Post editor services
		self::register(
			'post_editor',
			function() {
				if ( class_exists( 'NuclearEngagement\\Editor\\PostEditor' ) ) {
					ServiceContainer::resolve( 'NuclearEngagement\\Editor\\PostEditor' )->init();
				}
			},
			'admin_init',
			function() {
				return is_admin() && self::is_post_editor_page();
			}
		);

		// API services (load only when needed)
		self::register(
			'rest_api',
			function() {
				if ( class_exists( 'NuclearEngagement\\API\\RestEndpoints' ) ) {
					ServiceContainer::resolve( 'NuclearEngagement\\API\\RestEndpoints' )->register();
				}
			},
			'init',
			function() {
				return defined( 'REST_REQUEST' ) && REST_REQUEST;
			}
		);

		// AJAX services
		self::register(
			'ajax_handlers',
			function() {
				if ( class_exists( 'NuclearEngagement\\Ajax\\Handlers' ) ) {
					ServiceContainer::resolve( 'NuclearEngagement\\Ajax\\Handlers' )->register();
				}
			},
			'init',
			function() {
				return defined( 'DOING_AJAX' ) && DOING_AJAX;
			}
		);

		// Cron services
		self::register(
			'cron_jobs',
			function() {
				if ( class_exists( 'NuclearEngagement\\Cron\\Jobs' ) ) {
					ServiceContainer::resolve( 'NuclearEngagement\\Cron\\Jobs' )->schedule();
				}
			},
			'init',
			function() {
				return defined( 'DOING_CRON' ) && DOING_CRON;
			}
		);
	}

	/**
	 * Process services for a specific trigger.
	 *
	 * @param string $trigger Trigger name.
	 */
	private static function process_trigger( string $trigger ): void {
		$services_to_load = [];

		foreach ( self::$lazy_configs as $service_id => $config ) {
			if ( $config['trigger'] === $trigger && ! isset( self::$loaded_services[$service_id] ) ) {
				$services_to_load[] = [
					'id'     => $service_id,
					'config' => $config,
				];
			}
		}

		// Sort by priority
		usort( $services_to_load, function( $a, $b ) {
			return $a['config']['priority'] <=> $b['config']['priority'];
		} );

		foreach ( $services_to_load as $service ) {
			self::load_now( $service['id'] );
		}
	}

	/**
	 * Check loading condition with caching.
	 *
	 * @param string   $service_id Service identifier.
	 * @param callable $condition  Condition to check.
	 * @return bool Whether condition is met.
	 */
	private static function check_condition( string $service_id, callable $condition ): bool {
		if ( isset( self::$condition_cache[$service_id] ) ) {
			return self::$condition_cache[$service_id];
		}

		$result = call_user_func( $condition );
		self::$condition_cache[$service_id] = $result;
		
		return $result;
	}

	/**
	 * Check if current page is the dashboard.
	 *
	 * @return bool Whether on dashboard page.
	 */
	private static function is_dashboard_page(): bool {
		global $pagenow;
		return $pagenow === 'index.php';
	}

	/**
	 * Check if current page is the post editor.
	 *
	 * @return bool Whether on post editor page.
	 */
	private static function is_post_editor_page(): bool {
		global $pagenow;
		return in_array( $pagenow, [ 'post.php', 'post-new.php' ], true );
	}

	/**
	 * Check if frontend assets should be loaded.
	 *
	 * @return bool Whether to load frontend assets.
	 */
	private static function should_load_frontend_assets(): bool {
		// Cache the decision for this request
		static $should_load = null;
		
		if ( $should_load === null ) {
			$should_load = CacheManager::remember( 
				'should_load_assets_' . get_queried_object_id(),
				function() {
					// Check if current post/page has Nuclear Engagement content
					if ( is_singular() ) {
						$post = get_queried_object();
						if ( $post ) {
							// Check for shortcodes or blocks
							return has_shortcode( $post->post_content, 'nuclen' ) ||
								   strpos( $post->post_content, 'wp:nuclen/' ) !== false ||
								   get_post_meta( $post->ID, 'nuclen-quiz-data', true ) ||
								   get_post_meta( $post->ID, 'nuclen-summary-data', true );
						}
					}
					
					// Check for widgets or other global conditions
					return false;
				},
				'assets',
				300 // 5 minutes cache
			);
		}
		
		return $should_load;
	}

	/**
	 * Preload critical services.
	 *
	 * @param array $service_ids Service IDs to preload.
	 */
	public static function preload( array $service_ids ): void {
		foreach ( $service_ids as $service_id ) {
			self::load_now( $service_id );
		}
	}

	/**
	 * Force reload a service.
	 *
	 * @param string $service_id Service identifier.
	 * @return bool Whether service was reloaded.
	 */
	public static function reload( string $service_id ): bool {
		unset( self::$loaded_services[$service_id] );
		unset( self::$condition_cache[$service_id] );
		
		return self::load_now( $service_id );
	}

	/**
	 * Clear all loading caches.
	 */
	public static function clear_cache(): void {
		self::$condition_cache = [];
		CacheManager::invalidate_group( 'assets', 'lazy_loader_clear' );
	}
}