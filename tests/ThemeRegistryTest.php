<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Core\ThemeRegistry;

class ThemeRegistryTest extends TestCase {
	public static function setUpBeforeClass(): void {
		require_once dirname(__DIR__) . '/nuclear-engagement/inc/Core/Themes.php';
	}

	protected function setUp(): void {
		$this->resetRegistry();
	}

	private function resetRegistry(): void {
		$ref = new \ReflectionClass(ThemeRegistry::class);
		$prop = $ref->getProperty('themes');
		$prop->setAccessible(true);
		$prop->setValue([
			'bright' => 'nuclen-theme-bright.css',
			'dark'   => 'nuclen-theme-dark.css',
		]);
	}

	public function test_get_themes_returns_default_themes(): void {
		$expected = [
			'bright' => 'nuclen-theme-bright.css',
			'dark'   => 'nuclen-theme-dark.css',
		];
		$this->assertSame($expected, ThemeRegistry::get_themes());
	}

	public function test_register_adds_new_theme(): void {
		ThemeRegistry::register('blue', 'nuclen-blue.css');
		$themes = ThemeRegistry::get_themes();
		$this->assertArrayHasKey('blue', $themes);
		$this->assertSame('nuclen-blue.css', $themes['blue']);
	}

	public function test_get_returns_stylesheet_or_null(): void {
		ThemeRegistry::register('mono', 'mono.css');
		$this->assertSame('mono.css', ThemeRegistry::get('mono'));
		$this->assertNull(ThemeRegistry::get('missing'));
	}
}
