<?php
namespace NuclearEngagement\Services;

use NuclearEngagement\Repositories\ThemeRepository;
use NuclearEngagement\Models\Theme;

class ThemeLoader {
    private $repository;
    private $loaded_themes = [];
    private $enqueued_scripts = false;

    public function __construct(ThemeRepository $repository = null) {
        $this->repository = $repository ?: new ThemeRepository();
    }

    public function init() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_lazy_loader']);
        add_action('wp_ajax_nuclen_get_theme_urls', [$this, 'ajax_get_theme_urls']);
        add_action('wp_ajax_nopriv_nuclen_get_theme_urls', [$this, 'ajax_get_theme_urls']);
    }

    public function enqueue_lazy_loader() {
        if ($this->enqueued_scripts) {
            return;
        }

        $active_theme = $this->repository->get_active();
        
        if (!$active_theme || $active_theme->type === Theme::TYPE_PRESET) {
            return;
        }

        wp_enqueue_script(
            'nuclen-theme-loader',
            NUCLEN_PLUGIN_URL . 'assets/js/theme-loader.js',
            [],
            NUCLEN_PLUGIN_VERSION,
            true
        );

        wp_localize_script('nuclen-theme-loader', 'nuclenThemeLoader', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('nuclen_theme_loader'),
            'activeThemeId' => $active_theme->id,
            'offset' => apply_filters('nuclen_theme_loader_offset', 200),
            'components' => $this->get_component_selectors(),
        ]);

        $this->enqueued_scripts = true;
    }

    public function load_theme_css($theme_id = null) {
        if ($theme_id === null) {
            $theme = $this->repository->get_active();
        } else {
            $theme = $this->repository->find($theme_id);
        }

        if (!$theme || isset($this->loaded_themes[$theme->id])) {
            return;
        }

        if ($theme->type === Theme::TYPE_PRESET) {
            $this->load_preset_theme($theme);
        } else {
            $this->load_custom_theme($theme);
        }

        $this->loaded_themes[$theme->id] = true;
    }

    private function load_preset_theme(Theme $theme) {
        $preset_file = NUCLEN_PLUGIN_URL . 'assets/css/themes/' . $theme->name . '.css';
        
        wp_enqueue_style(
            'nuclen-theme-' . $theme->name,
            $preset_file,
            ['nuclen-front'],
            NUCLEN_VERSION
        );

        if ($theme->name === 'dark') {
            add_filter('nuclen_root_attributes', function($attrs) {
                $attrs['data-theme'] = 'dark';
                return $attrs;
            });
        }
    }

    private function load_custom_theme(Theme $theme) {
        if (!$theme->css_path) {
            $generator = new ThemeCssGenerator($this->repository);
            $generator->generate_css($theme);
            $this->repository->save($theme);
        }

        if ($theme->css_path && file_exists($theme->get_css_file_path())) {
            wp_enqueue_style(
                'nuclen-theme-custom-' . $theme->id,
                $theme->get_css_url(),
                ['nuclen-front'],
                $theme->css_hash
            );
        }
    }

    public function ajax_get_theme_urls() {
        check_ajax_referer('nuclen_theme_loader', 'nonce');

        $theme_ids = isset($_POST['theme_ids']) ? array_map('intval', $_POST['theme_ids']) : [];
        
        $urls = [];
        
        foreach ($theme_ids as $theme_id) {
            $theme = $this->repository->find($theme_id);
            
            if ($theme) {
                if ($theme->type === Theme::TYPE_PRESET) {
                    $urls[$theme_id] = NUCLEN_PLUGIN_URL . 'assets/css/themes/' . $theme->name . '.css';
                } elseif ($theme->css_path) {
                    $urls[$theme_id] = $theme->get_css_url();
                }
            }
        }

        wp_send_json_success($urls);
    }

    private function get_component_selectors() {
        return apply_filters('nuclen_theme_component_selectors', [
            'quiz' => '.nuclen-quiz-container',
            'progress' => '.nuclen-progress-bar',
            'summary' => '.nuclen-summary-container',
            'toc' => '.nuclen-toc',
            'button' => '.nuclen-quiz-button',
        ]);
    }

    public function get_inline_critical_css() {
        $active_theme = $this->repository->get_active();
        
        if (!$active_theme) {
            return '';
        }

        $critical_vars = $this->extract_critical_vars($active_theme->config);
        
        if (empty($critical_vars)) {
            return '';
        }

        $css = "<style id='nuclen-theme-critical'>\n";
        $css .= ":root {\n";
        
        foreach ($critical_vars as $var => $value) {
            $css .= "    {$var}: {$value};\n";
        }
        
        $css .= "}\n";
        $css .= "</style>\n";

        return $css;
    }

    private function extract_critical_vars($config) {
        $critical = [];
        
        $critical_keys = [
            'quiz_container' => ['background_color', 'border_color'],
            'quiz_button' => ['background_color', 'text_color'],
            'progress_bar' => ['background_color', 'fill_color'],
        ];

        foreach ($critical_keys as $component => $props) {
            if (isset($config[$component])) {
                foreach ($props as $prop) {
                    if (isset($config[$component][$prop])) {
                        $var_name = "--nuclen-{$component}-" . str_replace('_', '-', $prop);
                        $critical[$var_name] = $config[$component][$prop];
                    }
                }
            }
        }

        return $critical;
    }
}