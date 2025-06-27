<?php
/**
 * Unique slug generator for TOC headings.
 *
 * @package NuclearEngagement
 */

declare(strict_types=1);

namespace NuclearEngagement\Modules\TOC;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Generates unique IDs for headings within a post.
 */
final class SlugGenerator {

    /** @var array<string,bool> Track used IDs. */
    private array $used = array();

    /**
     * Seed the generator with known IDs.
     *
     * @param string[] $ids Existing IDs.
     */
    public function prime( array $ids ): void {
        foreach ( $ids as $id ) {
            $this->used[ $id ] = true;
        }
    }

    /**
     * Generate a unique slug from the provided text.
     *
     * @param string $text Heading text.
     * @return string Unique slug.
     */
    public function generate( string $text ): string {
        $base = sanitize_title( $text );
        $id   = $base;
        $n    = 2;

        while ( isset( $this->used[ $id ] ) ) {
            $id = $base . '-' . ( $n++ );
        }

        $this->used[ $id ] = true;
        return $id;
    }
}
