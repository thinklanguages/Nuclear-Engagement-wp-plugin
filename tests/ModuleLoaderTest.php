<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Core\ModuleLoader;

class ModuleLoaderTest extends TestCase {
	private static string $dir;

	public static function setUpBeforeClass(): void {
		self::$dir = sys_get_temp_dir() . '/mods_' . uniqid();
		mkdir(self::$dir . '/inc/Modules/Alpha', 0777, true);
		mkdir(self::$dir . '/inc/Modules/Beta', 0777, true);
		file_put_contents(self::$dir . '/inc/Modules/Alpha/loader.php', "<?php\n\$GLOBALS['loaded'][] = 'alpha';\n");
		file_put_contents(self::$dir . '/inc/Modules/Beta/loader.php', "<?php\n\$GLOBALS['loaded'][] = 'beta';\n");
		require_once dirname(__DIR__) . '/nuclear-engagement/inc/Core/ModuleLoader.php';
	}

	protected function setUp(): void {
		$GLOBALS['loaded'] = [];
	}

	public static function tearDownAfterClass(): void {
		self::deleteDir(self::$dir);
	}

	private static function deleteDir(string $dir): void {
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
				self::deleteDir($path);
			} else {
				@unlink($path);
			}
		}
		@rmdir($dir);
	}

	public function test_load_all_loads_modules(): void {
		$loader = new ModuleLoader(self::$dir . '/');
		$loader->load_all();
		sort($GLOBALS['loaded']);
		$this->assertSame(['alpha', 'beta'], $GLOBALS['loaded']);
	}
}
