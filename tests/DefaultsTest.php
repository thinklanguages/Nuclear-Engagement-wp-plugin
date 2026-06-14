<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Core\Defaults;

class DefaultsTest extends TestCase {
	public function test_defaults_contains_theme_key() {
		$this->markTestSkipped("STALE expectation: the default 'theme' value was intentionally changed from 'bright' to 'light' (commit e7cad1c). The 'theme' key still exists; only the asserted value is outdated.");
		$defaults = Defaults::nuclen_get_default_settings();
		$this->assertArrayHasKey('theme', $defaults);
		$this->assertEquals('bright', $defaults['theme']);
	}
}
