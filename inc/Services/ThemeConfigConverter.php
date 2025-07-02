<?php
declare(strict_types=1);
namespace NuclearEngagement\Services;

class ThemeConfigConverter {
    
    private const LEGACY_TO_NEW_MAPPING = [
        'bg_color' => 'quiz_container.background_color',
        'quiz_border_color' => 'quiz_container.border_color',
        'quiz_border_width' => 'quiz_container.border_width',
        'quiz_border_radius' => 'quiz_container.border_radius',
        'font_color' => 'quiz_container.text_color',
        'font_size' => 'quiz_container.font_size',
        
        'quiz_answer_button_bg_color' => 'quiz_button.background_color',
        'quiz_answer_button_border_color' => 'quiz_button.border_color',
        'quiz_answer_button_border_width' => 'quiz_button.border_width',
        'quiz_answer_button_border_radius' => 'quiz_button.border_radius',
        
        'quiz_progress_bar_bg_color' => 'progress_bar.background_color',
        'quiz_progress_bar_fg_color' => 'progress_bar.fill_color',
        'quiz_progress_bar_height' => 'progress_bar.height',
        
        'summary_bg_color' => 'summary_container.background_color',
        'summary_border_color' => 'summary_container.border_color',
        'summary_border_width' => 'summary_container.border_width',
        'summary_border_radius' => 'summary_container.border_radius',
        'summary_font_color' => 'summary_container.text_color',
        'summary_font_size' => 'summary_container.font_size',
        
        'toc_bg_color' => 'table_of_contents.background_color',
        'toc_border_color' => 'table_of_contents.border_color',
        'toc_border_width' => 'table_of_contents.border_width',
        'toc_border_radius' => 'table_of_contents.border_radius',
        'toc_font_color' => 'table_of_contents.text_color',
        'toc_font_size' => 'table_of_contents.font_size',
        'toc_link_color' => 'table_of_contents.link_color'
    ];
    
    public function convert_legacy_to_new(array $legacy_config): array {
        $new_config = [];
        
        foreach ($legacy_config as $legacy_key => $value) {
            if (isset(self::LEGACY_TO_NEW_MAPPING[$legacy_key]) && !empty($value)) {
                $new_path = self::LEGACY_TO_NEW_MAPPING[$legacy_key];
                $this->set_nested_value($new_config, $new_path, $this->normalize_value($value));
            }
        }
        
        return $new_config;
    }
    
    public function convert_new_to_legacy(array $new_config): array {
        $legacy_config = [];
        
        $flipped_mapping = array_flip(self::LEGACY_TO_NEW_MAPPING);
        
        foreach ($new_config as $component => $settings) {
            if (!is_array($settings)) {
                continue;
            }
            
            foreach ($settings as $property => $value) {
                $new_path = "{$component}.{$property}";
                
                if (isset($flipped_mapping[$new_path]) && !empty($value)) {
                    $legacy_key = $flipped_mapping[$new_path];
                    $legacy_config[$legacy_key] = $this->convert_value_to_legacy($value, $legacy_key);
                }
            }
        }
        
        return $legacy_config;
    }
    
    private function set_nested_value(array &$array, string $path, $value): void {
        $keys = explode('.', $path);
        $current = &$array;
        
        foreach ($keys as $key) {
            if (!isset($current[$key])) {
                $current[$key] = [];
            }
            $current = &$current[$key];
        }
        
        $current = $value;
    }
    
    private function normalize_value($value): string {
        $value = (string) $value;
        
        // Add 'px' suffix to numeric values that don't have units
        if (is_numeric($value)) {
            return $value . 'px';
        }
        
        // Ensure hex colors start with #
        if (preg_match('/^[A-Fa-f0-9]{6}$/', $value)) {
            return '#' . $value;
        }
        
        return $value;
    }
    
    private function convert_value_to_legacy($value, string $legacy_key): string {
        $value = (string) $value;
        
        // Remove 'px' suffix for certain legacy fields that expect numeric values
        $numeric_fields = [
            'quiz_border_width',
            'quiz_answer_button_border_width', 
            'summary_border_width',
            'toc_border_width',
            'quiz_border_radius',
            'quiz_answer_button_border_radius',
            'summary_border_radius', 
            'toc_border_radius',
            'font_size',
            'summary_font_size',
            'toc_font_size',
            'quiz_progress_bar_height'
        ];
        
        if (in_array($legacy_key, $numeric_fields)) {
            return rtrim($value, 'px');
        }
        
        return $value;
    }
    
    public function get_default_config(): array {
        return [
            'quiz_container' => [
                'background_color' => '#ffffff',
                'border_color' => '#e5e7eb',
                'border_width' => '1px',
                'border_radius' => '6px',
                'text_color' => '#374151',
                'font_size' => '16px',
                'padding' => '20px'
            ],
            'quiz_button' => [
                'background_color' => '#3b82f6',
                'text_color' => '#ffffff',
                'border_color' => '#3b82f6',
                'border_width' => '1px',
                'border_radius' => '4px',
                'padding' => '12px 24px',
                'font_size' => '16px',
                'font_weight' => '500',
                'hover_background_color' => '#2563eb',
                'hover_text_color' => '#ffffff'
            ],
            'progress_bar' => [
                'background_color' => '#e5e7eb',
                'fill_color' => '#10b981',
                'height' => '8px'
            ],
            'summary_container' => [
                'background_color' => '#f9fafb',
                'border_color' => '#e5e7eb',
                'border_width' => '1px',
                'border_radius' => '6px',
                'text_color' => '#374151',
                'font_size' => '16px',
                'padding' => '20px'
            ],
            'table_of_contents' => [
                'background_color' => '#ffffff',
                'border_color' => '#e5e7eb',
                'border_width' => '1px',
                'border_radius' => '6px',
                'text_color' => '#374151',
                'font_size' => '16px',
                'link_color' => '#3b82f6',
                'link_hover_color' => '#2563eb',
                'padding' => '20px'
            ]
        ];
    }
    
    public function merge_with_defaults(array $config): array {
        $defaults = $this->get_default_config();
        
        foreach ($config as $component => $settings) {
            if (isset($defaults[$component]) && is_array($settings)) {
                $defaults[$component] = array_merge($defaults[$component], $settings);
            }
        }
        
        return $defaults;
    }
}