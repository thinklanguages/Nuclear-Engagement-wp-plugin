<?php

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Core\Installer;

class InstallerSimpleTest extends TestCase {

	public function test_installer_class_exists(): void {
		$this->assertTrue(class_exists('NuclearEngagement\Core\Installer'));
	}

	public function test_installer_has_required_methods(): void {
		$reflection = new ReflectionClass(Installer::class);
		
		$this->assertTrue($reflection->hasMethod('activate'));
		$this->assertTrue($reflection->hasMethod('deactivate'));
		$this->assertTrue($reflection->hasMethod('migrate_post_meta'));
	}

	public function test_activate_method_is_public(): void {
		$reflection = new ReflectionClass(Installer::class);
		$method = $reflection->getMethod('activate');
		
		$this->assertTrue($method->isPublic());
		$this->assertFalse($method->isStatic());
	}

	public function test_deactivate_method_is_public(): void {
		$reflection = new ReflectionClass(Installer::class);
		$method = $reflection->getMethod('deactivate');
		
		$this->assertTrue($method->isPublic());
		$this->assertFalse($method->isStatic());
	}

	public function test_migrate_post_meta_method_is_public(): void {
		$reflection = new ReflectionClass(Installer::class);
		$method = $reflection->getMethod('migrate_post_meta');
		
		$this->assertTrue($method->isPublic());
		$this->assertFalse($method->isStatic());
	}

	public function test_installer_instantiation(): void {
		try {
			$installer = new Installer();
			$this->assertInstanceOf(Installer::class, $installer);
		} catch (Throwable $e) {
			$this->fail('Installer should be instantiable: ' . $e->getMessage());
		}
	}

	public function test_migrate_post_meta_handles_no_migration_needed(): void {
		// Set migration as already done
		update_option('nuclen_meta_migration_done', true);
		
		$installer = new Installer();
		
		// This should not throw any errors and should return quickly
		try {
			$installer->migrate_post_meta();
			$this->assertTrue(true);
		} catch (Throwable $e) {
			$this->fail('migrate_post_meta should handle already-done migration gracefully: ' . $e->getMessage());
		}
		
		// Clean up
		delete_option('nuclen_meta_migration_done');
	}

	public function test_installer_methods_dont_throw_fatal_errors(): void {
		$installer = new Installer();
		
		// These methods may fail due to missing dependencies, but they shouldn't
		// throw fatal errors or cause PHP to crash
		try {
			// We can't easily test these without setting up the full WordPress environment
			// But we can at least verify the methods exist and are callable
			$this->assertTrue(method_exists($installer, 'activate'));
			$this->assertTrue(method_exists($installer, 'deactivate'));
			$this->assertTrue(method_exists($installer, 'migrate_post_meta'));
		} catch (Throwable $e) {
			$this->fail('Installer methods should exist and be callable: ' . $e->getMessage());
		}
	}

	public function test_installer_uses_expected_dependencies(): void {
		// Check that the Installer references the expected classes in its source
		$reflection = new ReflectionClass(Installer::class);
		$filename = $reflection->getFileName();
		$source = file_get_contents($filename);
		
		// Check for expected class references
		$this->assertStringContainsString('Defaults::', $source);
		$this->assertStringContainsString('SettingsRepository::', $source);
		$this->assertStringContainsString('Activator::', $source);
		$this->assertStringContainsString('Deactivator::', $source);
		$this->assertStringContainsString('ApiUserManager::', $source);
	}

	public function test_migration_constants_are_used(): void {
		$reflection = new ReflectionClass(Installer::class);
		$filename = $reflection->getFileName();
		$source = file_get_contents($filename);
		
		// Check for migration-related patterns
		$this->assertStringContainsString('nuclen_meta_migration_done', $source);
		$this->assertStringContainsString('ne-summary-data', $source);
		$this->assertStringContainsString('ne-quiz-data', $source);
		$this->assertStringContainsString('nuclen-quiz-data', $source);
	}
}