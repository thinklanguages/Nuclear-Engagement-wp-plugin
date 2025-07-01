<?php
declare(strict_types=1);
/**
 * File: inc/Entities/Post.php
 *
 * Post entity class.
 *
 * @package NuclearEngagement\Entities
 */

namespace NuclearEngagement\Entities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post entity representing a WordPress post.
 */
class Post {
	
	/** @var int */
	private int $id;
	
	/** @var string */
	private string $title;
	
	/** @var string */
	private string $content;
	
	/** @var string */
	private string $excerpt;
	
	/** @var string */
	private string $status;
	
	/** @var string */
	private string $type;
	
	/** @var int */
	private int $author_id;
	
	/** @var string */
	private string $date;
	
	/** @var string */
	private string $modified_date;
	
	/** @var array */
	private array $meta_data = array();
	
	public function __construct(
		int $id,
		string $title,
		string $content,
		string $excerpt = '',
		string $status = 'publish',
		string $type = 'post',
		int $author_id = 0,
		string $date = '',
		string $modified_date = ''
	) {
		$this->id = $id;
		$this->title = $title;
		$this->content = $content;
		$this->excerpt = $excerpt;
		$this->status = $status;
		$this->type = $type;
		$this->author_id = $author_id;
		$this->date = $date ?: current_time( 'mysql' );
		$this->modified_date = $modified_date ?: current_time( 'mysql' );
	}
	
	public function get_id(): int {
		return $this->id;
	}
	
	public function get_title(): string {
		return $this->title;
	}
	
	public function set_title( string $title ): void {
		$this->title = $title;
	}
	
	public function get_content(): string {
		return $this->content;
	}
	
	public function set_content( string $content ): void {
		$this->content = $content;
	}
	
	public function get_excerpt(): string {
		return $this->excerpt;
	}
	
	public function set_excerpt( string $excerpt ): void {
		$this->excerpt = $excerpt;
	}
	
	public function get_status(): string {
		return $this->status;
	}
	
	public function set_status( string $status ): void {
		$this->status = $status;
	}
	
	public function get_type(): string {
		return $this->type;
	}
	
	public function set_type( string $type ): void {
		$this->type = $type;
	}
	
	public function get_author_id(): int {
		return $this->author_id;
	}
	
	public function set_author_id( int $author_id ): void {
		$this->author_id = $author_id;
	}
	
	public function get_date(): string {
		return $this->date;
	}
	
	public function get_modified_date(): string {
		return $this->modified_date;
	}
	
	public function set_modified_date( string $modified_date ): void {
		$this->modified_date = $modified_date;
	}
	
	/**
	 * Get meta value.
	 *
	 * @param string $key     Meta key.
	 * @param mixed  $default Default value.
	 * @return mixed Meta value.
	 */
	public function get_meta( string $key, $default = null ) {
		return $this->meta_data[ $key ] ?? $default;
	}
	
	/**
	 * Set meta value.
	 *
	 * @param string $key   Meta key.
	 * @param mixed  $value Meta value.
	 */
	public function set_meta( string $key, $value ): void {
		$this->meta_data[ $key ] = $value;
	}
	
	/**
	 * Get all meta data.
	 *
	 * @return array Meta data.
	 */
	public function get_all_meta(): array {
		return $this->meta_data;
	}
	
	/**
	 * Set all meta data.
	 *
	 * @param array $meta_data Meta data.
	 */
	public function set_all_meta( array $meta_data ): void {
		$this->meta_data = $meta_data;
	}
	
	/**
	 * Check if post has specific meta key.
	 *
	 * @param string $key Meta key.
	 * @return bool Whether meta key exists.
	 */
	public function has_meta( string $key ): bool {
		return isset( $this->meta_data[ $key ] );
	}
	
	/**
	 * Remove meta key.
	 *
	 * @param string $key Meta key.
	 */
	public function remove_meta( string $key ): void {
		unset( $this->meta_data[ $key ] );
	}
	
	/**
	 * Check if post is published.
	 *
	 * @return bool Whether post is published.
	 */
	public function is_published(): bool {
		return $this->status === 'publish';
	}
	
	/**
	 * Check if post is draft.
	 *
	 * @return bool Whether post is draft.
	 */
	public function is_draft(): bool {
		return $this->status === 'draft';
	}
	
	/**
	 * Check if post has quiz data.
	 *
	 * @return bool Whether post has quiz data.
	 */
	public function has_quiz_data(): bool {
		return $this->has_meta( 'nuclen-quiz-data' );
	}
	
	/**
	 * Check if post has summary data.
	 *
	 * @return bool Whether post has summary data.
	 */
	public function has_summary_data(): bool {
		return $this->has_meta( 'nuclen-summary-data' );
	}
	
	/**
	 * Check if post is quiz protected.
	 *
	 * @return bool Whether post is quiz protected.
	 */
	public function is_quiz_protected(): bool {
		return $this->get_meta( 'nuclen_quiz_protected' ) === '1';
	}
	
	/**
	 * Check if post is summary protected.
	 *
	 * @return bool Whether post is summary protected.
	 */
	public function is_summary_protected(): bool {
		return $this->get_meta( 'nuclen_summary_protected' ) === '1';
	}
	
	/**
	 * Get post permalink.
	 *
	 * @return string Post permalink.
	 */
	public function get_permalink(): string {
		return get_permalink( $this->id ) ?: '';
	}
	
	/**
	 * Get post edit link.
	 *
	 * @return string Post edit link.
	 */
	public function get_edit_link(): string {
		return get_edit_post_link( $this->id ) ?: '';
	}
	
	/**
	 * Get stripped content for processing.
	 *
	 * @return string Stripped content.
	 */
	public function get_stripped_content(): string {
		return wp_strip_all_tags( $this->content );
	}
	
	/**
	 * Get content word count.
	 *
	 * @return int Word count.
	 */
	public function get_word_count(): int {
		return str_word_count( $this->get_stripped_content() );
	}
	
	/**
	 * Convert to array.
	 *
	 * @return array Post data array.
	 */
	public function to_array(): array {
		return array(
			'id' => $this->id,
			'title' => $this->title,
			'content' => $this->content,
			'excerpt' => $this->excerpt,
			'status' => $this->status,
			'type' => $this->type,
			'author_id' => $this->author_id,
			'date' => $this->date,
			'modified_date' => $this->modified_date,
			'meta_data' => $this->meta_data,
		);
	}
	
	/**
	 * Create from array.
	 *
	 * @param array $data Post data array.
	 * @return Post Post instance.
	 */
	public static function from_array( array $data ): Post {
		$post = new self(
			$data['id'] ?? 0,
			$data['title'] ?? '',
			$data['content'] ?? '',
			$data['excerpt'] ?? '',
			$data['status'] ?? 'publish',
			$data['type'] ?? 'post',
			$data['author_id'] ?? 0,
			$data['date'] ?? '',
			$data['modified_date'] ?? ''
		);
		
		if ( isset( $data['meta_data'] ) && is_array( $data['meta_data'] ) ) {
			$post->set_all_meta( $data['meta_data'] );
		}
		
		return $post;
	}
}