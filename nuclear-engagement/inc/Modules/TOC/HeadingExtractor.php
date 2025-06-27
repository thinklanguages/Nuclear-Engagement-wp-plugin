<?php
/**
 * Parse post headings with caching.
 *
 * @package NuclearEngagement
 */

declare(strict_types=1);

namespace NuclearEngagement\Modules\TOC;

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

final class HeadingExtractor {
	
	private const CACHE_GROUP = 'nuclen_toc';
	private const CACHE_TTL   = 6 * HOUR_IN_SECONDS;
	
	/**
	 * Extract headings from HTML content.
	 */
	public static function extract( string $html, array $heading_levels, int $post_id = 0 ): array {
	$t0 = microtime( true );
	
	if ( empty( $heading_levels ) ) {
	$heading_levels = range( 2, 6 );
	}
	
	$heading_levels = array_filter(
	array_map( 'intval', $heading_levels ),
	static function ( $level ) {
	return $level >= 1 && $level <= 6;
	}
	);
	
	if ( empty( $heading_levels ) ) {
	$heading_levels = range( 2, 6 );
	}
	
	sort( $heading_levels );
	$heading_levels = array_unique( $heading_levels );
	
	if ( $post_id > 0 ) {
	$stored = get_post_meta( $post_id, Nuclen_TOC_Headings::META_KEY, true );
	if ( is_array( $stored ) && ! empty( $stored ) ) {
	SlugGenerator::reset();
	SlugGenerator::prime( wp_list_pluck( $stored, 'id' ) );
	TocCache::set_last_parse_ms( (int) round( ( microtime( true ) - $t0 ) * 1000 ) );
	return $stored;
	}
	}
	
	list( $key, $transient, $hit ) = self::get_cached_headings( $html, $heading_levels );
	if ( false !== $hit ) {
	SlugGenerator::reset();
	SlugGenerator::prime( wp_list_pluck( $hit, 'id' ) );
	TocCache::set_last_parse_ms( (int) round( ( microtime( true ) - $t0 ) * 1000 ) );
	return $hit;
	}
	
	$out = self::parse_headings( $html, $heading_levels );
	
	wp_cache_set( $key, $out, self::CACHE_GROUP, self::CACHE_TTL );
	set_transient( $transient, $out, self::CACHE_TTL );
	TocCache::set_last_parse_ms( (int) round( ( microtime( true ) - $t0 ) * 1000 ) );
	return $out;
	}
	
	/**
	 * Get cached headings and cache keys.
	 */
	private static function get_cached_headings( string $html, array $heading_levels ): array {
	$key       = md5( $html ) . '_' . implode( '', $heading_levels );
	$transient = 'nuclen_toc_' . $key;
	$hit       = wp_cache_get( $key, self::CACHE_GROUP );
	if ( false === $hit ) {
	$hit = get_transient( $transient );
	}
	return array( $key, $transient, $hit );
	}
	
	/**
	 * Parse headings from HTML using DOM.
	 */
	private static function parse_headings( string $html, array $heading_levels ): array {
	$out = array();
	SlugGenerator::reset();
	
	if ( nuclen_str_contains( $html, '<h' ) ) {
	libxml_use_internal_errors( true );
	$dom = new \DOMDocument();
	$dom->loadHTML( '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $html, \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	
	$xpath = new \DOMXPath( $dom );
	$nodes = $xpath->query( '//h1|//h2|//h3|//h4|//h5|//h6' );
	
	foreach ( $nodes as $node ) {
	$tag = strtolower( $node->nodeName );
	$lvl = (int) substr( $tag, 1 );
	
	if ( ! in_array( $lvl, $heading_levels, true ) ) {
	continue;
	}
	if ( preg_match( '/\bno-?toc\b/i', $node->getAttribute( 'class' ) ) ) {
	continue;
	}
	$data_toc = $node->getAttribute( 'data-toc' );
	if ( '' !== $data_toc && strtolower( $data_toc ) === 'false' ) {
	continue;
	}
	
	$inner = self::inner_html( $node );
	$text  = trim( wp_strip_all_tags( $inner ) );
	if ( '' === $text ) {
	continue;
	}
	
	$id = $node->hasAttribute( 'id' )
	? sanitize_html_class( $node->getAttribute( 'id' ) )
	: SlugGenerator::generate( $text );
	
	$out[] = array(
	'tag'   => $tag,
	'level' => $lvl,
	'text'  => $text,
	'inner' => $inner,
	'id'    => $id,
	);
	}
	}
	
	return $out;
	}
	
	/**
	 * Get inner HTML of a DOM element.
	 */
	private static function inner_html( \DOMElement $el ): string {
	$html = '';
	foreach ( $el->childNodes as $child ) {
	$html .= $el->ownerDocument->saveHTML( $child );
	}
	return $html;
	}
	}
