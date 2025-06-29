<?php
// nuclear-engagement/bootstrap.php
declare(strict_types=1);

use NuclearEngagement\Core\Bootloader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


require_once __DIR__ . '/inc/Core/Bootloader.php';

try {
	Bootloader::init();
} catch ( \Throwable $e ) {
}
