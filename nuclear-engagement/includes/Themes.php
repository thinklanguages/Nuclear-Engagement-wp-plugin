<?php
declare(strict_types=1);

namespace NuclearEngagement;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Central theme definitions to avoid duplication.
 */
final class Themes {
    public const MAP = [
        'bright' => 'nuclen-theme-bright.css',
        'dark'   => 'nuclen-theme-dark.css',
    ];
}
