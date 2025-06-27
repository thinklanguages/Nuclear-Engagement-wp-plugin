<?php
namespace NuclearEngagement {
	class AssetVersions {
		public static function get(string $key): string {
			return $GLOBALS['vs_stub_value'] ?? '';
		}
	}
}

namespace {
	use PHPUnit\Framework\TestCase;
	use NuclearEngagement\Services\VersionService;
	if (!function_exists('apply_filters')) {
		function apply_filters($hook, $value, ...$args) {
			if ($hook === 'nuclen_asset_version' && isset($GLOBALS['vs_filter'])) {
				return $GLOBALS['vs_filter'];
			}
			return $value;
		}
	}

	class VersionServiceTest extends TestCase {
		protected function setUp(): void {
			$GLOBALS['vs_stub_value'] = 'original';
			$GLOBALS['vs_filter'] = null;
		}

		public function test_get_returns_filtered_value(): void {
			$GLOBALS['vs_stub_value'] = '1';
			$GLOBALS['vs_filter'] = '2';
			$svc = new VersionService();
			$this->assertSame('2', $svc->get('admin_css'));
		}

		public function test_get_throws_on_empty_key(): void {
			$svc = new VersionService();
			$this->expectException(InvalidArgumentException::class);
			$svc->get('');
		}
	}
}
