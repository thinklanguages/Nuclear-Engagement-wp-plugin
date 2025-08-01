<?php
namespace NuclearEngagement\Services\Styles;

class QuizContainerStyleGenerator implements StyleGeneratorInterface {
    
    public function generate_styles(array $config): string {
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
        
        if (!empty($config['text_color'])) {
            $css .= "        color: var(--nuclen-quiz-container-text-color);\n";
        }
        
        if (!empty($config['font_size'])) {
            $css .= "        font-size: var(--nuclen-quiz-container-font-size);\n";
        }
        
        $css .= "    }\n\n";
        
        return $css;
    }
    
    public function get_supported_component(): string {
        return 'quiz_container';
    }
    
    public function get_css_variables(array $config): array {
        $variables = [];
        
        $property_map = [
            'background_color' => '--nuclen-quiz-container-background-color',
            'border_color' => '--nuclen-quiz-container-border-color',
            'border_width' => '--nuclen-quiz-container-border-width',
            'border_radius' => '--nuclen-quiz-container-border-radius',
            'text_color' => '--nuclen-quiz-container-text-color',
            'font_size' => '--nuclen-quiz-container-font-size',
            'padding' => '--nuclen-quiz-container-padding'
        ];
        
        foreach ($property_map as $config_key => $css_var) {
            if (!empty($config[$config_key])) {
                $variables[$css_var] = $config[$config_key];
            }
        }
        
        return $variables;
    }
}