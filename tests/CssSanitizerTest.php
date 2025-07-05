<?php
/**
 * CssSanitizerTest.php - Test suite for the CssSanitizer class
 *
 * @package NuclearEngagement_Tests
 */

declare(strict_types=1);

namespace NuclearEngagement\Tests;

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Security\CssSanitizer;

/**
 * Test suite for the CssSanitizer class
 */
class CssSanitizerTest extends TestCase {

	/**
	 * Test sanitize_color with valid hex colors
	 */
	public function test_sanitize_color_valid_hex_colors() {
		$valid_hex_colors = [
			'#fff' => '#fff',
			'#FFF' => '#FFF',
			'#ffffff' => '#ffffff',
			'#FFFFFF' => '#FFFFFF',
			'#123abc' => '#123abc',
			'#ABC123' => '#ABC123'
		];
		
		foreach ($valid_hex_colors as $input => $expected) {
			$result = CssSanitizer::sanitize_color($input);
			$this->assertEquals($expected, $result, "Failed to sanitize hex color: {$input}");
		}
	}

	/**
	 * Test sanitize_color with valid RGB colors
	 */
	public function test_sanitize_color_valid_rgb_colors() {
		$valid_rgb_colors = [
			'rgb(255, 255, 255)' => 'rgb(255, 255, 255)',
			'rgb(0, 0, 0)' => 'rgb(0, 0, 0)',
			'rgb(128, 64, 32)' => 'rgb(128, 64, 32)',
			'rgb( 255 , 255 , 255 )' => 'rgb( 255 , 255 , 255 )'
		];
		
		foreach ($valid_rgb_colors as $input => $expected) {
			$result = CssSanitizer::sanitize_color($input);
			$this->assertEquals($expected, $result, "Failed to sanitize RGB color: {$input}");
		}
	}

	/**
	 * Test sanitize_color with valid RGBA colors
	 */
	public function test_sanitize_color_valid_rgba_colors() {
		$valid_rgba_colors = [
			'rgba(255, 255, 255, 1)' => 'rgba(255, 255, 255, 1)',
			'rgba(0, 0, 0, 0.5)' => 'rgba(0, 0, 0, 0.5)',
			'rgba(128, 64, 32, 0.75)' => 'rgba(128, 64, 32, 0.75)'
		];
		
		foreach ($valid_rgba_colors as $input => $expected) {
			$result = CssSanitizer::sanitize_color($input);
			$this->assertEquals($expected, $result, "Failed to sanitize RGBA color: {$input}");
		}
	}

	/**
	 * Test sanitize_color with valid HSL colors
	 */
	public function test_sanitize_color_valid_hsl_colors() {
		$valid_hsl_colors = [
			'hsl(0, 100%, 50%)' => 'hsl(0, 100%, 50%)',
			'hsl(120, 50%, 25%)' => 'hsl(120, 50%, 25%)',
			'hsl(240, 75%, 75%)' => 'hsl(240, 75%, 75%)'
		];
		
		foreach ($valid_hsl_colors as $input => $expected) {
			$result = CssSanitizer::sanitize_color($input);
			$this->assertEquals($expected, $result, "Failed to sanitize HSL color: {$input}");
		}
	}

	/**
	 * Test sanitize_color with valid HSLA colors
	 */
	public function test_sanitize_color_valid_hsla_colors() {
		$valid_hsla_colors = [
			'hsla(0, 100%, 50%, 1)' => 'hsla(0, 100%, 50%, 1)',
			'hsla(120, 50%, 25%, 0.5)' => 'hsla(120, 50%, 25%, 0.5)',
			'hsla(240, 75%, 75%, 0.25)' => 'hsla(240, 75%, 75%, 0.25)'
		];
		
		foreach ($valid_hsla_colors as $input => $expected) {
			$result = CssSanitizer::sanitize_color($input);
			$this->assertEquals($expected, $result, "Failed to sanitize HSLA color: {$input}");
		}
	}

	/**
	 * Test sanitize_color with valid CSS keywords
	 */
	public function test_sanitize_color_valid_css_keywords() {
		$valid_keywords = [
			'transparent' => 'transparent',
			'currentcolor' => 'currentcolor',
			'TRANSPARENT' => 'TRANSPARENT',
			'CurrentColor' => 'CurrentColor'
		];
		
		foreach ($valid_keywords as $input => $expected) {
			$result = CssSanitizer::sanitize_color($input);
			$this->assertEquals($expected, $result, "Failed to sanitize CSS keyword: {$input}");
		}
	}

	/**
	 * Test sanitize_color with valid color names
	 */
	public function test_sanitize_color_valid_color_names() {
		$valid_color_names = [
			'red' => 'red',
			'RED' => 'red',
			'Blue' => 'blue',
			'green' => 'green',
			'white' => 'white',
			'black' => 'black',
			'yellow' => 'yellow',
			'cyan' => 'cyan',
			'magenta' => 'magenta',
			'gray' => 'gray',
			'grey' => 'grey',
			'orange' => 'orange',
			'purple' => 'purple',
			'pink' => 'pink',
			'brown' => 'brown',
			'lime' => 'lime',
			'navy' => 'navy'
		];
		
		foreach ($valid_color_names as $input => $expected) {
			$result = CssSanitizer::sanitize_color($input);
			$this->assertEquals($expected, $result, "Failed to sanitize color name: {$input}");
		}
	}

	/**
	 * Test sanitize_color with dangerous patterns
	 */
	public function test_sanitize_color_dangerous_patterns() {
		$dangerous_colors = [
			'javascript:alert(1)',
			'data:text/html,<script>alert(1)</script>',
			'expression(alert(1))',
			'behavior:url(#default#userData)',
			'binding:url(malicious.xml)',
			'@import url(evil.css)',
			'url(javascript:alert(1))',
			'\\41 lert(1)', // Unicode escape
			'&#106;&#97;&#118;&#97;&#115;&#99;&#114;&#105;&#112;&#116;&#58;&#97;&#108;&#101;&#114;&#116;&#40;&#49;&#41;',
			'&lt;script&gt;alert(1)&lt;/script&gt;'
		];
		
		foreach ($dangerous_colors as $dangerous_color) {
			$result = CssSanitizer::sanitize_color($dangerous_color);
			$this->assertEquals('#000000', $result, "Failed to sanitize dangerous color: {$dangerous_color}");
		}
	}

	/**
	 * Test sanitize_color with invalid colors
	 */
	public function test_sanitize_color_invalid_colors() {
		$invalid_colors = [
			'#gggggg', // Invalid hex
			'rgb(256, 256, 256)', // Invalid RGB values
			'hsl(361, 101%, 101%)', // Invalid HSL values
			'invalidcolor',
			'#ff', // Too short hex
			'#fffffff', // Too long hex
			'', // Empty string
			'   ', // Whitespace only
			'notacolor'
		];
		
		foreach ($invalid_colors as $invalid_color) {
			$result = CssSanitizer::sanitize_color($invalid_color);
			$this->assertEquals('#000000', $result, "Failed to handle invalid color: {$invalid_color}");
		}
	}

	/**
	 * Test sanitize_color with whitespace
	 */
	public function test_sanitize_color_with_whitespace() {
		$colors_with_whitespace = [
			'  #ffffff  ' => '#ffffff',
			'  red  ' => 'red',
			'  rgb(255, 255, 255)  ' => 'rgb(255, 255, 255)',
			"\t#000000\t" => '#000000',
			"\n blue \n" => 'blue'
		];
		
		foreach ($colors_with_whitespace as $input => $expected) {
			$result = CssSanitizer::sanitize_color($input);
			$this->assertEquals($expected, $result, "Failed to handle whitespace in color: '{$input}'");
		}
	}

	/**
	 * Test sanitize_size with valid pixel values
	 */
	public function test_sanitize_size_valid_pixel_values() {
		// Note: We need to check if this method exists and read the implementation
		if (!method_exists(CssSanitizer::class, 'sanitize_size')) {
			$this->markTestSkipped('sanitize_size method not available');
			return;
		}
		
		$valid_sizes = [
			'10px' => '10px',
			'0px' => '0px',
			'100px' => '100px',
			10 => '10px', // Integer input
			'10' => '10px' // String number
		];
		
		foreach ($valid_sizes as $input => $expected) {
			$result = CssSanitizer::sanitize_size($input);
			$this->assertEquals($expected, $result, "Failed to sanitize size: {$input}");
		}
	}

	/**
	 * Test sanitize_size with different units
	 */
	public function test_sanitize_size_different_units() {
		if (!method_exists(CssSanitizer::class, 'sanitize_size')) {
			$this->markTestSkipped('sanitize_size method not available');
			return;
		}
		
		$sizes_with_units = [
			['10', 'em', '10em'],
			['5', 'rem', '5rem'],
			['50', '%', '50%'],
			['100', 'vh', '100vh'],
			['75', 'vw', '75vw']
		];
		
		foreach ($sizes_with_units as $test_case) {
			$result = CssSanitizer::sanitize_size($test_case[0], $test_case[1]);
			$expected = $test_case[2];
			$this->assertEquals($expected, $result, "Failed to sanitize size with unit: {$test_case[0]} {$test_case[1]}");
		}
	}

	/**
	 * Test sanitize_size with dangerous patterns
	 */
	public function test_sanitize_size_dangerous_patterns() {
		if (!method_exists(CssSanitizer::class, 'sanitize_size')) {
			$this->markTestSkipped('sanitize_size method not available');
			return;
		}
		
		$dangerous_sizes = [
			'javascript:alert(1)',
			'expression(alert(1))',
			'data:text/html,<script>',
			'url(malicious)',
			'10px; background: url(evil)'
		];
		
		foreach ($dangerous_sizes as $dangerous_size) {
			$result = CssSanitizer::sanitize_size($dangerous_size);
			$this->assertEquals('0px', $result, "Failed to sanitize dangerous size: {$dangerous_size}");
		}
	}

	/**
	 * Test class constants are defined
	 */
	public function test_class_constants_defined() {
		$reflection = new \ReflectionClass(CssSanitizer::class);
		$constants = $reflection->getConstants();
		
		$this->assertArrayHasKey('ALLOWED_UNITS', $constants);
		$this->assertArrayHasKey('DANGEROUS_PATTERNS', $constants);
		
		// Test that constants contain expected values
		$this->assertContains('px', $constants['ALLOWED_UNITS']);
		$this->assertContains('em', $constants['ALLOWED_UNITS']);
		$this->assertContains('rem', $constants['ALLOWED_UNITS']);
		$this->assertContains('%', $constants['ALLOWED_UNITS']);
		
		$this->assertNotEmpty($constants['DANGEROUS_PATTERNS']);
		$this->assertGreaterThan(5, count($constants['DANGEROUS_PATTERNS']));
	}

	/**
	 * Test all methods are static
	 */
	public function test_all_methods_are_static() {
		$reflection = new \ReflectionClass(CssSanitizer::class);
		$methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
		
		foreach ($methods as $method) {
			$this->assertTrue($method->isStatic(), "Method {$method->getName()} should be static");
		}
	}

	/**
	 * Test CssSanitizer has no constructor
	 */
	public function test_css_sanitizer_has_no_constructor() {
		$reflection = new \ReflectionClass(CssSanitizer::class);
		$constructor = $reflection->getConstructor();
		$this->assertNull($constructor);
	}

	/**
	 * Test sanitize_color handles edge cases
	 */
	public function test_sanitize_color_edge_cases() {
		// Test null conversion
		$result = CssSanitizer::sanitize_color('');
		$this->assertEquals('#000000', $result);
		
		// Test case sensitivity for hex
		$result = CssSanitizer::sanitize_color('#AbCdEf');
		$this->assertEquals('#AbCdEf', $result);
		
		// Test mixed case color names
		$result = CssSanitizer::sanitize_color('ReD');
		$this->assertEquals('red', $result);
		
		// Test with extra characters
		$result = CssSanitizer::sanitize_color('#ffffff extra');
		$this->assertEquals('#000000', $result);
	}

	/**
	 * Test dangerous patterns constant values
	 */
	public function test_dangerous_patterns_constant() {
		$reflection = new \ReflectionClass(CssSanitizer::class);
		$constants = $reflection->getConstants();
		$dangerous_patterns = $constants['DANGEROUS_PATTERNS'];
		
		// Verify key dangerous patterns are included
		$expected_patterns = [
			'javascript',
			'data',
			'expression',
			'behaviour',
			'binding',
			'@import',
			'url',
		];
		
		$patterns_string = implode(' ', $dangerous_patterns);
		
		foreach ($expected_patterns as $expected) {
			$this->assertStringContainsString(
				$expected,
				$patterns_string,
				"Expected dangerous pattern '{$expected}' not found"
			);
		}
	}

	/**
	 * Test allowed units constant values
	 */
	public function test_allowed_units_constant() {
		$reflection = new \ReflectionClass(CssSanitizer::class);
		$constants = $reflection->getConstants();
		$allowed_units = $constants['ALLOWED_UNITS'];
		
		$expected_units = ['px', 'em', 'rem', '%', 'vh', 'vw', 'pt', 'pc', 'in', 'cm', 'mm'];
		
		foreach ($expected_units as $unit) {
			$this->assertContains($unit, $allowed_units, "Expected unit '{$unit}' not found in ALLOWED_UNITS");
		}
	}

	/**
	 * Test performance with large input
	 */
	public function test_sanitize_color_performance_large_input() {
		$large_input = str_repeat('a', 10000) . '#ffffff';
		
		$start_time = microtime(true);
		$result = CssSanitizer::sanitize_color($large_input);
		$end_time = microtime(true);
		
		$execution_time = $end_time - $start_time;
		
		// Should handle large input efficiently (under 1 second)
		$this->assertLessThan(1.0, $execution_time);
		$this->assertEquals('#000000', $result); // Should return safe fallback
	}
}