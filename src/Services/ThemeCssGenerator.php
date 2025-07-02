<?php
namespace NuclearEngagement\Services;

use NuclearEngagement\Models\Theme;
use NuclearEngagement\Repositories\ThemeRepository;
use NuclearEngagement\Services\Styles\StyleGeneratorFactory;
use WP_Filesystem_Base;

class ThemeCssGenerator {
    private $repository;
    private $upload_base_path;
    private $cache_manifest_path;
    private $event_manager;

    public function __construct(ThemeRepository $repository = null, ThemeEventManager $event_manager = null) {
        $this->repository = $repository ?: new ThemeRepository();
        $this->event_manager = $event_manager ?: ThemeEventManager::instance();
        
        $upload_dir = wp_upload_dir();
        $this->upload_base_path = $upload_dir['basedir'] . '/nuclear-engagement/themes/';
        $this->cache_manifest_path = $this->upload_base_path . 'manifest.json';
    }

    public function generate_css(Theme $theme) {
        $new_hash = $theme->generate_hash();
        
        if (!$theme->needs_css_update() && $theme->css_path && file_exists($theme->get_css_file_path())) {
            return true;
        }

        // Apply pre-generation filters
        $filtered_config = $this->event_manager->apply_css_before_generation_filters($theme->config, $theme);
        
        $css = $this->build_css_from_config($filtered_config);
        
        // Apply post-generation filters
        $css = $this->event_manager->apply_css_after_generation_filters($css, $theme);
        
        $filename = $this->generate_filename($theme, $new_hash);
        $filepath = $this->upload_base_path . $filename;
        
        if ($this->write_css_file($filepath, $css)) {
            if ($theme->css_path && $theme->css_path !== $filename) {
                $this->delete_css_file($theme->get_css_file_path());
            }
            
            $theme->css_path = $filename;
            $theme->css_hash = $new_hash;
            
            $this->update_manifest($theme);
            $this->event_manager->trigger_css_generated($theme, $css);
            
            return true;
        }
        
        return false;
    }

    private function build_css_from_config($config) {
        $css = ":root {\n";
        
        // Generate CSS variables using style generators
        $all_variables = [];
        $generators = StyleGeneratorFactory::get_all_generators();
        
        foreach ($config as $component => $component_config) {
            if (isset($generators[$component])) {
                $generator = $generators[$component];
                $variables = $generator->get_css_variables($component_config);
                $all_variables = array_merge($all_variables, $variables);
            }
        }
        
        foreach ($all_variables as $var_name => $value) {
            $css .= "    {$var_name}: {$value};\n";
        }
        
        $css .= "}\n\n";
        
        $css .= $this->generate_component_styles($config);
        
        return $css;
    }


    private function generate_component_styles($config) {
        $css = "";
        
        $css .= "@layer nuclen.theme {\n";
        
        $generators = StyleGeneratorFactory::get_all_generators();
        
        foreach ($config as $component => $component_config) {
            if (!empty($component_config) && isset($generators[$component])) {
                $generator = $generators[$component];
                $css .= $generator->generate_styles($component_config);
            }
        }
        
        $css .= "}\n";
        
        return $css;
    }


    private function generate_filename(Theme $theme, $hash) {
        $safe_name = sanitize_file_name($theme->name);
        return "{$safe_name}-{$hash}.css";
    }

    private function write_css_file($filepath, $css) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        
        WP_Filesystem();
        global $wp_filesystem;
        
        if (!$wp_filesystem->is_dir($this->upload_base_path)) {
            $wp_filesystem->mkdir($this->upload_base_path, 0755, true);
        }
        
        return $wp_filesystem->put_contents($filepath, $css, FS_CHMOD_FILE);
    }

    private function delete_css_file($filepath) {
        if (!$filepath || !file_exists($filepath)) {
            return true;
        }
        
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        
        WP_Filesystem();
        global $wp_filesystem;
        
        return $wp_filesystem->delete($filepath);
    }

    private function update_manifest(Theme $theme) {
        $manifest = $this->load_manifest();
        
        $manifest[$theme->id] = [
            'name' => $theme->name,
            'hash' => $theme->css_hash,
            'path' => $theme->css_path,
            'updated' => current_time('mysql'),
        ];
        
        $this->save_manifest($manifest);
    }

    private function load_manifest() {
        if (!file_exists($this->cache_manifest_path)) {
            return [];
        }
        
        $content = file_get_contents($this->cache_manifest_path);
        return json_decode($content, true) ?: [];
    }

    private function save_manifest($manifest) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        
        WP_Filesystem();
        global $wp_filesystem;
        
        $wp_filesystem->put_contents(
            $this->cache_manifest_path,
            json_encode($manifest, JSON_PRETTY_PRINT),
            FS_CHMOD_FILE
        );
    }

    public function clean_unused_files() {
        $manifest = $this->load_manifest();
        $themes = $this->repository->get_all();
        
        $active_files = [];
        foreach ($themes as $theme) {
            if ($theme->css_path) {
                $active_files[] = $theme->css_path;
            }
        }
        
        $files = glob($this->upload_base_path . '*.css');
        foreach ($files as $file) {
            $filename = basename($file);
            if (!in_array($filename, $active_files)) {
                unlink($file);
            }
        }
        
        $cleaned_manifest = [];
        foreach ($manifest as $id => $data) {
            if (in_array($data['path'], $active_files)) {
                $cleaned_manifest[$id] = $data;
            }
        }
        
        $this->save_manifest($cleaned_manifest);
    }
}