<?php
/**
 * Shared test support for seeding TaskTransientManager data in memory.
 *
 * Problem this replaces:
 *   Individual test files used to declare an ad-hoc replacement class via
 *   eval() that exposed a public static `$test_data` array. That only worked
 *   if the replacement eval'd class won autoloader races; any test that ran
 *   after Composer loaded the real TaskTransientManager saw a nonexistent
 *   property and crashed with a fatal error.
 *
 * The fix:
 *   Tests use the real TaskTransientManager against the bootstrap's in-memory
 *   `$GLOBALS['wp_transients']` store. Seeding happens through this trait so
 *   key prefixing stays in one place. A namespaced `wp_using_ext_object_cache`
 *   shim forces the transient-API path instead of direct-DB fallback.
 */

namespace NuclearEngagement\Services {
	if ( ! function_exists( __NAMESPACE__ . '\\wp_using_ext_object_cache' ) ) {
		function wp_using_ext_object_cache() {
			return false;
		}
	}
}

namespace NuclearEngagement\Tests\Support {

	use NuclearEngagement\Services\TaskTransientManager;

	trait TaskTransientStubTrait {

		/**
		 * Seed a parent-task transient. Key is `nuclen_bulk_job_{task_id}` —
		 * the same prefix used by TaskTransientManager::set_task_transient.
		 */
		protected function seedTaskTransient( string $task_id, array $data ): void {
			$this->ensureTransientStore();
			$GLOBALS['wp_transients'][ TaskTransientManager::TRANSIENT_PREFIX . $task_id ] = $data;
		}

		/**
		 * Seed a batch transient. Key is `nuclen_batch_{batch_id}`.
		 */
		protected function seedBatchTransient( string $batch_id, array $data ): void {
			$this->ensureTransientStore();
			$GLOBALS['wp_transients'][ TaskTransientManager::BATCH_PREFIX . $batch_id ] = $data;
		}

		/**
		 * Read back a parent-task transient directly (for assertions).
		 */
		protected function getTaskTransientRaw( string $task_id ) {
			$this->ensureTransientStore();
			return $GLOBALS['wp_transients'][ TaskTransientManager::TRANSIENT_PREFIX . $task_id ] ?? null;
		}

		/**
		 * Read back a batch transient directly (for assertions).
		 */
		protected function getBatchTransientRaw( string $batch_id ) {
			$this->ensureTransientStore();
			return $GLOBALS['wp_transients'][ TaskTransientManager::BATCH_PREFIX . $batch_id ] ?? null;
		}

		/**
		 * Clear all seeded transients. Call from setUp/tearDown to isolate tests.
		 */
		protected function resetTransientStubs(): void {
			$GLOBALS['wp_transients'] = array();
		}

		private function ensureTransientStore(): void {
			if ( ! isset( $GLOBALS['wp_transients'] ) || ! is_array( $GLOBALS['wp_transients'] ) ) {
				$GLOBALS['wp_transients'] = array();
			}
		}
	}
}
