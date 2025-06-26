<?php
namespace NuclearEngagement {
    class OptinData {
        public static int $calls = 0;
        public static function handle_export(): void { self::$calls++; }
    }
}

namespace {
    use PHPUnit\Framework\TestCase;
    use NuclearEngagement\Admin\Controller\OptinExportController;
    require_once __DIR__ . '/../nuclear-engagement/admin/Controller/OptinExportController.php';

    class OptinExportControllerTest extends TestCase {
        protected function setUp(): void {
            \NuclearEngagement\OptinData::$calls = 0;
        }

        public function test_handle_invokes_export(): void {
            $c = new OptinExportController();
            $c->handle();
            $this->assertSame(1, \NuclearEngagement\OptinData::$calls);
        }
    }
}
