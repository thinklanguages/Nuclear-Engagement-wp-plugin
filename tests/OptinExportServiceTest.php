<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Services\OptinExportService;

// Mock WordPress functions
if (!function_exists('current_user_can')) {
	function current_user_can($capability) {
		return $GLOBALS['current_user_can'] ?? true;
	}
}

if (!function_exists('wp_die')) {
	function wp_die($message = '', $code = 0) {
		$GLOBALS['wp_die_called'] = true;
		$GLOBALS['wp_die_message'] = $message;
		$GLOBALS['wp_die_code'] = $code;
		throw new WPDieException($message, $code);
	}
}

if (!function_exists('wp_verify_nonce')) {
	function wp_verify_nonce($nonce, $action) {
		return $GLOBALS['wp_verify_nonce'] ?? true;
	}
}

if (!function_exists('__')) {
	function __($text, $domain = '') {
		return $text;
	}
}

if (!function_exists('nocache_headers')) {
	function nocache_headers() {
		$GLOBALS['headers']['Cache-Control'] = 'no-cache, must-revalidate, max-age=0';
		$GLOBALS['headers']['Pragma'] = 'no-cache';
		$GLOBALS['headers']['Expires'] = '0';
	}
}

if (!function_exists('header')) {
	function header($header) {
		list($key, $value) = explode(': ', $header, 2);
		$GLOBALS['headers'][$key] = $value;
	}
}

if (!function_exists('gmdate')) {
	function gmdate($format, $timestamp = null) {
		return date($format, $timestamp ?? time());
	}
}

// Custom exceptions
if (!class_exists('WPDieException')) {
    class WPDieException extends \Exception {}
}
if (!class_exists('SystemExit')) {
    class SystemExit extends \Exception {}
}

/**
 * Tests for OptinExportService
 */
class OptinExportServiceTest extends TestCase {
	
	private $service;
	private $originalWpdb;
	private $mockWpdb;
	private $outputBuffer;
	
	protected function setUp(): void {
		parent::setUp();
		
		// Define constants
		if (!defined('ABSPATH')) {
			define('ABSPATH', '/tmp/');
		}
		
		// Mock OptinData if not exists
		if (!class_exists('NuclearEngagement\OptinData')) {
			require_once __DIR__ . '/mocks/OptinDataMock.php';
		}
		
		// Mock LoggingService if not exists
		if (!class_exists('NuclearEngagement\Services\LoggingService')) {
			require_once __DIR__ . '/mocks/LoggingServiceMock.php';
		}
		
		$this->service = new OptinExportService();
		
		// Save original wpdb and create mock
		global $wpdb;
		$this->originalWpdb = $wpdb;
		$this->mockWpdb = $this->createMock(\stdClass::class);
		$this->mockWpdb->method('prepare')->willReturnCallback(function($query, ...$args) {
			return vsprintf(str_replace('%d', '%d', $query), $args);
		});
		
		// Reset globals
		$GLOBALS['_REQUEST'] = [];
		$GLOBALS['current_user_can'] = true;
		$GLOBALS['wp_verify_nonce'] = true;
		$GLOBALS['headers_sent'] = false;
		$GLOBALS['headers'] = [];
		$GLOBALS['wp_die_called'] = false;
		$GLOBALS['wp_die_message'] = '';
		$GLOBALS['wp_die_code'] = 0;
		
		// Start output buffering to capture CSV output
		ob_start();
	}
	
	protected function tearDown(): void {
		// Clean up output buffer
		if (ob_get_level() > 0) {
			ob_end_clean();
		}
		
		// Restore original wpdb
		global $wpdb;
		$wpdb = $this->originalWpdb;
		
		parent::tearDown();
	}
	
	/**
	 * Test CSV export with valid data
	 */
	public function test_stream_csv_success() {
		global $wpdb;
		$wpdb = $this->mockWpdb;
		
		// Mock database results
		$mockData = [
			[
				'datetime' => '2024-01-15 10:30:00',
				'url' => 'https://example.com/quiz',
				'name' => 'John Doe',
				'email' => 'john@example.com'
			],
			[
				'datetime' => '2024-01-14 15:45:00',
				'url' => 'https://example.com/quiz2',
				'name' => 'Jane Smith',
				'email' => 'jane@example.com'
			]
		];
		
		$wpdb->expects($this->once())
			->method('get_results')
			->willReturn($mockData);
		
		// Set up valid request
		$_REQUEST['_wpnonce'] = 'valid_nonce';
		
		// Capture output instead of exiting
		$GLOBALS['exit_called'] = false;
		
		// Execute
		try {
			$this->service->stream_csv();
		} catch (SystemExit $e) {
			// Expected
		}
		
		$output = ob_get_contents();
		
		// Verify CSV headers were set
		$this->assertArrayHasKey('Content-Type', $GLOBALS['headers']);
		$this->assertEquals('text/csv; charset=utf-8', $GLOBALS['headers']['Content-Type']);
		$this->assertArrayHasKey('Content-Disposition', $GLOBALS['headers']);
		$this->assertStringContainsString('attachment; filename=nuclen_optins_', $GLOBALS['headers']['Content-Disposition']);
		
		// Verify CSV content
		$lines = explode("\n", trim($output));
		$this->assertCount(3, $lines); // Header + 2 data rows
		
		// Check header
		$this->assertEquals('datetime,url,name,email', $lines[0]);
		
		// Check data rows
		$this->assertStringContainsString('2024-01-15 10:30:00,https://example.com/quiz,John Doe,john@example.com', $lines[1]);
		$this->assertStringContainsString('2024-01-14 15:45:00,https://example.com/quiz2,Jane Smith,jane@example.com', $lines[2]);
	}
	
	/**
	 * Test permission check
	 */
	public function test_stream_csv_insufficient_permissions() {
		$GLOBALS['current_user_can'] = false;
		
		try {
			$this->service->stream_csv();
		} catch (WPDieException $e) {
			// Expected
		}
		
		$this->assertTrue($GLOBALS['wp_die_called']);
		$this->assertEquals('Insufficient permissions.', $GLOBALS['wp_die_message']);
		$this->assertEquals(403, $GLOBALS['wp_die_code']);
	}
	
	/**
	 * Test nonce validation
	 */
	public function test_stream_csv_invalid_nonce() {
		$GLOBALS['wp_verify_nonce'] = false;
		$_REQUEST['_wpnonce'] = 'invalid_nonce';
		
		try {
			$this->service->stream_csv();
		} catch (WPDieException $e) {
			// Expected
		}
		
		$this->assertTrue($GLOBALS['wp_die_called']);
		$this->assertEquals('Invalid nonce.', $GLOBALS['wp_die_message']);
		$this->assertEquals(400, $GLOBALS['wp_die_code']);
	}
	
	/**
	 * Test CSV field escaping for formula injection
	 */
	public function test_stream_csv_escapes_formulas() {
		global $wpdb;
		$wpdb = $this->mockWpdb;
		
		// Mock data with potential formula injection
		$mockData = [
			[
				'datetime' => '2024-01-15 10:30:00',
				'url' => '=SUM(A1:A10)',
				'name' => '+CMD|"/c calc"!A0',
				'email' => '@SUM(1+1)'
			]
		];
		
		$wpdb->expects($this->once())
			->method('get_results')
			->willReturn($mockData);
		
		$_REQUEST['_wpnonce'] = 'valid_nonce';
		
		try {
			$this->service->stream_csv();
		} catch (SystemExit $e) {
			// Expected
		}
		
		$output = ob_get_contents();
		$lines = explode("\n", trim($output));
		
		// Verify formulas are escaped with single quote
		$this->assertStringContainsString("'=SUM(A1:A10)", $lines[1]);
		$this->assertStringContainsString("'+CMD|\"/c calc\"!A0", $lines[1]);
		$this->assertStringContainsString("'@SUM(1+1)", $lines[1]);
	}
	
	/**
	 * Test pagination for large datasets
	 */
	public function test_stream_csv_pagination() {
		global $wpdb;
		$wpdb = $this->mockWpdb;
		
		// Create 500 records for first batch
		$firstBatch = [];
		for ($i = 0; $i < 500; $i++) {
			$firstBatch[] = [
				'datetime' => '2024-01-15 10:30:00',
				'url' => 'https://example.com/quiz',
				'name' => 'User ' . $i,
				'email' => 'user' . $i . '@example.com'
			];
		}
		
		// Create 100 records for second batch
		$secondBatch = [];
		for ($i = 500; $i < 600; $i++) {
			$secondBatch[] = [
				'datetime' => '2024-01-15 10:30:00',
				'url' => 'https://example.com/quiz',
				'name' => 'User ' . $i,
				'email' => 'user' . $i . '@example.com'
			];
		}
		
		$wpdb->expects($this->exactly(2))
			->method('get_results')
			->willReturnOnConsecutiveCalls($firstBatch, $secondBatch);
		
		$_REQUEST['_wpnonce'] = 'valid_nonce';
		
		try {
			$this->service->stream_csv();
		} catch (SystemExit $e) {
			// Expected
		}
		
		$output = ob_get_contents();
		$lines = explode("\n", trim($output));
		
		// Should have header + 600 data rows
		$this->assertCount(601, $lines);
		
		// Verify some specific rows
		$this->assertStringContainsString('User 0,user0@example.com', $lines[1]);
		$this->assertStringContainsString('User 499,user499@example.com', $lines[500]);
		$this->assertStringContainsString('User 500,user500@example.com', $lines[501]);
		$this->assertStringContainsString('User 599,user599@example.com', $lines[600]);
	}
	
	/**
	 * Test empty dataset
	 */
	public function test_stream_csv_empty_data() {
		global $wpdb;
		$wpdb = $this->mockWpdb;
		
		$wpdb->expects($this->once())
			->method('get_results')
			->willReturn([]);
		
		$_REQUEST['_wpnonce'] = 'valid_nonce';
		
		try {
			$this->service->stream_csv();
		} catch (SystemExit $e) {
			// Expected
		}
		
		$output = ob_get_contents();
		$lines = explode("\n", trim($output));
		
		// Should only have header
		$this->assertCount(1, $lines);
		$this->assertEquals('datetime,url,name,email', $lines[0]);
	}
	
	/**
	 * Test output buffer cleaning
	 */
	public function test_stream_csv_cleans_output_buffer() {
		global $wpdb;
		$wpdb = $this->mockWpdb;
		
		// Add some content to output buffer
		echo "Previous output that should be cleaned";
		
		$wpdb->expects($this->once())
			->method('get_results')
			->willReturn([]);
		
		$_REQUEST['_wpnonce'] = 'valid_nonce';
		
		try {
			$this->service->stream_csv();
		} catch (SystemExit $e) {
			// Expected
		}
		
		$output = ob_get_contents();
		
		// Previous output should be cleaned
		$this->assertStringNotContainsString("Previous output", $output);
		$this->assertStringStartsWith('datetime,url,name,email', trim($output));
	}
}