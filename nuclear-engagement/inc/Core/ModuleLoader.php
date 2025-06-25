<?php
declare(strict_types=1);
namespace NuclearEngagement;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ModuleLoader {
    private string $base_dir;

    public function __construct( string $base_dir = NUCLEN_PLUGIN_DIR ) {
        $this->base_dir = rtrim( $base_dir, '/\\' ) . '/';
    }

    public function load_all(): void {
        $pattern = $this->base_dir . 'inc/Modules/*/loader.php';
        $files   = glob( $pattern );
        if ( ! is_array( $files ) ) {
            return;
        }
        foreach ( $files as $file ) {
            if ( file_exists( $file ) ) {
                require_once $file;
            }
        }
    }
}
