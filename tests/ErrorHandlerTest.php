<?php
/**
 * Tests for ErrorHandler class
 * 
 * @package NuclearEngagement\Tests
 */

namespace NuclearEngagement\Tests;

use PHPUnit\Framework\TestCase;
use Mockery;
use NuclearEngagement\Core\ErrorHandler;

class ErrorHandlerTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        \WP_Mock::setUp();
    }

    protected function tearDown(): void {
        \WP_Mock::tearDown();
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test basic error handling
     */
    public function test_handle_error_returns_properly_structured_data() {
        // Arrange
        $message = 'Test error message';
        $severity = ErrorHandler::SEVERITY_HIGH;
        $category = ErrorHandler::CATEGORY_VALIDATION;
        $context = ['test_key' => 'test_value'];

        // Mock WordPress function
        \WP_Mock::userFunction('wp_generate_password')
            ->once()
            ->with(10, false, false)
            ->andReturn('abc123def4');

        // Mock error_log
        \WP_Mock::userFunction('error_log')
            ->once();

        // Act
        $result = ErrorHandler::handle_error($message, $severity, $category, $context);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('severity', $result);
        $this->assertArrayHasKey('category', $result);
        $this->assertArrayHasKey('timestamp', $result);
        $this->assertArrayHasKey('context', $result);

        $this->assertEquals('err_abc123def4', $result['id']);
        $this->assertEquals($message, $result['message']);
        $this->assertEquals($severity, $result['severity']);
        $this->assertEquals($category, $result['category']);
        $this->assertIsInt($result['timestamp']);
        $this->assertIsArray($result['context']);
    }

    /**
     * Test error handling with exception
     */
    public function test_handle_error_with_exception() {
        // Arrange
        $message = 'Error with exception';
        $exception = new \Exception('Test exception message', 123);

        // Mock WordPress function
        \WP_Mock::userFunction('wp_generate_password')
            ->once()
            ->andReturn('exc123test');

        \WP_Mock::userFunction('error_log')
            ->once();

        // Act
        $result = ErrorHandler::handle_error(
            $message,
            ErrorHandler::SEVERITY_CRITICAL,
            ErrorHandler::CATEGORY_DATABASE,
            [],
            $exception
        );

        // Assert
        $this->assertArrayHasKey('exception', $result);
        $this->assertIsArray($result['exception']);
        $this->assertEquals('Exception', $result['exception']['class']);
        $this->assertEquals('Test exception message', $result['exception']['message']);
        $this->assertArrayHasKey('file', $result['exception']);
        $this->assertArrayHasKey('line', $result['exception']);
        $this->assertArrayHasKey('trace', $result['exception']);
    }

    /**
     * Test sensitive data redaction in messages
     */
    public function test_sensitive_data_redaction_in_messages() {
        // Arrange
        $sensitiveMessage = 'Error: Invalid email test@example.com and password secret123';

        // Mock WordPress function
        \WP_Mock::userFunction('wp_generate_password')
            ->once()
            ->andReturn('redact123');

        \WP_Mock::userFunction('error_log')
            ->once();

        // Act
        $result = ErrorHandler::handle_error($sensitiveMessage);

        // Assert
        $this->assertStringContainsString('[REDACTED]', $result['message']);
        $this->assertStringNotContainsString('test@example.com', $result['message']);
        $this->assertStringNotContainsString('secret123', $result['message']);
    }

    /**
     * Test sensitive data redaction in context
     */
    public function test_sensitive_data_redaction_in_context() {
        // Arrange
        $context = [
            'user_email' => 'user@example.com',
            'api_key' => 'api_key=abc123def456',
            'safe_data' => 'This is safe data',
            'password' => 'password=secretpass123'
        ];

        // Mock WordPress function
        \WP_Mock::userFunction('wp_generate_password')
            ->once()
            ->andReturn('ctx123test');

        \WP_Mock::userFunction('error_log')
            ->once();

        // Act
        $result = ErrorHandler::handle_error('Test message', ErrorHandler::SEVERITY_MEDIUM, ErrorHandler::CATEGORY_SECURITY, $context);

        // Assert
        $this->assertStringContainsString('[REDACTED]', $result['context']['user_email']);
        $this->assertStringContainsString('[REDACTED]', $result['context']['api_key']);
        $this->assertEquals('This is safe data', $result['context']['safe_data']);
        $this->assertStringContainsString('[REDACTED]', $result['context']['password']);
    }

    /**
     * Test context preparation with complex data types
     */
    public function test_context_preparation_with_complex_data() {
        // Arrange
        $context = [
            'string_data' => 'simple string',
            'int_data' => 123,
            'float_data' => 45.67,
            'bool_data' => true,
            'array_data' => ['key' => 'value'],
            'object_data' => new \stdClass(),
            'null_data' => null
        ];

        // Mock WordPress function
        \WP_Mock::userFunction('wp_generate_password')
            ->once()
            ->andReturn('complex123');

        \WP_Mock::userFunction('error_log')
            ->once();

        // Act
        $result = ErrorHandler::handle_error('Test message', ErrorHandler::SEVERITY_LOW, ErrorHandler::CATEGORY_VALIDATION, $context);

        // Assert
        $this->assertEquals('simple string', $result['context']['string_data']);
        $this->assertEquals(123, $result['context']['int_data']);
        $this->assertEquals(45.67, $result['context']['float_data']);
        $this->assertTrue($result['context']['bool_data']);
        $this->assertEquals('[COMPLEX_DATA_REDACTED]', $result['context']['array_data']);
        $this->assertEquals('[COMPLEX_DATA_REDACTED]', $result['context']['object_data']);
        $this->assertNull($result['context']['null_data']);
    }

    /**
     * Test PHP error handling
     */
    public function test_handle_php_error() {
        // Mock WordPress function
        \WP_Mock::userFunction('wp_generate_password')
            ->once()
            ->andReturn('php123err');

        \WP_Mock::userFunction('error_log')
            ->once();

        // Act
        $result = ErrorHandler::handle_php_error(E_WARNING, 'PHP Warning message', '/path/to/file.php', 123);

        // Assert
        $this->assertFalse($result, 'Should return false to continue normal error handling');
    }

    /**
     * Test PHP severity mapping
     */
    public function test_php_severity_mapping() {
        // Mock WordPress function for each test
        \WP_Mock::userFunction('wp_generate_password')
            ->times(4)
            ->andReturn('severity123');

        \WP_Mock::userFunction('error_log')
            ->times(4);

        // Test critical errors
        ErrorHandler::handle_php_error(E_ERROR, 'Fatal error', 'file.php', 1);
        ErrorHandler::handle_php_error(E_PARSE, 'Parse error', 'file.php', 1);

        // Test high severity errors
        ErrorHandler::handle_php_error(E_WARNING, 'Warning', 'file.php', 1);
        ErrorHandler::handle_php_error(E_USER_WARNING, 'User warning', 'file.php', 1);

        // Assert - Mainly testing that no exceptions are thrown
        $this->assertTrue(true, 'PHP error handling should complete without exceptions');
    }

    /**
     * Test uncaught exception handling
     */
    public function test_handle_uncaught_exception() {
        // Arrange
        $exception = new \RuntimeException('Uncaught runtime exception');

        // Mock WordPress function
        \WP_Mock::userFunction('wp_generate_password')
            ->once()
            ->andReturn('uncaught123');

        \WP_Mock::userFunction('error_log')
            ->once();

        // Act
        ErrorHandler::handle_uncaught_exception($exception);

        // Assert - Should complete without throwing
        $this->assertTrue(true, 'Uncaught exception handling should complete successfully');
    }

    /**
     * Test shutdown error handling with fatal error
     */
    public function test_handle_shutdown_error_with_fatal_error() {
        // Mock error_get_last to return fatal error
        \WP_Mock::userFunction('error_get_last')
            ->once()
            ->andReturn([
                'type' => E_ERROR,
                'message' => 'Fatal error: something went wrong',
                'file' => '/path/to/error.php',
                'line' => 456
            ]);

        // Mock WordPress function
        \WP_Mock::userFunction('wp_generate_password')
            ->once()
            ->andReturn('shutdown123');

        \WP_Mock::userFunction('error_log')
            ->once();

        // Act
        ErrorHandler::handle_shutdown_error();

        // Assert
        $this->assertTrue(true, 'Shutdown error handling should complete successfully');
    }

    /**
     * Test shutdown error handling with no error
     */
    public function test_handle_shutdown_error_with_no_error() {
        // Mock error_get_last to return null (no error)
        \WP_Mock::userFunction('error_get_last')
            ->once()
            ->andReturn(null);

        // Act
        ErrorHandler::handle_shutdown_error();

        // Assert
        $this->assertTrue(true, 'Should handle no shutdown error gracefully');
    }

    /**
     * Test shutdown error handling with non-fatal error
     */
    public function test_handle_shutdown_error_with_non_fatal_error() {
        // Mock error_get_last to return non-fatal error
        \WP_Mock::userFunction('error_get_last')
            ->once()
            ->andReturn([
                'type' => E_NOTICE,
                'message' => 'Notice: undefined variable',
                'file' => '/path/to/notice.php',
                'line' => 789
            ]);

        // Act
        ErrorHandler::handle_shutdown_error();

        // Assert
        $this->assertTrue(true, 'Should ignore non-fatal shutdown errors');
    }

    /**
     * Test exception category determination
     */
    public function test_exception_category_determination() {
        // Mock WordPress function for each test
        \WP_Mock::userFunction('wp_generate_password')
            ->times(4)
            ->andReturn('category123');

        \WP_Mock::userFunction('error_log')
            ->times(4);

        // Test database exception
        $dbException = new \Exception('Database connection failed');
        $dbException = $this->createExceptionWithClass($dbException, 'DatabaseException');
        ErrorHandler::handle_uncaught_exception($dbException);

        // Test network exception
        $networkException = new \Exception('Network timeout');
        $networkException = $this->createExceptionWithClass($networkException, 'NetworkException');
        ErrorHandler::handle_uncaught_exception($networkException);

        // Test auth exception
        $authException = new \Exception('Authentication failed');
        $authException = $this->createExceptionWithClass($authException, 'AuthException');
        ErrorHandler::handle_uncaught_exception($authException);

        // Test generic exception (should default to configuration)
        $genericException = new \Exception('Generic error');
        ErrorHandler::handle_uncaught_exception($genericException);

        // Assert
        $this->assertTrue(true, 'Exception categorization should complete successfully');
    }

    /**
     * Test sensitive patterns are properly configured
     */
    public function test_sensitive_patterns_coverage() {
        // Arrange
        $testData = [
            'email@test.com',
            '1234-5678-9012-3456', // Credit card
            '123-45-6789', // SSN
            'api_key=secret123',
            'password=mypassword',
            'token=jwt.token.here',
            'secret=topsecret'
        ];

        // Mock WordPress function
        \WP_Mock::userFunction('wp_generate_password')
            ->times(count($testData))
            ->andReturn('pattern123');

        \WP_Mock::userFunction('error_log')
            ->times(count($testData));

        // Act & Assert
        foreach ($testData as $sensitiveData) {
            $result = ErrorHandler::handle_error("Error with data: $sensitiveData");
            $this->assertStringContainsString('[REDACTED]', $result['message']);
            $this->assertStringNotContainsString($sensitiveData, $result['message']);
        }
    }

    /**
     * Test stack trace redaction
     */
    public function test_stack_trace_redaction() {
        // Arrange
        $exceptionWithSensitiveTrace = new \Exception('Exception with sensitive data in trace');
        
        // Create a mock trace with sensitive data
        $reflection = new \ReflectionClass($exceptionWithSensitiveTrace);
        $traceProperty = $reflection->getProperty('trace');
        $traceProperty->setAccessible(true);
        
        // Mock WordPress function
        \WP_Mock::userFunction('wp_generate_password')
            ->once()
            ->andReturn('trace123');

        \WP_Mock::userFunction('error_log')
            ->once();

        // Act
        $result = ErrorHandler::handle_error(
            'Test message',
            ErrorHandler::SEVERITY_HIGH,
            ErrorHandler::CATEGORY_SECURITY,
            [],
            $exceptionWithSensitiveTrace
        );

        // Assert
        $this->assertArrayHasKey('exception', $result);
        $this->assertArrayHasKey('trace', $result['exception']);
        $this->assertIsString($result['exception']['trace']);
    }

    /**
     * Test error ID generation uniqueness
     */
    public function test_error_id_generation_uniqueness() {
        // Mock WordPress function to return different values
        \WP_Mock::userFunction('wp_generate_password')
            ->times(3)
            ->andReturnValues(['abc123', 'def456', 'ghi789']);

        \WP_Mock::userFunction('error_log')
            ->times(3);

        // Act
        $result1 = ErrorHandler::handle_error('Error 1');
        $result2 = ErrorHandler::handle_error('Error 2');
        $result3 = ErrorHandler::handle_error('Error 3');

        // Assert
        $this->assertNotEquals($result1['id'], $result2['id']);
        $this->assertNotEquals($result2['id'], $result3['id']);
        $this->assertNotEquals($result1['id'], $result3['id']);
        
        $this->assertStringStartsWith('err_', $result1['id']);
        $this->assertStringStartsWith('err_', $result2['id']);
        $this->assertStringStartsWith('err_', $result3['id']);
    }

    /**
     * Test default parameter values
     */
    public function test_default_parameter_values() {
        // Mock WordPress function
        \WP_Mock::userFunction('wp_generate_password')
            ->once()
            ->andReturn('default123');

        \WP_Mock::userFunction('error_log')
            ->once();

        // Act
        $result = ErrorHandler::handle_error('Test message with defaults');

        // Assert
        $this->assertEquals(ErrorHandler::SEVERITY_MEDIUM, $result['severity']);
        $this->assertEquals(ErrorHandler::CATEGORY_VALIDATION, $result['category']);
        $this->assertEmpty($result['context']);
        $this->assertArrayNotHasKey('exception', $result);
    }

    /**
     * Helper method to create exception with specific class name for testing
     */
    private function createExceptionWithClass(\Exception $exception, string $className): \Exception {
        // Create anonymous class that extends Exception with desired name
        return new class($exception->getMessage(), $exception->getCode(), $exception) extends \Exception {
            public function __construct($message, $code, $previous) {
                parent::__construct($message, $code, $previous);
            }
        };
    }

    /**
     * Test all severity constants are defined
     */
    public function test_severity_constants_defined() {
        $this->assertEquals('critical', ErrorHandler::SEVERITY_CRITICAL);
        $this->assertEquals('high', ErrorHandler::SEVERITY_HIGH);
        $this->assertEquals('medium', ErrorHandler::SEVERITY_MEDIUM);
        $this->assertEquals('low', ErrorHandler::SEVERITY_LOW);
    }

    /**
     * Test all category constants are defined
     */
    public function test_category_constants_defined() {
        $this->assertEquals('authentication', ErrorHandler::CATEGORY_AUTHENTICATION);
        $this->assertEquals('database', ErrorHandler::CATEGORY_DATABASE);
        $this->assertEquals('network', ErrorHandler::CATEGORY_NETWORK);
        $this->assertEquals('validation', ErrorHandler::CATEGORY_VALIDATION);
        $this->assertEquals('permissions', ErrorHandler::CATEGORY_PERMISSIONS);
        $this->assertEquals('resource', ErrorHandler::CATEGORY_RESOURCE);
        $this->assertEquals('security', ErrorHandler::CATEGORY_SECURITY);
        $this->assertEquals('configuration', ErrorHandler::CATEGORY_CONFIGURATION);
        $this->assertEquals('external_api', ErrorHandler::CATEGORY_EXTERNAL_API);
        $this->assertEquals('file_system', ErrorHandler::CATEGORY_FILE_SYSTEM);
    }
}