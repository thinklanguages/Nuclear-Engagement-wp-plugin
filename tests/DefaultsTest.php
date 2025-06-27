<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Core\Defaults;

class DefaultsTest extends TestCase {
	public function test_defaults_contains_theme_key() {
		$defaults = Defaults::nuclen_get_default_settings();
		$this->assertArrayHasKey('theme', $defaults);
		$this->assertEquals('bright', $defaults['theme']);
	}
}
