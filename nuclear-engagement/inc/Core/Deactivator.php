<?php
declare(strict_types=1);

namespace NuclearEngagement\Core;

use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Services\AutoGenerationService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Fired during plugin deactivation
 *
 * @link       https://www.nuclearengagement.com
 * @since     0.3.1
 *
 * @package Nuclear_Engagement
 * @subpackage Nuclear_Engagement/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since     0.3.1
 * @package Nuclear_Engagement
 * @subpackage Nuclear_Engagement/includes
 * @author   Stefano Lodola <stefano@nuclearengagement.com>
 */
class Deactivator {
    /**
     * Handle plugin deactivation
     *
     * @since 0.3.1
     * @param SettingsRepository|null $settings Optional settings repository instance
     */
        public static function nuclen_deactivate( ?SettingsRepository $settings = null ) {
                // Clear scheduled cron hooks
                wp_clear_scheduled_hook( AutoGenerationService::START_HOOK );
                wp_clear_scheduled_hook( AutoGenerationService::QUEUE_HOOK );
                wp_clear_scheduled_hook( 'nuclen_poll_generation' );

                // Remove any pending generation records
                delete_option( 'nuclen_active_generations' );

                // Clear any scheduled hooks or transients if needed
                delete_transient( 'nuclen_plugin_activation_redirect' );

        // If settings instance is provided, perform any necessary cleanup
        if ( $settings !== null ) {
            // Clear any cached settings if needed
            $settings->clear_cache();
        }
    }
}
