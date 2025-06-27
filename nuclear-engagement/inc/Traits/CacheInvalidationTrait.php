<?php
/**
 * File: includes/Traits/CacheInvalidationTrait.php
 *
 * Provides a helper to register standard post and term hooks
 * used for cache invalidation.
 *
 * @package NuclearEngagement
 * @subpackage Traits
 */

declare( strict_types = 1 );

namespace NuclearEngagement\Traits;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait CacheInvalidationTrait {
	/**
	 * Register hooks that invalidate caches.
	 *
	 * @param callable $callback Callback to execute when hooks fire.
	 */
	protected static function register_cache_invalidation_hooks( callable $callback ): void {
		foreach ( array(
			'save_post',
			'delete_post',
			'deleted_post',
			'trashed_post',
			'untrashed_post',
			'transition_post_status',
			'clean_post_cache',
		) as $hook ) {
			add_action( $hook, $callback );
		}

		foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) {
			add_action( $hook, $callback );
		}

		foreach ( array(
			'create_term',
			'created_term',
			'edit_term',
			'edited_term',
			'delete_term',
			'deleted_term',
			'set_object_terms',
			'added_term_relationship',
			'deleted_term_relationships',
			'edited_terms',
		) as $hook ) {
			add_action( $hook, $callback );
		}

		add_action( 'switch_blog', $callback );
	}
}
