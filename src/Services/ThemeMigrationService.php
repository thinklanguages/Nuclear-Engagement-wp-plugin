<?php
namespace NuclearEngagement\Services;

use NuclearEngagement\Models\Theme;
use NuclearEngagement\Repositories\ThemeRepository;
use NuclearEngagement\Database\Schema\ThemeSchema;

class ThemeMigrationService {
    private $repository;
    private $css_generator;

    public function __construct(ThemeRepository $repository = null, ThemeCssGenerator $css_generator = null) {
        $this->repository = $repository ?: new ThemeRepository();
        $this->css_generator = $css_generator ?: new ThemeCssGenerator($this->repository);
    }

    public function migrate_legacy_settings() {
        if (get_option('nuclen_theme_migration_completed')) {
            return true;
        }

        ThemeSchema::create_table();

        $this->create_preset_themes();

        $legacy_settings = get_option('nuclear_engagement_settings', []);
        
        if (!empty($legacy_settings)) {
            $this->migrate_user_theme($legacy_settings);
        }

        update_option('nuclen_theme_migration_completed', true);
        
        return true;
    }

    private function create_preset_themes() {
        $this->create_light_theme();
        $this->create_dark_theme();
    }

    private function create_light_theme() {
        if ($this->repository->find_by_name('light')) {
            return;
        }

        $light_config = [
            'quiz_container' => [
                'background_color' => '#ffffff',
                'border_color' => '#e5e7eb',
                'border_width' => '1px',
                'border_radius' => '8px',
                'padding' => '24px',
                'box_shadow' => '0 1px 3px 0 rgba(0, 0, 0, 0.1)',
            ],
            'quiz_button' => [
                'background_color' => '#3b82f6',
                'text_color' => '#ffffff',
                'border_radius' => '6px',
                'padding' => '12px 24px',
                'font_size' => '16px',
                'font_weight' => '500',
                'hover_background_color' => '#2563eb',
                'hover_text_color' => '#ffffff',
            ],
            'progress_bar' => [
                'background_color' => '#e5e7eb',
                'fill_color' => '#10b981',
                'height' => '8px',
                'border_radius' => '4px',
            ],
            'summary_container' => [
                'background_color' => '#f8fafc',
                'border_color' => '#e2e8f0',
                'border_width' => '1px',
                'border_radius' => '8px',
                'padding' => '20px',
            ],
            'table_of_contents' => [
                'background_color' => '#ffffff',
                'border_color' => '#e5e7eb',
                'border_width' => '1px',
                'border_radius' => '8px',
                'padding' => '16px',
                'link_color' => '#3b82f6',
                'link_hover_color' => '#2563eb',
            ],
        ];

        $theme = new Theme([
            'name' => 'light',
            'type' => Theme::TYPE_PRESET,
            'config' => $light_config,
        ]);

        $this->repository->save($theme);
    }

    private function create_dark_theme() {
        if ($this->repository->find_by_name('dark')) {
            return;
        }

        $dark_config = [
            'quiz_container' => [
                'background_color' => '#1f2937',
                'border_color' => '#374151',
                'border_width' => '1px',
                'border_radius' => '8px',
                'padding' => '24px',
                'box_shadow' => '0 1px 3px 0 rgba(0, 0, 0, 0.3)',
            ],
            'quiz_button' => [
                'background_color' => '#3b82f6',
                'text_color' => '#ffffff',
                'border_radius' => '6px',
                'padding' => '12px 24px',
                'font_size' => '16px',
                'font_weight' => '500',
                'hover_background_color' => '#60a5fa',
                'hover_text_color' => '#ffffff',
            ],
            'progress_bar' => [
                'background_color' => '#374151',
                'fill_color' => '#10b981',
                'height' => '8px',
                'border_radius' => '4px',
            ],
            'summary_container' => [
                'background_color' => '#111827',
                'border_color' => '#374151',
                'border_width' => '1px',
                'border_radius' => '8px',
                'padding' => '20px',
            ],
            'table_of_contents' => [
                'background_color' => '#1f2937',
                'border_color' => '#374151',
                'border_width' => '1px',
                'border_radius' => '8px',
                'padding' => '16px',
                'link_color' => '#60a5fa',
                'link_hover_color' => '#93c5fd',
            ],
        ];

        $theme = new Theme([
            'name' => 'dark',
            'type' => Theme::TYPE_PRESET,
            'config' => $dark_config,
        ]);

        $this->repository->save($theme);
    }

    private function migrate_user_theme($legacy_settings) {
        $theme_name = $legacy_settings['theme'] ?? 'bright';
        
        switch ($theme_name) {
            case 'bright':
            case 'light':
                $this->set_active_theme('light');
                break;
                
            case 'dark':
                $this->set_active_theme('dark');
                break;
                
            case 'custom':
                $this->migrate_custom_theme($legacy_settings);
                break;
                
            default:
                $this->set_active_theme('light');
        }

        $this->backup_legacy_settings($legacy_settings);
    }

    private function migrate_custom_theme($legacy_settings) {
        $custom_config = $this->convert_legacy_to_new_config($legacy_settings);
        
        $existing_custom = $this->repository->find_by_name('custom-migrated');
        
        if ($existing_custom) {
            $existing_custom->config = $custom_config;
            $theme = $this->repository->save($existing_custom);
        } else {
            $theme = new Theme([
                'name' => 'custom-migrated',
                'type' => Theme::TYPE_CUSTOM,
                'config' => $custom_config,
            ]);
            
            $theme = $this->repository->save($theme);
        }

        if ($theme) {
            $this->css_generator->generate_css($theme);
            $this->repository->save($theme);
            $this->set_active_theme_by_id($theme->id);
        }
    }

    private function convert_legacy_to_new_config($legacy) {
        $config = [
            'quiz_container' => [
                'background_color' => $legacy['bg_color'] ?? '#ffffff',
                'border_color' => $legacy['quiz_border_color'] ?? '#000000',
                'border_width' => ($legacy['quiz_border_width'] ?? '1') . 'px',
                'border_radius' => ($legacy['quiz_border_radius'] ?? '6') . 'px',
                'padding' => '24px',
                'font_size' => ($legacy['font_size'] ?? '16') . 'px',
                'text_color' => $legacy['font_color'] ?? '#000000',
            ],
            'quiz_button' => [
                'background_color' => $legacy['quiz_answer_button_bg_color'] ?? '#94544A',
                'text_color' => '#ffffff',
                'border_color' => $legacy['quiz_answer_button_border_color'] ?? '#94544A',
                'border_width' => ($legacy['quiz_answer_button_border_width'] ?? '2') . 'px',
                'border_radius' => ($legacy['quiz_answer_button_border_radius'] ?? '4') . 'px',
                'padding' => '12px 24px',
                'font_size' => ($legacy['font_size'] ?? '16') . 'px',
                'font_weight' => '500',
            ],
            'progress_bar' => [
                'background_color' => $legacy['quiz_progress_bar_bg_color'] ?? '#e0e0e0',
                'fill_color' => $legacy['quiz_progress_bar_fg_color'] ?? '#1B977D',
                'height' => ($legacy['quiz_progress_bar_height'] ?? '10') . 'px',
                'border_radius' => '4px',
            ],
            'summary_container' => [
                'background_color' => $legacy['summary_bg_color'] ?? '#ffffff',
                'border_color' => $legacy['summary_border_color'] ?? '#000000',
                'border_width' => ($legacy['summary_border_width'] ?? '1') . 'px',
                'border_radius' => ($legacy['summary_border_radius'] ?? '6') . 'px',
                'padding' => '20px',
                'font_size' => ($legacy['summary_font_size'] ?? '16') . 'px',
                'text_color' => $legacy['summary_font_color'] ?? '#000000',
            ],
            'table_of_contents' => [
                'background_color' => $legacy['toc_bg_color'] ?? '#ffffff',
                'border_color' => $legacy['toc_border_color'] ?? '#000000',
                'border_width' => ($legacy['toc_border_width'] ?? '1') . 'px',
                'border_radius' => ($legacy['toc_border_radius'] ?? '6') . 'px',
                'padding' => '16px',
                'font_size' => ($legacy['toc_font_size'] ?? '16') . 'px',
                'text_color' => $legacy['toc_font_color'] ?? '#000000',
                'link_color' => $legacy['toc_link_color'] ?? '#1e73be',
                'link_hover_color' => '#0f5596',
            ],
        ];

        if (!empty($legacy['quiz_shadow_color']) && !empty($legacy['quiz_shadow_blur'])) {
            $config['quiz_container']['box_shadow'] = "0 0 {$legacy['quiz_shadow_blur']}px {$legacy['quiz_shadow_color']}";
        }

        if (!empty($legacy['summary_shadow_color']) && !empty($legacy['summary_shadow_blur'])) {
            $config['summary_container']['box_shadow'] = "0 0 {$legacy['summary_shadow_blur']}px {$legacy['summary_shadow_color']}";
        }

        if (!empty($legacy['toc_shadow_color']) && !empty($legacy['toc_shadow_blur'])) {
            $config['table_of_contents']['box_shadow'] = "0 0 {$legacy['toc_shadow_blur']}px {$legacy['toc_shadow_color']}";
        }

        return $config;
    }

    private function set_active_theme($theme_name) {
        $theme = $this->repository->find_by_name($theme_name);
        if ($theme) {
            $this->repository->set_active($theme->id);
        }
    }

    private function set_active_theme_by_id($theme_id) {
        $this->repository->set_active($theme_id);
    }

    private function backup_legacy_settings($legacy_settings) {
        update_option('nuclear_engagement_settings_backup', $legacy_settings);
    }

    public function rollback_migration() {
        $backup = get_option('nuclear_engagement_settings_backup');
        
        if ($backup) {
            update_option('nuclear_engagement_settings', $backup);
            delete_option('nuclear_engagement_settings_backup');
        }

        $this->repository->deactivate_all();
        
        delete_option('nuclen_theme_migration_completed');
        
        return true;
    }

    public function check_migration_status() {
        return get_option('nuclen_theme_migration_completed', false);
    }

    public function get_legacy_custom_css_path() {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/nuclear-engagement/nuclen-theme-custom.css';
    }

    public function has_legacy_custom_css() {
        return file_exists($this->get_legacy_custom_css_path());
    }

    public function cleanup_legacy_files() {
        $legacy_css_path = $this->get_legacy_custom_css_path();
        
        if (file_exists($legacy_css_path)) {
            $backup_path = str_replace('.css', '-backup.css', $legacy_css_path);
            rename($legacy_css_path, $backup_path);
        }
    }
}