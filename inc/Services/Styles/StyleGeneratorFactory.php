<?php
declare(strict_types=1);
namespace NuclearEngagement\Services\Styles;

class StyleGeneratorFactory {
    
    private static $generators = null;
    
    public static function create_generator(string $component): ?StyleGeneratorInterface {
        if (self::$generators === null) {
            self::initialize_generators();
        }
        
        return self::$generators[$component] ?? null;
    }
    
    public static function get_all_generators(): array {
        if (self::$generators === null) {
            self::initialize_generators();
        }
        
        return self::$generators;
    }
    
    public static function get_supported_components(): array {
        return array_keys(self::get_all_generators());
    }
    
    private static function initialize_generators(): void {
        self::$generators = [
            'quiz_container' => new QuizContainerStyleGenerator(),
            'quiz_button' => new QuizButtonStyleGenerator(),
            'progress_bar' => new ProgressBarStyleGenerator(),
            'summary_container' => new SummaryContainerStyleGenerator(),
            'table_of_contents' => new TocStyleGenerator()
        ];
    }
}