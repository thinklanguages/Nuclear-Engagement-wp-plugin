<?php
declare(strict_types=1);
namespace NuclearEngagement\Services\Styles;

class SummaryContainerStyleGenerator implements StyleGeneratorInterface {
    
    public function generate_styles(array $config): string {
        $css = "    .nuclen-summary-container {\n";
        
        if (!empty($config['background_color'])) {
            $css .= "        background-color: var(--nuclen-summary-container-background-color);\n";
        }
        
        if (!empty($config['border_width']) || !empty($config['border_color'])) {
            $css .= "        border: var(--nuclen-summary-container-border-width, 1px) solid var(--nuclen-summary-container-border-color, #e5e7eb);\n";
        }
        
        if (!empty($config['border_radius'])) {
            $css .= "        border-radius: var(--nuclen-summary-container-border-radius);\n";
        }
        
        if (!empty($config['padding'])) {
            $css .= "        padding: var(--nuclen-summary-container-padding);\n";
        }
        
        if (!empty($config['text_color'])) {
            $css .= "        color: var(--nuclen-summary-container-text-color);\n";
        }
        
        if (!empty($config['font_size'])) {
            $css .= "        font-size: var(--nuclen-summary-container-font-size);\n";
        }
        
        $css .= "    }\n\n";
        
        return $css;
    }
    
    public function get_supported_component(): string {
        return 'summary_container';
    }
    
    public function get_css_variables(array $config): array {
        $variables = [];
        
        $property_map = [
            'background_color' => '--nuclen-summary-container-background-color',
            'border_color' => '--nuclen-summary-container-border-color',
            'border_width' => '--nuclen-summary-container-border-width',
            'border_radius' => '--nuclen-summary-container-border-radius',
            'text_color' => '--nuclen-summary-container-text-color',
            'font_size' => '--nuclen-summary-container-font-size',
            'padding' => '--nuclen-summary-container-padding'
        ];
        
        foreach ($property_map as $config_key => $css_var) {
            if (!empty($config[$config_key])) {
                $variables[$css_var] = $config[$config_key];
            }
        }
        
        return $variables;
    }
}