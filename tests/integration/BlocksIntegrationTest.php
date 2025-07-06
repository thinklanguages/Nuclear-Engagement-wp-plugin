<?php
/**
 * Integration test for Gutenberg blocks with WordPress environment
 *
 * @package NuclearEngagement_Tests_Integration
 */

declare(strict_types=1);

namespace NuclearEngagement\Tests\Integration;

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Core\Blocks;

/**
 * BlocksIntegrationTest class.
 *
 * Tests Gutenberg blocks integration with WordPress environment,
 * ensuring proper registration and rendering with real WordPress functions.
 */
class BlocksIntegrationTest extends TestCase {

	/**
	 * Test that blocks registration works with WordPress environment.
	 */
	public function test_blocks_register_in_wordpress_environment(): void {
		// Skip if running without WordPress
		if ( ! function_exists( 'register_block_type' ) ) {
			$this->markTestSkipped( 'WordPress environment not available' );
		}

		// Store initial state
		global $wp_registered_blocks;
		$initial_blocks = $wp_registered_blocks ?? array();

		// Mock admin script registration
		wp_register_script( 'nuclen-admin', 'http://example.com/admin.js', array(), '1.0.0', true );

		// Register blocks
		Blocks::register();

		// Check that blocks were registered
		$this->assertArrayHasKey( 'nuclear-engagement/quiz', $wp_registered_blocks );
		$this->assertArrayHasKey( 'nuclear-engagement/summary', $wp_registered_blocks );
		$this->assertArrayHasKey( 'nuclear-engagement/toc', $wp_registered_blocks );

		// Verify block properties
		$quiz_block = $wp_registered_blocks['nuclear-engagement/quiz'];
		$this->assertEquals( 2, $quiz_block['api_version'] );
		$this->assertEquals( 'Quiz', $quiz_block['title'] );
		$this->assertEquals( 'widgets', $quiz_block['category'] );

		// Test render callbacks are callable
		$this->assertTrue( is_callable( $quiz_block['render_callback'] ) );
		$this->assertTrue( is_callable( $wp_registered_blocks['nuclear-engagement/summary']['render_callback'] ) );
		$this->assertTrue( is_callable( $wp_registered_blocks['nuclear-engagement/toc']['render_callback'] ) );

		// Clean up
		foreach ( array( 'nuclear-engagement/quiz', 'nuclear-engagement/summary', 'nuclear-engagement/toc' ) as $block_name ) {
			unset( $wp_registered_blocks[ $block_name ] );
		}
		wp_deregister_script( 'nuclen-admin' );
	}

	/**
	 * Test block rendering with actual shortcode execution.
	 */
	public function test_block_rendering_with_shortcodes(): void {
		if ( ! function_exists( 'register_block_type' ) || ! function_exists( 'do_shortcode' ) ) {
			$this->markTestSkipped( 'WordPress environment not available' );
		}

		// Register a test shortcode that returns known content
		add_shortcode( 'nuclear_engagement_quiz', function() {
			return '<div class="test-quiz">Test Quiz Content</div>';
		} );

		add_shortcode( 'nuclear_engagement_summary', function() {
			return ''; // Empty content to test fallback
		} );

		add_shortcode( 'nuclear_engagement_toc', function() {
			return '<nav class="test-toc">Test TOC</nav>';
		} );

		// Mock admin script
		wp_register_script( 'nuclen-admin', 'http://example.com/admin.js', array(), '1.0.0', true );

		// Register blocks
		Blocks::register();

		global $wp_registered_blocks;

		// Test quiz block renders shortcode content
		$quiz_callback = $wp_registered_blocks['nuclear-engagement/quiz']['render_callback'];
		$quiz_output = call_user_func( $quiz_callback );
		$this->assertEquals( '<div class="test-quiz">Test Quiz Content</div>', $quiz_output );

		// Test summary block renders fallback for empty content
		$summary_callback = $wp_registered_blocks['nuclear-engagement/summary']['render_callback'];
		$summary_output = call_user_func( $summary_callback );
		$this->assertStringContainsString( 'Summary unavailable', $summary_output );

		// Test TOC block renders shortcode content
		$toc_callback = $wp_registered_blocks['nuclear-engagement/toc']['render_callback'];
		$toc_output = call_user_func( $toc_callback );
		$this->assertEquals( '<nav class="test-toc">Test TOC</nav>', $toc_output );

		// Clean up
		remove_shortcode( 'nuclear_engagement_quiz' );
		remove_shortcode( 'nuclear_engagement_summary' );
		remove_shortcode( 'nuclear_engagement_toc' );
		
		foreach ( array( 'nuclear-engagement/quiz', 'nuclear-engagement/summary', 'nuclear-engagement/toc' ) as $block_name ) {
			unset( $wp_registered_blocks[ $block_name ] );
		}
		wp_deregister_script( 'nuclen-admin' );
	}

	/**
	 * Test that blocks don't register when admin script is missing.
	 */
	public function test_blocks_require_admin_script(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			$this->markTestSkipped( 'WordPress environment not available' );
		}

		global $wp_registered_blocks;
		$initial_count = count( $wp_registered_blocks );

		// Ensure admin script is NOT registered
		wp_deregister_script( 'nuclen-admin' );

		// Try to register blocks
		Blocks::register();

		// Should not have registered any new blocks
		$this->assertEquals( $initial_count, count( $wp_registered_blocks ) );
		$this->assertArrayNotHasKey( 'nuclear-engagement/quiz', $wp_registered_blocks );
		$this->assertArrayNotHasKey( 'nuclear-engagement/summary', $wp_registered_blocks );
		$this->assertArrayNotHasKey( 'nuclear-engagement/toc', $wp_registered_blocks );
	}

	/**
	 * Test block editor JavaScript dependencies.
	 */
	public function test_block_editor_script_dependencies(): void {
		if ( ! function_exists( 'register_block_type' ) || ! function_exists( 'wp_scripts' ) ) {
			$this->markTestSkipped( 'WordPress environment not available' );
		}

		// Register admin script with dependencies
		wp_register_script( 'nuclen-admin', 'http://example.com/admin.js', array( 'wp-blocks', 'wp-element' ), '1.0.0', true );

		// Register blocks
		Blocks::register();

		global $wp_registered_blocks;

		// Verify all blocks use the correct editor script
		$blocks = array( 'nuclear-engagement/quiz', 'nuclear-engagement/summary', 'nuclear-engagement/toc' );
		foreach ( $blocks as $block_name ) {
			$this->assertArrayHasKey( $block_name, $wp_registered_blocks );
			$this->assertEquals( 'nuclen-admin', $wp_registered_blocks[ $block_name ]['editor_script'] );
		}

		// Verify script dependencies are correct
		$scripts = wp_scripts();
		$admin_script = $scripts->registered['nuclen-admin'];
		$this->assertContains( 'wp-blocks', $admin_script->deps );
		$this->assertContains( 'wp-element', $admin_script->deps );

		// Clean up
		foreach ( $blocks as $block_name ) {
			unset( $wp_registered_blocks[ $block_name ] );
		}
		wp_deregister_script( 'nuclen-admin' );
	}

	/**
	 * Test block registration timing and hooks.
	 */
	public function test_block_registration_timing(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			$this->markTestSkipped( 'WordPress environment not available' );
		}

		// Track when registration happens
		$registration_calls = array();
		
		// Hook into block registration to track timing
		add_filter( 'register_block_type_args', function( $args, $name ) use ( &$registration_calls ) {
			$registration_calls[] = array(
				'name' => $name,
				'time' => microtime( true ),
				'hook' => current_filter()
			);
			return $args;
		}, 10, 2 );

		// Mock admin script
		wp_register_script( 'nuclen-admin', 'http://example.com/admin.js', array(), '1.0.0', true );

		// Register blocks
		Blocks::register();

		// Verify all blocks were registered
		$registered_names = array_column( $registration_calls, 'name' );
		$this->assertContains( 'nuclear-engagement/quiz', $registered_names );
		$this->assertContains( 'nuclear-engagement/summary', $registered_names );
		$this->assertContains( 'nuclear-engagement/toc', $registered_names );

		// Clean up
		remove_all_filters( 'register_block_type_args' );
		global $wp_registered_blocks;
		foreach ( array( 'nuclear-engagement/quiz', 'nuclear-engagement/summary', 'nuclear-engagement/toc' ) as $block_name ) {
			unset( $wp_registered_blocks[ $block_name ] );
		}
		wp_deregister_script( 'nuclen-admin' );
	}
}