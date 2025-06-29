<?php
/**
 * Tests for TokenManager class
 * 
 * @package NuclearEngagement\Tests
 */

namespace NuclearEngagement\Tests;

use PHPUnit\Framework\TestCase;
use Mockery;
use NuclearEngagement\Security\TokenManager;
use NuclearEngagement\Core\SettingsRepository;

class TokenManagerTest extends TestCase {

    private $mockSettingsRepository;
    private $tokenManager;

    protected function setUp(): void {
        parent::setUp();
        \WP_Mock::setUp();
        
        // Mock SettingsRepository
        $this->mockSettingsRepository = Mockery::mock(SettingsRepository::class);
        
        // Mock WordPress option functions for encryption key
        \WP_Mock::userFunction('get_option')
            ->with('nuclear_engagement_encryption_key')
            ->andReturn('dGVzdF9lbmNyeXB0aW9uX2tleV9mb3JfdGVzdGluZw=='); // base64 encoded test key

        $this->tokenManager = new TokenManager($this->mockSettingsRepository);
    }

    protected function tearDown(): void {
        \WP_Mock::tearDown();
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test secure token generation
     */
    public function test_generate_secure_token_returns_valid_token() {
        // Act
        $token = $this->tokenManager->generate_secure_token();

        // Assert
        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token)); // 32 bytes = 64 hex characters
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    /**
     * Test that each generated token is unique
     */
    public function test_generate_secure_token_produces_unique_tokens() {
        // Act
        $token1 = $this->tokenManager->generate_secure_token();
        $token2 = $this->tokenManager->generate_secure_token();
        $token3 = $this->tokenManager->generate_secure_token();

        // Assert
        $this->assertNotEquals($token1, $token2);
        $this->assertNotEquals($token2, $token3);
        $this->assertNotEquals($token1, $token3);
    }

    /**
     * Test token hashing functionality
     */
    public function test_hash_token_returns_consistent_hash() {
        // Arrange
        $token = 'test_token_12345';

        // Act
        $hash1 = $this->tokenManager->hash_token($token);
        $hash2 = $this->tokenManager->hash_token($token);

        // Assert
        $this->assertIsString($hash1);
        $this->assertEquals($hash1, $hash2); // Same token should produce same hash
        $this->assertEquals(64, strlen($hash1)); // SHA256 produces 64 character hex string
    }

    /**
     * Test different tokens produce different hashes
     */
    public function test_hash_token_produces_different_hashes_for_different_tokens() {
        // Arrange
        $token1 = 'test_token_1';
        $token2 = 'test_token_2';

        // Act
        $hash1 = $this->tokenManager->hash_token($token1);
        $hash2 = $this->tokenManager->hash_token($token2);

        // Assert
        $this->assertNotEquals($hash1, $hash2);
    }

    /**
     * Test token verification with correct token
     */
    public function test_verify_token_returns_true_for_correct_token() {
        // Arrange
        $token = 'correct_token_123';
        $storedHash = $this->tokenManager->hash_token($token);

        // Act
        $result = $this->tokenManager->verify_token($token, $storedHash);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test token verification with incorrect token
     */
    public function test_verify_token_returns_false_for_incorrect_token() {
        // Arrange
        $correctToken = 'correct_token_123';
        $incorrectToken = 'incorrect_token_456';
        $storedHash = $this->tokenManager->hash_token($correctToken);

        // Act
        $result = $this->tokenManager->verify_token($incorrectToken, $storedHash);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test sensitive data encryption
     */
    public function test_encrypt_sensitive_data_returns_encrypted_string() {
        // Arrange
        $sensitiveData = 'This is sensitive information that needs encryption';

        // Act
        $encrypted = $this->tokenManager->encrypt_sensitive_data($sensitiveData);

        // Assert
        $this->assertIsString($encrypted);
        $this->assertNotEquals($sensitiveData, $encrypted);
        $this->assertNotEmpty($encrypted);
        
        // Verify it's base64 encoded
        $decoded = base64_decode($encrypted, true);
        $this->assertNotFalse($decoded);
    }

    /**
     * Test sensitive data encryption produces different output each time (due to random nonce)
     */
    public function test_encrypt_sensitive_data_produces_different_output_each_time() {
        // Arrange
        $sensitiveData = 'Same data for encryption';

        // Act
        $encrypted1 = $this->tokenManager->encrypt_sensitive_data($sensitiveData);
        $encrypted2 = $this->tokenManager->encrypt_sensitive_data($sensitiveData);

        // Assert
        $this->assertNotEquals($encrypted1, $encrypted2);
    }

    /**
     * Test sensitive data decryption
     */
    public function test_decrypt_sensitive_data_returns_original_data() {
        // Arrange
        $originalData = 'Original sensitive data for testing decryption';
        $encrypted = $this->tokenManager->encrypt_sensitive_data($originalData);

        // Act
        $decrypted = $this->tokenManager->decrypt_sensitive_data($encrypted);

        // Assert
        $this->assertEquals($originalData, $decrypted);
    }

    /**
     * Test decryption with invalid data returns null
     */
    public function test_decrypt_sensitive_data_returns_null_for_invalid_data() {
        // Arrange
        $invalidEncryptedData = 'invalid_base64_data_that_cannot_be_decrypted';

        // Act
        $result = $this->tokenManager->decrypt_sensitive_data($invalidEncryptedData);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test decryption with corrupted base64 data
     */
    public function test_decrypt_sensitive_data_returns_null_for_corrupted_base64() {
        // Arrange
        $corruptedData = base64_encode('corrupted_data_that_is_not_properly_encrypted');

        // Act
        $result = $this->tokenManager->decrypt_sensitive_data($corruptedData);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test encryption/decryption with empty string
     */
    public function test_encrypt_decrypt_empty_string() {
        // Arrange
        $emptyData = '';

        // Act
        $encrypted = $this->tokenManager->encrypt_sensitive_data($emptyData);
        $decrypted = $this->tokenManager->decrypt_sensitive_data($encrypted);

        // Assert
        $this->assertEquals($emptyData, $decrypted);
    }

    /**
     * Test encryption/decryption with special characters
     */
    public function test_encrypt_decrypt_special_characters() {
        // Arrange
        $specialData = 'Special chars: àáâãäåæçèéêë ñòóôõö ùúûüý 中文 🔐💻';

        // Act
        $encrypted = $this->tokenManager->encrypt_sensitive_data($specialData);
        $decrypted = $this->tokenManager->decrypt_sensitive_data($encrypted);

        // Assert
        $this->assertEquals($specialData, $decrypted);
    }

    /**
     * Test encryption/decryption with JSON data
     */
    public function test_encrypt_decrypt_json_data() {
        // Arrange
        $jsonData = json_encode([
            'user_id' => 123,
            'api_key' => 'secret_api_key_12345',
            'permissions' => ['read', 'write', 'admin'],
            'expires' => '2024-12-31T23:59:59Z'
        ]);

        // Act
        $encrypted = $this->tokenManager->encrypt_sensitive_data($jsonData);
        $decrypted = $this->tokenManager->decrypt_sensitive_data($encrypted);

        // Assert
        $this->assertEquals($jsonData, $decrypted);
        
        // Verify the decrypted data is still valid JSON
        $decodedArray = json_decode($decrypted, true);
        $this->assertIsArray($decodedArray);
        $this->assertEquals(123, $decodedArray['user_id']);
    }

    /**
     * Test encryption key generation when no key exists
     */
    public function test_encryption_key_generation_when_none_exists() {
        // Arrange
        $mockSettingsRepository = Mockery::mock(SettingsRepository::class);

        // Mock get_option to return empty (no existing key)
        \WP_Mock::userFunction('get_option')
            ->with('nuclear_engagement_encryption_key')
            ->andReturn('');

        // Mock update_option to be called with new key
        \WP_Mock::userFunction('update_option')
            ->once()
            ->with('nuclear_engagement_encryption_key', Mockery::type('string'), false)
            ->andReturn(true);

        // Act
        $tokenManager = new TokenManager($mockSettingsRepository);

        // Assert - Should complete without errors
        $this->assertInstanceOf(TokenManager::class, $tokenManager);
    }

    /**
     * Test encryption with large data
     */
    public function test_encrypt_decrypt_large_data() {
        // Arrange
        $largeData = str_repeat('Large data string for testing encryption performance. ', 1000);

        // Act
        $startTime = microtime(true);
        $encrypted = $this->tokenManager->encrypt_sensitive_data($largeData);
        $decrypted = $this->tokenManager->decrypt_sensitive_data($encrypted);
        $endTime = microtime(true);

        // Assert
        $this->assertEquals($largeData, $decrypted);
        $this->assertLessThan(1.0, $endTime - $startTime, 'Encryption/decryption should complete within 1 second');
    }

    /**
     * Test that decryption fails with wrong key
     */
    public function test_decryption_fails_with_different_key() {
        // Arrange
        $data = 'Test data for key validation';
        
        // Encrypt with current instance
        $encrypted = $this->tokenManager->encrypt_sensitive_data($data);

        // Create new instance with different key
        \WP_Mock::userFunction('get_option')
            ->with('nuclear_engagement_encryption_key')
            ->andReturn('ZGlmZmVyZW50X2tleV9mb3JfdGVzdGluZw=='); // Different base64 encoded key

        $differentKeyTokenManager = new TokenManager($this->mockSettingsRepository);

        // Act
        $decrypted = $differentKeyTokenManager->decrypt_sensitive_data($encrypted);

        // Assert
        $this->assertNull($decrypted, 'Decryption should fail with different key');
    }

    /**
     * Test token verification timing attack resistance
     */
    public function test_verify_token_timing_attack_resistance() {
        // Arrange
        $token = 'timing_test_token';
        $correctHash = $this->tokenManager->hash_token($token);
        $incorrectHash = 'incorrect_hash_of_same_length_for_timing_test_security_check';

        // Act - Measure timing for correct verification
        $startCorrect = microtime(true);
        $this->tokenManager->verify_token($token, $correctHash);
        $timeCorrect = microtime(true) - $startCorrect;

        // Act - Measure timing for incorrect verification
        $startIncorrect = microtime(true);
        $this->tokenManager->verify_token($token, $incorrectHash);
        $timeIncorrect = microtime(true) - $startIncorrect;

        // Assert - Timing should be similar (within reasonable variance)
        $timeDifference = abs($timeCorrect - $timeIncorrect);
        $this->assertLessThan(0.001, $timeDifference, 'Verification timing should be similar to prevent timing attacks');
    }

    /**
     * Test that hash_token uses HMAC for security
     */
    public function test_hash_token_uses_hmac() {
        // Arrange
        $token = 'test_hmac_token';

        // Act
        $hash = $this->tokenManager->hash_token($token);

        // Assert
        $this->assertIsString($hash);
        $this->assertEquals(64, strlen($hash)); // HMAC-SHA256 produces 64 char hex string
        
        // Verify it's different from a simple hash
        $simpleHash = hash('sha256', $token);
        $this->assertNotEquals($simpleHash, $hash, 'Should use HMAC, not simple hash');
    }
}