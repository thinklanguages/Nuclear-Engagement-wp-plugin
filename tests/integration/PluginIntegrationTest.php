<?php
/**
 * Plugin Integration Tests
 * 
 * @package NuclearEngagement\Tests\Integration
 */

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class PluginIntegrationTest extends TestCase {
    
    /**
     * Test that the plugin main file exists and has proper header
     */
    public function testPluginFileExists() {
        $plugin_file = dirname(dirname(__DIR__)) . '/nuclear-engagement/nuclear-engagement.php';
        $this->assertFileExists($plugin_file, 'Plugin main file should exist');
        
        $contents = file_get_contents($plugin_file);
        $this->assertStringContainsString('Plugin Name:', $contents, 'Plugin should have proper header');
        $this->assertStringContainsString('Nuclear Engagement', $contents, 'Plugin name should be Nuclear Engagement');
    }
    
    /**
     * Test that required directories exist
     */
    public function testRequiredDirectoriesExist() {
        $base_path = dirname(dirname(__DIR__)) . '/nuclear-engagement';
        
        $required_dirs = [
            'admin',
            'front',
            'inc',
            'inc/Modules',
            'inc/Services',
            'templates'
        ];
        
        foreach ($required_dirs as $dir) {
            $this->assertDirectoryExists($base_path . '/' . $dir, "Directory {$dir} should exist");
        }
    }
    
    /**
     * Test that autoloader is properly configured
     */
    public function testAutoloaderConfiguration() {
        $this->assertTrue(
            class_exists('NuclearEngagement\Core\Bootloader'),
            'Bootloader class should be autoloaded'
        );
    }
    
    /**
     * Test that critical services can be instantiated
     */
    public function testCriticalServicesInstantiation() {
        $services = [
            'NuclearEngagement\Services\GenerationService',
            'NuclearEngagement\Services\PostsQueryService',
            'NuclearEngagement\Services\PostDataFetcher'
        ];
        
        foreach ($services as $service) {
            $this->assertTrue(
                class_exists($service),
                "Service class {$service} should exist"
            );
        }
    }
    
    /**
     * Test that admin controllers exist
     */
    public function testAdminControllersExist() {
        $controllers = [
            'NuclearEngagement\Admin\Controller\Ajax\GenerateController',
            'NuclearEngagement\Admin\Controller\Ajax\PostsCountController'
        ];
        
        foreach ($controllers as $controller) {
            $this->assertTrue(
                class_exists($controller),
                "Controller class {$controller} should exist"
            );
        }
    }
    
    /**
     * Test that module classes exist
     */
    public function testModulesExist() {
        $modules = [
            'NuclearEngagement\Modules\Quiz\Quiz_Shortcode',
            'NuclearEngagement\Modules\Summary\Nuclen_Summary_Shortcode',
            'NuclearEngagement\Modules\TOC\Nuclen_TOC_Headings',
            'NuclearEngagement\Modules\TOC\Nuclen_TOC_Render'
        ];
        
        foreach ($modules as $module) {
            $this->assertTrue(
                class_exists($module),
                "Module class {$module} should exist"
            );
        }
    }
    
    /**
     * Test JavaScript build artifacts exist
     */
    public function testBuildArtifactsExist() {
        $base_path = dirname(dirname(__DIR__)) . '/nuclear-engagement';
        
        $js_files = [
            'admin/js/nuclen-admin.js',
            'front/js/nuclen-front.js'
        ];
        
        foreach ($js_files as $file) {
            $this->assertFileExists(
                $base_path . '/' . $file,
                "Built JavaScript file {$file} should exist"
            );
        }
    }
    
    /**
     * Test that templates exist
     */
    public function testTemplatesExist() {
        $base_path = dirname(dirname(__DIR__)) . '/nuclear-engagement/templates';
        
        $templates = [
            'admin/page-header.php',
            'admin/settings/placement/positions.php'
        ];
        
        foreach ($templates as $template) {
            $this->assertFileExists(
                $base_path . '/' . $template,
                "Template file {$template} should exist"
            );
        }
    }
}