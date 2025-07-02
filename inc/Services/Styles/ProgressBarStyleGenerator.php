<?php
declare(strict_types=1);
namespace NuclearEngagement\Services\Styles;

class ProgressBarStyleGenerator implements StyleGeneratorInterface {
    
    public function generate_styles(array $config): string {
        $css = "    .nuclen-progress-bar {\n";
        
        if (!empty($config['background_color'])) {
            $css .= "        background-color: var(--nuclen-progress-bar-background-color);\n";
        }
        
        if (!empty($config['height'])) {
            $css .= "        height: var(--nuclen-progress-bar-height);\n";
        }
        
        $css .= "    }\n\n";
        
        $css .= "    .nuclen-progress-bar-fill {\n";
        
        if (!empty($config['fill_color'])) {
            $css .= "        background-color: var(--nuclen-progress-bar-fill-color);\n";
        }
        
        $css .= "    }\n\n";
        
        return $css;
    }
    
    public function get_supported_component(): string {
        return 'progress_bar';
    }
    
    public function get_css_variables(array $config): array {
        $variables = [];
        
        $property_map = [
            'background_color' => '--nuclen-progress-bar-background-color',
            'fill_color' => '--nuclen-progress-bar-fill-color',
            'height' => '--nuclen-progress-bar-height'
        ];
        
        foreach ($property_map as $config_key => $css_var) {
            if (!empty($config[$config_key])) {
                $variables[$css_var] = $config[$config_key];
            }
        }
        
        return $variables;
    }
}