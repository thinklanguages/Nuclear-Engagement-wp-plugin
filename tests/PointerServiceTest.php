<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Services\PointerService;

class PointerServiceTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['wp_user_meta'] = [];
    }

    public function test_dismiss_pointer_sets_meta(): void {
        $service = new PointerService();
        $service->dismissPointer('intro', 10);
        $this->assertTrue(
            $GLOBALS['wp_user_meta'][10]['nuclen_pointer_dismissed_intro'] ?? false
        );
    }

    public function test_get_undismissed_filters_dismissed(): void {
        $GLOBALS['wp_user_meta'][5]['nuclen_pointer_dismissed_a'] = true;
        $pointers = [
            ['id' => 'a', 'title' => 'A'],
            ['id' => 'b', 'title' => 'B'],
        ];
        $service = new PointerService();
        $undismissed = $service->getUndismissedPointers($pointers, 5);
        $this->assertCount(1, $undismissed);
        $this->assertSame('b', $undismissed[0]['id']);
    }
}
