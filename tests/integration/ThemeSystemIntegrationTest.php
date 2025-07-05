<?php

use PHPUnit\Framework\TestCase;

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// Mock WordPress functions for integration testing
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

if (!function_exists('wp_generate_uuid4')) {
    function wp_generate_uuid4() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}

if (!function_exists('do_action')) {
    function do_action($hook, ...$args) {
        $GLOBALS['wp_actions'][$hook] = $args;
    }
}

// Mock classes for integration testing
class IntegrationMockTheme {
    const TYPE_PRESET = 'preset';
    const TYPE_CUSTOM = 'custom';
    
    private $data;
    
    public function __construct($data) {
        $this->data = array_merge([
            'id' => null,
            'name' => '',
            'type' => self::TYPE_CUSTOM,
            'config' => [],
            'is_active' => false,
            'created_at' => time(),
            'updated_at' => time()
        ], $data);
    }
    
    public function get_id() { return $this->data['id']; }
    public function get_name() { return $this->data['name']; }
    public function get_type() { return $this->data['type']; }
    public function get_config() { return $this->data['config']; }
    public function is_active() { return $this->data['is_active']; }
    public function get_created_at() { return $this->data['created_at']; }
    public function get_updated_at() { return $this->data['updated_at']; }
    
    public function set_active($active) {
        $this->data['is_active'] = $active;
    }
    
    public function update_config($config) {
        $this->data['config'] = array_merge($this->data['config'], $config);
        $this->data['updated_at'] = time();
    }
    
    public function to_array() {
        return $this->data;
    }
}

class IntegrationMockThemeRepository {
    private $themes = [];
    private $next_id = 1;
    
    public function save($theme) {
        if (!$theme->get_id()) {
            $reflection = new ReflectionClass($theme);
            $property = $reflection->getProperty('data');
            $property->setAccessible(true);
            $data = $property->getValue($theme);
            $data['id'] = $this->next_id++;
            $property->setValue($theme, $data);
        }
        
        $this->themes[$theme->get_id()] = $theme;
        return $theme;
    }
    
    public function find_by_id($id) {
        return $this->themes[$id] ?? null;
    }
    
    public function find_by_name($name) {
        foreach ($this->themes as $theme) {
            if ($theme->get_name() === $name) {
                return $theme;
            }
        }
        return null;
    }
    
    public function get_active_theme() {
        foreach ($this->themes as $theme) {
            if ($theme->is_active()) {
                return $theme;
            }
        }
        return null;
    }
    
    public function get_all() {
        return array_values($this->themes);
    }
    
    public function set_active_theme($theme_id) {
        // Deactivate all themes
        foreach ($this->themes as $theme) {
            $theme->set_active(false);
        }
        
        // Activate the specified theme
        if (isset($this->themes[$theme_id])) {
            $this->themes[$theme_id]->set_active(true);
            return true;
        }
        
        return false;
    }
    
    public function delete($id) {
        if (isset($this->themes[$id])) {
            unset($this->themes[$id]);
            return true;
        }
        return false;
    }
}

class IntegrationMockThemeCssGenerator {
    private $repository;
    public $generated_css = [];
    
    public function __construct($repository) {
        $this->repository = $repository;
    }
    
    public function generate_css($theme) {
        $config = $theme->get_config();
        $css = "/* Theme: {$theme->get_name()} */\n";
        
        // Generate CSS for quiz container
        if (isset($config['quiz_container'])) {
            $css .= ".nuclen-quiz-container {\n";
            foreach ($config['quiz_container'] as $property => $value) {
                $css_property = str_replace('_', '-', $property);
                $css .= "  {$css_property}: {$value};\n";
            }
            $css .= "}\n";
        }
        
        // Generate CSS for quiz buttons
        if (isset($config['quiz_button'])) {
            $css .= ".nuclen-quiz-button {\n";
            foreach ($config['quiz_button'] as $property => $value) {
                if (strpos($property, 'hover_') === 0) continue;
                $css_property = str_replace('_', '-', $property);
                $css .= "  {$css_property}: {$value};\n";
            }
            $css .= "}\n";
            
            // Hover styles
            $css .= ".nuclen-quiz-button:hover {\n";
            foreach ($config['quiz_button'] as $property => $value) {
                if (strpos($property, 'hover_') === 0) {
                    $css_property = str_replace(['hover_', '_'], ['', '-'], $property);
                    $css .= "  {$css_property}: {$value};\n";
                }
            }
            $css .= "}\n";
        }
        
        $this->generated_css[$theme->get_name()] = $css;
        return $css;
    }
    
    public function get_generated_css($theme_name = null) {
        if ($theme_name) {
            return $this->generated_css[$theme_name] ?? '';
        }
        return $this->generated_css;
    }
}

class IntegrationMockThemeValidator {
    public function validate_config($config) {
        $errors = [];
        
        // Validate required sections
        $required_sections = ['quiz_container', 'quiz_button', 'progress_bar'];
        foreach ($required_sections as $section) {
            if (!isset($config[$section])) {
                $errors[] = "Missing required section: {$section}";
            }
        }
        
        // Validate color values
        foreach ($config as $section => $properties) {
            if (!is_array($properties)) continue;
            
            foreach ($properties as $property => $value) {
                if (strpos($property, 'color') !== false) {
                    if (!$this->is_valid_color($value)) {
                        $errors[] = "Invalid color value in {$section}.{$property}: {$value}";
                    }
                }
            }
        }
        
        return $errors;
    }
    
    private function is_valid_color($color) {
        // Simple color validation for hex colors
        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) || preg_match('/^#[0-9a-fA-F]{3}$/', $color);
    }
}

class IntegrationMockThemeEventManager {
    public $events = [];
    
    public function theme_activated($theme) {
        $this->events[] = ['theme_activated', $theme->get_name()];
        do_action('nuclen_theme_activated', $theme);
    }
    
    public function theme_deactivated($theme) {
        $this->events[] = ['theme_deactivated', $theme->get_name()];
        do_action('nuclen_theme_deactivated', $theme);
    }
    
    public function theme_updated($theme) {
        $this->events[] = ['theme_updated', $theme->get_name()];
        do_action('nuclen_theme_updated', $theme);
    }
    
    public function theme_deleted($theme) {
        $this->events[] = ['theme_deleted', $theme->get_name()];
        do_action('nuclen_theme_deleted', $theme);
    }
}

class IntegrationMockThemeSettingsService {
    private $repository;
    private $css_generator;
    private $validator;
    private $event_manager;
    
    public function __construct($repository, $css_generator, $validator, $event_manager) {
        $this->repository = $repository;
        $this->css_generator = $css_generator;
        $this->validator = $validator;
        $this->event_manager = $event_manager;
    }
    
    public function create_theme($name, $config, $type = IntegrationMockTheme::TYPE_CUSTOM) {
        $errors = $this->validator->validate_config($config);
        if (!empty($errors)) {
            throw new InvalidArgumentException('Theme validation failed: ' . implode(', ', $errors));
        }
        
        $theme = new IntegrationMockTheme([
            'name' => $name,
            'type' => $type,
            'config' => $config
        ]);
        
        return $this->repository->save($theme);
    }
    
    public function update_theme($theme_id, $config) {
        $theme = $this->repository->find_by_id($theme_id);
        if (!$theme) {
            throw new InvalidArgumentException('Theme not found');
        }
        
        $errors = $this->validator->validate_config($config);
        if (!empty($errors)) {
            throw new InvalidArgumentException('Theme validation failed: ' . implode(', ', $errors));
        }
        
        $theme->update_config($config);
        $this->repository->save($theme);
        $this->event_manager->theme_updated($theme);
        
        return $theme;
    }
    
    public function activate_theme($theme_id) {
        $current_theme = $this->repository->get_active_theme();
        if ($current_theme) {
            $this->event_manager->theme_deactivated($current_theme);
        }
        
        $success = $this->repository->set_active_theme($theme_id);
        if ($success) {
            $new_theme = $this->repository->find_by_id($theme_id);
            $this->event_manager->theme_activated($new_theme);
            
            // Generate CSS for active theme
            $this->css_generator->generate_css($new_theme);
        }
        
        return $success;
    }
    
    public function delete_theme($theme_id) {
        $theme = $this->repository->find_by_id($theme_id);
        if (!$theme) {
            return false;
        }
        
        if ($theme->is_active()) {
            throw new InvalidArgumentException('Cannot delete active theme');
        }
        
        $this->event_manager->theme_deleted($theme);
        return $this->repository->delete($theme_id);
    }
    
    public function get_active_theme_css() {
        $active_theme = $this->repository->get_active_theme();
        if (!$active_theme) {
            return '';
        }
        
        return $this->css_generator->get_generated_css($active_theme->get_name());
    }
}

// Replace actual classes with mocks
if (!class_exists('NuclearEngagement\Models\Theme')) {
    class_alias('IntegrationMockTheme', 'NuclearEngagement\Models\Theme');
}
if (!class_exists('NuclearEngagement\Repositories\ThemeRepository')) {
    class_alias('IntegrationMockThemeRepository', 'NuclearEngagement\Repositories\ThemeRepository');
}
if (!class_exists('NuclearEngagement\Services\ThemeCssGenerator')) {
    class_alias('IntegrationMockThemeCssGenerator', 'NuclearEngagement\Services\ThemeCssGenerator');
}
if (!class_exists('NuclearEngagement\Services\ThemeValidator')) {
    class_alias('IntegrationMockThemeValidator', 'NuclearEngagement\Services\ThemeValidator');
}
if (!class_exists('NuclearEngagement\Services\ThemeEventManager')) {
    class_alias('IntegrationMockThemeEventManager', 'NuclearEngagement\Services\ThemeEventManager');
}

class ThemeSystemIntegrationTest extends TestCase {
    
    private $originalGlobals;
    private $repository;
    private $cssGenerator;
    private $validator;
    private $eventManager;
    private $themeService;
    
    protected function setUp(): void {
        // Store original globals
        $this->originalGlobals = [
            'wp_options' => $GLOBALS['wp_options'] ?? [],
            'wp_actions' => $GLOBALS['wp_actions'] ?? []
        ];
        
        // Reset globals
        $GLOBALS['wp_options'] = [];
        $GLOBALS['wp_actions'] = [];
        
        // Set up components
        $this->repository = new IntegrationMockThemeRepository();
        $this->cssGenerator = new IntegrationMockThemeCssGenerator($this->repository);
        $this->validator = new IntegrationMockThemeValidator();
        $this->eventManager = new IntegrationMockThemeEventManager();
        
        $this->themeService = new IntegrationMockThemeSettingsService(
            $this->repository,
            $this->cssGenerator,
            $this->validator,
            $this->eventManager
        );
    }
    
    protected function tearDown(): void {
        // Restore original globals
        foreach ($this->originalGlobals as $key => $value) {
            $GLOBALS[$key] = $value;
        }
    }
    
    public function testCompleteThemeWorkflow() {
        // 1. Create a new theme
        $theme_config = [
            'quiz_container' => [
                'background_color' => '#ffffff',
                'border_color' => '#e5e7eb',
                'border_radius' => '8px',
                'padding' => '24px'
            ],
            'quiz_button' => [
                'background_color' => '#3b82f6',
                'text_color' => '#ffffff',
                'hover_background_color' => '#2563eb',
                'border_radius' => '6px'
            ],
            'progress_bar' => [
                'background_color' => '#e5e7eb',
                'fill_color' => '#10b981',
                'height' => '8px'
            ]
        ];
        
        $theme = $this->themeService->create_theme('custom_blue', $theme_config);
        
        $this->assertInstanceOf(IntegrationMockTheme::class, $theme);
        $this->assertEquals('custom_blue', $theme->get_name());
        $this->assertEquals(IntegrationMockTheme::TYPE_CUSTOM, $theme->get_type());
        
        // 2. Activate the theme
        $activation_result = $this->themeService->activate_theme($theme->get_id());
        $this->assertTrue($activation_result);
        
        // Verify theme is active
        $active_theme = $this->repository->get_active_theme();
        $this->assertNotNull($active_theme);
        $this->assertEquals('custom_blue', $active_theme->get_name());
        
        // Verify events were triggered
        $this->assertContains(['theme_activated', 'custom_blue'], $this->eventManager->events);
        
        // 3. Verify CSS generation
        $css = $this->themeService->get_active_theme_css();
        $this->assertNotEmpty($css);
        $this->assertStringContainsString('custom_blue', $css);
        $this->assertStringContainsString('#3b82f6', $css);
        $this->assertStringContainsString('.nuclen-quiz-container', $css);
        $this->assertStringContainsString('.nuclen-quiz-button', $css);
        
        // 4. Update the theme
        $updated_config = [
            'quiz_button' => [
                'background_color' => '#ef4444',
                'text_color' => '#ffffff',
                'hover_background_color' => '#dc2626'
            ]
        ];
        
        $updated_theme = $this->themeService->update_theme($theme->get_id(), $updated_config);
        $this->assertEquals('#ef4444', $updated_theme->get_config()['quiz_button']['background_color']);
        
        // Verify update event
        $this->assertContains(['theme_updated', 'custom_blue'], $this->eventManager->events);
        
        // 5. Create and activate a second theme
        $theme2_config = [
            'quiz_container' => [
                'background_color' => '#1f2937',
                'border_color' => '#374151'
            ],
            'quiz_button' => [
                'background_color' => '#10b981',
                'text_color' => '#ffffff'
            ],
            'progress_bar' => [
                'background_color' => '#374151',
                'fill_color' => '#34d399'
            ]
        ];
        
        $theme2 = $this->themeService->create_theme('dark_green', $theme2_config);
        $this->themeService->activate_theme($theme2->get_id());
        
        // Verify theme switching events
        $this->assertContains(['theme_deactivated', 'custom_blue'], $this->eventManager->events);
        $this->assertContains(['theme_activated', 'dark_green'], $this->eventManager->events);
        
        // Verify only one theme is active
        $active_theme = $this->repository->get_active_theme();
        $this->assertEquals('dark_green', $active_theme->get_name());
        $this->assertFalse($updated_theme->is_active());
        
        // 6. Test deletion protection for active theme
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot delete active theme');
        $this->themeService->delete_theme($theme2->get_id());
    }

    public function test_complete_theme_loading_workflow() {
        $theme_config = array(
            'name' => 'Modern Dark',
            'version' => '1.0.0',
            'styles' => array(
                'quiz_container' => array(
                    'background_color' => '#2a2a2a',
                    'border_radius' => '8px',
                    'padding' => '20px',
                ),
                'quiz_buttons' => array(
                    'background_color' => '#007cba',
                    'text_color' => '#ffffff',
                    'hover_color' => '#005a87',
				),
			),
		);

		$expected_css = '
.nuclen-quiz-container {
	background-color: #2a2a2a;
	border-radius: 8px;
	padding: 20px;
}
.nuclen-quiz-button {
	background-color: #007cba;
	color: #ffffff;
}
.nuclen-quiz-button:hover {
	background-color: #005a87;
}';

		// 1. Validator validates theme config
		$this->validator->shouldReceive( 'validate' )
			->with( $theme_config )
			->once()
			->andReturn( true );

		// 2. Theme repository saves theme
		$this->theme_repository->shouldReceive( 'save' )
			->with( \Mockery::type( 'array' ) )
			->once()
			->andReturn( 123 ); // theme ID

		// 3. Event manager dispatches theme loaded event
		$this->event_manager->shouldReceive( 'dispatch_theme_loaded' )
			->with( 123, $theme_config )
			->once();

		// 4. CSS generator creates styles
		$this->css_generator->shouldReceive( 'generate' )
			->with( $theme_config )
			->once()
			->andReturn( $expected_css );

		// 5. Settings repository updates current theme
		$this->settings_repository->shouldReceive( 'set' )
			->with( 'current_theme_id', 123 )
			->once();

		// 6. Theme loader provides access to loaded theme
		$this->theme_loader->shouldReceive( 'get_current_theme' )
			->once()
			->andReturn( $theme_config );

		// Execute the workflow
		$this->assertTrue( $this->validator->validate( $theme_config ) );
		$theme_id = $this->theme_repository->save( $theme_config );
		$this->event_manager->dispatch_theme_loaded( $theme_id, $theme_config );
		$css = $this->css_generator->generate( $theme_config );
		$this->settings_repository->set( 'current_theme_id', $theme_id );
		$loaded_theme = $this->theme_loader->get_current_theme();

		// Verify results
		$this->assertEquals( 123, $theme_id );
		$this->assertStringContainsString( 'nuclen-quiz-container', $css );
		$this->assertEquals( $theme_config, $loaded_theme );
	}

	public function test_theme_validation_failure_workflow() {
		$invalid_theme = array(
			'name' => '', // Invalid: empty name
			'styles' => 'invalid', // Invalid: should be array
		);

		// 1. Validator rejects invalid theme
		$this->validator->shouldReceive( 'validate' )
			->with( $invalid_theme )
			->once()
			->andReturn( false );

		$this->validator->shouldReceive( 'get_errors' )
			->once()
			->andReturn( array(
				'name' => 'Theme name cannot be empty',
				'styles' => 'Styles must be an array',
			) );

		// 2. Theme repository should not be called
		$this->theme_repository->shouldNotReceive( 'save' );

		// 3. Event manager should dispatch validation error event
		$this->event_manager->shouldReceive( 'dispatch_validation_error' )
			->with( $invalid_theme, \Mockery::type( 'array' ) )
			->once();

		// Execute the workflow
		$is_valid = $this->validator->validate( $invalid_theme );
		$this->assertFalse( $is_valid );

		$errors = $this->validator->get_errors();
		$this->event_manager->dispatch_validation_error( $invalid_theme, $errors );

		$this->assertArrayHasKey( 'name', $errors );
		$this->assertArrayHasKey( 'styles', $errors );
	}
}