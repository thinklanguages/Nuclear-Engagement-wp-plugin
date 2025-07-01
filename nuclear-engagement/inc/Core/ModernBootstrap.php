<?php
declare(strict_types=1);
/**
 * File: inc/Core/ModernBootstrap.php
 *
 * Modern bootstrap with dependency injection and clean architecture.
 *
 * @package NuclearEngagement\Core
 */

namespace NuclearEngagement\Core;

use NuclearEngagement\Core\Container;
use NuclearEngagement\Core\Environment;
use NuclearEngagement\Contracts\LoggerInterface;
use NuclearEngagement\Contracts\CacheInterface;
use NuclearEngagement\Contracts\ValidatorInterface;
use NuclearEngagement\Services\Implementation\StructuredLogger;
use NuclearEngagement\Services\Implementation\WordPressCache;
use NuclearEngagement\Validators\Validator;
use NuclearEngagement\Factories\ServiceFactory;
use NuclearEngagement\Events\EventDispatcher;
use NuclearEngagement\Exceptions\BaseException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Modern plugin bootstrap with clean architecture.
 */
class ModernBootstrap {
	
	/** @var Container */
	private Container $container;
	
	/** @var LoggerInterface */
	private LoggerInterface $logger;
	
	/** @var bool */
	private bool $initialized = false;
	
	/** @var array */
	private array $startup_errors = array();
	
	public function __construct() {
		$this->container = Container::get_instance();
	}
	
	/**
	 * Initialize the plugin with modern architecture.
	 */
	public function initialize(): void {
		if ( $this->initialized ) {
			return;
		}
		
		try {
			// Phase 1: Core setup
			$this->setup_environment();
			$this->register_core_services();
			$this->setup_error_handling();
			
			// Phase 2: Application services
			$this->register_application_services();
			$this->setup_event_system();
			
			// Phase 3: WordPress integration
			$this->register_wordpress_hooks();
			$this->setup_database_migrations();
			
			// Phase 4: Finalization
			$this->finalize_initialization();
			
			$this->initialized = true;
			
			$this->logger->info( 'Nuclear Engagement plugin initialized successfully', array(
				'version' => NUCLEN_PLUGIN_VERSION ?? 'unknown',
				'environment' => Environment::get_environment(),
				'memory_usage' => memory_get_usage( true ),
			) );
			
		} catch ( \Throwable $e ) {
			$this->handle_initialization_error( $e );
		}
	}
	
	/**
	 * Setup environment configuration.
	 */
	private function setup_environment(): void {
		// Apply environment-specific settings
		Environment::apply_environment_settings();
		
		// Define plugin constants if not already defined
		if ( ! defined( 'NUCLEN_PLUGIN_VERSION' ) ) {
			$plugin_data = get_file_data(
				NUCLEN_PLUGIN_FILE,
				array( 'Version' => 'Version' ),
				'plugin'
			);
			define( 'NUCLEN_PLUGIN_VERSION', $plugin_data['Version'] ?? '1.0.0' );
		}
	}
	
	/**
	 * Register core services in container.
	 */
	private function register_core_services(): void {
		// Register logger
		$this->container->singleton( LoggerInterface::class, function() {
			return new StructuredLogger();
		} );
		
		// Register cache
		$this->container->singleton( CacheInterface::class, function() {
			return new WordPressCache( 'nuclen_modern' );
		} );
		
		// Register validator
		$this->container->singleton( ValidatorInterface::class, function() {
			$validator = new Validator();
			
			// Add custom validation rules
			$validator->add_rule( 'workflow_type', function( $value ) {
				return in_array( $value, array( 'quiz', 'summary' ), true ) ? true : 'Invalid workflow type';
			} );
			
			$validator->add_rule( 'post_id', function( $value ) {
				$post = get_post( $value );
				return $post ? true : 'Post does not exist';
			} );
			
			return $validator;
		} );
		
		// Get logger instance
		$this->logger = $this->container->get( LoggerInterface::class );
	}
	
	/**
	 * Setup global error handling.
	 */
	private function setup_error_handling(): void {
		// Register error handler for uncaught exceptions
		set_exception_handler( array( $this, 'handle_uncaught_exception' ) );
		
		// Register error handler for PHP errors
		set_error_handler( array( $this, 'handle_php_error' ), E_ALL );
		
		// Register shutdown handler for fatal errors
		register_shutdown_function( array( $this, 'handle_shutdown' ) );
	}
	
	/**
	 * Register application services.
	 */
	private function register_application_services(): void {
		$service_factory = new ServiceFactory( $this->container );
		$service_factory->register_services();
	}
	
	/**
	 * Setup event system and register core listeners.
	 */
	private function setup_event_system(): void {
		$event_dispatcher = $this->container->get( EventDispatcher::class );
		
		// Register core event listeners
		$this->register_core_event_listeners( $event_dispatcher );
	}
	
	/**
	 * Register core event listeners.
	 *
	 * @param EventDispatcher $dispatcher Event dispatcher.
	 */
	private function register_core_event_listeners( EventDispatcher $dispatcher ): void {
		// Log important events
		$dispatcher->add_listener( 'nuclen.post.meta_updated', function( $event ) {
			$this->logger->debug( 'Post meta updated', $event->get_data() );
		} );
		
		$dispatcher->add_listener( 'nuclen.post.protected', function( $event ) {
			$this->logger->info( 'Post protected from generation', $event->get_data() );
		} );
		
		$dispatcher->add_listener( 'nuclen.generation.started', function( $event ) {
			$this->logger->info( 'Content generation started', $event->get_data() );
		} );
		
		$dispatcher->add_listener( 'nuclen.generation.completed', function( $event ) {
			$this->logger->info( 'Content generation completed', $event->get_data() );
		} );
		
		// Cache invalidation
		$dispatcher->add_listener( 'nuclen.post.meta_updated', function( $event ) {
			$cache = $this->container->get( CacheInterface::class );
			$post_id = $event->get( 'post_id' );
			if ( $post_id ) {
				$cache->delete( "post_{$post_id}" );
			}
		} );
	}
	
	/**
	 * Register WordPress hooks.
	 */
	private function register_wordpress_hooks(): void {
		// Initialization hooks
		add_action( 'init', array( $this, 'on_wp_init' ), 5 );
		add_action( 'admin_init', array( $this, 'on_admin_init' ), 5 );
		
		// Activation/deactivation hooks
		register_activation_hook( NUCLEN_PLUGIN_FILE, array( $this, 'on_activation' ) );
		register_deactivation_hook( NUCLEN_PLUGIN_FILE, array( $this, 'on_deactivation' ) );
		
		// Error reporting
		add_action( 'admin_notices', array( $this, 'display_startup_errors' ) );
	}
	
	/**
	 * Setup database migrations.
	 */
	private function setup_database_migrations(): void {
		if ( DatabaseMigrations::needs_migration() ) {
			add_action( 'admin_init', function() {
				DatabaseMigrations::migrate();
			} );
		}
	}
	
	/**
	 * Finalize initialization.
	 */
	private function finalize_initialization(): void {
		// Fire initialization complete event
		$event_dispatcher = $this->container->get( EventDispatcher::class );
		$event = new \NuclearEngagement\Events\Event( 'nuclen.initialization.completed', array(
			'timestamp' => microtime( true ),
			'memory_usage' => memory_get_usage( true ),
		) );
		$event_dispatcher->dispatch( $event );
		
		// Clean up temporary data
		$this->cleanup_initialization();
	}
	
	/**
	 * WordPress init hook handler.
	 */
	public function on_wp_init(): void {
		// Load text domain
		load_plugin_textdomain(
			'nuclear-engagement',
			false,
			dirname( plugin_basename( NUCLEN_PLUGIN_FILE ) ) . '/languages/'
		);
		
		// Initialize plugin components that depend on WordPress being loaded
		do_action( 'nuclen_wp_init', $this->container );
	}
	
	/**
	 * WordPress admin init hook handler.
	 */
	public function on_admin_init(): void {
		// Initialize admin-specific components
		do_action( 'nuclen_admin_init', $this->container );
	}
	
	/**
	 * Plugin activation handler.
	 */
	public function on_activation(): void {
		try {
			// Run database migrations
			DatabaseMigrations::migrate();
			
			// Set activation redirect flag
			set_transient( 'nuclen_plugin_activation_redirect', true, 30 );
			
			$this->logger->info( 'Nuclear Engagement plugin activated' );
			
		} catch ( \Throwable $e ) {
			$this->logger->error( 'Plugin activation failed', array(
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
			) );
			
			// Deactivate plugin on critical error
			deactivate_plugins( plugin_basename( NUCLEN_PLUGIN_FILE ) );
			wp_die( 'Nuclear Engagement plugin activation failed: ' . esc_html( $e->getMessage() ) );
		}
	}
	
	/**
	 * Plugin deactivation handler.
	 */
	public function on_deactivation(): void {
		try {
			// Clean up scheduled events
			wp_clear_scheduled_hook( 'nuclen_cleanup_logs' );
			wp_clear_scheduled_hook( 'nuclen_cleanup_cache' );
			
			// Fire deactivation event
			$event_dispatcher = $this->container->get( EventDispatcher::class );
			$event = new \NuclearEngagement\Events\Event( 'nuclen.plugin.deactivated' );
			$event_dispatcher->dispatch( $event );
			
			$this->logger->info( 'Nuclear Engagement plugin deactivated' );
			
		} catch ( \Throwable $e ) {
			$this->logger->error( 'Plugin deactivation error', array(
				'error' => $e->getMessage(),
			) );
		}
	}
	
	/**
	 * Handle initialization errors.
	 *
	 * @param \Throwable $e Exception.
	 */
	private function handle_initialization_error( \Throwable $e ): void {
		$this->startup_errors[] = $e;
		
		// Log to error_log as fallback
		error_log( 'Nuclear Engagement initialization failed: ' . $e->getMessage() );
		
		// Try to log with structured logger if available
		if ( isset( $this->logger ) ) {
			$this->logger->exception( $e, array( 'phase' => 'initialization' ) );
		}
	}
	
	/**
	 * Handle uncaught exceptions.
	 *
	 * @param \Throwable $exception Exception.
	 */
	public function handle_uncaught_exception( \Throwable $exception ): void {
		if ( isset( $this->logger ) ) {
			$this->logger->exception( $exception, array( 'type' => 'uncaught_exception' ) );
		} else {
			error_log( 'Nuclear Engagement uncaught exception: ' . $exception->getMessage() );
		}
		
		// Show user-friendly error in admin
		if ( is_admin() && $exception instanceof BaseException ) {
			add_action( 'admin_notices', function() use ( $exception ) {
				echo '<div class="notice notice-error"><p>';
				echo '<strong>Nuclear Engagement Error:</strong> ';
				echo esc_html( $exception->get_user_message() );
				echo '</p></div>';
			} );
		}
	}
	
	/**
	 * Handle PHP errors.
	 *
	 * @param int    $severity Error severity.
	 * @param string $message  Error message.
	 * @param string $filename Error filename.
	 * @param int    $lineno   Error line number.
	 * @return bool Whether error was handled.
	 */
	public function handle_php_error( int $severity, string $message, string $filename, int $lineno ): bool {
		// Only handle errors from our plugin
		if ( strpos( $filename, 'nuclear-engagement' ) === false ) {
			return false;
		}
		
		$level = $this->get_log_level_from_severity( $severity );
		
		if ( isset( $this->logger ) ) {
			$this->logger->log( $level, $message, array(
				'severity' => $severity,
				'filename' => $filename,
				'line' => $lineno,
				'type' => 'php_error',
			) );
		}
		
		return false; // Don't prevent default error handling
	}
	
	/**
	 * Handle shutdown and check for fatal errors.
	 */
	public function handle_shutdown(): void {
		$error = error_get_last();
		
		if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ) ) ) {
			// Only handle errors from our plugin
			if ( strpos( $error['file'], 'nuclear-engagement' ) !== false ) {
				if ( isset( $this->logger ) ) {
					$this->logger->error( 'Fatal error: ' . $error['message'], array(
						'filename' => $error['file'],
						'line' => $error['line'],
						'type' => 'fatal_error',
					) );
				}
			}
		}
	}
	
	/**
	 * Display startup errors in admin.
	 */
	public function display_startup_errors(): void {
		if ( empty( $this->startup_errors ) ) {
			return;
		}
		
		foreach ( $this->startup_errors as $error ) {
			echo '<div class="notice notice-error"><p>';
			echo '<strong>Nuclear Engagement Startup Error:</strong> ';
			
			if ( $error instanceof BaseException ) {
				echo esc_html( $error->get_user_message() );
			} else {
				echo esc_html( $error->getMessage() );
			}
			
			echo '</p></div>';
		}
	}
	
	/**
	 * Get log level from PHP error severity.
	 *
	 * @param int $severity Error severity.
	 * @return string Log level.
	 */
	private function get_log_level_from_severity( int $severity ): string {
		switch ( $severity ) {
			case E_ERROR:
			case E_PARSE:
			case E_CORE_ERROR:
			case E_COMPILE_ERROR:
				return 'error';
			
			case E_WARNING:
			case E_CORE_WARNING:
			case E_COMPILE_WARNING:
				return 'warning';
			
			case E_NOTICE:
			case E_USER_NOTICE:
				return 'info';
			
			default:
				return 'debug';
		}
	}
	
	/**
	 * Clean up initialization data.
	 */
	private function cleanup_initialization(): void {
		// Clear any temporary initialization data
		$this->startup_errors = array();
	}
	
	/**
	 * Get container instance.
	 *
	 * @return Container Container instance.
	 */
	public function get_container(): Container {
		return $this->container;
	}
	
	/**
	 * Check if plugin is properly initialized.
	 *
	 * @return bool Whether initialized.
	 */
	public function is_initialized(): bool {
		return $this->initialized;
	}
}