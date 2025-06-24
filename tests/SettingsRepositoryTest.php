<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\SettingsRepository;
use NuclearEngagement\SettingsSanitizer;

class SettingsRepositoryTest extends TestCase {
    private \ReflectionMethod $sanitizeMethod;

    protected function setUp(): void {
        $this->sanitizeMethod = new \ReflectionMethod(SettingsSanitizer::class, 'sanitize_heading_levels');
        $this->sanitizeMethod->setAccessible(true);
    }

    public function test_sanitize_heading_levels_filters_invalid_values() {
        $input = ['1', '2', '7', 'abc'];
        $expected = [1,2];
        $this->assertSame($expected, $this->sanitizeMethod->invoke(null, $input));
    }

    public function test_singleton_returns_same_instance() {
        $a = SettingsRepository::get_instance(['theme' => 'dark']);
        $b = SettingsRepository::get_instance();
        $this->assertSame($a, $b);
    }

    public function test_sanitize_post_types_removes_invalid() {
        SettingsRepository::reset_for_tests();
        $ref = new \ReflectionMethod(SettingsSanitizer::class, 'sanitize_post_types');
        $ref->setAccessible(true);
        $input = ['POST', 'page', 'invalid', 'custom?'];
        $expected = ['post', 'page'];
        $this->assertSame($expected, $ref->invoke(null, $input));
    }

    public function test_should_autoload_based_on_size() {
        SettingsRepository::reset_for_tests();
        $instance = SettingsRepository::get_instance();
        $ref = new \ReflectionMethod(SettingsRepository::class, 'should_autoload');
        $ref->setAccessible(true);
        $small = ['a' => 'b'];
        $this->assertTrue($ref->invoke($instance, $small));
        $big = ['data' => str_repeat('x', SettingsRepository::MAX_AUTOLOAD_SIZE + 1)];
        $this->assertFalse($ref->invoke($instance, $big));
    }

    public function test_get_defaults_includes_custom_values() {
        SettingsRepository::reset_for_tests();
        $instance = SettingsRepository::get_instance(['foo' => 'bar', 'theme' => 'dark']);
        $defaults = $instance->get_defaults();
        $this->assertSame('bar', $defaults['foo']);
        $this->assertSame('dark', $defaults['theme']);
        $this->assertArrayHasKey('quiz_title', $defaults);
    }
}
