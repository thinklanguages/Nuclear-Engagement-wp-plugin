<?php
namespace NuclearEngagement\Services;

use NuclearEngagement\Models\Theme;
use NuclearEngagement\Repositories\ThemeRepository;
use WP_Filesystem_Base;

class ThemeCssGenerator {
    private $repository;
    private $upload_base_path;
    private $cache_manifest_path;

    public function __construct(ThemeRepository $repository = null) {
        $this->repository = $repository ?: new ThemeRepository();
        
        $upload_dir = wp_upload_dir();
        $this->upload_base_path = $upload_dir['basedir'] . '/nuclear-engagement/themes/';
        $this->cache_manifest_path = $this->upload_base_path . 'manifest.json';
    }

    public function generate_css(Theme $theme) {
        $new_hash = $theme->generate_hash();
        
        if (!$theme->needs_css_update() && $theme->css_path && file_exists($theme->get_css_file_path())) {
            return true;
        }

        $css = $this->build_css_from_config($theme->config);
        
        $filename = $this->generate_filename($theme, $new_hash);
        $filepath = $this->upload_base_path . $filename;
        
        if ($this->write_css_file($filepath, $css)) {
            if ($theme->css_path && $theme->css_path !== $filename) {
                $this->delete_css_file($theme->get_css_file_path());
            }
            
            $theme->css_path = $filename;
            $theme->css_hash = $new_hash;
            
            $this->update_manifest($theme);
            
            return true;
        }
        
        return false;
    }

    private function build_css_from_config($config) {
        $css = ":root {\n";
        
        $css_vars = $this->flatten_config_to_css_vars($config);
        
        foreach ($css_vars as $var_name => $value) {
            $css .= "    {$var_name}: {$value};\n";
        }
        
        $css .= "}\n\n";
        
        $css .= $this->generate_component_styles($config);
        
        return $css;
    }

    private function flatten_config_to_css_vars($config, $prefix = '--nuclen') {
        $vars = [];
        
        foreach ($config as $key => $value) {
            $var_name = $prefix . '-' . str_replace('_', '-', $key);
            
            if (is_array($value) && !isset($value[0])) {
                $nested_vars = $this->flatten_config_to_css_vars($value, $var_name);
                $vars = array_merge($vars, $nested_vars);
            } else {
                if (is_array($value)) {
                    $value = implode(' ', $value);
                }
                $vars[$var_name] = $value;
            }
        }
        
        return $vars;
    }

    private function generate_component_styles($config) {
        $css = "";
        
        $css .= "@layer nuclen.theme {\n";
        
        if (!empty($config['quiz_container'])) {
            $css .= $this->generate_quiz_container_styles($config['quiz_container']);
        }
        
        if (!empty($config['quiz_button'])) {
            $css .= $this->generate_quiz_button_styles($config['quiz_button']);
        }
        
        if (!empty($config['progress_bar'])) {
            $css .= $this->generate_progress_bar_styles($config['progress_bar']);
        }
        
        if (!empty($config['summary_container'])) {
            $css .= $this->generate_summary_container_styles($config['summary_container']);
        }
        
        if (!empty($config['table_of_contents'])) {
            $css .= $this->generate_toc_styles($config['table_of_contents']);
        }
        
        $css .= "}\n";
        
        return $css;
    }

    private function generate_quiz_container_styles($config) {
        $css = "    .nuclen-quiz-container {\n";
        
        if (!empty($config['background_color'])) {
            $css .= "        background-color: var(--nuclen-quiz-container-background-color);\n";
        }
        
        if (!empty($config['border_width']) || !empty($config['border_color'])) {
            $css .= "        border: var(--nuclen-quiz-container-border-width, 1px) solid var(--nuclen-quiz-container-border-color, #e5e7eb);\n";
        }
        
        if (!empty($config['border_radius'])) {
            $css .= "        border-radius: var(--nuclen-quiz-container-border-radius);\n";
        }
        
        if (!empty($config['padding'])) {
            $css .= "        padding: var(--nuclen-quiz-container-padding);\n";
        }
        
        $css .= "    }\n\n";
        
        return $css;
    }

    private function generate_quiz_button_styles($config) {
        $css = "    .nuclen-quiz-button {\n";
        $css .= "        background-color: var(--nuclen-quiz-button-background-color);\n";
        $css .= "        color: var(--nuclen-quiz-button-text-color);\n";
        $css .= "        border-radius: var(--nuclen-quiz-button-border-radius);\n";
        $css .= "        padding: var(--nuclen-quiz-button-padding);\n";
        $css .= "        font-size: var(--nuclen-quiz-button-font-size);\n";
        $css .= "        font-weight: var(--nuclen-quiz-button-font-weight);\n";
        $css .= "    }\n\n";
        
        $css .= "    .nuclen-quiz-button:hover {\n";
        $css .= "        background-color: var(--nuclen-quiz-button-hover-background-color);\n";
        $css .= "        color: var(--nuclen-quiz-button-hover-text-color);\n";
        $css .= "    }\n\n";
        
        return $css;
    }

    private function generate_progress_bar_styles($config) {
        $css = "    .nuclen-progress-bar {\n";
        $css .= "        background-color: var(--nuclen-progress-bar-background-color);\n";
        $css .= "        height: var(--nuclen-progress-bar-height);\n";
        $css .= "    }\n\n";
        
        $css .= "    .nuclen-progress-bar-fill {\n";
        $css .= "        background-color: var(--nuclen-progress-bar-fill-color);\n";
        $css .= "    }\n\n";
        
        return $css;
    }

    private function generate_summary_container_styles($config) {
        $css = "    .nuclen-summary-container {\n";
        $css .= "        background-color: var(--nuclen-summary-container-background-color);\n";
        $css .= "        border: var(--nuclen-summary-container-border-width) solid var(--nuclen-summary-container-border-color);\n";
        $css .= "        border-radius: var(--nuclen-summary-container-border-radius);\n";
        $css .= "        padding: var(--nuclen-summary-container-padding);\n";
        $css .= "    }\n\n";
        
        return $css;
    }

    private function generate_toc_styles($config) {
        $css = "    .nuclen-toc {\n";
        $css .= "        background-color: var(--nuclen-table-of-contents-background-color);\n";
        $css .= "        border: var(--nuclen-table-of-contents-border-width) solid var(--nuclen-table-of-contents-border-color);\n";
        $css .= "        border-radius: var(--nuclen-table-of-contents-border-radius);\n";
        $css .= "        padding: var(--nuclen-table-of-contents-padding);\n";
        $css .= "    }\n\n";
        
        $css .= "    .nuclen-toc-item {\n";
        $css .= "        color: var(--nuclen-table-of-contents-link-color);\n";
        $css .= "    }\n\n";
        
        $css .= "    .nuclen-toc-item:hover {\n";
        $css .= "        color: var(--nuclen-table-of-contents-link-hover-color);\n";
        $css .= "    }\n\n";
        
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