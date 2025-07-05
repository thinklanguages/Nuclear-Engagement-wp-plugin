<?php
/**
 * QuizButtonStyleGenerator.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Services_Styles
 */

namespace NuclearEngagement\Services\Styles;

class QuizButtonStyleGenerator implements StyleGeneratorInterface {

	public function generate_styles( array $config ): string {
		$css = "    .nuclen-quiz-button {\n";

		$properties = array(
			'background_color' => 'background-color',
			'text_color'       => 'color',
			'border_radius'    => 'border-radius',
			'padding'          => 'padding',
			'font_size'        => 'font-size',
			'font_weight'      => 'font-weight',
		);

		foreach ( $properties as $config_key => $css_property ) {
			if ( ! empty( $config[ $config_key ] ) ) {
				$var_name = "--nuclen-quiz-button-{$config_key}";
				$var_name = str_replace( '_', '-', $var_name );
				$css     .= "        {$css_property}: var({$var_name});\n";
			}
		}

		if ( ! empty( $config['border_width'] ) || ! empty( $config['border_color'] ) ) {
			$css .= "        border: var(--nuclen-quiz-button-border-width, 1px) solid var(--nuclen-quiz-button-border-color, transparent);\n";
		}

		$css .= "    }\n\n";

		// Hover states.
		if ( ! empty( $config['hover_background_color'] ) || ! empty( $config['hover_text_color'] ) ) {
			$css .= "    .nuclen-quiz-button:hover {\n";

			if ( ! empty( $config['hover_background_color'] ) ) {
				$css .= "        background-color: var(--nuclen-quiz-button-hover-background-color);\n";
			}

			if ( ! empty( $config['hover_text_color'] ) ) {
				$css .= "        color: var(--nuclen-quiz-button-hover-text-color);\n";
			}

			$css .= "    }\n\n";
		}

		return $css;
	}

	public function get_supported_component(): string {
		return 'quiz_button';
	}

	public function get_css_variables( array $config ): array {
		$variables = array();

		$property_map = array(
			'background_color'       => '--nuclen-quiz-button-background-color',
			'text_color'             => '--nuclen-quiz-button-text-color',
			'border_color'           => '--nuclen-quiz-button-border-color',
			'border_width'           => '--nuclen-quiz-button-border-width',
			'border_radius'          => '--nuclen-quiz-button-border-radius',
			'padding'                => '--nuclen-quiz-button-padding',
			'font_size'              => '--nuclen-quiz-button-font-size',
			'font_weight'            => '--nuclen-quiz-button-font-weight',
			'hover_background_color' => '--nuclen-quiz-button-hover-background-color',
			'hover_text_color'       => '--nuclen-quiz-button-hover-text-color',
		);

		foreach ( $property_map as $config_key => $css_var ) {
			if ( ! empty( $config[ $config_key ] ) ) {
				$variables[ $css_var ] = $config[ $config_key ];
			}
		}

		return $variables;
	}
}
