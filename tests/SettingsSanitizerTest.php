<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Core\SettingsSanitizer;

if (!function_exists('sanitize_text_field')) {
	function sanitize_text_field($text) {
		return trim(strip_tags($text));
	}
}
if (!function_exists('rest_sanitize_boolean')) {
	function rest_sanitize_boolean($value) {
		return filter_var($value, FILTER_VALIDATE_BOOLEAN);
	}
}

class SettingsSanitizerTest extends TestCase {
	public function test_sanitize_settings_handles_various_inputs() {
		$input = [
			'api_key' => ' abc ',
			'font_size' => '15',
			'show_attribution' => '1',
			'custom' => ' <b>foo</b> ',
			'arr' => [ ' <i>bar</i> ', 8, [' <u>baz</u> ', false] ],
			5 => 'ignored',
		];
		$expected = [
			'api_key' => 'abc',
			'font_size' => 15,
			'show_attribution' => true,
			'custom' => 'foo',
			'arr' => ['bar', 8, ['baz', false]],
		];
		$this->assertSame($expected, SettingsSanitizer::sanitize_settings($input));
		$result = SettingsSanitizer::sanitize_settings($input);
		$this->assertArrayNotHasKey(5, $result);
	}

	public function test_sanitize_setting_casts_types() {
		$this->assertSame(10, SettingsSanitizer::sanitize_setting('font_size', '10'));
		$this->assertTrue(SettingsSanitizer::sanitize_setting('connected', '1'));
		$this->assertFalse(SettingsSanitizer::sanitize_setting('custom', false));
		$this->assertSame(3, SettingsSanitizer::sanitize_setting('custom', '3'));
		$this->assertSame(3.14, SettingsSanitizer::sanitize_setting('custom', 3.14));
	}

	public function test_sanitize_array_recursively_sanitizes_strings() {
		$ref = new \ReflectionMethod(SettingsSanitizer::class, 'sanitize_array');
		$ref->setAccessible(true);
		$input = [
			'foo' => ' <strong>bar</strong> ',
			'nested' => [' <em>baz</em> ', [' qux ']],
		];
		$expected = [
			'foo' => 'bar',
			'nested' => ['baz', ['qux']],
		];
		$this->assertSame($expected, $ref->invoke(null, $input));
	}
}
