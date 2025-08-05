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
		$content   = '';
		$extractor = new ElementorWidgetExtractor();

		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			// Extract widget content
			if ( isset( $element['elType'] ) && 'widget' === $element['elType'] ) {
				$widget_content = $extractor->extractFromWidget( $element );
				if ( ! empty( $widget_content ) ) {
					$content .= ' ' . $widget_content;
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

/**
 * Handles extraction of content from different Elementor widget types
 */
class ElementorWidgetExtractor {

	/**
	 * @var array Widget extractors mapped by widget type
	 */
	private array $extractors;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->extractors = $this->initializeExtractors();
	}

	/**
	 * Initialize widget extractors
	 *
	 * @return array
	 */
	private function initializeExtractors(): array {
		return array(
			'text-editor'        => new TextEditorExtractor(),
			'theme-post-content' => new TextEditorExtractor(),
			'heading'            => new HeadingExtractor(),
			'text'               => new TextExtractor(),
			'blockquote'         => new BlockquoteExtractor(),
			'testimonial'        => new TestimonialExtractor(),
			'icon-list'          => new IconListExtractor(),
			'accordion'          => new AccordionExtractor(),
			'toggle'             => new AccordionExtractor(),
		);
	}

	/**
	 * Extract content from a widget
	 *
	 * @param array $element Widget element data
	 * @return string Extracted content
	 */
	public function extractFromWidget( array $element ): string {
		$widget_type = $element['widgetType'] ?? '';
		$settings    = $element['settings'] ?? array();

		if ( isset( $this->extractors[ $widget_type ] ) ) {
			return $this->extractors[ $widget_type ]->extract( $settings );
		}

		return '';
	}
}

/**
 * Interface for widget extractors
 */
interface WidgetExtractorInterface {
	/**
	 * Extract content from widget settings
	 *
	 * @param array $settings Widget settings
	 * @return string Extracted content
	 */
	public function extract( array $settings ): string;
}

/**
 * Extracts content from text editor widgets
 */
class TextEditorExtractor implements WidgetExtractorInterface {
	public function extract( array $settings ): string {
		return $settings['editor'] ?? '';
	}
}

/**
 * Extracts content from heading widgets
 */
class HeadingExtractor implements WidgetExtractorInterface {
	public function extract( array $settings ): string {
		return $settings['title'] ?? '';
	}
}

/**
 * Extracts content from text widgets
 */
class TextExtractor implements WidgetExtractorInterface {
	public function extract( array $settings ): string {
		return $settings['text'] ?? '';
	}
}

/**
 * Extracts content from blockquote widgets
 */
class BlockquoteExtractor implements WidgetExtractorInterface {
	public function extract( array $settings ): string {
		$content = '';

		if ( isset( $settings['text'] ) ) {
			$content .= $settings['text'];
		}

		if ( isset( $settings['blockquote_content'] ) ) {
			if ( ! empty( $content ) ) {
				$content .= ' ';
			}
			$content .= $settings['blockquote_content'];
		}

		return $content;
	}
}

/**
 * Extracts content from testimonial widgets
 */
class TestimonialExtractor implements WidgetExtractorInterface {
	public function extract( array $settings ): string {
		$content = '';

		if ( isset( $settings['testimonial_content'] ) ) {
			$content .= $settings['testimonial_content'];
		}

		if ( isset( $settings['testimonial_name'] ) ) {
			if ( ! empty( $content ) ) {
				$content .= ' ';
			}
			$content .= $settings['testimonial_name'];
		}

		return $content;
	}
}

/**
 * Extracts content from icon list widgets
 */
class IconListExtractor implements WidgetExtractorInterface {
	public function extract( array $settings ): string {
		$content = '';

		if ( isset( $settings['icon_list'] ) && is_array( $settings['icon_list'] ) ) {
			$items = array();
			foreach ( $settings['icon_list'] as $item ) {
				if ( isset( $item['text'] ) ) {
					$items[] = $item['text'];
				}
			}
			$content = implode( ' ', $items );
		}

		return $content;
	}
}

/**
 * Extracts content from accordion/toggle widgets
 */
class AccordionExtractor implements WidgetExtractorInterface {
	public function extract( array $settings ): string {
		$content = '';

		if ( isset( $settings['tabs'] ) && is_array( $settings['tabs'] ) ) {
			$items = array();
			foreach ( $settings['tabs'] as $tab ) {
				if ( isset( $tab['tab_title'] ) ) {
					$items[] = $tab['tab_title'];
				}
				if ( isset( $tab['tab_content'] ) ) {
					$items[] = $tab['tab_content'];
				}
			}
			$content = implode( ' ', $items );
		}

		return $content;
	}
}
