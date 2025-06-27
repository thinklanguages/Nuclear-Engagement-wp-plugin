<?php

declare(strict_types=1);

namespace NuclearEngagement\Core;

use NuclearEngagement\Core\Defaults;
use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Core\Activator;
use NuclearEngagement\Core\Deactivator;
use NuclearEngagement\Modules\Summary\Summary_Service;
use NuclearEngagement\Services\LoggingService;

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

class Installer {
public function activate(): void {
$defaults = Defaults::nuclen_get_default_settings();
$settings = SettingsRepository::get_instance( $defaults );
Activator::nuclen_activate( $settings );
}

public function deactivate(): void {
$settings = SettingsRepository::get_instance();
Deactivator::nuclen_deactivate( $settings );
}

public function migrate_post_meta(): void {
if ( get_option( 'nuclen_meta_migration_done' ) ) {
return;
}

global $wpdb;

$check_error = static function () use ( $wpdb ) {
if ( ! empty( $wpdb->last_error ) ) {
LoggingService::log( 'Meta migration error: ' . $wpdb->last_error );
update_option( 'nuclen_meta_migration_error', $wpdb->last_error );
return false;
}
return true;
};

$wpdb->query(
$wpdb->prepare(
"UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s",
Summary_Service::META_KEY,
'ne-summary-data'
)
);
if ( ! $check_error() ) {
return;
}

$wpdb->query(
$wpdb->prepare(
"UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s",
'nuclen-quiz-data',
'ne-quiz-data'
)
);
if ( ! $check_error() ) {
return;
}

delete_option( 'nuclen_meta_migration_error' );
update_option( 'nuclen_meta_migration_done', true );
}
}
