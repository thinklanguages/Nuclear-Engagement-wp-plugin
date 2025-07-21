<?php
/**
 * ConsolidatedSettingsPageTrait.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Admin_Traits
 */

declare(strict_types=1);

namespace NuclearEngagement\Admin\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Consolidated settings page management trait.
 *
 * Combines functionality from SettingsPageTrait, SettingsPageLoadTrait,
 * SettingsPageSaveTrait, and SettingsPageCustomCSSTrait.
 *
 * @package NuclearEngagement\Admin\Traits
 * @since 1.0.0
 */
trait ConsolidatedSettingsPageTrait {

	/**
	 * Display the settings page.
	 */
	public function nuclen_display_settings_page(): void {
		$current_settings = $this->nuclen_get_current_settings();

		// Handle save action.
		// phpcs:ignore WordPress.Security.NonceVerification

		if ( isset( $_POST['nuclen_save_settings'] ) ) {
			$this->nuclen_handle_save_settings();
			// Refresh settings after save.
			$current_settings = $this->nuclen_get_current_settings();
		}

		// Include the settings page template.
		$template_path = NUCLEN_PLUGIN_DIR . 'templates/admin/settings/settings-page.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		}
	}

	/**
	 * Get current settings merged with defaults.
	 *
	 * @return array Current settings.
	 */
	public function nuclen_get_current_settings(): array {
		$defaults = $this->settings_repository->get_defaults();
		$current  = $this->settings_repository->get_all();

		return array_merge( $defaults, $current );
	}

	/**
	 * Handle settings save process.
	 *
	 * @return bool Whether save was successful.
	 */
	public function nuclen_handle_save_settings(): bool {
		// Verify nonce.
		if ( ! isset( $_POST['nuclen_settings_nonce'] ) ||
			! wp_verify_nonce( $_POST['nuclen_settings_nonce'], 'nuclen_save_settings' ) ) {
			$this->add_admin_notice( 'Security check failed. Please try again.', 'error' );
			return false;
		}

		try {
			// Collect and sanitize input.
			$collected_settings = $this->collect_settings_input();
			$sanitized_settings = $this->sanitize_all_settings( $collected_settings );

			// Persist settings.
			$this->settings_repository->save_settings( $sanitized_settings );

			// Generate custom CSS.
			$this->nuclen_write_custom_css( $sanitized_settings );

			$this->add_admin_notice( 'Settings saved successfully!', 'success' );
			return true;

		} catch ( \Exception $e ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( '[ERROR] Settings save failed: %s', $e->getMessage() ),
				'error'
			);
			\NuclearEngagement\Services\LoggingService::log_exception( $e );
			
			// Use generic error message to avoid exposing internal details
			if ( $e instanceof \NuclearEngagement\Exceptions\UserFriendlyException ) {
				$this->add_admin_notice( 'Error saving settings: ' . $e->getMessage(), 'error' );
			} else {
				$this->add_admin_notice( 'Error saving settings. Please check your input and try again.', 'error' );
			}
			return false;
		}
	}

	/**
	 * Collect settings from POST data.
	 *
	 * @return array Collected settings.
	 */
	private function collect_settings_input(): array {
		$collected = array();

		// Core settings collection.
		$this->collect_general_settings( $collected );
		$this->collect_style_settings( $collected );
		$this->collect_optin_settings( $collected );

		return $collected;
	}

	/**
	 * Collect general settings.
	 *
	 * @param array &$collected Reference to collected settings array.
	 */
	private function collect_general_settings( array &$collected ): void {
		$general_fields = array(
			'theme',
			'count_summary',
			'count_toc',
			'count_quiz',
			'placement_summary',
			'placement_toc',
			'placement_quiz',
			'allow_html_summary',
			'allow_html_toc',
			'allow_html_quiz',
			'auto_generate_summary',
			'auto_generate_toc',
			'auto_generate_quiz',
		);

		foreach ( $general_fields as $field ) {
			// phpcs:ignore WordPress.Security.NonceVerification

			if ( isset( $_POST[ $field ] ) ) {
				$collected[ $field ] = sanitize_text_field( $_POST[ $field ] );
			}
		}
	}

	/**
	 * Collect style settings.
	 *
	 * @param array &$collected Reference to collected settings array.
	 */
	private function collect_style_settings( array &$collected ): void {
		$style_fields = array(
			// Quiz styles.
			'quiz_bg_color',
			'quiz_text_color',
			'quiz_border_color',
			'quiz_border_width',
			'quiz_border_radius',
			'quiz_padding',
			'quiz_margin',
			'quiz_font_size',
			// Summary styles.
			'summary_bg_color',
			'summary_text_color',
			'summary_border_color',
			'summary_border_width',
			'summary_border_radius',
			'summary_padding',
			'summary_margin',
			'summary_font_size',
			// TOC styles.
			'toc_bg_color',
			'toc_text_color',
			'toc_border_color',
			'toc_border_width',
			'toc_border_radius',
			'toc_padding',
			'toc_margin',
			'toc_font_size',
		);

		foreach ( $style_fields as $field ) {
			// phpcs:ignore WordPress.Security.NonceVerification

			if ( isset( $_POST[ $field ] ) ) {
				$collected[ $field ] = sanitize_text_field( $_POST[ $field ] );
			}
		}
	}

	/**
	 * Collect opt-in settings.
	 *
	 * @param array &$collected Reference to collected settings array.
	 */
	private function collect_optin_settings( array &$collected ): void {
		$optin_fields = array(
			'webhook_url',
			'position',
			'display_text',
			'submit_text',
			'success_message',
			'error_message',
		);

		foreach ( $optin_fields as $field ) {
			// phpcs:ignore WordPress.Security.NonceVerification

			if ( isset( $_POST[ $field ] ) ) {
				$collected[ $field ] = sanitize_text_field( $_POST[ $field ] );
			}
		}
	}

	/**
	 * Sanitize all collected settings.
	 *
	 * @param array $settings Raw settings to sanitize.
	 * @return array Sanitized settings.
	 */
	private function sanitize_all_settings( array $settings ): array {
		$sanitized = array();

		// Sanitize general settings.
		$sanitized = array_merge( $sanitized, $this->sanitize_general_settings( $settings ) );

		// Sanitize style settings.
		$sanitized = array_merge( $sanitized, $this->sanitize_style_settings( $settings ) );

		// Sanitize opt-in settings.
		$sanitized = array_merge( $sanitized, $this->sanitize_optin_settings( $settings ) );

		return $sanitized;
	}

	/**
	 * Sanitize general settings.
	 *
	 * @param array $settings Settings to sanitize.
	 * @return array Sanitized general settings.
	 */
	private function sanitize_general_settings( array $settings ): array {
		$sanitized = array();

		// Theme validation.
		if ( isset( $settings['theme'] ) ) {
			$valid_themes       = array( 'default', 'dark', 'light', 'custom' );
			$sanitized['theme'] = in_array( $settings['theme'], $valid_themes, true )
				? $settings['theme']
				: 'default';
		}

		// Count validation (1-100).
		foreach ( array( 'count_summary', 'count_toc', 'count_quiz' ) as $field ) {
			if ( isset( $settings[ $field ] ) ) {
				$value               = intval( $settings[ $field ] );
				$sanitized[ $field ] = max( 1, min( 100, $value ) );
			}
		}

		// Placement validation.
		$valid_placements = array( 'before', 'after', 'both', 'manual' );
		foreach ( array( 'placement_summary', 'placement_toc', 'placement_quiz' ) as $field ) {
			if ( isset( $settings[ $field ] ) ) {
				$sanitized[ $field ] = in_array( $settings[ $field ], $valid_placements, true )
					? $settings[ $field ]
					: 'after';
			}
		}

		// Boolean fields.
		foreach ( array(
			'allow_html_summary',
			'allow_html_toc',
			'allow_html_quiz',
			'auto_generate_summary',
			'auto_generate_toc',
			'auto_generate_quiz',
		) as $field ) {
			if ( isset( $settings[ $field ] ) ) {
				$sanitized[ $field ] = (bool) $settings[ $field ];
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize style settings.
	 *
	 * @param array $settings Settings to sanitize.
	 * @return array Sanitized style settings.
	 */
	private function sanitize_style_settings( array $settings ): array {
		$sanitized = array();

		// Color validation (HEX format).
		$color_fields = array(
			'quiz_bg_color',
			'quiz_text_color',
			'quiz_border_color',
			'summary_bg_color',
			'summary_text_color',
			'summary_border_color',
			'toc_bg_color',
			'toc_text_color',
			'toc_border_color',
		);

		foreach ( $color_fields as $field ) {
			if ( isset( $settings[ $field ] ) ) {
				$sanitized[ $field ] = $this->sanitize_hex_color( $settings[ $field ] );
			}
		}

		// Dimension validation (0-100px).
		$dimension_fields = array(
			'quiz_border_width',
			'quiz_border_radius',
			'quiz_padding',
			'quiz_margin',
			'summary_border_width',
			'summary_border_radius',
			'summary_padding',
			'summary_margin',
			'toc_border_width',
			'toc_border_radius',
			'toc_padding',
			'toc_margin',
		);

		foreach ( $dimension_fields as $field ) {
			if ( isset( $settings[ $field ] ) ) {
				$value               = intval( $settings[ $field ] );
				$sanitized[ $field ] = max( 0, min( 100, $value ) ) . 'px';
			}
		}

		// Font size validation (8-72px).
		foreach ( array( 'quiz_font_size', 'summary_font_size', 'toc_font_size' ) as $field ) {
			if ( isset( $settings[ $field ] ) ) {
				$value               = intval( $settings[ $field ] );
				$sanitized[ $field ] = max( 8, min( 72, $value ) ) . 'px';
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize opt-in settings.
	 *
	 * @param array $settings Settings to sanitize.
	 * @return array Sanitized opt-in settings.
	 */
	private function sanitize_optin_settings( array $settings ): array {
		$sanitized = array();

		// Webhook URL validation.
		if ( isset( $settings['webhook_url'] ) ) {
			$url                      = esc_url_raw( $settings['webhook_url'] );
			$sanitized['webhook_url'] = filter_var( $url, FILTER_VALIDATE_URL ) ? $url : '';
		}

		// Position validation.
		if ( isset( $settings['position'] ) ) {
			$valid_positions       = array( 'top', 'bottom', 'sidebar', 'floating' );
			$sanitized['position'] = in_array( $settings['position'], $valid_positions, true )
				? $settings['position']
				: 'bottom';
		}

		// Text fields.
		$text_fields = array( 'display_text', 'submit_text', 'success_message', 'error_message' );
		foreach ( $text_fields as $field ) {
			if ( isset( $settings[ $field ] ) ) {
				$sanitized[ $field ] = sanitize_textarea_field( $settings[ $field ] );
			}
		}

		return $sanitized;
	}

	/**
	 * Write custom CSS file based on settings.
	 *
	 * @param array $settings Current settings.
	 * @return bool Whether CSS was written successfully.
	 */
	public function nuclen_write_custom_css( array $settings = array() ): bool {
		if ( empty( $settings ) ) {
			$settings = $this->nuclen_get_current_settings();
		}

		try {
			$css_content   = $this->generate_css_content( $settings );
			$css_file_path = $this->get_css_file_path();

			// Ensure directory exists.
			$css_dir = dirname( $css_file_path );
			if ( ! file_exists( $css_dir ) ) {
				wp_mkdir_p( $css_dir );
			}

			// Write CSS file with atomic operation.
			$temp_file = $css_file_path . '.tmp';
			$bytes_written = file_put_contents( $temp_file, $css_content, LOCK_EX );
			
			if ( $bytes_written === false ) {
				\NuclearEngagement\Services\LoggingService::log(
					sprintf( '[ERROR] Failed to write CSS temp file: %s', $temp_file ),
					'error'
				);
				return false;
			}
			
			if ( ! rename( $temp_file, $css_file_path ) ) {
				\NuclearEngagement\Services\LoggingService::log(
					sprintf( '[ERROR] Failed to rename CSS file from %s to %s', $temp_file, $css_file_path ),
					'error'
				);
				// Clean up temp file
				@unlink( $temp_file );
				return false;
			}
			
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( '[INFO] Successfully wrote CSS file: %s (%d bytes)', $css_file_path, $bytes_written )
			);
			return true;

		} catch ( \Exception $e ) {
			\NuclearEngagement\Services\LoggingService::log(
				sprintf( '[ERROR] CSS generation failed: %s', $e->getMessage() ),
				'error'
			);
			\NuclearEngagement\Services\LoggingService::log_exception( $e );
			
			// Fire action hook to allow error recovery
			do_action( 'nuclen_css_generation_failed', $settings, $e );
			return false;
		}
	}

	/**
	 * Generate CSS content from settings.
	 *
	 * @param array $settings Current settings.
	 * @return string Generated CSS content.
	 */
	private function generate_css_content( array $settings ): string {
		$css = "/* Nuclear Engagement - Generated Styles */\n\n";

		// Quiz styles.
		$css .= $this->generate_component_css( 'quiz', $settings );

		// Summary styles.
		$css .= $this->generate_component_css( 'summary', $settings );

		// TOC styles.
		$css .= $this->generate_component_css( 'toc', $settings );

		return $css;
	}

	/**
	 * Generate CSS for a specific component.
	 *
	 * @param string $component Component name (quiz, summary, toc).
	 * @param array  $settings Current settings.
	 * @return string CSS for the component.
	 */
	private function generate_component_css( string $component, array $settings ): string {
		$css  = "/* {$component} styles */\n";
		$css .= ".nuclen-{$component} {\n";

		$properties = array(
			'background-color' => $settings[ "{$component}_bg_color" ] ?? '',
			'color'            => $settings[ "{$component}_text_color" ] ?? '',
			'border-color'     => $settings[ "{$component}_border_color" ] ?? '',
			'border-width'     => $settings[ "{$component}_border_width" ] ?? '',
			'border-radius'    => $settings[ "{$component}_border_radius" ] ?? '',
			'padding'          => $settings[ "{$component}_padding" ] ?? '',
			'margin'           => $settings[ "{$component}_margin" ] ?? '',
			'font-size'        => $settings[ "{$component}_font_size" ] ?? '',
		);

		foreach ( $properties as $property => $value ) {
			if ( ! empty( $value ) ) {
				$css .= "    {$property}: {$value};\n";
			}
		}

		$css .= "}\n\n";

		return $css;
	}

	/**
	 * Get the CSS file path.
	 *
	 * @return string CSS file path.
	 */
	private function get_css_file_path(): string {
		$upload_dir = wp_upload_dir();
		return $upload_dir['basedir'] . '/nuclear-engagement/custom-styles.css';
	}

	/**
	 * Sanitize hex color value.
	 *
	 * @param string $color Color value.
	 * @return string Sanitized color.
	 */
	private function sanitize_hex_color( string $color ): string {
		$color = ltrim( $color, '#' );

		if ( ctype_xdigit( $color ) && ( strlen( $color ) === 3 || strlen( $color ) === 6 ) ) {
			return '#' . $color;
		}

		return ''; // Return empty if invalid.
	}

	/**
	 * Add an admin notice.
	 *
	 * @param string $message Notice message.
	 * @param string $type Notice type (success, error, warning, info).
	 */
	private function add_admin_notice( string $message, string $type = 'info' ): void {
		add_action(
			'admin_notices',
			function () use ( $message, $type ) {
				printf(
					'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
					esc_attr( $type ),
					esc_html( $message )
				);
			}
		);
	}
}
