<?php
/**
 * Nuclen_Summary_View.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Modules_Summary
 */

declare(strict_types=1);

namespace NuclearEngagement\Modules\Summary;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Nuclen_Summary_View {
	public function container( array $summary_data, array $settings ): string {
		$summary_content = wp_kses_post( $summary_data['summary'] );
		return sprintf(
			'<section id="nuclen-summary-container" class="nuclen-summary" data-testid="nuclen-summary">'
			. '<h2 id="nuclen-summary-title" class="nuclen-fg">%s</h2>'
			. '<div class="nuclen-fg" id="nuclen-summary-body">%s</div>'
			. '</section>',
			esc_html( $settings['summary_title'] ),
			$summary_content
		);
	}

	public function attribution( bool $show ): string {
		if ( ! $show ) {
			return '';
		}
		return sprintf(
			'<div class="nuclen-attribution">%s <a rel="nofollow" href="https://www.nuclearengagement.com" target="_blank">%s</a></div>',
			esc_html__( 'Summary by', 'nuclear-engagement' ),
			esc_html__( 'Nuclear Engagement', 'nuclear-engagement' )
		);
	}
}
