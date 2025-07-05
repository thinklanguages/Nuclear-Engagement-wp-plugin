<?php
/**
 * Nuclen_Summary_Metabox.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Modules_Summary
 */

declare(strict_types=1);
/**
 * Summary meta box handler.
 *
 * Formerly the AdminSummaryMetabox trait, converted into a dedicated class
 * under the Summary module.
 */

namespace NuclearEngagement\Modules\Summary;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use NuclearEngagement\Core\SettingsRepository;
use NuclearEngagement\Modules\Summary\Summary_Service;

final class Nuclen_Summary_Metabox {

	private SettingsRepository $settings;

	public function __construct( SettingsRepository $settings ) {
		$this->settings = $settings;
		add_action( 'add_meta_boxes', array( $this, 'nuclen_add_summary_data_meta_box' ) );
		add_action( 'save_post', array( $this, 'nuclen_save_summary_data_meta' ) );
	}

	private function nuclen_get_settings_repository(): SettingsRepository {
			return $this->settings;
	}

	/*
	-------------------------------------------------------------------------
	 *  Meta-box registration
	 * ---------------------------------------------------------------------- */

	public function nuclen_add_summary_data_meta_box() {
		$settings_repo = $this->nuclen_get_settings_repository();
		$post_types    = $settings_repo->get( 'generation_post_types', array( 'post' ) );
		$post_types    = is_array( $post_types ) ? $post_types : array( 'post' );

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'nuclen-summary-data-meta-box',
				'Summary',
				array( $this, 'nuclen_render_summary_data_meta_box' ),
				$post_type,
				'normal',
				'default'
			);
		}
	}

	/*
	-------------------------------------------------------------------------
	 *  Summary meta-box – render
	 * ---------------------------------------------------------------------- */

	public function nuclen_render_summary_data_meta_box( $post ) {
				$summary_data = get_post_meta( $post->ID, Summary_Service::META_KEY, true );
		if ( ! empty( $summary_data ) ) {
			$summary_data = maybe_unserialize( $summary_data );
		} else {
			$summary_data = array(
				'summary'         => '',
				'date'            => '',
				'format'          => 'paragraph',
				'length'          => NUCLEN_SUMMARY_LENGTH_DEFAULT,
				'number_of_items' => NUCLEN_SUMMARY_ITEMS_DEFAULT,
			);
		}

		$summary = $summary_data['summary'] ?? '';
		$date    = $summary_data['date'] ?? '';

				$summary_protected = get_post_meta( $post->ID, Summary_Service::PROTECTED_KEY, true );

				require NUCLEN_PLUGIN_DIR . 'templates/admin/summary-metabox.php';
	}

	/*
	-------------------------------------------------------------------------
	 *  Summary meta-box – save (FIXED to preserve HTML)
	 * ---------------------------------------------------------------------- */

	public function nuclen_save_summary_data_meta( $post_id ) {

		/* ---- Capability / nonce / autosave checks ------------------------ */
		$nonce = $_POST['nuclen_summary_data_nonce'] ?? '';
		if ( ! wp_verify_nonce( $nonce, 'nuclen_summary_data_nonce' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		/* ---- Raw input ---------------------------------------------------- */
		$raw = $_POST['nuclen_summary_data'] ?? array();
		$raw = is_array( $raw ) ? wp_unslash( $raw ) : array();

		/* ---- Sanitise & format ------------------------------------------- */
		$formatted = array(
			'date'    => sanitize_text_field( $raw['date'] ?? gmdate( 'Y-m-d H:i:s' ) ),
			'summary' => wp_kses_post( $raw['summary'] ?? '' ),
		);

		/* ---- Save to DB --------------------------------------------------- */
				$updated = update_post_meta( $post_id, Summary_Service::META_KEY, $formatted );
		if ( $updated === false ) {
			\NuclearEngagement\Services\LoggingService::log( 'Failed to update summary data for post ' . $post_id );
			\NuclearEngagement\Services\LoggingService::notify_admin( 'Failed to update summary data for post ' . $post_id );
		}
		clean_post_cache( $post_id );

		/* ---- Update post_modified if enabled ----------------------------- */
		$settings_repo = $this->nuclen_get_settings_repository();
		$settings      = $settings_repo->get( 'update_last_modified', 0 );
		if ( ! empty( $settings ) && (int) $settings === 1 ) {
				remove_action( 'save_post', array( $this, 'nuclen_save_summary_data_meta' ), 10 );

				$time   = current_time( 'mysql' );
				$result = wp_update_post(
					array(
						'ID'                => $post_id,
						'post_modified'     => $time,
						'post_modified_gmt' => get_gmt_from_date( $time ),
					),
					true
				);

			if ( is_wp_error( $result ) ) {
				\NuclearEngagement\Services\LoggingService::log( 'Failed to update modified time for post ' . $post_id . ': ' . $result->get_error_message() );
				\NuclearEngagement\Services\LoggingService::notify_admin( 'Failed to update modified time for post ' . $post_id . ': ' . $result->get_error_message() );
			}

				add_action( 'save_post', array( $this, 'nuclen_save_summary_data_meta' ), 10, 1 );
		}

		/*
		---- Protected flag ---------------------------------------------- */
		// phpcs:ignore WordPress.Security.NonceVerification

		if ( isset( $_POST[ Summary_Service::PROTECTED_KEY ] ) && $_POST[ Summary_Service::PROTECTED_KEY ] === '1' ) {
				$updated_prot = update_post_meta( $post_id, Summary_Service::PROTECTED_KEY, 1 );
			if ( $updated_prot === false ) {
						\NuclearEngagement\Services\LoggingService::log( 'Failed to update summary protected flag for post ' . $post_id );
						\NuclearEngagement\Services\LoggingService::notify_admin( 'Failed to update summary protected flag for post ' . $post_id );
			}
		} else {
				delete_post_meta( $post_id, Summary_Service::PROTECTED_KEY );
		}
	}
}
