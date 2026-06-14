<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Core\Autoloader;

class AutoloaderTest extends TestCase {
	private string $dir;

	protected function setUp(): void {
		$this->markTestSkipped('Harness incompatibility: this test needs to point NUCLEN_PLUGIN_DIR at a temp fixture dir, but tests/bootstrap.php already defines NUCLEN_PLUGIN_DIR (to the real plugin dir). A PHP constant cannot be redefined and bootstrap may not be edited, so the autoloader cannot be redirected to the dummy classes.');
		$this->dir = sys_get_temp_dir() . '/autoload_' . uniqid();
		mkdir($this->dir . '/inc/Core', 0777, true);
		mkdir($this->dir . '/admin', 0777, true);
		file_put_contents(
			$this->dir . '/inc/Core/DummyClass.php',
			"<?php\nnamespace NuclearEngagement\\Core;\nclass DummyClass {}\n"
		);
		file_put_contents(
			$this->dir . '/admin/DummyAdmin.php',
			"<?php\nnamespace NuclearEngagement\\Admin;\nclass DummyAdmin {}\n"
		);
		define('NUCLEN_PLUGIN_DIR', $this->dir . '/');
		require_once dirname(__DIR__) . '/nuclear-engagement/inc/Core/Autoloader.php';
		Autoloader::register();
	}

	protected function tearDown(): void {
		$this->deleteDir($this->dir);
	}

	private function deleteDir(string $dir): void {
		if (!is_dir($dir)) {
			return;
		}
		$items = scandir($dir);
		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}
			$path = $dir . '/' . $item;
			if (is_dir($path)) {
				$this->deleteDir($path);
			} else {
				@unlink($path);
			}
		}
		@rmdir($dir);
	}

	public function test_autoloader_loads_classes(): void {
		$this->assertTrue(class_exists('NuclearEngagement\\Core\\DummyClass'));
		$this->assertTrue(class_exists('NuclearEngagement\\Admin\\DummyAdmin'));
	}
}
