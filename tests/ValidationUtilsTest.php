<?php

use NuclearEngagement\Utils\ValidationUtils;
use PHPUnit\Framework\TestCase;

class ValidationUtilsTest extends TestCase {

	public function setUp(): void {
		\WP_Mock::setUp();
	}

	public function tearDown(): void {
		\WP_Mock::tearDown();
	}

	/**
	 * @dataProvider intValidationProvider
	 */
	public function test_validate_int( $input, $min, $max, $expected ) {
		$result = ValidationUtils::validate_int( $input, $min, $max );
		$this->assertEquals( $expected, $result );
	}

	public function intValidationProvider(): array {
		return [
			// Valid cases
			[ 5, 0, 10, 5 ],
			[ '5', 0, 10, 5 ],
			[ 0, 0, 10, 0 ],
			[ 10, 0, 10, 10 ],
			[ null, 0, 10, null ],
			
			// Invalid cases
			[ -1, 0, 10, null ],
			[ 11, 0, 10, null ],
			[ 'abc', 0, 10, null ],
			[ 5.5, 0, 10, null ],
			[ array(), 0, 10, null ],
			[ true, 0, 10, null ],
			
			// Edge cases
			[ PHP_INT_MAX, 0, PHP_INT_MAX, PHP_INT_MAX ],
			[ PHP_INT_MIN, PHP_INT_MIN, 0, PHP_INT_MIN ],
			[ '0', -5, 5, 0 ],
			[ '', 0, 10, null ],
		];
	}

	/**
	 * @dataProvider stringValidationProvider
	 */
	public function test_validate_string( $input, $max_length, $allowed, $allow_html, $expected ) {
		\WP_Mock::userFunction( 'sanitize_text_field' )
			->andReturnUsing( function( $input ) {
				return trim( strip_tags( $input ) );
			} );

		\WP_Mock::userFunction( 'wp_kses_post' )
			->andReturnUsing( function( $input ) {
				return $input; // Simplified for testing
			} );

		$result = ValidationUtils::validate_string( $input, $max_length, $allowed, $allow_html );
		$this->assertEquals( $expected, $result );
	}

	public function stringValidationProvider(): array {
		return [
			// Valid cases
			[ 'hello', 255, array(), false, 'hello' ],
			[ 'test', 10, array( 'test', 'other' ), false, 'test' ],
			[ 123, 255, array(), false, '123' ],
			
			// Invalid cases
			[ array(), 255, array(), false, null ],
			[ 'toolongstring', 5, array(), false, null ],
			[ 'notallowed', 255, array( 'allowed' ), false, null ],
			
			// Edge cases
			[ '', 255, array(), false, '' ],
			[ '   ', 255, array(), false, '' ], // Trimmed
			[ 'a', 1, array(), false, 'a' ],
		];
	}

	/**
	 * @dataProvider arrayValidationProvider
	 */
	public function test_validate_array( $input, $max_items, $item_type, $options, $expected ) {
		\WP_Mock::userFunction( 'sanitize_text_field' )
			->andReturnUsing( function( $input ) {
				return trim( strip_tags( $input ) );
			} );

		$result = ValidationUtils::validate_array( $input, $max_items, $item_type, $options );
		$this->assertEquals( $expected, $result );
	}

	public function arrayValidationProvider(): array {
		return [
			// Valid cases
			[ array( 'a', 'b' ), 5, 'string', array(), array( 'a', 'b' ) ],
			[ array( 1, 2, 3 ), 5, 'int', array(), array( 1, 2, 3 ) ],
			[ array(), 5, 'string', array(), array() ],
			
			// Invalid cases
			[ 'notarray', 5, 'string', array(), null ],
			[ array( 1, 2, 3, 4, 5, 6 ), 5, 'string', array(), null ],
			[ array( 'invalid' ), 5, 'invalidtype', array(), null ],
			[ array( -1 ), 5, 'int', array( 'min' => 0 ), null ],
		];
	}

	public function test_validate_nonce() {
		// Use the stub system instead of WP_Mock for wp_verify_nonce since it's already defined
		$GLOBALS['test_verify_nonce'] = null; // Reset
		
		// Test valid nonce
		$GLOBALS['test_verify_nonce'] = 1;
		$this->assertTrue( ValidationUtils::validate_nonce( 'valid_nonce', 'test_action' ) );
		
		// Test invalid nonce  
		$GLOBALS['test_verify_nonce'] = false;
		$this->assertFalse( ValidationUtils::validate_nonce( 'invalid_nonce', 'test_action' ) );
		
		// Clean up
		unset($GLOBALS['test_verify_nonce']);
	}

	public function test_validate_capability() {
		\WP_Mock::userFunction( 'current_user_can' )
			->with( 'manage_options' )
			->once()
			->andReturn( true );

		\WP_Mock::userFunction( 'current_user_can' )
			->with( 'invalid_cap' )
			->once()
			->andReturn( false );

		$this->assertTrue( ValidationUtils::validate_capability( 'manage_options' ) );
		$this->assertFalse( ValidationUtils::validate_capability( 'invalid_cap' ) );
	}

	public function test_validate_ajax_request() {
		\WP_Mock::userFunction( 'wp_doing_ajax' )
			->once()
			->andReturn( true );

		\WP_Mock::userFunction( 'check_ajax_referer' )
			->with( 'test_action', 'nonce', false )
			->once()
			->andReturn( true );

		\WP_Mock::userFunction( 'current_user_can' )
			->with( 'manage_options' )
			->once()
			->andReturn( true );

		$this->assertTrue( ValidationUtils::validate_ajax_request( 'test_action' ) );
	}

	public function test_validate_ajax_request_not_ajax() {
		\WP_Mock::userFunction( 'wp_doing_ajax' )
			->once()
			->andReturn( false );

		$this->assertFalse( ValidationUtils::validate_ajax_request( 'test_action' ) );
	}

	public function test_sanitize_api_key() {
		\WP_Mock::userFunction( 'sanitize_text_field' )
			->with( 'test_key' )
			->once()
			->andReturn( 'test_key' );

		$result = ValidationUtils::sanitize_api_key( '  test_key  ' );
		$this->assertEquals( 'test_key', $result );
	}

	/**
	 * @dataProvider uuidProvider
	 */
	public function test_is_valid_uuid( $uuid, $expected ) {
		$result = ValidationUtils::is_valid_uuid( $uuid );
		$this->assertEquals( $expected, $result );
	}

	public function uuidProvider(): array {
		return [
			[ '123e4567-e89b-12d3-a456-426614174000', true ],
			[ '123E4567-E89B-12D3-A456-426614174000', true ],
			[ 'invalid-uuid', false ],
			[ '123e4567-e89b-12d3-a456', false ],
			[ '', false ],
			[ '123e4567-e89b-12d3-a456-426614174000-extra', false ],
		];
	}

	/**
	 * @dataProvider urlProvider
	 */
	public function test_validate_url( $url, $expected ) {
		$result = ValidationUtils::validate_url( $url );
		$this->assertEquals( $expected, $result );
	}

	public function urlProvider(): array {
		return [
			[ 'https://example.com', true ],
			[ 'http://example.com', true ],
			[ 'ftp://example.com', true ],
			[ 'invalid-url', false ],
			[ '', false ],
			[ 'javascript:alert(1)', false ],
		];
	}

	public function test_validate_email() {
		\WP_Mock::userFunction( 'is_email' )
			->with( 'test@example.com' )
			->once()
			->andReturn( 'test@example.com' );

		\WP_Mock::userFunction( 'is_email' )
			->with( 'invalid-email' )
			->once()
			->andReturn( false );

		$this->assertTrue( ValidationUtils::validate_email( 'test@example.com' ) );
		$this->assertFalse( ValidationUtils::validate_email( 'invalid-email' ) );
	}

	public function test_validate_post_id() {
		// Set up posts in the global array for testing
		$GLOBALS['wp_posts'][123] = (object) array( 'ID' => 123 );
		// 999 will return null (not exist)
		
		$this->assertEquals( 123, ValidationUtils::validate_post_id( 123 ) );
		$this->assertEquals( 123, ValidationUtils::validate_post_id( '123' ) );
		$this->assertNull( ValidationUtils::validate_post_id( 999 ) );
		$this->assertNull( ValidationUtils::validate_post_id( -1 ) );
		$this->assertNull( ValidationUtils::validate_post_id( 'invalid' ) );
		
		// Clean up
		unset($GLOBALS['wp_posts'][123]);
	}

	/**
	 * @dataProvider boolProvider
	 */
	public function test_validate_bool( $input, $expected ) {
		$result = ValidationUtils::validate_bool( $input );
		$this->assertEquals( $expected, $result );
	}

	public function boolProvider(): array {
		return [
			// Boolean inputs
			[ true, true ],
			[ false, false ],
			
			// String inputs
			[ 'true', true ],
			[ 'TRUE', true ],
			[ '1', true ],
			[ 'yes', true ],
			[ 'on', true ],
			[ 'false', false ],
			[ 'FALSE', false ],
			[ '0', false ],
			[ 'no', false ],
			[ 'off', false ],
			[ '', false ],
			
			// Numeric inputs
			[ 1, true ],
			[ 0, false ],
			[ 5, true ],
			[ -1, true ],
			[ 0.0, false ],
			[ 0.1, true ],
			
			// Invalid inputs
			[ array(), null ],
			[ 'maybe', null ],
			[ 'invalid', null ],
		];
	}

	public function test_validate_batch_success() {
		\WP_Mock::userFunction( 'sanitize_text_field' )
			->andReturnUsing( function( $input ) {
				return trim( strip_tags( $input ) );
			} );

		$inputs = array(
			'name'  => 'John',
			'age'   => 25,
			'admin' => true,
		);

		$rules = array(
			'name'  => array( 'type' => 'string', 'required' => true ),
			'age'   => array( 'type' => 'int', 'required' => true ),
			'admin' => array( 'type' => 'bool', 'required' => false ),
		);

		$result = ValidationUtils::validate_batch( $inputs, $rules );
		$this->assertEquals( $inputs, $result );
	}

	public function test_validate_batch_missing_required() {
		$inputs = array(
			'age' => 25,
		);

		$rules = array(
			'name' => array( 'type' => 'string', 'required' => true ),
			'age'  => array( 'type' => 'int', 'required' => true ),
		);

		$result = ValidationUtils::validate_batch( $inputs, $rules );
		$this->assertNull( $result );
	}

	public function test_validate_batch_invalid_type() {
		$inputs = array(
			'name' => 'John',
			'age'  => 'invalid',
		);

		$rules = array(
			'name' => array( 'type' => 'string', 'required' => true ),
			'age'  => array( 'type' => 'int', 'required' => true ),
		);

		$result = ValidationUtils::validate_batch( $inputs, $rules );
		$this->assertNull( $result );
	}

	public function test_validate_batch_unknown_type() {
		$inputs = array(
			'field' => 'value',
		);

		$rules = array(
			'field' => array( 'type' => 'unknown', 'required' => true ),
		);

		$result = ValidationUtils::validate_batch( $inputs, $rules );
		$this->assertNull( $result );
	}

	public function test_validate_batch_optional_field() {
		\WP_Mock::userFunction( 'sanitize_text_field' )
			->andReturnUsing( function( $input ) {
				return trim( strip_tags( $input ) );
			} );

		$inputs = array(
			'name' => 'John',
		);

		$rules = array(
			'name'     => array( 'type' => 'string', 'required' => true ),
			'optional' => array( 'type' => 'string', 'required' => false ),
		);

		$expected = array(
			'name'     => 'John',
			'optional' => null,
		);

		$result = ValidationUtils::validate_batch( $inputs, $rules );
		$this->assertEquals( $expected, $result );
	}

	public function test_string_validation_with_html_allowed() {
		\WP_Mock::userFunction( 'wp_kses_post' )
			->with( '<p>Hello</p>' )
			->once()
			->andReturn( '<p>Hello</p>' );

		$result = ValidationUtils::validate_string( '<p>Hello</p>', 255, array(), true );
		$this->assertEquals( '<p>Hello</p>', $result );
	}

	public function test_array_validation_with_options() {
		\WP_Mock::userFunction( 'sanitize_text_field' )
			->andReturnUsing( function( $input ) {
				return trim( strip_tags( $input ) );
			} );

		$input = array( 'hello', 'world' );
		$options = array(
			'max_length' => 10,
			'allowed' => array( 'hello', 'world', 'test' ),
		);

		$result = ValidationUtils::validate_array( $input, 10, 'string', $options );
		$this->assertEquals( $input, $result );
	}

	public function test_array_validation_fails_on_disallowed_value() {
		\WP_Mock::userFunction( 'sanitize_text_field' )
			->andReturnUsing( function( $input ) {
				return trim( strip_tags( $input ) );
			} );

		$input = array( 'hello', 'forbidden' );
		$options = array(
			'allowed' => array( 'hello', 'world' ),
		);

		$result = ValidationUtils::validate_array( $input, 10, 'string', $options );
		$this->assertNull( $result );
	}
}