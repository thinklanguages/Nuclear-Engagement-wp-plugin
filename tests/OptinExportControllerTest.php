<?php
namespace NuclearEngagement\Services {
	class OptinExportService {
		public static int $calls = 0;
		public function stream_csv(): void { self::$calls++; }
	}
}

namespace NuclearEngagement {
	use NuclearEngagement\Services\OptinExportService;
	class OptinData {
		public static function handle_export(): void {
			( new OptinExportService() )->stream_csv();
		}
	}
}

namespace {
	use PHPUnit\Framework\TestCase;
	use NuclearEngagement\Admin\Controller\OptinExportController;
	require_once __DIR__ . '/../nuclear-engagement/admin/Controller/OptinExportController.php';

	class OptinExportControllerTest extends TestCase {
		protected function setUp(): void {
			\NuclearEngagement\Services\OptinExportService::$calls = 0;
		}

		public function test_handle_invokes_export(): void {
			$c = new OptinExportController();
			$c->handle();
			$this->assertSame(1, \NuclearEngagement\Services\OptinExportService::$calls);
		}
	}
}
