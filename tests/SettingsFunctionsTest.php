<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Helpers\SettingsHelper;
use NuclearEngagement\Helpers\SettingsFunctions;

require_once dirname(__DIR__) . '/nuclear-engagement/inc/Helpers/SettingsHelper.php';
require_once dirname(__DIR__) . '/nuclear-engagement/inc/Helpers/SettingsFunctions.php';

class DummyRepo2 {
public function all(): array { return ['baz' => 'qux']; }
public function get(string $key, $default = null) { return 'g_' . $key; }
public function get_bool(string $key, bool $default = false): bool { return false; }
public function get_int(string $key, int $default = 0): int { return 7; }
public function get_string(string $key, string $default = ''): string { return 'ok'; }
public function get_array(string $key, array $default = array()): array { return ['x']; }
}

class SettingsFunctionsTest extends TestCase {
private DummyRepo2 $repo;
private \ReflectionProperty $prop;

protected function setUp(): void {
$this->repo = new DummyRepo2();
$this->prop = new \ReflectionProperty(SettingsHelper::class, 'repo');
$this->prop->setAccessible(true);
$this->prop->setValue(null, $this->repo);
}

protected function tearDown(): void {
$this->prop->setValue(null, null);
}

public function test_wrappers_proxy_helper_methods(): void {
$this->assertSame(['baz' => 'qux'], SettingsFunctions::get());
$this->assertSame('g_name', SettingsFunctions::get('name'));
$this->assertFalse(SettingsFunctions::get_bool('flag'));
$this->assertSame(7, SettingsFunctions::get_int('num'));
$this->assertSame('ok', SettingsFunctions::get_string('text'));
$this->assertSame(['x'], SettingsFunctions::get_array('arr'));
}
}
