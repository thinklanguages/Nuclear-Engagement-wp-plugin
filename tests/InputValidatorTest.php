<?php
/**
 * Tests for InputValidator class
 * 
 * @package NuclearEngagement\Tests
 */

namespace NuclearEngagement\Tests;

use PHPUnit\Framework\TestCase;
use Mockery;
use NuclearEngagement\Helpers\InputValidator;

class InputValidatorTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        \WP_Mock::setUp();
        
        // Clear errors before each test
        InputValidator::clear_errors();
    }

    protected function tearDown(): void {
        \WP_Mock::tearDown();
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test basic text validation with no rules
     */
    public function test_validate_text_basic() {
        // Arrange
        $value = 'Hello World';
        $field = 'test_field';

        // Mock WordPress function
        \WP_Mock::userFunction('sanitize_text_field')
            ->once()
            ->with($value)
            ->andReturn($value);

        // Act
        $result = InputValidator::validate_text($value, $field);

        // Assert
        $this->assertEquals($value, $result);
        $this->assertFalse(InputValidator::has_errors());
    }

    /**
     * Test text validation with required rule - valid input
     */
    public function test_validate_text_required_valid() {
        // Arrange
        $value = 'Valid text';
        $field = 'required_field';
        $rules = ['required' => true];

        // Mock WordPress function
        \WP_Mock::userFunction('sanitize_text_field')
            ->once()
            ->andReturn($value);

        // Act
        $result = InputValidator::validate_text($value, $field, $rules);

        // Assert
        $this->assertEquals($value, $result);
        $this->assertFalse(InputValidator::has_errors());
    }

    /**
     * Test text validation with required rule - empty input
     */
    public function test_validate_text_required_empty() {
        // Arrange
        $value = '';
        $field = 'required_field';
        $rules = ['required' => true];

        // Act
        $result = InputValidator::validate_text($value, $field, $rules);

        // Assert
        $this->assertFalse($result);
        $this->assertTrue(InputValidator::has_errors());
        $this->assertContains('required_field is required.', InputValidator::get_field_errors($field));
    }

    /**
     * Test text validation with minimum length rule
     */
    public function test_validate_text_min_length() {
        // Arrange
        $value = 'short';
        $field = 'min_length_field';
        $rules = ['min_length' => 10];

        // Act
        $result = InputValidator::validate_text($value, $field, $rules);

        // Assert
        $this->assertFalse($result);
        $this->assertTrue(InputValidator::has_errors());
        $this->assertContains('min_length_field must be at least 10 characters.', InputValidator::get_field_errors($field));
    }

    /**
     * Test text validation with maximum length rule
     */
    public function test_validate_text_max_length() {
        // Arrange
        $value = 'This text is way too long for the maximum length rule';
        $field = 'max_length_field';
        $rules = ['max_length' => 10];

        // Act
        $result = InputValidator::validate_text($value, $field, $rules);

        // Assert
        $this->assertFalse($result);
        $this->assertTrue(InputValidator::has_errors());
        $this->assertContains('max_length_field must not exceed 10 characters.', InputValidator::get_field_errors($field));
    }

    /**
     * Test text validation with pattern rule - valid pattern
     */
    public function test_validate_text_pattern_valid() {
        // Arrange
        $value = 'ABC123';
        $field = 'pattern_field';
        $rules = ['pattern' => '/^[A-Z0-9]+$/'];

        // Mock WordPress function
        \WP_Mock::userFunction('sanitize_text_field')
            ->once()
            ->andReturn($value);

        // Act
        $result = InputValidator::validate_text($value, $field, $rules);

        // Assert
        $this->assertEquals($value, $result);
        $this->assertFalse(InputValidator::has_errors());
    }

    /**
     * Test text validation with pattern rule - invalid pattern
     */
    public function test_validate_text_pattern_invalid() {
        // Arrange
        $value = 'abc123';
        $field = 'pattern_field';
        $rules = ['pattern' => '/^[A-Z0-9]+$/'];

        // Act
        $result = InputValidator::validate_text($value, $field, $rules);

        // Assert
        $this->assertFalse($result);
        $this->assertTrue(InputValidator::has_errors());
        $this->assertContains('pattern_field format is invalid.', InputValidator::get_field_errors($field));
    }

    /**
     * Test text validation with custom pattern message
     */
    public function test_validate_text_pattern_custom_message() {
        // Arrange
        $value = 'invalid';
        $field = 'custom_pattern_field';
        $rules = [
            'pattern' => '/^[A-Z]+$/',
            'pattern_message' => 'Must be all uppercase letters'
        ];

        // Act
        $result = InputValidator::validate_text($value, $field, $rules);

        // Assert
        $this->assertFalse($result);
        $this->assertTrue(InputValidator::has_errors());
        $this->assertContains('Must be all uppercase letters', InputValidator::get_field_errors($field));
    }

    /**
     * Test text validation with alphanumeric rule - valid
     */
    public function test_validate_text_alphanumeric_valid() {
        // Arrange
        $value = 'Test123-_';
        $field = 'alphanumeric_field';
        $rules = ['alphanumeric' => true];

        // Mock WordPress function
        \WP_Mock::userFunction('sanitize_text_field')
            ->once()
            ->andReturn($value);

        // Act
        $result = InputValidator::validate_text($value, $field, $rules);

        // Assert
        $this->assertEquals($value, $result);
        $this->assertFalse(InputValidator::has_errors());
    }

    /**
     * Test text validation with alphanumeric rule - invalid
     */
    public function test_validate_text_alphanumeric_invalid() {
        // Arrange
        $value = 'Test@123!';
        $field = 'alphanumeric_field';
        $rules = ['alphanumeric' => true];

        // Act
        $result = InputValidator::validate_text($value, $field, $rules);

        // Assert
        $this->assertFalse($result);
        $this->assertTrue(InputValidator::has_errors());
        $this->assertContains('alphanumeric_field can only contain letters, numbers, hyphens, underscores, and spaces.', InputValidator::get_field_errors($field));
    }

    /**
     * Test email validation - valid email
     */
    public function test_validate_email_valid() {
        // Arrange
        $email = 'test@example.com';
        $field = 'email_field';

        // Mock WordPress functions
        \WP_Mock::userFunction('is_email')
            ->once()
            ->with($email)
            ->andReturn($email);

        \WP_Mock::userFunction('sanitize_email')
            ->once()
            ->with($email)
            ->andReturn($email);

        // Act
        $result = InputValidator::validate_email($email, $field);

        // Assert
        $this->assertEquals($email, $result);
        $this->assertFalse(InputValidator::has_errors());
    }

    /**
     * Test email validation - invalid email
     */
    public function test_validate_email_invalid() {
        // Arrange
        $email = 'invalid-email';
        $field = 'email_field';

        // Mock WordPress function
        \WP_Mock::userFunction('is_email')
            ->once()
            ->with($email)
            ->andReturn(false);

        // Act
        $result = InputValidator::validate_email($email, $field);

        // Assert
        $this->assertFalse($result);
        $this->assertTrue(InputValidator::has_errors());
        $this->assertContains('email_field must be a valid email address.', InputValidator::get_field_errors($field));
    }

    /**
     * Test email validation - required but empty
     */
    public function test_validate_email_required_empty() {
        // Arrange
        $email = '';
        $field = 'required_email';

        // Act
        $result = InputValidator::validate_email($email, $field, true);

        // Assert
        $this->assertFalse($result);
        $this->assertTrue(InputValidator::has_errors());
        $this->assertContains('required_email is required.', InputValidator::get_field_errors($field));
    }

    /**
     * Test email validation - not required and empty
     */
    public function test_validate_email_not_required_empty() {
        // Arrange
        $email = '';
        $field = 'optional_email';

        // Mock WordPress function
        \WP_Mock::userFunction('sanitize_email')
            ->once()
            ->with($email)
            ->andReturn($email);

        // Act
        $result = InputValidator::validate_email($email, $field, false);

        // Assert
        $this->assertEquals($email, $result);
        $this->assertFalse(InputValidator::has_errors());
    }

    /**
     * Test URL validation - valid URL
     */
    public function test_validate_url_valid() {
        // Arrange
        $url = 'https://example.com';
        $field = 'url_field';

        // Mock WordPress function
        \WP_Mock::userFunction('esc_url_raw')
            ->once()
            ->with($url)
            ->andReturn($url);

        // Act
        $result = InputValidator::validate_url($url, $field);

        // Assert
        $this->assertEquals($url, $result);
        $this->assertFalse(InputValidator::has_errors());
    }

    /**
     * Test URL validation - invalid URL
     */
    public function test_validate_url_invalid() {
        // Arrange
        $url = 'not-a-url';
        $field = 'url_field';

        // Act
        $result = InputValidator::validate_url($url, $field);

        // Assert
        $this->assertFalse($result);
        $this->assertTrue(InputValidator::has_errors());
        $this->assertContains('url_field must be a valid URL.', InputValidator::get_field_errors($field));
    }

    /**
     * Test URL validation with allowed protocols
     */
    public function test_validate_url_allowed_protocols() {
        // Arrange
        $url = 'ftp://example.com';
        $field = 'url_field';
        $rules = ['allowed_protocols' => ['http', 'https']];

        // Act
        $result = InputValidator::validate_url($url, $field, $rules);

        // Assert
        $this->assertFalse($result);
        $this->assertTrue(InputValidator::has_errors());
        $this->assertContains('url_field must use an allowed protocol (http, https).', InputValidator::get_field_errors($field));
    }

    /**
     * Test URL validation with allowed domains
     */
    public function test_validate_url_allowed_domains() {
        // Arrange
        $url = 'https://example.org';
        $field = 'url_field';
        $rules = ['allowed_domains' => ['example.com', 'test.com']];

        // Act
        $result = InputValidator::validate_url($url, $field, $rules);

        // Assert
        $this->assertFalse($result);
        $this->assertTrue(InputValidator::has_errors());
        $this->assertContains('url_field must be from an allowed domain.', InputValidator::get_field_errors($field));
    }

    /**
     * Test integer validation - valid integer
     */
    public function test_validate_integer_valid() {
        // Arrange
        $value = '123';
        $field = 'integer_field';

        // Act
        $result = InputValidator::validate_integer($value, $field);

        // Assert
        $this->assertEquals(123, $result);
        $this->assertFalse(InputValidator::has_errors());
    }

    /**
     * Test integer validation - invalid integer
     */
    public function test_validate_integer_invalid() {
        // Arrange
        $value = 'not-a-number';
        $field = 'integer_field';

        // Act
        $result = InputValidator::validate_integer($value, $field);

        // Assert
        $this->assertFalse($result);
        $this->assertTrue(InputValidator::has_errors());
        $this->assertContains('integer_field must be a number.', InputValidator::get_field_errors($field));
    }

    /**
     * Test integer validation with minimum value
     */
    public function test_validate_integer_min_value() {
        // Arrange
        $value = '5';
        $field = 'integer_field';
        $rules = ['min' => 10];

        // Act
        $result = InputValidator::validate_integer($value, $field, $rules);

        // Assert
        $this->assertFalse($result);
        $this->assertTrue(InputValidator::has_errors());
        $this->assertContains('integer_field must be at least 10.', InputValidator::get_field_errors($field));
    }

    /**
     * Test integer validation with maximum value
     */
    public function test_validate_integer_max_value() {
        // Arrange
        $value = '150';
        $field = 'integer_field';
        $rules = ['max' => 100];

        // Act
        $result = InputValidator::validate_integer($value, $field, $rules);

        // Assert
        $this->assertFalse($result);
        $this->assertTrue(InputValidator::has_errors());
        $this->assertContains('integer_field must not exceed 100.', InputValidator::get_field_errors($field));
    }

    /**
     * Test integer validation with positive rule
     */
    public function test_validate_integer_positive() {
        // Arrange
        $value = '-5';
        $field = 'integer_field';
        $rules = ['positive' => true];

        // Act
        $result = InputValidator::validate_integer($value, $field, $rules);

        // Assert
        $this->assertFalse($result);
        $this->assertTrue(InputValidator::has_errors());
        $this->assertContains('integer_field must be a positive number.', InputValidator::get_field_errors($field));
    }

    /**
     * Test integer validation with default value
     */
    public function test_validate_integer_default_value() {
        // Arrange
        $value = '';
        $field = 'integer_field';
        $rules = ['default' => 42];

        // Act
        $result = InputValidator::validate_integer($value, $field, $rules);

        // Assert
        $this->assertEquals(42, $result);
        $this->assertFalse(InputValidator::has_errors());
    }

    /**
     * Test array validation - valid array
     */
    public function test_validate_array_valid() {
        // Arrange
        $value = ['item1', 'item2', 'item3'];
        $field = 'array_field';

        // Act
        $result = InputValidator::validate_array($value, $field);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertFalse(InputValidator::has_errors());
    }

    /**
     * Test array validation - not an array
     */
    public function test_validate_array_not_array() {
        // Arrange
        $value = 'not an array';
        $field = 'array_field';

        // Act
        $result = InputValidator::validate_array($value, $field);

        // Assert
        $this->assertFalse($result);
        $this->assertTrue(InputValidator::has_errors());
        $this->assertContains('array_field must be an array.', InputValidator::get_field_errors($field));
    }

    /**
     * Test array validation with minimum items
     */
    public function test_validate_array_min_items() {
        // Arrange
        $value = ['item1'];
        $field = 'array_field';
        $rules = ['min_items' => 3];

        // Act
        $result = InputValidator::validate_array($value, $field, $rules);

        // Assert
        $this->assertFalse($result);
        $this->assertTrue(InputValidator::has_errors());
        $this->assertContains('array_field must contain at least 3 items.', InputValidator::get_field_errors($field));
    }

    /**
     * Test array validation with maximum items
     */
    public function test_validate_array_max_items() {
        // Arrange
        $value = ['item1', 'item2', 'item3', 'item4', 'item5'];
        $field = 'array_field';
        $rules = ['max_items' => 3];

        // Act
        $result = InputValidator::validate_array($value, $field, $rules);

        // Assert
        $this->assertFalse($result);
        $this->assertTrue(InputValidator::has_errors());
        $this->assertContains('array_field must not contain more than 3 items.', InputValidator::get_field_errors($field));
    }

    /**
     * Test array validation with element rules
     */
    public function test_validate_array_with_element_rules() {
        // Arrange
        $value = ['123', '456', '789'];
        $field = 'array_field';
        $rules = [
            'element_rules' => [
                'type' => 'integer',
                'rules' => ['min' => 100]
            ]
        ];

        // Act
        $result = InputValidator::validate_array($value, $field, $rules);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals([123, 456, 789], $result);
        $this->assertFalse(InputValidator::has_errors());
    }

    /**
     * Test validate by type - text type
     */
    public function test_validate_by_type_text() {
        // Arrange
        $value = 'test value';
        $field = 'test_field';
        $config = [
            'type' => 'text',
            'rules' => ['required' => true]
        ];

        // Mock WordPress function
        \WP_Mock::userFunction('sanitize_text_field')
            ->once()
            ->andReturn($value);

        // Act
        $result = InputValidator::validate_by_type($value, $field, $config);

        // Assert
        $this->assertEquals($value, $result);
        $this->assertFalse(InputValidator::has_errors());
    }

    /**
     * Test validate by type - email type
     */
    public function test_validate_by_type_email() {
        // Arrange
        $value = 'test@example.com';
        $field = 'email_field';
        $config = [
            'type' => 'email',
            'rules' => ['required' => true]
        ];

        // Mock WordPress functions
        \WP_Mock::userFunction('is_email')
            ->once()
            ->andReturn($value);

        \WP_Mock::userFunction('sanitize_email')
            ->once()
            ->andReturn($value);

        // Act
        $result = InputValidator::validate_by_type($value, $field, $config);

        // Assert
        $this->assertEquals($value, $result);
        $this->assertFalse(InputValidator::has_errors());
    }

    /**
     * Test validate by type - unknown type defaults to text
     */
    public function test_validate_by_type_unknown_type() {
        // Arrange
        $value = 'test value';
        $field = 'unknown_field';
        $config = ['type' => 'unknown_type'];

        // Mock WordPress function
        \WP_Mock::userFunction('sanitize_text_field')
            ->once()
            ->with($value)
            ->andReturn($value);

        // Act
        $result = InputValidator::validate_by_type($value, $field, $config);

        // Assert
        $this->assertEquals($value, $result);
        $this->assertFalse(InputValidator::has_errors());
    }

    /**
     * Test error management functions
     */
    public function test_error_management() {
        // Initially no errors
        $this->assertFalse(InputValidator::has_errors());
        $this->assertEmpty(InputValidator::get_errors());

        // Add some errors by validation
        InputValidator::validate_text('', 'field1', ['required' => true]);
        InputValidator::validate_text('', 'field2', ['required' => true]);

        // Check errors exist
        $this->assertTrue(InputValidator::has_errors());
        $this->assertNotEmpty(InputValidator::get_errors());

        // Check specific field errors
        $field1Errors = InputValidator::get_field_errors('field1');
        $this->assertNotEmpty($field1Errors);
        $this->assertContains('field1 is required.', $field1Errors);

        // Clear all errors
        InputValidator::clear_errors();
        $this->assertFalse(InputValidator::has_errors());
        $this->assertEmpty(InputValidator::get_errors());
    }

    /**
     * Test multiple validation rules on single field
     */
    public function test_multiple_validation_rules() {
        // Arrange
        $value = 'a'; // Too short for min_length
        $field = 'multi_rule_field';
        $rules = [
            'required' => true,
            'min_length' => 5,
            'max_length' => 10,
            'pattern' => '/^[a-z]+$/'
        ];

        // Act
        $result = InputValidator::validate_text($value, $field, $rules);

        // Assert
        $this->assertFalse($result);
        $this->assertTrue(InputValidator::has_errors());
        $this->assertContains('multi_rule_field must be at least 5 characters.', InputValidator::get_field_errors($field));
    }

    /**
     * Test complex array validation with failing element rules
     */
    public function test_array_validation_with_failing_element_rules() {
        // Arrange
        $value = ['valid@email.com', 'invalid-email'];
        $field = 'email_array_field';
        $rules = [
            'element_rules' => [
                'type' => 'email',
                'rules' => ['required' => true]
            ]
        ];

        // Mock WordPress functions
        \WP_Mock::userFunction('is_email')
            ->twice()
            ->andReturnValues([true, false]);

        \WP_Mock::userFunction('sanitize_email')
            ->once()
            ->andReturn('valid@email.com');

        // Act
        $result = InputValidator::validate_array($value, $field, $rules);

        // Assert
        $this->assertFalse($result);
        $this->assertTrue(InputValidator::has_errors());
    }

    /**
     * Test integer validation with zero value and positive rule
     */
    public function test_validate_integer_zero_with_positive_rule() {
        // Arrange
        $value = '0';
        $field = 'zero_field';
        $rules = ['positive' => true];

        // Act
        $result = InputValidator::validate_integer($value, $field, $rules);

        // Assert
        $this->assertFalse($result);
        $this->assertTrue(InputValidator::has_errors());
        $this->assertContains('zero_field must be a positive number.', InputValidator::get_field_errors($field));
    }

    /**
     * Test URL validation with empty value and required rule
     */
    public function test_validate_url_empty_required() {
        // Arrange
        $value = '';
        $field = 'required_url';
        $rules = ['required' => true];

        // Act
        $result = InputValidator::validate_url($value, $field, $rules);

        // Assert
        $this->assertFalse($result);
        $this->assertTrue(InputValidator::has_errors());
        $this->assertContains('required_url is required.', InputValidator::get_field_errors($field));
    }

    /**
     * Test field error clearing when validation is called multiple times
     */
    public function test_field_error_clearing_on_revalidation() {
        // Arrange
        $field = 'revalidation_field';

        // First validation - fail
        InputValidator::validate_text('', $field, ['required' => true]);
        $this->assertTrue(InputValidator::has_errors());

        // Mock WordPress function for second validation
        \WP_Mock::userFunction('sanitize_text_field')
            ->once()
            ->andReturn('valid value');

        // Second validation - pass
        $result = InputValidator::validate_text('valid value', $field, ['required' => true]);

        // Assert
        $this->assertEquals('valid value', $result);
        $this->assertEmpty(InputValidator::get_field_errors($field));
    }
}