<?php
/**
 * TocStyleGenerator.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Services_Styles
 */

namespace NuclearEngagement\Services\Styles;

class TocStyleGenerator implements StyleGeneratorInterface {

	public function generate_styles( array $config ): string {
		$css = "    .nuclen-toc {\n";

		if ( ! empty( $config['background_color'] ) ) {
			$css .= "        background-color: var(--nuclen-table-of-contents-background-color);\n";
		}

		if ( ! empty( $config['border_width'] ) || ! empty( $config['border_color'] ) ) {
			$css .= "        border: var(--nuclen-table-of-contents-border-width, 1px) solid var(--nuclen-table-of-contents-border-color, #e5e7eb);\n";
		}

		if ( ! empty( $config['border_radius'] ) ) {
			$css .= "        border-radius: var(--nuclen-table-of-contents-border-radius);\n";
		}

		if ( ! empty( $config['padding'] ) ) {
			$css .= "        padding: var(--nuclen-table-of-contents-padding);\n";
		}

		if ( ! empty( $config['text_color'] ) ) {
			$css .= "        color: var(--nuclen-table-of-contents-text-color);\n";
		}

		if ( ! empty( $config['font_size'] ) ) {
			$css .= "        font-size: var(--nuclen-table-of-contents-font-size);\n";
		}

		$css .= "    }\n\n";

		if ( ! empty( $config['link_color'] ) ) {
			$css .= "    .nuclen-toc-item {\n";
			$css .= "        color: var(--nuclen-table-of-contents-link-color);\n";
			$css .= "    }\n\n";
		}

		if ( ! empty( $config['link_hover_color'] ) ) {
			$css .= "    .nuclen-toc-item:hover {\n";
			$css .= "        color: var(--nuclen-table-of-contents-link-hover-color);\n";
			$css .= "    }\n\n";
		}

		return $css;
	}

	public function get_supported_component(): string {
		return 'table_of_contents';
	}

	public function get_css_variables( array $config ): array {
		$variables = array();

		$property_map = array(
			'background_color' => '--nuclen-table-of-contents-background-color',
			'border_color'     => '--nuclen-table-of-contents-border-color',
			'border_width'     => '--nuclen-table-of-contents-border-width',
			'border_radius'    => '--nuclen-table-of-contents-border-radius',
			'text_color'       => '--nuclen-table-of-contents-text-color',
			'font_size'        => '--nuclen-table-of-contents-font-size',
			'link_color'       => '--nuclen-table-of-contents-link-color',
			'link_hover_color' => '--nuclen-table-of-contents-link-hover-color',
			'padding'          => '--nuclen-table-of-contents-padding',
		);

		foreach ( $property_map as $config_key => $css_var ) {
			if ( ! empty( $config[ $config_key ] ) ) {
				$variables[ $css_var ] = $config[ $config_key ];
			}
		}

		return $variables;
	}
}
