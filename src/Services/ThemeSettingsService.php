<?php
namespace NuclearEngagement\Services;

use NuclearEngagement\Models\Theme;
use NuclearEngagement\Repositories\ThemeRepository;

class ThemeSettingsService {
    private $repository;
    private $css_generator;

    public function __construct(ThemeRepository $repository = null, ThemeCssGenerator $css_generator = null) {
        $this->repository = $repository ?: new ThemeRepository();
        $this->css_generator = $css_generator ?: new ThemeCssGenerator($this->repository);
    }

    public function get_available_themes() {
        return $this->repository->get_all();
    }

    public function get_preset_themes() {
        return $this->repository->get_all(Theme::TYPE_PRESET);
    }

    public function get_custom_themes() {
        return $this->repository->get_all(Theme::TYPE_CUSTOM);
    }

    public function get_active_theme() {
        return $this->repository->get_active();
    }

    public function save_theme_selection($theme_identifier) {
        if (is_numeric($theme_identifier)) {
            return $this->set_active_theme_by_id((int) $theme_identifier);
        }

        return $this->set_active_theme_by_name($theme_identifier);
    }

    public function save_custom_theme($config, $name = 'custom') {
        $existing_theme = $this->repository->find_by_name($name);
        
        if ($existing_theme && $existing_theme->type === Theme::TYPE_CUSTOM) {
            $existing_theme->config = $config;
            $theme = $this->repository->save($existing_theme);
        } else {
            $theme = new Theme([
                'name' => $name,
                'type' => Theme::TYPE_CUSTOM,
                'config' => $config,
            ]);
            
            $theme = $this->repository->save($theme);
        }

        if ($theme) {
            $this->css_generator->generate_css($theme);
            $this->repository->save($theme);
            $this->repository->set_active($theme->id);
        }

        return $theme;
    }

    public function delete_theme($theme_id) {
        $theme = $this->repository->find($theme_id);
        
        if (!$theme || $theme->type === Theme::TYPE_PRESET) {
            return false;
        }

        if ($theme->is_active) {
            $light_theme = $this->repository->find_by_name('light');
            if ($light_theme) {
                $this->repository->set_active($light_theme->id);
            }
        }

        if ($theme->css_path && file_exists($theme->get_css_file_path())) {
            unlink($theme->get_css_file_path());
        }

        return $this->repository->delete($theme_id);
    }

    public function duplicate_theme($theme_id, $new_name) {
        $original = $this->repository->find($theme_id);
        
        if (!$original) {
            return false;
        }

        if ($this->repository->find_by_name($new_name)) {
            return false;
        }

        $new_theme = new Theme([
            'name' => $new_name,
            'type' => Theme::TYPE_CUSTOM,
            'config' => $original->config,
        ]);

        $theme = $this->repository->save($new_theme);

        if ($theme) {
            $this->css_generator->generate_css($theme);
            $this->repository->save($theme);
        }

        return $theme;
    }

    public function regenerate_css($theme_id = null) {
        if ($theme_id) {
            $theme = $this->repository->find($theme_id);
            if ($theme && $theme->type === Theme::TYPE_CUSTOM) {
                $this->css_generator->generate_css($theme);
                $this->repository->save($theme);
            }
        } else {
            $custom_themes = $this->get_custom_themes();
            foreach ($custom_themes as $theme) {
                $this->css_generator->generate_css($theme);
                $this->repository->save($theme);
            }
        }
        
        return true;
    }

    private function set_active_theme_by_id($theme_id) {
        $theme = $this->repository->find($theme_id);
        
        if (!$theme) {
            return false;
        }

        return $this->repository->set_active($theme_id);
    }

    private function set_active_theme_by_name($name) {
        $theme = $this->repository->find_by_name($name);
        
        if (!$theme) {
            return false;
        }

        return $this->repository->set_active($theme->id);
    }

    public function export_theme($theme_id) {
        $theme = $this->repository->find($theme_id);
        
        if (!$theme) {
            return false;
        }

        return [
            'name' => $theme->name,
            'type' => $theme->type,
            'config' => $theme->config,
            'exported_at' => current_time('mysql'),
        ];
    }

    public function import_theme($theme_data, $new_name = null) {
        if (!isset($theme_data['config']) || !is_array($theme_data['config'])) {
            return false;
        }

        $name = $new_name ?: ($theme_data['name'] ?? 'imported-theme');
        
        if ($this->repository->find_by_name($name)) {
            $name .= '-' . time();
        }

        $theme = new Theme([
            'name' => $name,
            'type' => Theme::TYPE_CUSTOM,
            'config' => $theme_data['config'],
        ]);

        $theme = $this->repository->save($theme);

        if ($theme) {
            $this->css_generator->generate_css($theme);
            $this->repository->save($theme);
        }

        return $theme;
    }

    public function get_theme_config_for_legacy($theme_name) {
        $theme = $this->repository->find_by_name($theme_name);
        
        if (!$theme) {
            return [];
        }

        return $this->convert_new_config_to_legacy($theme->config);
    }

    private function convert_new_config_to_legacy($config) {
        $legacy = [];
        
        if (isset($config['quiz_container'])) {
            $qc = $config['quiz_container'];
            $legacy['bg_color'] = $qc['background_color'] ?? '#ffffff';
            $legacy['quiz_border_color'] = $qc['border_color'] ?? '#000000';
            $legacy['quiz_border_width'] = rtrim($qc['border_width'] ?? '1px', 'px');
            $legacy['quiz_border_radius'] = rtrim($qc['border_radius'] ?? '6px', 'px');
            $legacy['font_color'] = $qc['text_color'] ?? '#000000';
            $legacy['font_size'] = rtrim($qc['font_size'] ?? '16px', 'px');
        }

        if (isset($config['quiz_button'])) {
            $qb = $config['quiz_button'];
            $legacy['quiz_answer_button_bg_color'] = $qb['background_color'] ?? '#94544A';
            $legacy['quiz_answer_button_border_color'] = $qb['border_color'] ?? '#94544A';
            $legacy['quiz_answer_button_border_width'] = rtrim($qb['border_width'] ?? '2px', 'px');
            $legacy['quiz_answer_button_border_radius'] = rtrim($qb['border_radius'] ?? '4px', 'px');
        }

        if (isset($config['progress_bar'])) {
            $pb = $config['progress_bar'];
            $legacy['quiz_progress_bar_bg_color'] = $pb['background_color'] ?? '#e0e0e0';
            $legacy['quiz_progress_bar_fg_color'] = $pb['fill_color'] ?? '#1B977D';
            $legacy['quiz_progress_bar_height'] = rtrim($pb['height'] ?? '10px', 'px');
        }

        if (isset($config['summary_container'])) {
            $sc = $config['summary_container'];
            $legacy['summary_bg_color'] = $sc['background_color'] ?? '#ffffff';
            $legacy['summary_border_color'] = $sc['border_color'] ?? '#000000';
            $legacy['summary_border_width'] = rtrim($sc['border_width'] ?? '1px', 'px');
            $legacy['summary_border_radius'] = rtrim($sc['border_radius'] ?? '6px', 'px');
            $legacy['summary_font_color'] = $sc['text_color'] ?? '#000000';
            $legacy['summary_font_size'] = rtrim($sc['font_size'] ?? '16px', 'px');
        }

        if (isset($config['table_of_contents'])) {
            $toc = $config['table_of_contents'];
            $legacy['toc_bg_color'] = $toc['background_color'] ?? '#ffffff';
            $legacy['toc_border_color'] = $toc['border_color'] ?? '#000000';
            $legacy['toc_border_width'] = rtrim($toc['border_width'] ?? '1px', 'px');
            $legacy['toc_border_radius'] = rtrim($toc['border_radius'] ?? '6px', 'px');
            $legacy['toc_font_color'] = $toc['text_color'] ?? '#000000';
            $legacy['toc_font_size'] = rtrim($toc['font_size'] ?? '16px', 'px');
            $legacy['toc_link_color'] = $toc['link_color'] ?? '#1e73be';
        }

        return $legacy;
    }
}