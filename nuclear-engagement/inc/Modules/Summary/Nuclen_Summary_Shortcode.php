<?php
/**
 * Nuclen_Summary_Shortcode.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Modules_Summary
 */

declare(strict_types=1);

namespace NuclearEngagement\Modules\Summary;

use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Front\FrontClass;
use NuclearEngagement\Modules\Summary\Summary_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Nuclen_Summary_Shortcode {
	private SettingsRepository $settings;
	private Nuclen_Summary_View $view;
	private FrontClass $front;

	public function __construct( SettingsRepository $settings, FrontClass $front ) {
		$this->settings = $settings;
		$this->front    = $front;
		$this->view     = new Nuclen_Summary_View();
	}

	public function register(): void {
		add_shortcode( 'nuclear_engagement_summary', array( $this, 'render' ) );
	}

	public function render(): string {
		$this->front->nuclen_force_enqueue_assets();
		$summary_data = $this->getSummaryData();
		if ( ! $this->isValidSummaryData( $summary_data ) ) {
			return '';
		}

		$settings = $this->getSummarySettings();
		$theme    = $this->settings->get_string( 'theme', 'bright' );
		$html     = '<div class="nuclen-root" data-theme="' . esc_attr( $theme ) . '">';
		$html    .= $this->view->container( $summary_data, $settings );
		$html    .= $this->view->attribution( $settings['show_attribution'] );
		$html    .= '</div>';
		return $html;
	}

	private function getSummaryData() {
			$post_id = get_the_ID();

			// Validate post ID before proceeding.
		if ( ! $post_id || ! is_int( $post_id ) ) {
			return false;
		}

			return get_post_meta( $post_id, Summary_Service::META_KEY, true );
	}

	private function isValidSummaryData( $summary_data ): bool {
		return ! empty( $summary_data ) && ! empty( trim( $summary_data['summary'] ?? '' ) );
	}

	private function getSummarySettings(): array {
		return array(
			'summary_title'    => $this->settings->get_string( 'summary_title', __( 'Key Facts', 'nuclear-engagement' ) ),
			'show_attribution' => $this->settings->get_bool( 'show_attribution', false ),
		);
	}
}
