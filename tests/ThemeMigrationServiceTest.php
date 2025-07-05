<?php

use PHPUnit\Framework\TestCase;

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// Mock WordPress functions
if (!function_exists('get_option')) {
    function get_option($name, $default = false) {
        return $GLOBALS['wp_options'][$name] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($name, $value, $autoload = 'yes') {
        $GLOBALS['wp_options'][$name] = $value;
        return true;
    }
}

// Mock classes for theme migration
class MockThemeRepository {
    public $themes = [];
    public $saved_themes = [];
    
    public function find_by_name($name) {
        return $this->themes[$name] ?? null;
    }
    
    public function save($theme) {
        $this->saved_themes[] = $theme;
        $this->themes[$theme->get_name()] = $theme;
        return true;
    }
    
    public function get_active_theme() {
        return $this->themes['active'] ?? null;
    }
    
    public function get_all() {
        return array_values($this->themes);
    }
}

class MockTheme {
    const TYPE_PRESET = 'preset';
    const TYPE_CUSTOM = 'custom';
    
    private $data;
    
    public function __construct($data) {
        $this->data = $data;
    }
    
    public function get_name() {
        return $this->data['name'];
    }
    
    public function get_type() {
        return $this->data['type'];
    }
    
    public function get_config() {
        return $this->data['config'];
    }
    
    public function to_array() {
        return $this->data;
    }
}

class MockThemeCssGenerator {
    public $repository;
    public $generated_css = [];
    
    public function __construct($repository) {
        $this->repository = $repository;
    }
    
    public function generate_css($theme) {
        $css = "/* Generated CSS for theme: {$theme->get_name()} */";
        $this->generated_css[] = $css;
        return $css;
    }
}

class MockThemeSchema {
    public static $table_created = false;
    
    public static function create_table() {
        self::$table_created = true;
        return true;
    }
}

// Replace the actual classes with mocks
class_alias('MockTheme', 'NuclearEngagement\Models\Theme');
class_alias('MockThemeRepository', 'NuclearEngagement\Repositories\ThemeRepository');
class_alias('MockThemeCssGenerator', 'NuclearEngagement\Services\ThemeCssGenerator');
class_alias('MockThemeSchema', 'NuclearEngagement\Database\Schema\ThemeSchema');

require_once __DIR__ . '/../nuclear-engagement/inc/Services/ThemeMigrationService.php';

class ThemeMigrationServiceTest extends TestCase {
    
    private $originalGlobals;
    private $service;
    private $mockRepository;
    private $mockCssGenerator;
    
    protected function setUp(): void {
        // Store original globals
        $this->originalGlobals = [
            'wp_options' => $GLOBALS['wp_options'] ?? []
        ];
        
        // Reset globals
        $GLOBALS['wp_options'] = [];
        
        // Reset mock classes
        MockThemeSchema::$table_created = false;
        
        // Create mock dependencies
        $this->mockRepository = new MockThemeRepository();
        $this->mockCssGenerator = new MockThemeCssGenerator($this->mockRepository);
        
        // Create service instance
        $this->service = new \NuclearEngagement\Services\ThemeMigrationService(
            $this->mockRepository,
            $this->mockCssGenerator
        );
    }
    
    protected function tearDown(): void {
        // Restore original globals
        foreach ($this->originalGlobals as $key => $value) {
            $GLOBALS[$key] = $value;
        }
    }
    
    public function testMigrateLegacySettingsWhenAlreadyCompleted() {
        // Set migration as already completed
        update_option('nuclen_theme_migration_completed', true);
        
        $result = $this->service->migrate_legacy_settings();
        
        $this->assertTrue($result);
        // Table should not be created again
        $this->assertFalse(MockThemeSchema::$table_created);
    }
    
    public function testMigrateLegacySettingsWithoutLegacyData() {
        $result = $this->service->migrate_legacy_settings();
        
        $this->assertTrue($result);
        $this->assertTrue(MockThemeSchema::$table_created);
        $this->assertTrue(get_option('nuclen_theme_migration_completed'));
        
        // Should have created preset themes
        $this->assertCount(2, $this->mockRepository->saved_themes);
        
        $themeNames = array_map(function($theme) {
            return $theme->get_name();
        }, $this->mockRepository->saved_themes);
        
        $this->assertContains('light', $themeNames);
        $this->assertContains('dark', $themeNames);
    }
    
    public function testMigrateLegacySettingsWithLegacyData() {
        // Set up legacy settings
        $legacy_settings = [
            'quiz_container_background' => '#ffffff',
            'quiz_container_border' => '#cccccc',
            'quiz_button_background' => '#0073aa',
            'quiz_button_text' => '#ffffff',
            'progress_bar_color' => '#00a32a',
            'summary_background' => '#f9f9f9'
        ];
        
        update_option('nuclear_engagement_settings', $legacy_settings);
        
        $result = $this->service->migrate_legacy_settings();
        
        $this->assertTrue($result);
        $this->assertTrue(MockThemeSchema::$table_created);
        $this->assertTrue(get_option('nuclen_theme_migration_completed'));
        
        // Should have created preset themes + custom migrated theme
        $this->assertCount(3, $this->mockRepository->saved_themes);
        
        $themeNames = array_map(function($theme) {
            return $theme->get_name();
        }, $this->mockRepository->saved_themes);
        
        $this->assertContains('light', $themeNames);
        $this->assertContains('dark', $themeNames);
        $this->assertContains('migrated_custom', $themeNames);
    }
    
    public function testCreateLightTheme() {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('create_light_theme');
        $method->setAccessible(true);
        
        $method->invoke($this->service);
        
        $this->assertCount(1, $this->mockRepository->saved_themes);
        
        $lightTheme = $this->mockRepository->saved_themes[0];
        $this->assertEquals('light', $lightTheme->get_name());
        $this->assertEquals(MockTheme::TYPE_PRESET, $lightTheme->get_type());
        
        $config = $lightTheme->get_config();
        $this->assertArrayHasKey('quiz_container', $config);
        $this->assertArrayHasKey('quiz_button', $config);
        $this->assertArrayHasKey('progress_bar', $config);
        $this->assertArrayHasKey('summary_container', $config);
        $this->assertArrayHasKey('table_of_contents', $config);
        
        // Test specific light theme colors
        $this->assertEquals('#ffffff', $config['quiz_container']['background_color']);
        $this->assertEquals('#3b82f6', $config['quiz_button']['background_color']);
        $this->assertEquals('#10b981', $config['progress_bar']['fill_color']);
    }
    
    public function testCreateDarkTheme() {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('create_dark_theme');
        $method->setAccessible(true);
        
        $method->invoke($this->service);
        
        $this->assertCount(1, $this->mockRepository->saved_themes);
        
        $darkTheme = $this->mockRepository->saved_themes[0];
        $this->assertEquals('dark', $darkTheme->get_name());
        $this->assertEquals(MockTheme::TYPE_PRESET, $darkTheme->get_type());
        
        $config = $darkTheme->get_config();
        $this->assertArrayHasKey('quiz_container', $config);
        $this->assertArrayHasKey('quiz_button', $config);
        $this->assertArrayHasKey('progress_bar', $config);
        $this->assertArrayHasKey('summary_container', $config);
        $this->assertArrayHasKey('table_of_contents', $config);
        
        // Test specific dark theme colors
        $this->assertEquals('#1f2937', $config['quiz_container']['background_color']);
        $this->assertEquals('#3b82f6', $config['quiz_button']['background_color']);
        $this->assertEquals('#10b981', $config['progress_bar']['fill_color']);
    }
    
    public function testCreatePresetThemesSkipsExistingThemes() {
        // Pre-populate light theme
        $existingLight = new MockTheme([
            'name' => 'light',
            'type' => MockTheme::TYPE_PRESET,
            'config' => []
        ]);
        $this->mockRepository->themes['light'] = $existingLight;
        
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('create_preset_themes');
        $method->setAccessible(true);
        
        $method->invoke($this->service);
        
        // Should only create dark theme, not light
        $this->assertCount(1, $this->mockRepository->saved_themes);
        $this->assertEquals('dark', $this->mockRepository->saved_themes[0]->get_name());
    }
    
    public function testMigrateUserThemeWithValidSettings() {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('migrate_user_theme');
        $method->setAccessible(true);
        
        $legacy_settings = [
            'quiz_container_background' => '#f0f0f0',
            'quiz_container_border' => '#dddddd',
            'quiz_button_background' => '#ff6600',
            'quiz_button_text' => '#ffffff',
            'progress_bar_color' => '#33cc33',
            'summary_background' => '#fafafa'
        ];
        
        $method->invoke($this->service, $legacy_settings);
        
        $this->assertCount(1, $this->mockRepository->saved_themes);
        
        $migratedTheme = $this->mockRepository->saved_themes[0];
        $this->assertEquals('migrated_custom', $migratedTheme->get_name());
        $this->assertEquals(MockTheme::TYPE_CUSTOM, $migratedTheme->get_type());
        
        $config = $migratedTheme->get_config();
        $this->assertEquals('#f0f0f0', $config['quiz_container']['background_color']);
        $this->assertEquals('#ff6600', $config['quiz_button']['background_color']);
        $this->assertEquals('#33cc33', $config['progress_bar']['fill_color']);
    }
    
    public function testMigrateUserThemeWithPartialSettings() {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('migrate_user_theme');
        $method->setAccessible(true);
        
        $legacy_settings = [
            'quiz_button_background' => '#red',
            'progress_bar_color' => '#green'
            // Missing other settings
        ];
        
        $method->invoke($this->service, $legacy_settings);
        
        $this->assertCount(1, $this->mockRepository->saved_themes);
        
        $migratedTheme = $this->mockRepository->saved_themes[0];
        $config = $migratedTheme->get_config();
        
        // Should have migrated available settings and used defaults for missing ones
        $this->assertEquals('#red', $config['quiz_button']['background_color']);
        $this->assertEquals('#green', $config['progress_bar']['fill_color']);
        
        // Should have default values for missing settings
        $this->assertArrayHasKey('quiz_container', $config);
        $this->assertArrayHasKey('summary_container', $config);
    }
    
    public function testMigrateUserThemeWithEmptySettings() {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('migrate_user_theme');
        $method->setAccessible(true);
        
        $legacy_settings = [];
        
        $method->invoke($this->service, $legacy_settings);
        
        $this->assertCount(1, $this->mockRepository->saved_themes);
        
        $migratedTheme = $this->mockRepository->saved_themes[0];
        $config = $migratedTheme->get_config();
        
        // Should create theme with all default values
        $this->assertArrayHasKey('quiz_container', $config);
        $this->assertArrayHasKey('quiz_button', $config);
        $this->assertArrayHasKey('progress_bar', $config);
        $this->assertArrayHasKey('summary_container', $config);
        $this->assertArrayHasKey('table_of_contents', $config);
    }
    
    public function testConstructorWithDefaults() {
        $service = new \NuclearEngagement\Services\ThemeMigrationService();
        
        // Should not throw any errors and should be ready to use
        $this->assertInstanceOf(\NuclearEngagement\Services\ThemeMigrationService::class, $service);
    }
    
    public function testFullMigrationWorkflow() {
        // Set up legacy settings
        $legacy_settings = [
            'quiz_container_background' => '#ffffff',
            'quiz_container_border' => '#cccccc',
            'quiz_button_background' => '#0073aa',
            'quiz_button_text' => '#ffffff',
            'progress_bar_color' => '#00a32a',
            'summary_background' => '#f9f9f9'
        ];
        
        update_option('nuclear_engagement_settings', $legacy_settings);
        
        // Run full migration
        $result = $this->service->migrate_legacy_settings();
        
        // Verify complete migration
        $this->assertTrue($result);
        $this->assertTrue(get_option('nuclen_theme_migration_completed'));
        $this->assertTrue(MockThemeSchema::$table_created);
        
        // Verify all themes were created
        $this->assertCount(3, $this->mockRepository->saved_themes);
        
        $themeNames = array_map(function($theme) {
            return $theme->get_name();
        }, $this->mockRepository->saved_themes);
        
        $this->assertContains('light', $themeNames);
        $this->assertContains('dark', $themeNames);
        $this->assertContains('migrated_custom', $themeNames);
        
        // Verify migrated theme has correct values
        $migratedTheme = null;
        foreach ($this->mockRepository->saved_themes as $theme) {
            if ($theme->get_name() === 'migrated_custom') {
                $migratedTheme = $theme;
                break;
            }
        }
        
        $this->assertNotNull($migratedTheme);
        $config = $migratedTheme->get_config();
        $this->assertEquals('#ffffff', $config['quiz_container']['background_color']);
        $this->assertEquals('#0073aa', $config['quiz_button']['background_color']);
        $this->assertEquals('#00a32a', $config['progress_bar']['fill_color']);
    }
    
    public function testMultipleMigrationCalls() {
        // First migration
        $result1 = $this->service->migrate_legacy_settings();
        $this->assertTrue($result1);
        
        $firstMigrationCount = count($this->mockRepository->saved_themes);
        
        // Second migration (should be skipped)
        $result2 = $this->service->migrate_legacy_settings();
        $this->assertTrue($result2);
        
        // Should not create additional themes
        $this->assertCount($firstMigrationCount, $this->mockRepository->saved_themes);
    }
}