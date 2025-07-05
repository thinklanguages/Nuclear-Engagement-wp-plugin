<?php
/**
 * CacheInterface.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Contracts
 */

declare(strict_types=1);
/**
 * File: inc/Contracts/CacheInterface.php
 *
 * Cache interface for dependency injection.
 *
 * @package NuclearEngagement\Contracts
 */

namespace NuclearEngagement\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defines caching contract for the plugin.
 */
interface CacheInterface {

	/**
	 * Get cached value.
	 *
	 * @param string $key     Cache key.
	 * @param mixed  $default Default value if not found.
	 * @return mixed Cached value or default.
	 */
	public function get( string $key, $default = null );

	/**
	 * Set cached value.
	 *
	 * @param string $key        Cache key.
	 * @param mixed  $value      Value to cache.
	 * @param int    $expiration Expiration time in seconds.
	 * @return bool Success status.
	 */
	public function set( string $key, $value, int $expiration = 3600 ): bool;

	/**
	 * Delete cached value.
	 *
	 * @param string $key Cache key.
	 * @return bool Success status.
	 */
	public function delete( string $key ): bool;

	/**
	 * Clear all cached values in group.
	 *
	 * @param string $group Cache group.
	 * @return bool Success status.
	 */
	public function flush_group( string $group ): bool;

	/**
	 * Clear all cached values.
	 *
	 * @return bool Success status.
	 */
	public function flush(): bool;

	/**
	 * Check if key exists in cache.
	 *
	 * @param string $key Cache key.
	 * @return bool Whether key exists.
	 */
	public function exists( string $key ): bool;
}
