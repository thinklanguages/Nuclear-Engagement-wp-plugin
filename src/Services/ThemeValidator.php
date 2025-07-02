<?php
namespace NuclearEngagement\Services;

class ThemeValidator {
    
    private const VALID_COMPONENT_KEYS = [
        'quiz_container',
        'quiz_button', 
        'progress_bar',
        'summary_container',
        'table_of_contents'
    ];
    
    private const COMPONENT_SCHEMAS = [
        'quiz_container' => [
            'background_color' => 'color',
            'border_color' => 'color',
            'border_width' => 'size',
            'border_radius' => 'size',
            'text_color' => 'color',
            'font_size' => 'size',
            'padding' => 'size'
        ],
        'quiz_button' => [
            'background_color' => 'color',
            'text_color' => 'color',
            'border_color' => 'color',
            'border_width' => 'size',
            'border_radius' => 'size',
            'padding' => 'size',
            'font_size' => 'size',
            'font_weight' => 'font_weight',
            'hover_background_color' => 'color',
            'hover_text_color' => 'color'
        ],
        'progress_bar' => [
            'background_color' => 'color',
            'fill_color' => 'color',
            'height' => 'size'
        ],
        'summary_container' => [
            'background_color' => 'color',
            'border_color' => 'color',
            'border_width' => 'size',
            'border_radius' => 'size',
            'text_color' => 'color',
            'font_size' => 'size',
            'padding' => 'size'
        ],
        'table_of_contents' => [
            'background_color' => 'color',
            'border_color' => 'color',
            'border_width' => 'size',
            'border_radius' => 'size',
            'text_color' => 'color',
            'font_size' => 'size',
            'link_color' => 'color',
            'link_hover_color' => 'color',
            'padding' => 'size'
        ]
    ];
    
    private $errors = [];
    
    public function validate_theme_config(array $config): array {
        $this->errors = [];
        
        if (empty($config)) {
            $this->add_error('Config cannot be empty');
            return $this->get_validation_result();
        }
        
        foreach ($config as $component => $settings) {
            $this->validate_component($component, $settings);
        }
        
        return $this->get_validation_result();
    }
    
    public function validate_theme_name(string $name): array {
        $this->errors = [];
        
        if (empty(trim($name))) {
            $this->add_error('Theme name cannot be empty');
        }
        
        if (strlen($name) > 50) {
            $this->add_error('Theme name cannot exceed 50 characters');
        }
        
        if (!preg_match('/^[a-zA-Z0-9\s\-_]+$/', $name)) {
            $this->add_error('Theme name contains invalid characters');
        }
        
        return $this->get_validation_result();
    }
    
    public function sanitize_config(array $config): array {
        $sanitized = [];
        
        foreach ($config as $component => $settings) {
            if ($this->is_valid_component($component) && is_array($settings)) {
                $sanitized[$component] = $this->sanitize_component_settings($component, $settings);
            }
        }
        
        return $sanitized;
    }
    
    private function validate_component(string $component, $settings): void {
        if (!$this->is_valid_component($component)) {
            $this->add_error("Invalid component: {$component}");
            return;
        }
        
        if (!is_array($settings)) {
            $this->add_error("Component '{$component}' settings must be an array");
            return;
        }
        
        $schema = self::COMPONENT_SCHEMAS[$component];
        
        foreach ($settings as $property => $value) {
            if (!isset($schema[$property])) {
                $this->add_error("Invalid property '{$property}' for component '{$component}'");
                continue;
            }
            
            $expected_type = $schema[$property];
            if (!$this->validate_property_value($value, $expected_type)) {
                $this->add_error("Invalid value for '{$component}.{$property}': expected {$expected_type}");
            }
        }
    }
    
    private function validate_property_value($value, string $type): bool {
        if (empty($value)) {
            return true; // Allow empty values (will use defaults)
        }
        
        switch ($type) {
            case 'color':
                return $this->is_valid_color($value);
            case 'size':
                return $this->is_valid_size($value);
            case 'font_weight':
                return $this->is_valid_font_weight($value);
            default:
                return false;
        }
    }
    
    private function is_valid_color(string $value): bool {
        // Hex colors
        if (preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $value)) {
            return true;
        }
        
        // RGB/RGBA
        if (preg_match('/^rgba?\([0-9\s,%.]+\)$/', $value)) {
            return true;
        }
        
        // CSS color names
        $css_colors = [
            'transparent', 'black', 'white', 'red', 'blue', 'green', 'yellow',
            'orange', 'purple', 'pink', 'gray', 'grey', 'brown'
        ];
        
        return in_array(strtolower($value), $css_colors);
    }
    
    private function is_valid_size(string $value): bool {
        return preg_match('/^\d+(\.\d+)?(px|em|rem|%|vh|vw)$/', $value);
    }
    
    private function is_valid_font_weight($value): bool {
        $valid_weights = ['normal', 'bold', 'bolder', 'lighter', '100', '200', '300', '400', '500', '600', '700', '800', '900'];
        return in_array((string)$value, $valid_weights);
    }
    
    private function is_valid_component(string $component): bool {
        return in_array($component, self::VALID_COMPONENT_KEYS);
    }
    
    private function sanitize_component_settings(string $component, array $settings): array {
        $sanitized = [];
        $schema = self::COMPONENT_SCHEMAS[$component];
        
        foreach ($settings as $property => $value) {
            if (isset($schema[$property])) {
                $sanitized[$property] = $this->sanitize_property_value($value, $schema[$property]);
            }
        }
        
        return $sanitized;
    }
    
    private function sanitize_property_value($value, string $type) {
        if (empty($value)) {
            return $value;
        }
        
        switch ($type) {
            case 'color':
                return sanitize_text_field($value);
            case 'size':
                return sanitize_text_field($value);
            case 'font_weight':
                return sanitize_text_field($value);
            default:
                return sanitize_text_field($value);
        }
    }
    
    private function add_error(string $message): void {
        $this->errors[] = $message;
    }
    
    private function get_validation_result(): array {
        return [
            'valid' => empty($this->errors),
            'errors' => $this->errors
        ];
    }
}