<?php
/**
 * Test constants are properly defined
 *
 * @package NuclearEngagement_Tests
 */

declare(strict_types=1);

namespace NuclearEngagement\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Constants test class.
 *
 * Tests that all required constants are defined to prevent runtime errors.
 */
class ConstantsTest extends TestCase {

	/**
	 * Test that all core plugin constants are defined.
	 */
	public function test_core_constants_are_defined() {
		// Core plugin constants that must always be defined
		$required_constants = [
			'NUCLEN_PLUGIN_FILE',
			'NUCLEN_PLUGIN_DIR',
			'NUCLEN_PLUGIN_URL',
			'NUCLEN_PLUGIN_VERSION',
			'NUCLEN_ASSET_VERSION',
		];

		foreach ( $required_constants as $constant ) {
			$this->assertTrue(
				defined( $constant ),
				sprintf( 'Required constant %s is not defined', $constant )
			);
		}
	}

	/**
	 * Test that configuration constants are defined.
	 */
	public function test_configuration_constants_are_defined() {
		$config_constants = [
			'NUCLEN_LOG_FILE_MAX_SIZE',
			'NUCLEN_BUFFER_LOGS',
			'NUCLEN_API_TIMEOUT',
			'NUCLEN_INITIAL_POLL_DELAY',
			'NUCLEN_MAX_POLL_ATTEMPTS',
			'NUCLEN_ACTIVATION_REDIRECT_TTL',
			'NUCLEN_POLL_RETRY_DELAY',
			'NUCLEN_GENERATION_POLL_DELAY',
			'NUCLEN_POST_FETCH_CHUNK',
			'NUCLEN_SUMMARY_LENGTH_DEFAULT',
			'NUCLEN_SUMMARY_LENGTH_MIN',
			'NUCLEN_SUMMARY_LENGTH_MAX',
			'NUCLEN_SUMMARY_ITEMS_DEFAULT',
			'NUCLEN_SUMMARY_ITEMS_MIN',
			'NUCLEN_SUMMARY_ITEMS_MAX',
			'NUCLEN_TOC_SCROLL_OFFSET_DEFAULT',
			'NUCLEN_ADMIN_MENU_POSITION',
		];

		foreach ( $config_constants as $constant ) {
			$this->assertTrue(
				defined( $constant ),
				sprintf( 'Configuration constant %s is not defined', $constant )
			);
		}
	}

	/**
	 * Test that constants have appropriate values.
	 */
	public function test_constants_have_valid_values() {
		// Plugin directory should be a valid path
		$this->assertDirectoryExists( NUCLEN_PLUGIN_DIR );

		// Plugin URL should be a valid URL format
		$this->assertMatchesRegularExpression(
			'/^https?:\/\//',
			NUCLEN_PLUGIN_URL,
			'NUCLEN_PLUGIN_URL should be a valid URL'
		);

		// Version should follow semantic versioning
		$this->assertMatchesRegularExpression(
			'/^\d+\.\d+(\.\d+)?/',
			NUCLEN_PLUGIN_VERSION,
			'NUCLEN_PLUGIN_VERSION should follow semantic versioning'
		);

		// Asset version should not be empty
		$this->assertNotEmpty( NUCLEN_ASSET_VERSION );

		// Numeric constants should be positive integers
		$numeric_constants = [
			'NUCLEN_API_TIMEOUT' => 1,
			'NUCLEN_INITIAL_POLL_DELAY' => 1,
			'NUCLEN_MAX_POLL_ATTEMPTS' => 1,
			'NUCLEN_POST_FETCH_CHUNK' => 1,
			'NUCLEN_SUMMARY_LENGTH_MIN' => 1,
			'NUCLEN_TOC_SCROLL_OFFSET_DEFAULT' => 0,
			'NUCLEN_ADMIN_MENU_POSITION' => 1,
		];

		foreach ( $numeric_constants as $constant => $min_value ) {
			$this->assertIsInt(
				constant( $constant ),
				sprintf( '%s should be an integer', $constant )
			);
			$this->assertGreaterThanOrEqual(
				$min_value,
				constant( $constant ),
				sprintf( '%s should be >= %d', $constant, $min_value )
			);
		}

		// Range validations
		$this->assertLessThanOrEqual(
			NUCLEN_SUMMARY_LENGTH_MAX,
			NUCLEN_SUMMARY_LENGTH_DEFAULT,
			'Default summary length should not exceed maximum'
		);
		$this->assertGreaterThanOrEqual(
			NUCLEN_SUMMARY_LENGTH_MIN,
			NUCLEN_SUMMARY_LENGTH_DEFAULT,
			'Default summary length should not be less than minimum'
		);
	}

	/**
	 * Test that constants are defined before they're used.
	 * This helps catch dependency order issues.
	 */
	public function test_constants_dependency_order() {
		// MB_IN_BYTES should be defined before NUCLEN_LOG_FILE_MAX_SIZE
		$this->assertTrue( defined( 'MB_IN_BYTES' ) );
		$this->assertEquals( 1024 * 1024, MB_IN_BYTES );

		// MINUTE_IN_SECONDS should be available for NUCLEN_POLL_RETRY_DELAY
		$this->assertTrue( defined( 'MINUTE_IN_SECONDS' ) );
		$this->assertEquals( 60, MINUTE_IN_SECONDS );
	}
}