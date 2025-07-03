<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Admin\Traits\SettingsSanitizeOptinTrait;

/**
 * Test class for SettingsSanitizeOptinTrait
 */
class SettingsSanitizeOptinTraitTest extends TestCase {
	
	private $traitObject;
	
	protected function setUp(): void {
		parent::setUp();
		
		// Create an anonymous class that uses the trait
		$this->traitObject = new class {
			use SettingsSanitizeOptinTrait;
			
			// Make the private method public for testing
			public function sanitize_optin(array $input): array {
				return $this->nuclen_sanitize_optin($input);
			}
		};
	}
	
	/**
	 * Test default values when no input is provided
	 */
	public function test_sanitize_optin_with_empty_input() {
		$result = $this->traitObject->sanitize_optin([]);
		
		$this->assertFalse($result['enable_optin']);
		$this->assertEquals('', $result['optin_webhook']);
		$this->assertEquals('Thank you, your submission was successful!', $result['optin_success_message']);
		$this->assertEquals('with_results', $result['optin_position']);
		$this->assertFalse($result['optin_mandatory']);
		$this->assertEquals('Please enter your details to view your score:', $result['optin_prompt_text']);
		$this->assertEquals('Submit', $result['optin_button_text']);
	}
	
	/**
	 * Test sanitization with valid input
	 */
	public function test_sanitize_optin_with_valid_input() {
		$input = [
			'enable_optin' => '1',
			'optin_webhook' => 'https://example.com/webhook',
			'optin_success_message' => 'Thanks for subscribing!',
			'optin_position' => 'before_results',
			'optin_mandatory' => '1',
			'optin_prompt_text' => 'Subscribe to our newsletter',
			'optin_button_text' => 'Subscribe'
		];
		
		$result = $this->traitObject->sanitize_optin($input);
		
		$this->assertTrue($result['enable_optin']);
		$this->assertEquals('https://example.com/webhook', $result['optin_webhook']);
		$this->assertEquals('Thanks for subscribing!', $result['optin_success_message']);
		$this->assertEquals('before_results', $result['optin_position']);
		$this->assertTrue($result['optin_mandatory']);
		$this->assertEquals('Subscribe to our newsletter', $result['optin_prompt_text']);
		$this->assertEquals('Subscribe', $result['optin_button_text']);
	}
	
	/**
	 * Test optin_position validation
	 */
	public function test_sanitize_optin_position_validation() {
		// Test invalid position defaults to 'with_results'
		$input = ['optin_position' => 'invalid_position'];
		$result = $this->traitObject->sanitize_optin($input);
		$this->assertEquals('with_results', $result['optin_position']);
		
		// Test valid 'with_results'
		$input = ['optin_position' => 'with_results'];
		$result = $this->traitObject->sanitize_optin($input);
		$this->assertEquals('with_results', $result['optin_position']);
		
		// Test valid 'before_results'
		$input = ['optin_position' => 'before_results'];
		$result = $this->traitObject->sanitize_optin($input);
		$this->assertEquals('before_results', $result['optin_position']);
	}
	
	/**
	 * Test boolean conversion
	 */
	public function test_sanitize_optin_boolean_conversion() {
		// Test various truthy values
		$truthy_values = ['1', 1, true, 'true', 'yes'];
		foreach ($truthy_values as $value) {
			$result = $this->traitObject->sanitize_optin([
				'enable_optin' => $value,
				'optin_mandatory' => $value
			]);
			$this->assertTrue($result['enable_optin'], "Failed for value: " . var_export($value, true));
			$this->assertTrue($result['optin_mandatory'], "Failed for value: " . var_export($value, true));
		}
		
		// Test various falsy values
		$falsy_values = ['0', 0, false, 'false', 'no', '', null];
		foreach ($falsy_values as $value) {
			$result = $this->traitObject->sanitize_optin([
				'enable_optin' => $value,
				'optin_mandatory' => $value
			]);
			$this->assertFalse($result['enable_optin'], "Failed for value: " . var_export($value, true));
			$this->assertFalse($result['optin_mandatory'], "Failed for value: " . var_export($value, true));
		}
	}
	
	/**
	 * Test URL sanitization
	 */
	public function test_sanitize_optin_webhook_url() {
		// Test valid URL
		$input = ['optin_webhook' => 'https://example.com/webhook?param=value'];
		$result = $this->traitObject->sanitize_optin($input);
		$this->assertEquals('https://example.com/webhook?param=value', $result['optin_webhook']);
		
		// Test URL with spaces (should be trimmed)
		$input = ['optin_webhook' => '  https://example.com/webhook  '];
		$result = $this->traitObject->sanitize_optin($input);
		$this->assertEquals('https://example.com/webhook', $result['optin_webhook']);
		
		// Test invalid URL (esc_url_raw should return empty string)
		$input = ['optin_webhook' => 'not a url'];
		$result = $this->traitObject->sanitize_optin($input);
		$this->assertEquals('', $result['optin_webhook']);
		
		// Test javascript URL (should be sanitized)
		$input = ['optin_webhook' => 'javascript:alert("XSS")'];
		$result = $this->traitObject->sanitize_optin($input);
		$this->assertEquals('', $result['optin_webhook']);
	}
	
	/**
	 * Test text sanitization
	 */
	public function test_sanitize_optin_text_fields() {
		$input = [
			'optin_prompt_text' => '<script>alert("XSS")</script>Enter your details',
			'optin_button_text' => '<b>Submit</b>',
			'optin_success_message' => 'Thank you! <a href="#">Click here</a>'
		];
		
		$result = $this->traitObject->sanitize_optin($input);
		
		// sanitize_text_field should strip HTML tags
		$this->assertEquals('alert("XSS")Enter your details', $result['optin_prompt_text']);
		$this->assertEquals('Submit', $result['optin_button_text']);
		$this->assertEquals('Thank you! Click here', $result['optin_success_message']);
	}
	
	/**
	 * Test that only expected keys are returned
	 */
	public function test_sanitize_optin_returns_only_expected_keys() {
		$input = [
			'enable_optin' => true,
			'extra_field' => 'should not be included',
			'another_field' => 'also not included'
		];
		
		$result = $this->traitObject->sanitize_optin($input);
		
		$expected_keys = [
			'enable_optin',
			'optin_webhook',
			'optin_success_message',
			'optin_position',
			'optin_mandatory',
			'optin_prompt_text',
			'optin_button_text'
		];
		
		$this->assertEquals($expected_keys, array_keys($result));
	}
	
	/**
	 * Test with mixed valid and invalid data
	 */
	public function test_sanitize_optin_mixed_data() {
		$input = [
			'enable_optin' => 'yes', // Should become true
			'optin_webhook' => 'ftp://example.com/webhook', // Valid FTP URL
			'optin_position' => 'somewhere_else', // Invalid, should default to 'with_results'
			'optin_mandatory' => [], // Empty array should become false
			'optin_prompt_text' => '   Trimmed text   ', // Should be trimmed
			'optin_button_text' => null, // Should use default
			'optin_success_message' => '' // Empty string should use default
		];
		
		$result = $this->traitObject->sanitize_optin($input);
		
		$this->assertTrue($result['enable_optin']);
		$this->assertEquals('ftp://example.com/webhook', $result['optin_webhook']);
		$this->assertEquals('with_results', $result['optin_position']);
		$this->assertFalse($result['optin_mandatory']);
		$this->assertEquals('Trimmed text', $result['optin_prompt_text']);
		$this->assertEquals('Submit', $result['optin_button_text']);
		$this->assertEquals('Thank you, your submission was successful!', $result['optin_success_message']);
	}
}

// Mock WordPress functions
if (!function_exists('sanitize_text_field')) {
	function sanitize_text_field($text) {
		if (!is_string($text)) {
			return '';
		}
		// Strip HTML tags and trim
		return trim(strip_tags($text));
	}
}

if (!function_exists('esc_url_raw')) {
	function esc_url_raw($url) {
		if (!is_string($url)) {
			return '';
		}
		
		// Basic URL validation
		$url = trim($url);
		
		// Reject javascript: URLs
		if (stripos($url, 'javascript:') === 0) {
			return '';
		}
		
		// Simple check for valid URL structure
		if (!preg_match('/^(https?|ftp):\/\/[^\s]+$/', $url)) {
			return '';
		}
		
		return $url;
	}
}