<?php
/**
 * ShortcodesTrait.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Front
 */

declare(strict_types=1);
/**
 * File: front/traits/ShortcodesTrait.php
 *
 * Trait: ShortcodesTrait
 *
 * Delegates quiz and summary shortcode handling to dedicated classes
 * and provides auto-insertion helpers.
 *
 * Host class must implement `nuclen_get_settings_repository()`.
 *
 * @package NuclearEngagement\Front
 */

namespace NuclearEngagement\Front;

use NuclearEngagement\Front\QuizShortcode;
use NuclearEngagement\Front\SummaryShortcode;
use NuclearEngagement\Modules\Quiz\Quiz_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait ShortcodesTrait {
	private ?SummaryShortcode $summary_shortcode = null;

	private function get_summary_shortcode(): SummaryShortcode {
		if ( $this->summary_shortcode === null ) {
				$this->summary_shortcode = new SummaryShortcode(
					$this->nuclen_get_settings_repository(),
					$this
				);
		}
			return $this->summary_shortcode;
	}

	/* ---------- Auto-insert into content ---------- */
	public function nuclen_auto_insert_shortcodes( $content ) {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
				return $content;
		}

			$settings_repo    = $this->nuclen_get_settings_repository();
			$summary_position = $settings_repo->get( 'display_summary', 'none' );
			$quiz_position    = $settings_repo->get( 'display_quiz', 'none' );
			$toc_position     = $settings_repo->get( 'display_toc', 'manual' );

			$elements = array(
				'summary' => array(
					'position'  => $summary_position,
					'shortcode' => '[nuclear_engagement_summary]',
				),
				'quiz'    => array(
					'position'  => $quiz_position,
					'shortcode' => '[nuclear_engagement_quiz]',
				),
				'toc'     => array(
					'position'  => $toc_position,
					'shortcode' => '[nuclear_engagement_toc]',
				),
			);

			$before_content = '';
			if ( $elements['summary']['position'] === 'before' ) {
				$before_content .= do_shortcode( $elements['summary']['shortcode'] );
			}
			if ( $elements['toc']['position'] === 'before' ) {
				$before_content .= do_shortcode( $elements['toc']['shortcode'] );
			}
			if ( $elements['quiz']['position'] === 'before' ) {
				$before_content .= do_shortcode( $elements['quiz']['shortcode'] );
			}

			$after_content = '';
			if ( $elements['summary']['position'] === 'after' ) {
				$after_content .= do_shortcode( $elements['summary']['shortcode'] );
			}
			if ( $elements['toc']['position'] === 'after' ) {
				$after_content .= do_shortcode( $elements['toc']['shortcode'] );
			}
			if ( $elements['quiz']['position'] === 'after' ) {
				$after_content .= do_shortcode( $elements['quiz']['shortcode'] );
			}

			return $before_content . $content . $after_content;
	}

		/* ---------- Shortcode registrations ---------- */
	public function nuclen_register_quiz_shortcode() {
			$sc = new QuizShortcode(
				$this->nuclen_get_settings_repository(),
				$this,
				new Quiz_Service()
			);
			$sc->register();
	}

	public function nuclen_register_summary_shortcode() {
		$this->get_summary_shortcode()->register();
	}
}
