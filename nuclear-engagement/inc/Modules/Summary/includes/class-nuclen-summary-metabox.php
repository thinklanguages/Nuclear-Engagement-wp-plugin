<?php
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

use NuclearEngagement\SettingsRepository;

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
		$summary_data = get_post_meta( $post->ID, 'nuclen-summary-data', true );
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

		$summary_protected = get_post_meta( $post->ID, 'nuclen_summary_protected', true );

		wp_nonce_field( 'nuclen_summary_data_nonce', 'nuclen_summary_data_nonce' );

		echo '<div><label>';
		echo '<input type="checkbox" name="nuclen_summary_protected" value="1"';
		checked( $summary_protected, 1 );
		echo ' /> Protected? <span nuclen-tooltip="Tick this box and save post to prevent overwriting during bulk generation.">🛈</span>';
		echo '</label></div>';

		echo '<div>
            <button type="button"
                    id="nuclen-generate-summary-single"
                    class="button nuclen-generate-single"
                    data-post-id="' . esc_attr( $post->ID ) . '"
                    data-workflow="summary">
                Generate Summary with AI
            </button>
            <span nuclen-tooltip="(re)Generate. Data will be stored automatically (no need to save post).">🛈</span>
        </div>';

		echo '<p><strong>Date</strong><br>';
				echo '<input type="text" name="nuclen_summary_data[date]" value="' . esc_attr( $date ) . '" readonly class="nuclen-meta-date-input" />';
		echo '</p>';

		echo '<p><strong>Summary</strong><br>';
		wp_editor(
			$summary,
			'nuclen_summary_data_summary',
			array(
				'textarea_name' => 'nuclen_summary_data[summary]',
				'textarea_rows' => 5,
				'media_buttons' => false,
				'teeny'         => true,
			)
		);
		echo '</p>';
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
		update_post_meta( $post_id, 'nuclen-summary-data', $formatted );
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

		/* ---- Protected flag ---------------------------------------------- */
		if ( isset( $_POST['nuclen_summary_protected'] ) && $_POST['nuclen_summary_protected'] === '1' ) {
			update_post_meta( $post_id, 'nuclen_summary_protected', 1 );
		} else {
			delete_post_meta( $post_id, 'nuclen_summary_protected' );
		}
	}
}
