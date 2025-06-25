<?php
declare(strict_types=1);
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

use NuclearEngagement\Container;

// Get settings repository instance
$container = Container::getInstance();
$settings  = $container->get( 'settings' );

// Get theme settings with type-safe methods
$theme        = $settings->get_string( 'theme', 'bright' );
$font_size    = $settings->get_int( 'font_size', 16 );
$font_color   = $settings->get_string( 'font_color', '#000000' );
$bg_color     = $settings->get_string( 'bg_color', '#ffffff' );
$border_color = $settings->get_string( 'border_color', '#000000' );
$border_style = $settings->get_string( 'border_style', 'solid' );
$border_width = $settings->get_int( 'border_width', 1 );

// For backward compatibility, create an options array
$options = array(
	'theme'        => $theme,
	'font_size'    => $font_size,
	'font_color'   => $font_color,
	'bg_color'     => $bg_color,
	'border_color' => $border_color,
	'border_style' => $border_style,
	'border_width' => $border_width,
);
