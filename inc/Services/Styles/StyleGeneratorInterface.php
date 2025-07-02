<?php
declare(strict_types=1);
namespace NuclearEngagement\Services\Styles;

interface StyleGeneratorInterface {
    
    public function generate_styles(array $config): string;
    
    public function get_supported_component(): string;
    
    public function get_css_variables(array $config): array;
}