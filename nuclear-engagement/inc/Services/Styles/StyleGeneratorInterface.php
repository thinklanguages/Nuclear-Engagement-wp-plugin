<?php
/**
 * StyleGeneratorInterface.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Services_Styles
 */

namespace NuclearEngagement\Services\Styles;

interface StyleGeneratorInterface {

	public function generate_styles( array $config ): string;

	public function get_supported_component(): string;

	public function get_css_variables( array $config ): array;
}
