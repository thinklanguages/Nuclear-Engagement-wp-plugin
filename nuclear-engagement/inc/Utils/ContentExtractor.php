<?php
/**
 * ContentExtractor.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Utils
 */

declare(strict_types=1);
/**
 * File: inc/Utils/ContentExtractor.php
 *
 * Utility class for extracting content from various post formats.
 *
 * @package NuclearEngagement\Utils
 */

namespace NuclearEngagement\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extracts readable content from posts built with different editors.
 */
class ContentExtractor {

	/**
	 * Extract readable content from a post, handling various editors.
	 *
	 * @param \WP_Post|object $post Post object.
	 * @return string Extracted content.
	 */
	public static function extract_content( $post ): string {
		if ( ! is_object( $post ) || ! isset( $post->ID ) ) {
			return '';
		}

		$post_id = (int) $post->ID;
		$content = '';

		// Try Elementor first
		if ( self::is_elementor_post( $post_id ) ) {
			$content = self::extract_elementor_content( $post_id );
		}

		// Fall back to regular content if no Elementor content found
		if ( empty( $content ) && isset( $post->post_content ) ) {
			$content = $post->post_content;
		}

		// Strip shortcodes and HTML tags to get plain text
		$content = wp_strip_all_tags( strip_shortcodes( $content ) );

		// Clean up whitespace
		$content = preg_replace( '/\s+/', ' ', $content );
		$content = trim( $content );

		return $content;
	}

	/**
	 * Check if a post is built with Elementor.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True if post uses Elementor.
	 */
	private static function is_elementor_post( int $post_id ): bool {
		return 'yes' === get_post_meta( $post_id, '_elementor_edit_mode', true );
	}

	/**
	 * Extract content from Elementor data.
	 *
	 * @param int $post_id Post ID.
	 * @return string Extracted text content.
	 */
	private static function extract_elementor_content( int $post_id ): string {
		$elementor_data = get_post_meta( $post_id, '_elementor_data', true );
		
		if ( empty( $elementor_data ) ) {
			return '';
		}

		// Decode JSON data
		if ( is_string( $elementor_data ) ) {
			$elementor_data = json_decode( $elementor_data, true );
		}

		if ( ! is_array( $elementor_data ) ) {
			return '';
		}

		// Extract text from Elementor elements recursively
		return self::extract_text_from_elementor_data( $elementor_data );
	}

	/**
	 * Recursively extract text from Elementor data structure.
	 *
	 * @param array $elements Elementor elements array.
	 * @return string Concatenated text content.
	 */
	private static function extract_text_from_elementor_data( array $elements ): string {
		$content = '';

		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			// Check for text in widget settings
			if ( isset( $element['elType'] ) && 'widget' === $element['elType'] ) {
				$widget_type = $element['widgetType'] ?? '';
				$settings    = $element['settings'] ?? array();

				// Extract text based on widget type
				switch ( $widget_type ) {
					case 'text-editor':
					case 'theme-post-content':
						if ( isset( $settings['editor'] ) ) {
							$content .= ' ' . $settings['editor'];
						}
						break;

					case 'heading':
						if ( isset( $settings['title'] ) ) {
							$content .= ' ' . $settings['title'];
						}
						break;

					case 'text':
					case 'blockquote':
						if ( isset( $settings['text'] ) ) {
							$content .= ' ' . $settings['text'];
						}
						if ( isset( $settings['blockquote_content'] ) ) {
							$content .= ' ' . $settings['blockquote_content'];
						}
						break;

					case 'testimonial':
						if ( isset( $settings['testimonial_content'] ) ) {
							$content .= ' ' . $settings['testimonial_content'];
						}
						if ( isset( $settings['testimonial_name'] ) ) {
							$content .= ' ' . $settings['testimonial_name'];
						}
						break;

					case 'icon-list':
						if ( isset( $settings['icon_list'] ) && is_array( $settings['icon_list'] ) ) {
							foreach ( $settings['icon_list'] as $item ) {
								if ( isset( $item['text'] ) ) {
									$content .= ' ' . $item['text'];
								}
							}
						}
						break;

					case 'accordion':
					case 'toggle':
						if ( isset( $settings['tabs'] ) && is_array( $settings['tabs'] ) ) {
							foreach ( $settings['tabs'] as $tab ) {
								if ( isset( $tab['tab_title'] ) ) {
									$content .= ' ' . $tab['tab_title'];
								}
								if ( isset( $tab['tab_content'] ) ) {
									$content .= ' ' . $tab['tab_content'];
								}
							}
						}
						break;
				}
			}

			// Recursively process nested elements
			if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$content .= ' ' . self::extract_text_from_elementor_data( $element['elements'] );
			}
		}

		return trim( $content );
	}
}