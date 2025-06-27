<?php
namespace NuclearEngagement\Traits {
	// Stub add_action to record hooks
	function add_action(...$args) {
		$GLOBALS['ci_actions'][] = $args;
	}
}

namespace {
	use PHPUnit\Framework\TestCase;
	use NuclearEngagement\Traits\CacheInvalidationTrait;

	class DummyInvalidator {
		use CacheInvalidationTrait;

		public static function register(): void {
			self::register_invalidation_hooks( array( self::class, 'cb' ) );
		}

		public static function cb(): void {}
	}

	class CacheInvalidationTraitTest extends TestCase {
		protected function setUp(): void {
			$GLOBALS['ci_actions'] = array();
		}

		public function test_hooks_registered(): void {
			DummyInvalidator::register();
			$this->assertCount( 21, $GLOBALS['ci_actions'] );
			$this->assertSame( 'save_post', $GLOBALS['ci_actions'][0][0] );
			$this->assertSame( array( DummyInvalidator::class, 'cb' ), $GLOBALS['ci_actions'][0][1] );
			$this->assertSame( 'switch_blog', $GLOBALS['ci_actions'][20][0] );
		}
	}
}
