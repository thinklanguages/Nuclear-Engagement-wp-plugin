<?php

use PHPUnit\Framework\TestCase;

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// Mock WordPress functions for security testing
if (!function_exists('get_option')) {
    function get_option($name, $default = false) {
        return $GLOBALS['wp_options'][$name] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($name, $value, $autoload = 'yes') {
        $GLOBALS['wp_options'][$name] = $value;
        return true;
    }
}

if (!function_exists('wp_generate_password')) {
    function wp_generate_password($length = 12, $include_standard_special_chars = true) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        if ($include_standard_special_chars) {
            $chars .= '!@#$%^&*()';
        }
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }
}

if (!function_exists('wp_create_user')) {
    function wp_create_user($username, $password, $email) {
        $user_id = random_int(100, 999);
        $GLOBALS['wp_users'][$user_id] = [
            'ID' => $user_id,
            'user_login' => $username,
            'user_email' => $email,
            'user_pass' => password_hash($password, PASSWORD_DEFAULT)
        ];
        return $user_id;
    }
}

if (!function_exists('get_user_by')) {
    function get_user_by($field, $value) {
        foreach ($GLOBALS['wp_users'] ?? [] as $user) {
            if ($user[$field] === $value) {
                return (object) $user;
            }
        }
        return false;
    }
}

if (!function_exists('wp_delete_user')) {
    function wp_delete_user($user_id) {
        unset($GLOBALS['wp_users'][$user_id]);
        return true;
    }
}

if (!function_exists('current_time')) {
    function current_time($type) {
        return time();
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '') {
        return 'https://example.com' . $path;
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        return 1;
    }
}

if (!function_exists('add_role')) {
    function add_role($role, $display_name, $capabilities) {
        $GLOBALS['wp_roles'][$role] = compact('display_name', 'capabilities');
        return true;
    }
}

if (!function_exists('get_role')) {
    function get_role($role) {
        return isset($GLOBALS['wp_roles'][$role]) ? (object) $GLOBALS['wp_roles'][$role] : null;
    }
}

if (!function_exists('remove_role')) {
    function remove_role($role) {
        unset($GLOBALS['wp_roles'][$role]);
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($name) {
        unset($GLOBALS['wp_options'][$name]);
        return true;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags($str));
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512) {
        return json_encode($data, $options, $depth);
    }
}

if (!function_exists('__')) {
    function __($text, $domain = '') {
        return $text;
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        public $errors = [];
        public $error_data = [];
        
        public function __construct($code = '', $message = '', $data = '') {
            if (!empty($code)) {
                $this->errors[$code][] = $message;
                if (!empty($data)) {
                    $this->error_data[$code] = $data;
                }
            }
        }
        
        public function get_error_message() {
            $messages = [];
            foreach ($this->errors as $code => $error_messages) {
                $messages = array_merge($messages, $error_messages);
            }
            return implode(', ', $messages);
        }
    }
}

// Mock classes for extensive security testing
class SecurityMockTokenManager {
    private $settings_repository;
    private $encryption_key;
    private $active_tokens = [];
    private $revoked_tokens = [];
    
    public function __construct($settings_repository) {
        $this->settings_repository = $settings_repository;
        $this->encryption_key = get_option('nuclear_engagement_encryption_key', $this->generate_encryption_key());
    }
    
    public function generate_secure_token($length = 32) {
        $token = bin2hex(random_bytes($length));
        $this->active_tokens[$token] = [
            'created_at' => time(),
            'expires_at' => time() + 3600, // 1 hour
            'used_count' => 0,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ];
        return $token;
    }
    
    public function hash_token($token) {
        return hash('sha256', $token . $this->encryption_key);
    }
    
    public function validate_token($token) {
        if (!isset($this->active_tokens[$token])) {
            return false;
        }
        
        $token_data = $this->active_tokens[$token];
        
        // Check expiration
        if ($token_data['expires_at'] < time()) {
            $this->revoke_token($token);
            return false;
        }
        
        // Check rate limiting
        if ($token_data['used_count'] > 100) { // Max 100 uses per hour
            $this->revoke_token($token);
            return false;
        }
        
        // Update usage
        $this->active_tokens[$token]['used_count']++;
        $this->active_tokens[$token]['last_used'] = time();
        
        return true;
    }
    
    public function revoke_token($token) {
        if (isset($this->active_tokens[$token])) {
            $this->revoked_tokens[$token] = $this->active_tokens[$token];
            unset($this->active_tokens[$token]);
        }
    }
    
    public function revoke_all_tokens() {
        $this->revoked_tokens = array_merge($this->revoked_tokens, $this->active_tokens);
        $this->active_tokens = [];
    }
    
    public function get_token_info($token) {
        return $this->active_tokens[$token] ?? null;
    }
    
    public function cleanup_expired_tokens() {
        $now = time();
        foreach ($this->active_tokens as $token => $data) {
            if ($data['expires_at'] < $now) {
                $this->revoke_token($token);
            }
        }
    }
    
    public function get_active_tokens_count() {
        return count($this->active_tokens);
    }
    
    public function encrypt_data($data) {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $this->encryption_key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    public function decrypt_data($encrypted_data) {
        $data = base64_decode($encrypted_data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryption_key, 0, $iv);
    }
    
    private function generate_encryption_key() {
        $key = base64_encode(random_bytes(32));
        update_option('nuclear_engagement_encryption_key', $key);
        return $key;
    }
    
    private function get_client_ip() {
        $ip_headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}

class SecurityMockApiUserManager {
    const API_ROLE = 'nuclear_engagement_api';
    const SERVICE_ACCOUNT_USERNAME = 'nuclear_engagement_api_service';
    const SERVICE_ACCOUNT_OPTION = 'nuclear_engagement_api_user_id';
    
    private static $failed_attempts = [];
    private static $blocked_ips = [];
    
    public static function init() {
        if (!get_role(self::API_ROLE)) {
            add_role(
                self::API_ROLE,
                'Nuclear Engagement API',
                [
                    'read' => true,
                    'nuclear_engagement_api_access' => true
                ]
            );
        }
        
        if (!get_option(self::SERVICE_ACCOUNT_OPTION)) {
            self::create_service_account();
        }
    }
    
    public static function create_service_account() {
        $username = self::SERVICE_ACCOUNT_USERNAME;
        $password = wp_generate_password(32, true);
        $email = 'api@' . parse_url(home_url(), PHP_URL_HOST);
        
        // Check if user already exists
        if (get_user_by('login', $username)) {
            return new WP_Error('user_exists', 'API service account already exists');
        }
        
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        
        // Store the user ID
        update_option(self::SERVICE_ACCOUNT_OPTION, $user_id);
        
        // Store credentials securely
        update_option('nuclear_engagement_api_credentials', [
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => current_time('mysql')
        ]);
        
        return $user_id;
    }
    
    public static function authenticate_api_request($username, $password) {
        $ip = self::get_client_ip();
        
        // Check if IP is blocked
        if (self::is_ip_blocked($ip)) {
            return new WP_Error('ip_blocked', 'IP address is temporarily blocked due to failed attempts');
        }
        
        // Rate limiting check
        if (self::is_rate_limited($ip)) {
            return new WP_Error('rate_limited', 'Too many requests from this IP');
        }
        
        $user = get_user_by('login', $username);
        if (!$user) {
            self::record_failed_attempt($ip, $username);
            return new WP_Error('invalid_credentials', 'Invalid username or password');
        }
        
        $stored_credentials = get_option('nuclear_engagement_api_credentials');
        if (!$stored_credentials || !password_verify($password, $stored_credentials['password_hash'])) {
            self::record_failed_attempt($ip, $username);
            return new WP_Error('invalid_credentials', 'Invalid username or password');
        }
        
        // Reset failed attempts on successful login
        self::reset_failed_attempts($ip);
        
        return $user;
    }
    
    public static function revoke_service_account() {
        $user_id = get_option(self::SERVICE_ACCOUNT_OPTION);
        if ($user_id) {
            wp_delete_user($user_id);
            delete_option(self::SERVICE_ACCOUNT_OPTION);
            delete_option('nuclear_engagement_api_credentials');
        }
        
        remove_role(self::API_ROLE);
    }
    
    public static function rotate_credentials() {
        $user_id = get_option(self::SERVICE_ACCOUNT_OPTION);
        if (!$user_id) {
            return new WP_Error('no_service_account', 'No service account found');
        }
        
        $new_password = wp_generate_password(32, true);
        
        // Update password hash
        update_option('nuclear_engagement_api_credentials', [
            'username' => self::SERVICE_ACCOUNT_USERNAME,
            'password_hash' => password_hash($new_password, PASSWORD_DEFAULT),
            'created_at' => current_time('mysql'),
            'rotated_at' => current_time('mysql')
        ]);
        
        return [
            'username' => self::SERVICE_ACCOUNT_USERNAME,
            'password' => $new_password
        ];
    }
    
    private static function record_failed_attempt($ip, $username) {
        if (!isset(self::$failed_attempts[$ip])) {
            self::$failed_attempts[$ip] = [];
        }
        
        self::$failed_attempts[$ip][] = [
            'timestamp' => time(),
            'username' => $username,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ];
        
        // Block IP after 5 failed attempts
        if (count(self::$failed_attempts[$ip]) >= 5) {
            self::$blocked_ips[$ip] = time() + 1800; // Block for 30 minutes
        }
    }
    
    private static function reset_failed_attempts($ip) {
        unset(self::$failed_attempts[$ip]);
        unset(self::$blocked_ips[$ip]);
    }
    
    private static function is_ip_blocked($ip) {
        if (!isset(self::$blocked_ips[$ip])) {
            return false;
        }
        
        if (self::$blocked_ips[$ip] < time()) {
            unset(self::$blocked_ips[$ip]);
            return false;
        }
        
        return true;
    }
    
    private static function is_rate_limited($ip) {
        // Simple rate limiting: max 60 requests per minute
        $key = "rate_limit_$ip";
        $current_minute = floor(time() / 60);
        $stored_data = get_option($key, ['minute' => $current_minute, 'count' => 0]);
        
        if ($stored_data['minute'] !== $current_minute) {
            $stored_data = ['minute' => $current_minute, 'count' => 1];
        } else {
            $stored_data['count']++;
        }
        
        update_option($key, $stored_data);
        
        return $stored_data['count'] > 60;
    }
    
    private static function get_client_ip() {
        $ip_headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP', 
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    public static function get_security_stats() {
        return [
            'failed_attempts' => self::$failed_attempts,
            'blocked_ips' => self::$blocked_ips,
            'total_blocked_ips' => count(self::$blocked_ips),
            'total_failed_attempts' => array_sum(array_map('count', self::$failed_attempts))
        ];
    }
}

class SecurityMockSettingsRepository {
    private $settings = [];
    
    public function get($key, $default = null) {
        return $this->settings[$key] ?? $default;
    }
    
    public function set($key, $value) {
        $this->settings[$key] = $value;
        return true;
    }
    
    public function delete($key) {
        unset($this->settings[$key]);
        return true;
    }
    
    public function get_all() {
        return $this->settings;
    }
}

class SecurityExtensiveTest extends TestCase {
    
    private $originalGlobals;
    private $tokenManager;
    private $settingsRepository;
    
    protected function setUp(): void {
        // Store original globals
        $this->originalGlobals = [
            'wp_options' => $GLOBALS['wp_options'] ?? [],
            'wp_users' => $GLOBALS['wp_users'] ?? [],
            'wp_roles' => $GLOBALS['wp_roles'] ?? []
        ];
        
        // Reset globals
        $GLOBALS['wp_options'] = [];
        $GLOBALS['wp_users'] = [];
        $GLOBALS['wp_roles'] = [];
        
        // Set up mock environment
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit Test Suite';
        
        // Initialize components
        $this->settingsRepository = new SecurityMockSettingsRepository();
        $this->tokenManager = new SecurityMockTokenManager($this->settingsRepository);
        
        SecurityMockApiUserManager::init();
    }
    
    protected function tearDown(): void {
        // Restore original globals
        foreach ($this->originalGlobals as $key => $value) {
            $GLOBALS[$key] = $value;
        }
    }
    
    public function testTokenGenerationSecurity() {
        // Test token uniqueness
        $tokens = [];
        for ($i = 0; $i < 100; $i++) {
            $token = $this->tokenManager->generate_secure_token();
            $this->assertNotContains($token, $tokens, 'Generated tokens must be unique');
            $tokens[] = $token;
        }
        
        // Test token format
        foreach ($tokens as $token) {
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token, 'Token must be 64 character hex string');
        }
        
        // Test token entropy
        $combined = implode('', $tokens);
        $this->assertGreaterThan(0.9, $this->calculateEntropy($combined), 'Tokens must have high entropy');
    }
    
    public function testTokenValidationSecurity() {
        $token = $this->tokenManager->generate_secure_token();
        
        // Valid token should validate
        $this->assertTrue($this->tokenManager->validate_token($token));
        
        // Invalid token should not validate
        $this->assertFalse($this->tokenManager->validate_token('invalid_token'));
        
        // Expired token should not validate
        $tokenData = $this->tokenManager->get_token_info($token);
        $this->assertNotNull($tokenData);
        
        // Simulate token expiration
        $reflection = new ReflectionClass($this->tokenManager);
        $activeTokens = $reflection->getProperty('active_tokens');
        $activeTokens->setAccessible(true);
        $tokens = $activeTokens->getValue($this->tokenManager);
        $tokens[$token]['expires_at'] = time() - 1;
        $activeTokens->setValue($this->tokenManager, $tokens);
        
        $this->assertFalse($this->tokenManager->validate_token($token));
    }
    
    public function testTokenRateLimiting() {
        $token = $this->tokenManager->generate_secure_token();
        
        // Use token 100 times (should be allowed)
        for ($i = 0; $i < 100; $i++) {
            $this->assertTrue($this->tokenManager->validate_token($token), "Token should be valid for use $i");
        }
        
        // 101st use should fail
        $this->assertFalse($this->tokenManager->validate_token($token), 'Token should be revoked after rate limit exceeded');
    }
    
    public function testTokenRevocation() {
        $token = $this->tokenManager->generate_secure_token();
        
        // Token should be valid initially
        $this->assertTrue($this->tokenManager->validate_token($token));
        
        // Revoke token
        $this->tokenManager->revoke_token($token);
        
        // Token should no longer be valid
        $this->assertFalse($this->tokenManager->validate_token($token));
    }
    
    public function testEncryptionDecryption() {
        $sensitiveData = 'This is sensitive information that should be encrypted';
        
        // Encrypt data
        $encrypted = $this->tokenManager->encrypt_data($sensitiveData);
        $this->assertNotEquals($sensitiveData, $encrypted);
        $this->assertStringNotContainsString($sensitiveData, $encrypted);
        
        // Decrypt data
        $decrypted = $this->tokenManager->decrypt_data($encrypted);
        $this->assertEquals($sensitiveData, $decrypted);
        
        // Test with different data types
        $complexData = [
            'user_id' => 123,
            'permissions' => ['read', 'write'],
            'metadata' => ['created_at' => time(), 'ip' => '127.0.0.1']
        ];
        
        $serialized = serialize($complexData);
        $encrypted = $this->tokenManager->encrypt_data($serialized);
        $decrypted = $this->tokenManager->decrypt_data($encrypted);
        $unserialized = unserialize($decrypted);
        
        $this->assertEquals($complexData, $unserialized);
    }
    
    public function testApiUserManagerSecurity() {
        // Test service account creation
        $user_id = SecurityMockApiUserManager::create_service_account();
        $this->assertIsInt($user_id);
        
        // Test credential storage
        $credentials = get_option('nuclear_engagement_api_credentials');
        $this->assertIsArray($credentials);
        $this->assertArrayHasKey('username', $credentials);
        $this->assertArrayHasKey('password_hash', $credentials);
        
        // Test authentication with correct credentials
        $username = $credentials['username'];
        $password = 'temp_password_for_testing';
        
        // Update stored hash with known password
        $credentials['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        update_option('nuclear_engagement_api_credentials', $credentials);
        
        $auth_result = SecurityMockApiUserManager::authenticate_api_request($username, $password);
        $this->assertNotInstanceOf(WP_Error::class, $auth_result);
        
        // Test authentication with incorrect credentials
        $auth_result = SecurityMockApiUserManager::authenticate_api_request($username, 'wrong_password');
        $this->assertInstanceOf(WP_Error::class, $auth_result);
    }
    
    public function testBruteForceProtection() {
        $username = SecurityMockApiUserManager::SERVICE_ACCOUNT_USERNAME;
        
        // Make 4 failed attempts
        for ($i = 0; $i < 4; $i++) {
            $result = SecurityMockApiUserManager::authenticate_api_request($username, 'wrong_password');
            $this->assertInstanceOf(WP_Error::class, $result);
            $this->assertEquals('invalid_credentials', $result->errors[array_keys($result->errors)[0]][0] ? 'invalid_credentials' : '');
        }
        
        // 5th attempt should trigger IP blocking
        $result = SecurityMockApiUserManager::authenticate_api_request($username, 'wrong_password');
        $this->assertInstanceOf(WP_Error::class, $result);
        
        // Any subsequent attempt should be blocked
        $result = SecurityMockApiUserManager::authenticate_api_request($username, 'correct_password');
        $this->assertInstanceOf(WP_Error::class, $result);
    }
    
    public function testCredentialRotation() {
        // Create service account
        SecurityMockApiUserManager::create_service_account();
        
        $original_credentials = get_option('nuclear_engagement_api_credentials');
        
        // Rotate credentials
        $new_credentials = SecurityMockApiUserManager::rotate_credentials();
        
        $this->assertIsArray($new_credentials);
        $this->assertArrayHasKey('username', $new_credentials);
        $this->assertArrayHasKey('password', $new_credentials);
        
        $updated_credentials = get_option('nuclear_engagement_api_credentials');
        $this->assertNotEquals($original_credentials['password_hash'], $updated_credentials['password_hash']);
        $this->assertArrayHasKey('rotated_at', $updated_credentials);
    }
    
    public function testSecurityStatsCollection() {
        // Generate some failed attempts
        for ($i = 0; $i < 3; $i++) {
            SecurityMockApiUserManager::authenticate_api_request('test_user', 'wrong_password');
        }
        
        $stats = SecurityMockApiUserManager::get_security_stats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('failed_attempts', $stats);
        $this->assertArrayHasKey('blocked_ips', $stats);
        $this->assertArrayHasKey('total_blocked_ips', $stats);
        $this->assertArrayHasKey('total_failed_attempts', $stats);
        
        $this->assertGreaterThan(0, $stats['total_failed_attempts']);
    }
    
    public function testTokenCleanup() {
        // Generate several tokens
        $tokens = [];
        for ($i = 0; $i < 5; $i++) {
            $tokens[] = $this->tokenManager->generate_secure_token();
        }
        
        $initial_count = $this->tokenManager->get_active_tokens_count();
        $this->assertEquals(5, $initial_count);
        
        // Manually expire some tokens
        $reflection = new ReflectionClass($this->tokenManager);
        $activeTokens = $reflection->getProperty('active_tokens');
        $activeTokens->setAccessible(true);
        $tokenData = $activeTokens->getValue($this->tokenManager);
        
        // Expire first 3 tokens
        for ($i = 0; $i < 3; $i++) {
            $tokenData[$tokens[$i]]['expires_at'] = time() - 1;
        }
        $activeTokens->setValue($this->tokenManager, $tokenData);
        
        // Run cleanup
        $this->tokenManager->cleanup_expired_tokens();
        
        $final_count = $this->tokenManager->get_active_tokens_count();
        $this->assertEquals(2, $final_count);
    }
    
    public function testInputSanitization() {
        // Test various malicious inputs
        $malicious_inputs = [
            '<script>alert("xss")</script>',
            '<?php system("rm -rf /"); ?>',
            '"; DROP TABLE users; --',
            '../../../etc/passwd',
            'javascript:alert(1)',
            '%3Cscript%3Ealert(1)%3C/script%3E'
        ];
        
        foreach ($malicious_inputs as $input) {
            $sanitized = sanitize_text_field($input);
            $this->assertStringNotContainsString('<script>', $sanitized, 'Script tags should be removed');
            $this->assertStringNotContainsString('<?php', $sanitized, 'PHP tags should be removed');
            $this->assertStringNotContainsString('javascript:', $sanitized, 'JavaScript protocol should be removed');
        }
    }
    
    public function testHashConsistency() {
        $token = 'test_token_123';
        
        // Hash should be consistent
        $hash1 = $this->tokenManager->hash_token($token);
        $hash2 = $this->tokenManager->hash_token($token);
        $this->assertEquals($hash1, $hash2);
        
        // Different tokens should have different hashes
        $different_token = 'test_token_456';
        $hash3 = $this->tokenManager->hash_token($different_token);
        $this->assertNotEquals($hash1, $hash3);
        
        // Hash should be appropriate length for SHA256
        $this->assertEquals(64, strlen($hash1));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash1);
    }
    
    public function testSecureRandomGeneration() {
        // Test that random generation is truly random
        $random_values = [];
        for ($i = 0; $i < 1000; $i++) {
            $random_values[] = bin2hex(random_bytes(16));
        }
        
        // Check for duplicates (should be extremely rare)
        $unique_values = array_unique($random_values);
        $this->assertEquals(count($random_values), count($unique_values), 'Random values should be unique');
        
        // Test entropy
        $combined = implode('', $random_values);
        $entropy = $this->calculateEntropy($combined);
        $this->assertGreaterThan(0.9, $entropy, 'Random values should have high entropy');
    }
    
    private function calculateEntropy($string) {
        $length = strlen($string);
        $frequencies = array_count_values(str_split($string));
        
        $entropy = 0;
        foreach ($frequencies as $frequency) {
            $probability = $frequency / $length;
            $entropy -= $probability * log($probability, 2);
        }
        
        // Normalize to 0-1 range (assuming max entropy for hex characters is log2(16) = 4)
        return $entropy / 4;
    }
}