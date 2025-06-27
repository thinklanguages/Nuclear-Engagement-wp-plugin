<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Helpers\SettingsHelper;

require_once dirname(__DIR__) . '/nuclear-engagement/inc/Helpers/SettingsHelper.php';
require_once dirname(__DIR__) . '/nuclear-engagement/inc/Core/SettingsRepository.php';

class DummySettingsRepository {
public function all(): array { return ['foo' => 'bar']; }
public function get(string $key, $default = null) { return 'val_' . $key; }
public function get_bool(string $key, bool $default = false): bool { return true; }
public function get_int(string $key, int $default = 0): int { return 99; }
public function get_string(string $key, string $default = ''): string { return 'str'; }
public function get_array(string $key, array $default = array()): array { return ['a', 'b']; }
}

class SettingsHelperTest extends TestCase {
private DummySettingsRepository $repo;
private \ReflectionProperty $prop;

protected function setUp(): void {
$this->repo = new DummySettingsRepository();
$this->prop = new \ReflectionProperty(SettingsHelper::class, 'repo');
$this->prop->setAccessible(true);
$this->prop->setValue(null, $this->repo);
}

protected function tearDown(): void {
$this->prop->setValue(null, null);
}

public function test_get_returns_all_when_no_key(): void {
$this->assertSame(['foo' => 'bar'], SettingsHelper::get());
}

public function test_get_returns_specific_value(): void {
$this->assertSame('val_key', SettingsHelper::get('key'));
}

public function test_get_bool_proxies_repository(): void {
$this->assertTrue(SettingsHelper::get_bool('flag'));
}

public function test_get_int_proxies_repository(): void {
$this->assertSame(99, SettingsHelper::get_int('num'));
}

public function test_get_string_proxies_repository(): void {
$this->assertSame('str', SettingsHelper::get_string('text'));
}

public function test_get_array_proxies_repository(): void {
$this->assertSame(['a', 'b'], SettingsHelper::get_array('arr'));
}
}
