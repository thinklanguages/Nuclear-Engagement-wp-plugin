<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Helpers\SettingsHelper;
use NuclearEngagement\Helpers\SettingsFunctions;
use NuclearEngagement\Core\SettingsRepository;

require_once dirname(__DIR__) . '/nuclear-engagement/inc/Helpers/SettingsHelper.php';
require_once dirname(__DIR__) . '/nuclear-engagement/inc/Helpers/SettingsFunctions.php';

class SettingsFunctionsTest extends TestCase {
private $repo;
private \ReflectionProperty $prop;

protected function setUp(): void {
// SettingsHelper::$repo is now strictly typed as ?SettingsRepository,
// so the injected fake must be a SettingsRepository instance. Use a mock
// (constructor is private; createMock bypasses it) configured to return
// the same canned values the old duck-typed DummyRepo2 returned.
$this->repo = $this->createMock(SettingsRepository::class);
$this->repo->method('all')->willReturn(['baz' => 'qux']);
$this->repo->method('get')->willReturnCallback(fn($key, $default = null) => 'g_' . $key);
$this->repo->method('get_bool')->willReturn(false);
$this->repo->method('get_int')->willReturn(7);
$this->repo->method('get_string')->willReturn('ok');
$this->repo->method('get_array')->willReturn(['x']);
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
