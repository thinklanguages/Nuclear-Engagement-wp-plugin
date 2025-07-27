<?php
/**
 * ThemeEventManager.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Services
 */

namespace NuclearEngagement\Services;

class ThemeEventManager {

	private static $instance = null;

	public static function instance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$this->register_hooks();
	}

	private function register_hooks(): void {
		add_action( 'nuclen_theme_activated', array( $this, 'on_theme_activated' ), 10, 2 );
		add_action( 'nuclen_theme_deactivated', array( $this, 'on_theme_deactivated' ), 10, 2 );
		add_action( 'nuclen_theme_saved', array( $this, 'on_theme_saved' ), 10, 2 );
		add_action( 'nuclen_theme_deleted', array( $this, 'on_theme_deleted' ), 10, 1 );
		add_action( 'nuclen_theme_css_generated', array( $this, 'on_css_generated' ), 10, 2 );

		add_filter( 'nuclen_theme_config_before_save', array( $this, 'filter_config_before_save' ), 10, 2 );
		add_filter( 'nuclen_theme_css_before_generation', array( $this, 'filter_css_before_generation' ), 10, 2 );
		add_filter( 'nuclen_theme_css_after_generation', array( $this, 'filter_css_after_generation' ), 10, 2 );
	}

	public function trigger_theme_activated( $theme_id, $theme ): void {
		do_action( 'nuclen_theme_activated', $theme_id, $theme );
	}

	public function trigger_theme_deactivated( $theme_id, $theme ): void {
		do_action( 'nuclen_theme_deactivated', $theme_id, $theme );
	}

	public function trigger_theme_saved( $theme, $is_new = false ): void {
		do_action( 'nuclen_theme_saved', $theme, $is_new );
	}

	public function trigger_theme_deleted( $theme_id ): void {
		do_action( 'nuclen_theme_deleted', $theme_id );
	}

	public function trigger_css_generated( $theme, $css_content ): void {
		do_action( 'nuclen_theme_css_generated', $theme, $css_content );
	}

	public function apply_config_filters( array $config, $theme ): array {
		return apply_filters( 'nuclen_theme_config_before_save', $config, $theme );
	}

	public function apply_css_before_generation_filters( array $config, $theme ): array {
		return apply_filters( 'nuclen_theme_css_before_generation', $config, $theme );
	}

	public function apply_css_after_generation_filters( string $css, $theme ): string {
		return apply_filters( 'nuclen_theme_css_after_generation', $css, $theme );
	}

	public function on_theme_activated( $theme_id, $theme ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			\NuclearEngagement\Services\LoggingService::debug( "Theme '{$theme->name}' (ID: {$theme_id}) activated" );
		}

		// Clear any relevant caches.
		$this->clear_theme_caches();
	}

	public function on_theme_deactivated( $theme_id, $theme ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			\NuclearEngagement\Services\LoggingService::debug( "Theme '{$theme->name}' (ID: {$theme_id}) deactivated" );
		}

		// Clear any relevant caches.
		$this->clear_theme_caches();
	}

	public function on_theme_saved( $theme, $is_new ): void {
		$action = $is_new ? 'created' : 'updated';

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			\NuclearEngagement\Services\LoggingService::debug( "Theme '{$theme->name}' {$action}" );
		}

		// Clear theme caches.
		$this->clear_theme_caches();
	}

	public function on_theme_deleted( $theme_id ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			\NuclearEngagement\Services\LoggingService::debug( "Theme (ID: {$theme_id}) deleted" );
		}

		// Clear theme caches.
		$this->clear_theme_caches();
	}

	public function on_css_generated( $theme, $css_content ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$css_size = strlen( $css_content );
			\NuclearEngagement\Services\LoggingService::debug( "CSS generated for theme '{$theme->name}' ({$css_size} bytes)" );
		}
	}

	public function filter_config_before_save( array $config, $theme ): array {
		// Default implementation - can be extended.
		return $config;
	}

	public function filter_css_before_generation( array $config, $theme ): array {
		// Default implementation - can be extended.
		return $config;
	}

	public function filter_css_after_generation( string $css, $theme ): string {
		// Default implementation - can be extended.
		return $css;
	}

	private function clear_theme_caches(): void {
		// Clear WordPress object cache if available.
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'nuclen_themes' );
		}

		// Clear any transients.
		delete_transient( 'nuclen_active_theme' );
		delete_transient( 'nuclen_theme_list' );
	}

	public function register_custom_hook( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
		add_action( $hook, $callback, $priority, $accepted_args );
	}

	public function register_custom_filter( string $filter, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
		add_filter( $filter, $callback, $priority, $accepted_args );
	}
}
