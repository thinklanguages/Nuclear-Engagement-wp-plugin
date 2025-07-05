<?php
/**
 * OnboardingPointers.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Admin
 */

declare(strict_types=1);
/**
 * File: admin/OnboardingPointers.php
 *
 * Stores admin pointer definitions for the onboarding flow.
 *
 * @package NuclearEngagement\Admin
 */

namespace NuclearEngagement\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class OnboardingPointers {
	/**
	 * Get pointer definitions grouped by admin page.
	 *
	 * @return array<string,array<int,array<string,mixed>>>
	 */
	public static function get_pointers(): array {
		$file = __DIR__ . '/data/onboarding-pointers.json';
		if ( ! file_exists( $file ) ) {
			return array();
		}
		$data = wp_json_file_decode( $file, array( 'associative' => true ) );
		if ( ! is_array( $data ) ) {
			return array();
		}
		foreach ( $data as $page => &$pointers ) {
			if ( ! is_array( $pointers ) ) {
				$pointers = array();
				continue;
			}
			foreach ( $pointers as &$ptr ) {
				if ( isset( $ptr['title'] ) ) {
					$ptr['title'] = esc_html__( $ptr['title'], 'nuclear-engagement' );
				}
				if ( isset( $ptr['content'] ) ) {
					$ptr['content'] = esc_html__( $ptr['content'], 'nuclear-engagement' );
				}
			}
		}
		return $data;
	}
}
