<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Provide a public-facing view for the plugin
 * nuclear-engagement-public-display.php
 * This file is used to markup the public-facing aspects of the plugin.
 *
 * @link       https://www.nuclearengagement.com
 * @since      0.3.1
 *
 * @package    Nuclear_Engagement
 * @subpackage Nuclear_Engagement/public/partials
 */

$options = get_option(
	'nuclear_engagement_settings',
	array(
		'theme'        => 'bright',
		'font_size'    => '16',
		'font_color'   => '#000000',
		'bg_color'     => '#ffffff',
		'border_color' => '#000000',
		'border_style' => 'solid',
		'border_width' => '1',
	)
);
