<?php

use NuclearEngagement\Utils\SecurityUtils;
use PHPUnit\Framework\TestCase;

class SecurityUtilsTest extends TestCase {

	public function setUp(): void {
		\WP_Mock::setUp();
	}

	public function tearDown(): void {
		\WP_Mock::tearDown();
	}

	public function test_generate_secure_password() {
		\WP_Mock::userFunction( 'wp_generate_password' )
			->with( 32, true, true )
			->once()
			->andReturn( 'secure_password_123!' );

		$result = SecurityUtils::generate_secure_password();
		$this->assertEquals( 'secure_password_123!', $result );
	}

	public function test_generate_secure_password_custom_length() {
		\WP_Mock::userFunction( 'wp_generate_password' )
			->with( 16, false, true )
			->once()
			->andReturn( 'shortpassword123' );

		$result = SecurityUtils::generate_secure_password( 16, false );
		$this->assertEquals( 'shortpassword123', $result );
	}

	public function test_generate_uuid() {
		$uuid = '123e4567-e89b-12d3-a456-426614174000';
		\WP_Mock::userFunction( 'wp_generate_uuid4' )
			->once()
			->andReturn( $uuid );

		$result = SecurityUtils::generate_uuid();
		$this->assertEquals( $uuid, $result );
	}

	public function test_hash_password() {
		$password = 'test_password';
		$hash = '$P$BZlGAJHJDjAhDJ2Eyx1Bw9p4CzWcMa.';

		\WP_Mock::userFunction( 'wp_hash_password' )
			->with( $password )
			->once()
			->andReturn( $hash );

		$result = SecurityUtils::hash_password( $password );
		$this->assertEquals( $hash, $result );
	}

	public function test_verify_password_success() {
		$password = 'test_password';
		$hash = '$P$BZlGAJHJDjAhDJ2Eyx1Bw9p4CzWcMa.';

		\WP_Mock::userFunction( 'wp_check_password' )
			->with( $password, $hash )
			->once()
			->andReturn( true );

		$result = SecurityUtils::verify_password( $password, $hash );
		$this->assertTrue( $result );
	}

	public function test_verify_password_failure() {
		$password = 'wrong_password';
		$hash = '$P$BZlGAJHJDjAhDJ2Eyx1Bw9p4CzWcMa.';

		\WP_Mock::userFunction( 'wp_check_password' )
			->with( $password, $hash )
			->once()
			->andReturn( false );

		$result = SecurityUtils::verify_password( $password, $hash );
		$this->assertFalse( $result );
	}

	public function test_generate_nonce() {
		$action = 'test_action';
		$nonce = 'abc123def456';

		\WP_Mock::userFunction( 'wp_create_nonce' )
			->with( $action )
			->once()
			->andReturn( $nonce );

		$result = SecurityUtils::generate_nonce( $action );
		$this->assertEquals( $nonce, $result );
	}

	public function test_verify_nonce_success() {
		$nonce = 'abc123def456';
		$action = 'test_action';

		\WP_Mock::userFunction( 'wp_verify_nonce' )
			->with( $nonce, $action )
			->once()
			->andReturn( 1 );

		$result = SecurityUtils::verify_nonce( $nonce, $action );
		$this->assertTrue( $result );
	}

	public function test_verify_nonce_failure() {
		$nonce = 'invalid_nonce';
		$action = 'test_action';

		\WP_Mock::userFunction( 'wp_verify_nonce' )
			->with( $nonce, $action )
			->once()
			->andReturn( false );

		$result = SecurityUtils::verify_nonce( $nonce, $action );
		$this->assertFalse( $result );
	}

	/**
	 * @dataProvider sanitizeInputProvider
	 */
	public function test_sanitize_input( $input, $expected ) {
		\WP_Mock::userFunction( 'sanitize_text_field' )
			->andReturnUsing( function( $input ) {
				return trim( strip_tags( $input ) );
			} );

		$result = SecurityUtils::sanitize_input( $input );
		$this->assertEquals( $expected, $result );
	}

	public function sanitizeInputProvider(): array {
		return [
			// String inputs
			[ 'hello', 'hello' ],
			[ '<script>alert(1)</script>', 'alert(1)' ],
			[ 'normal text', 'normal text' ],
			
			// Array inputs
			[ array( 'hello', 'world' ), array( 'hello', 'world' ) ],
			[ array( '<script>', 'safe' ), array( '', 'safe' ) ],
			[ array( 'nested' => array( '<b>bold</b>' ) ), array( 'nested' => array( 'bold' ) ) ],
			
			// Other types
			[ 123, 123 ],
			[ true, true ],
			[ null, null ],
		];
	}

	public function test_rate_limit_check_first_request() {
		$key = 'test_user_123';
		$transient_key = 'nuclen_rate_limit_' . md5( $key );

		\WP_Mock::userFunction( 'get_transient' )
			->with( $transient_key )
			->once()
			->andReturn( false );

		\WP_Mock::userFunction( 'set_transient' )
			->with( $transient_key, 1, 300 )
			->once()
			->andReturn( true );

		$result = SecurityUtils::rate_limit_check( $key );
		$this->assertTrue( $result );
	}

	public function test_rate_limit_check_within_limit() {
		$key = 'test_user_123';
		$transient_key = 'nuclen_rate_limit_' . md5( $key );

		\WP_Mock::userFunction( 'get_transient' )
			->with( $transient_key )
			->once()
			->andReturn( 5 );

		\WP_Mock::userFunction( 'set_transient' )
			->with( $transient_key, 6, 300 )
			->once()
			->andReturn( true );

		$result = SecurityUtils::rate_limit_check( $key, 10, 300 );
		$this->assertTrue( $result );
	}

	public function test_rate_limit_check_at_limit() {
		$key = 'test_user_123';
		$transient_key = 'nuclen_rate_limit_' . md5( $key );

		\WP_Mock::userFunction( 'get_transient' )
			->with( $transient_key )
			->once()
			->andReturn( 10 );

		$result = SecurityUtils::rate_limit_check( $key, 10, 300 );
		$this->assertFalse( $result );
	}

	public function test_rate_limit_check_exceeded() {
		$key = 'test_user_123';
		$transient_key = 'nuclen_rate_limit_' . md5( $key );

		\WP_Mock::userFunction( 'get_transient' )
			->with( $transient_key )
			->once()
			->andReturn( 15 );

		$result = SecurityUtils::rate_limit_check( $key, 10, 300 );
		$this->assertFalse( $result );
	}

	public function test_rate_limit_check_custom_parameters() {
		$key = 'custom_user_456';
		$limit = 5;
		$window = 600;
		$transient_key = 'nuclen_rate_limit_' . md5( $key );

		\WP_Mock::userFunction( 'get_transient' )
			->with( $transient_key )
			->once()
			->andReturn( 3 );

		\WP_Mock::userFunction( 'set_transient' )
			->with( $transient_key, 4, $window )
			->once()
			->andReturn( true );

		$result = SecurityUtils::rate_limit_check( $key, $limit, $window );
		$this->assertTrue( $result );
	}

	public function test_sanitize_input_recursive_array() {
		\WP_Mock::userFunction( 'sanitize_text_field' )
			->andReturnUsing( function( $input ) {
				return trim( strip_tags( $input ) );
			} );

		$input = array(
			'level1' => array(
				'level2' => array(
					'level3' => '<script>alert("deep")</script>',
					'safe'   => 'normal text',
				),
			),
			'simple' => '<b>bold</b>',
		);

		$expected = array(
			'level1' => array(
				'level2' => array(
					'level3' => 'alert("deep")',
					'safe'   => 'normal text',
				),
			),
			'simple' => 'bold',
		);

		$result = SecurityUtils::sanitize_input( $input );
		$this->assertEquals( $expected, $result );
	}

	public function test_verify_nonce_returns_boolean() {
		\WP_Mock::userFunction( 'wp_verify_nonce' )
			->with( 'test_nonce', 'test_action' )
			->once()
			->andReturn( 2 ); // WordPress can return 1 or 2 for valid nonces

		$result = SecurityUtils::verify_nonce( 'test_nonce', 'test_action' );
		$this->assertTrue( $result );
		$this->assertIsBool( $result );
	}

	public function test_sanitize_input_preserves_non_string_types() {
		$input = array(
			'string' => 'text',
			'int'    => 42,
			'float'  => 3.14,
			'bool'   => true,
			'null'   => null,
			'object' => new stdClass(),
		);

		\WP_Mock::userFunction( 'sanitize_text_field' )
			->with( 'text' )
			->once()
			->andReturn( 'text' );

		$result = SecurityUtils::sanitize_input( $input );

		$this->assertEquals( 'text', $result['string'] );
		$this->assertEquals( 42, $result['int'] );
		$this->assertEquals( 3.14, $result['float'] );
		$this->assertEquals( true, $result['bool'] );
		$this->assertEquals( null, $result['null'] );
		$this->assertInstanceOf( 'stdClass', $result['object'] );
	}

	public function test_rate_limit_edge_cases() {
		// Test with empty key
		$empty_key = '';
		$transient_key = 'nuclen_rate_limit_' . md5( $empty_key );

		\WP_Mock::userFunction( 'get_transient' )
			->with( $transient_key )
			->once()
			->andReturn( false );

		\WP_Mock::userFunction( 'set_transient' )
			->with( $transient_key, 1, 300 )
			->once()
			->andReturn( true );

		$result = SecurityUtils::rate_limit_check( $empty_key );
		$this->assertTrue( $result );
	}

	public function test_rate_limit_key_hashing() {
		$key1 = 'user@example.com';
		$key2 = 'user@example.com'; // Same key
		$key3 = 'different@example.com';

		$hash1 = md5( $key1 );
		$hash2 = md5( $key2 );
		$hash3 = md5( $key3 );

		$this->assertEquals( $hash1, $hash2 );
		$this->assertNotEquals( $hash1, $hash3 );
	}
}