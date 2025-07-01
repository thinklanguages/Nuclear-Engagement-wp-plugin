<?php
declare(strict_types=1);
/**
 * File: inc/Services/Implementation/WordPressCache.php
 *
 * WordPress cache implementation.
 *
 * @package NuclearEngagement\Services\Implementation
 */

namespace NuclearEngagement\Services\Implementation;

use NuclearEngagement\Contracts\CacheInterface;
use NuclearEngagement\Core\Environment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress cache implementation with object cache and transients.
 */
class WordPressCache implements CacheInterface {
	
	/** @var string */
	private string $cache_group;
	
	/** @var string */
	private string $cache_prefix;
	
	public function __construct( string $cache_group = 'nuclen' ) {
		$this->cache_group = $cache_group;
		$this->cache_prefix = Environment::get_cache_prefix();
	}
	
	/**
	 * Get cached value.
	 *
	 * @param string $key     Cache key.
	 * @param mixed  $default Default value if not found.
	 * @return mixed Cached value or default.
	 */
	public function get( string $key, $default = null ) {
		$prefixed_key = $this->prefix_key( $key );
		
		// Try object cache first
		$found = false;
		$value = wp_cache_get( $prefixed_key, $this->cache_group, false, $found );
		
		if ( $found ) {
			return $this->unserialize_value( $value );
		}
		
		// Fallback to transients
		$transient_value = get_transient( $prefixed_key );
		if ( $transient_value !== false ) {
			// Store in object cache for faster access
			wp_cache_set( $prefixed_key, $transient_value, $this->cache_group );
			return $this->unserialize_value( $transient_value );
		}
		
		return $default;
	}
	
	/**
	 * Set cached value.
	 *
	 * @param string $key        Cache key.
	 * @param mixed  $value      Value to cache.
	 * @param int    $expiration Expiration time in seconds.
	 * @return bool Success status.
	 */
	public function set( string $key, $value, int $expiration = 3600 ): bool {
		$prefixed_key = $this->prefix_key( $key );
		$serialized_value = $this->serialize_value( $value );
		
		// Set in object cache
		$object_cache_result = wp_cache_set( $prefixed_key, $serialized_value, $this->cache_group, $expiration );
		
		// Set in transients as backup
		$transient_result = set_transient( $prefixed_key, $serialized_value, $expiration );
		
		return $object_cache_result || $transient_result;
	}
	
	/**
	 * Delete cached value.
	 *
	 * @param string $key Cache key.
	 * @return bool Success status.
	 */
	public function delete( string $key ): bool {
		$prefixed_key = $this->prefix_key( $key );
		
		// Delete from object cache
		$object_cache_result = wp_cache_delete( $prefixed_key, $this->cache_group );
		
		// Delete from transients
		$transient_result = delete_transient( $prefixed_key );
		
		return $object_cache_result || $transient_result;
	}
	
	/**
	 * Clear all cached values in group.
	 *
	 * @param string $group Cache group.
	 * @return bool Success status.
	 */
	public function flush_group( string $group ): bool {
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			return wp_cache_flush_group( $group );
		}
		
		// Fallback: increment cache version
		$version_key = "cache_version_{$group}";
		$current_version = (int) get_option( $version_key, 1 );
		return update_option( $version_key, $current_version + 1, false );
	}
	
	/**
	 * Clear all cached values.
	 *
	 * @return bool Success status.
	 */
	public function flush(): bool {
		return wp_cache_flush();
	}
	
	/**
	 * Check if key exists in cache.
	 *
	 * @param string $key Cache key.
	 * @return bool Whether key exists.
	 */
	public function exists( string $key ): bool {
		$prefixed_key = $this->prefix_key( $key );
		
		// Check object cache
		$found = false;
		wp_cache_get( $prefixed_key, $this->cache_group, false, $found );
		
		if ( $found ) {
			return true;
		}
		
		// Check transients
		return get_transient( $prefixed_key ) !== false;
	}
	
	/**
	 * Get multiple cached values.
	 *
	 * @param array $keys Cache keys.
	 * @return array Key-value pairs.
	 */
	public function get_multiple( array $keys ): array {
		$result = array();
		
		foreach ( $keys as $key ) {
			$result[ $key ] = $this->get( $key );
		}
		
		return $result;
	}
	
	/**
	 * Set multiple cached values.
	 *
	 * @param array $values     Key-value pairs to cache.
	 * @param int   $expiration Expiration time in seconds.
	 * @return bool Success status.
	 */
	public function set_multiple( array $values, int $expiration = 3600 ): bool {
		$success = true;
		
		foreach ( $values as $key => $value ) {
			if ( ! $this->set( $key, $value, $expiration ) ) {
				$success = false;
			}
		}
		
		return $success;
	}
	
	/**
	 * Delete multiple cached values.
	 *
	 * @param array $keys Cache keys.
	 * @return bool Success status.
	 */
	public function delete_multiple( array $keys ): bool {
		$success = true;
		
		foreach ( $keys as $key ) {
			if ( ! $this->delete( $key ) ) {
				$success = false;
			}
		}
		
		return $success;
	}
	
	/**
	 * Increment cached value.
	 *
	 * @param string $key   Cache key.
	 * @param int    $step  Increment step.
	 * @param int    $group Cache group.
	 * @return int|false New value or false on failure.
	 */
	public function increment( string $key, int $step = 1, $group = null ) {
		$group = $group ?: $this->cache_group;
		$prefixed_key = $this->prefix_key( $key );
		
		return wp_cache_incr( $prefixed_key, $step, $group );
	}
	
	/**
	 * Decrement cached value.
	 *
	 * @param string $key   Cache key.
	 * @param int    $step  Decrement step.
	 * @param int    $group Cache group.
	 * @return int|false New value or false on failure.
	 */
	public function decrement( string $key, int $step = 1, $group = null ) {
		$group = $group ?: $this->cache_group;
		$prefixed_key = $this->prefix_key( $key );
		
		return wp_cache_decr( $prefixed_key, $step, $group );
	}
	
	/**
	 * Add cache prefix to key.
	 *
	 * @param string $key Original key.
	 * @return string Prefixed key.
	 */
	private function prefix_key( string $key ): string {
		return $this->cache_prefix . $key;
	}
	
	/**
	 * Serialize value for storage.
	 *
	 * @param mixed $value Value to serialize.
	 * @return mixed Serialized value.
	 */
	private function serialize_value( $value ) {
		// WordPress cache handles serialization automatically for objects/arrays
		return $value;
	}
	
	/**
	 * Unserialize value from storage.
	 *
	 * @param mixed $value Value to unserialize.
	 * @return mixed Unserialized value.
	 */
	private function unserialize_value( $value ) {
		// WordPress cache handles unserialization automatically
		return $value;
	}
}