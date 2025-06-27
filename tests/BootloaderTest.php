<?php
namespace NuclearEngagement\Core {
	function add_action(...$args) {
		$GLOBALS['bl_actions'][] = $args;
	}
	function register_activation_hook(...$args) {
		$GLOBALS['bl_activation'][] = $args;
	}
	function register_deactivation_hook(...$args) {
		$GLOBALS['bl_deactivation'][] = $args;
	}
	if ( ! function_exists('__') ) {
		function __( $t, $d = null ) { return $t; }
	}
	if ( ! function_exists( 'get_file_data' ) ) {
		function get_file_data( $f, $k, $t = null ) { return array( 'Version' => '1.0' ); }
	}
}

namespace {
	use PHPUnit\Framework\TestCase;
	use NuclearEngagement\Core\Bootloader;

	class BootloaderTest extends TestCase {
		private string $vendorDir;

		protected function setUp(): void {
			$this->vendorDir = dirname( __DIR__ ) . '/nuclear-engagement/vendor';
			$GLOBALS['bl_actions'] = array();
			$GLOBALS['bl_activation'] = array();
			$GLOBALS['bl_deactivation'] = array();
			$GLOBALS['vendor_autoload_included'] = false;
			@mkdir( $this->vendorDir, 0777, true );
			file_put_contents(
			    $this->vendorDir . '/autoload.php',
			    "<?php\n\$GLOBALS['vendor_autoload_included'] = true;\n"
			);
			if ( ! defined( 'NUCLEN_PLUGIN_FILE' ) ) {
			    define( 'NUCLEN_PLUGIN_FILE', dirname( __DIR__ ) . '/nuclear-engagement/nuclear-engagement.php' );
			}
		}

		protected function tearDown(): void {
			@unlink( $this->vendorDir . '/autoload.php' );
			@rmdir( $this->vendorDir );
			unset( $GLOBALS['vendor_autoload_included'] );
		}

		public function test_init_defines_constants_and_registers_hooks(): void {
			$before = spl_autoload_functions() ?: array();
			require_once dirname( __DIR__ ) . '/nuclear-engagement/inc/Core/Bootloader.php';
			Bootloader::init();

			$this->assertTrue( defined( 'NUCLEN_PLUGIN_DIR' ) );
			$this->assertTrue( defined( 'NUCLEN_PLUGIN_VERSION' ) );
			$this->assertTrue( defined( 'NUCLEN_ASSET_VERSION' ) );

			$hooks = array_column( $GLOBALS['bl_actions'], 0 );
			$this->assertContains( 'init', $hooks );
			$this->assertContains( 'plugins_loaded', $hooks );
			$this->assertNotEmpty( $GLOBALS['bl_activation'] );
			$this->assertNotEmpty( $GLOBALS['bl_deactivation'] );

			$after = spl_autoload_functions();
			$this->assertGreaterThan( count( $before ), count( $after ) );
			$this->assertTrue( $GLOBALS['vendor_autoload_included'] );
		}
	}
}
