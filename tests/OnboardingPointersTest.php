<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Admin\OnboardingPointers;

require_once __DIR__ . '/../nuclear-engagement/admin/OnboardingPointers.php';

class OnboardingPointersTest extends TestCase {
    public function test_get_pointers_returns_expected_structure(): void {
        $pointers = OnboardingPointers::get_pointers();
        $this->assertIsArray($pointers);
        $this->assertArrayHasKey('post.php', $pointers);
        $first = $pointers['post.php'][0];
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('target', $first);
        $this->assertArrayHasKey('title', $first);
        $this->assertArrayHasKey('content', $first);
    }
}
