<?php
/**
	* Injects unique IDs into post headings for jump links.
	*
	* @package NuclearEngagement
	*/

declare(strict_types=1);

namespace NuclearEngagement\Modules\TOC;

use NuclearEngagement\Modules\TOC\HeadingExtractor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
	* Adds unique IDs to headings within post content.
	*/
final class Nuclen_TOC_Headings {

	/** Meta key for stored headings. */
		public const META_KEY = 'nuclen_toc_headings';

	/**
	 * Hook into content filters.
	 */
		public function __construct() {
		add_filter( 'the_content', array( $this, 'nuclen_add_heading_ids' ), 99 );
               add_action( 'save_post', array( $this, 'cache_headings_on_save' ), 10, 2 );
		add_action( 'delete_post', array( $this, 'delete_headings_cache' ) );
	}

		/**
		 * Back-compat wrapper for legacy callback name.
		 *
		 * @param string $content Post content to filter.
		 * @return string Filtered content.
		 */
		public function nuclen_add_heading_ids( string $content ): string {
			return $this->add_heading_ids( $content );
	}

		/**
		 * Inject IDs into headings that lack them.
		 *
		 * @param string $content HTML content to modify.
		 * @return string Modified HTML content.
		 */
		public function add_heading_ids( string $content ): string {
		if ( ! apply_filters( 'nuclen_toc_enable_heading_ids', true ) ) {
				return $content;
		}
		if ( ! nuclen_str_contains( $content, '<h' ) ) {
			return $content; }

				foreach ( HeadingExtractor::extract( $content, range( 1, 6 ), get_the_ID() ) as $h ) {
			$pat         = sprintf(
				'/(<%1$s\b(?![^>]*\bid=)[^>]*>)(%2$s)(<\/%1$s>)/is',
				$h['tag'],
				preg_quote( $h['inner'], '/' )
			);
				$rep     = sprintf(
					'<%1$s id="%2$s">%3$s</%1$s>',
					$h['tag'],
					esc_attr( $h['id'] ),
					$h['inner']
				);
				$content = preg_replace( $pat, $rep, $content, 1 );
		}
		return $content;
	}

		/**
		* Cache extracted headings when a post is saved.
		*
		* @param int      $post_id Post ID.
		* @param \WP_Post $post    Post object.
		* @param bool     $update  Whether this is an existing post being updated.
		*/
		public function cache_headings_on_save( int $post_id, \WP_Post $post, bool $update ): void {
		delete_post_meta( $post_id, self::META_KEY );
				$headings = HeadingExtractor::extract( $post->post_content, range( 1, 6 ), $post_id );
		update_post_meta( $post_id, self::META_KEY, $headings );
	}

	/**
	 * Remove cached headings when a post is deleted.
	 *
	 * @param int $post_id Post ID.
	 */
		public function delete_headings_cache( int $post_id ): void {
		delete_post_meta( $post_id, self::META_KEY );
	}
}
