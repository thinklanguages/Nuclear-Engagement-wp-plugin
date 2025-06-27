<?php
declare(strict_types=1);
/**
 * Handles streaming the opt-in CSV export.
 *
 * @package NuclearEngagement\\Services
 */

namespace NuclearEngagement\\Services;

use NuclearEngagement\\OptinData;
use NuclearEngagement\\Services\\LoggingService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OptinExportService {
	/**
	 * Stream the opt-in CSV file to the browser.
	 */
	public function stream_csv(): void {
	    if ( ! current_user_can( 'manage_options' ) ) {
	        wp_die( __( 'Insufficient permissions.', 'nuclear-engagement' ), 403 );
	    }

	    $nonce = $_REQUEST['_wpnonce'] ?? '';
	    if ( ! wp_verify_nonce( $nonce, 'nuclen_export_optin' ) ) {
	        wp_die( __( 'Invalid nonce.', 'nuclear-engagement' ), 400 );
	    }

	    global $wpdb;

	    if ( ob_get_length() ) {
	        ob_end_clean();
	    }
	    nocache_headers();
	    header( 'Content-Type: text/csv; charset=utf-8' );
	    header( 'Content-Disposition: attachment; filename=nuclen_optins_' . gmdate( 'Y-m-d' ) . '.csv' );

	    $out = fopen( 'php://output', 'w' );
	    if ( false === $out ) {
	        LoggingService::log( 'Failed to open output stream for CSV export' );
	        wp_die( __( 'Unable to generate export.', 'nuclear-engagement' ), 500 );
	    }

	    if ( false === fputcsv( $out, array( 'datetime', 'url', 'name', 'email' ) ) ) {
	        LoggingService::log( 'Failed writing CSV header' );
	    }

	    $limit  = 500;
	    $offset = 0;
	    do {
	        $rows = $wpdb->get_results(
	            $wpdb->prepare(
	                "SELECT submitted_at AS datetime,
	                    url,
	                    name,
	                    email
	                FROM " . OptinData::table_name() . "
	                ORDER BY submitted_at DESC
	                LIMIT %d OFFSET %d",
	                $limit,
	                $offset
	            ),
	            ARRAY_A
	        );

	        foreach ( $rows as $r ) {
	            $r['name']  = OptinData::escape_csv_field( $r['name'] );
	            $r['email'] = OptinData::escape_csv_field( $r['email'] );
	            $r['url']   = OptinData::escape_csv_field( $r['url'] );
	            if ( false === fputcsv( $out, $r ) ) {
	                LoggingService::log( 'Failed writing CSV row' );
	            }
	        }

	        $offset += $limit;
	    } while ( count( $rows ) === $limit );

	    fclose( $out );
	    exit;
	}
}
