<?php
// Activator.php

namespace NuclearEngagement;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Activator {
	public static function nuclen_activate() {
		set_transient( 'nuclen_plugin_activation_redirect', true, 30 );

		// Pull all defaults from Defaults class
		$default_settings = Defaults::nuclen_get_default_settings();
		$default_setup    = Defaults::nuclen_get_default_setup();

		// Only set the options if they don't already exist
		if ( false === get_option( 'nuclear_engagement_settings' ) ) {
			update_option( 'nuclear_engagement_settings', $default_settings );
		}
		if ( false === get_option( 'nuclear_engagement_setup' ) ) {
			update_option( 'nuclear_engagement_setup', $default_setup );
		}
	}
}
