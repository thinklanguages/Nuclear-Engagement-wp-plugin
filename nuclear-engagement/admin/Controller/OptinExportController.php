<?php
/**
 * File: admin/Controller/OptinExportController.php
 *
 * Handles opt-in CSV export requests.
 *
 * @package NuclearEngagement\Admin\Controller
 */

namespace NuclearEngagement\Admin\Controller;

use NuclearEngagement\OptinData;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Controller to stream the opt-in CSV file.
 */
class OptinExportController {
    /**
     * Execute export.
     */
    public function handle(): void {
        OptinData::handle_export();
    }
}
