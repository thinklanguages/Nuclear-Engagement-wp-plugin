# Security Guide - Nuclear Engagement Plugin

## Overview

This document outlines security best practices, guidelines, and procedures for the Nuclear Engagement WordPress plugin. Security is a fundamental aspect of the plugin's design and should be considered at every level of implementation and deployment.

## Table of Contents

- [Security Architecture](#security-architecture)
- [Authentication & Authorization](#authentication--authorization)
- [Data Protection](#data-protection)
- [Input Validation & Sanitization](#input-validation--sanitization)
- [API Security](#api-security)
- [Database Security](#database-security)
- [File System Security](#file-system-security)
- [Configuration Security](#configuration-security)
- [Monitoring & Logging](#monitoring--logging)
- [Incident Response](#incident-response)
- [Security Checklist](#security-checklist)

## Security Architecture

### Defense in Depth

The Nuclear Engagement plugin implements multiple layers of security:

1. **Application Layer**: Input validation, output encoding, authentication
2. **WordPress Layer**: Capabilities, nonces, sanitization functions
3. **Database Layer**: Prepared statements, access controls
4. **Server Layer**: HTTPS, file permissions, server hardening
5. **Network Layer**: Firewalls, rate limiting, DDoS protection

### Security Principles

- **Least Privilege**: Users and processes have minimum required permissions
- **Fail Securely**: Security failures default to deny access
- **Defense in Depth**: Multiple security layers prevent single point of failure
- **Security by Design**: Security considered from initial development
- **Regular Updates**: Continuous security monitoring and patching

## Authentication & Authorization

### Gold Code System

The plugin uses a unique Gold Code system for secure external API integration:

```php
// Gold Code validation example
class GoldCodeValidator {
    public function validate_gold_code($code, $site_url) {
        // Validate format (alphanumeric, specific length)
        if (!preg_match('/^[A-Z0-9]{12}$/', $code)) {
            return false;
        }
        
        // Rate limiting
        if ($this->is_rate_limited($site_url)) {
            return false;
        }
        
        // Verify with Nuclear Engagement API
        return $this->verify_with_api($code, $site_url);
    }
    
    private function is_rate_limited($site_url) {
        $transient_key = 'ne_auth_attempts_' . md5($site_url);
        $attempts = get_transient($transient_key) ?: 0;
        
        if ($attempts >= 5) {
            return true;
        }
        
        set_transient($transient_key, $attempts + 1, HOUR_IN_SECONDS);
        return false;
    }
}
```

### WordPress Authentication Integration

```php
// Capability checks example
public function check_quiz_permissions($quiz_id, $action = 'read') {
    $required_caps = [
        'read' => 'read',
        'edit' => 'edit_posts',
        'delete' => 'delete_posts',
        'manage' => 'manage_options'
    ];
    
    $required_cap = $required_caps[$action] ?? 'read';
    
    if (!current_user_can($required_cap)) {
        wp_die(__('Insufficient permissions.', 'nuclear-engagement'));
    }
    
    // Additional quiz-specific checks
    if ($action !== 'read' && !$this->user_can_edit_quiz($quiz_id)) {
        wp_die(__('Cannot edit this quiz.', 'nuclear-engagement'));
    }
}
```

### Multi-Factor Authentication Support

```php
// MFA integration for admin operations
add_action('nuclear_engagement_admin_login', function($user_id) {
    if (class_exists('Two_Factor_Core')) {
        $providers = Two_Factor_Core::get_enabled_providers_for_user($user_id);
        if (!empty($providers)) {
            // Require MFA for sensitive operations
            Two_Factor_Core::require_two_factor($user_id);
        }
    }
});
```

## Data Protection

### GDPR Compliance

#### Data Collection Transparency

```php
// GDPR data collection notice
public function get_privacy_policy_content() {
    return [
        'plugin_name' => __('Nuclear Engagement', 'nuclear-engagement'),
        'policy_text' => __(
            'This plugin collects the following data when you take quizzes: ' .
            'your responses, completion time, score, and IP address. ' .
            'This data is used to provide quiz functionality and analytics.',
            'nuclear-engagement'
        ),
        'suggested_text' => __(
            'We collect quiz responses and performance data to improve ' .
            'the learning experience. You can request deletion of your ' .
            'data at any time.',
            'nuclear-engagement'
        )
    ];
}
```

#### Data Export/Deletion

```php
// GDPR data export
public function export_user_data($email_address) {
    $user = get_user_by('email', $email_address);
    if (!$user) {
        return [];
    }
    
    $quiz_results = $this->get_user_quiz_results($user->ID);
    
    $export_items = [];
    foreach ($quiz_results as $result) {
        $export_items[] = [
            'group_id' => 'nuclear_engagement_quiz_results',
            'group_label' => __('Quiz Results', 'nuclear-engagement'),
            'item_id' => "quiz-result-{$result->id}",
            'data' => [
                'quiz_title' => $result->quiz_title,
                'score' => $result->score,
                'date_taken' => $result->date_taken,
                'time_taken' => $result->time_taken
            ]
        ];
    }
    
    return $export_items;
}

// GDPR data deletion
public function erase_user_data($email_address) {
    $user = get_user_by('email', $email_address);
    if (!$user) {
        return false;
    }
    
    // Delete quiz results
    $deleted_results = $this->delete_user_quiz_results($user->ID);
    
    // Delete analytics data
    $deleted_analytics = $this->delete_user_analytics($user->ID);
    
    return [
        'items_removed' => $deleted_results + $deleted_analytics,
        'items_retained' => false,
        'messages' => [__('All Nuclear Engagement data has been removed.', 'nuclear-engagement')]
    ];
}
```

### Data Encryption

#### Sensitive Data Encryption

```php
// Encrypt sensitive configuration data
class EncryptionHelper {
    private function get_encryption_key() {
        $key = get_option('nuclear_engagement_encryption_key');
        if (!$key) {
            $key = wp_generate_password(32, true, true);
            update_option('nuclear_engagement_encryption_key', $key);
        }
        return $key;
    }
    
    public function encrypt($data) {
        $key = $this->get_encryption_key();
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    public function decrypt($encrypted_data) {
        $key = $this->get_encryption_key();
        $data = base64_decode($encrypted_data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
}
```

## Input Validation & Sanitization

### Comprehensive Input Validation

```php
// Input validation class
class InputValidator {
    public static function validate_quiz_data($data) {
        $errors = [];
        
        // Title validation
        if (empty($data['title'])) {
            $errors[] = __('Quiz title is required.', 'nuclear-engagement');
        } elseif (strlen($data['title']) > 200) {
            $errors[] = __('Quiz title is too long.', 'nuclear-engagement');
        }
        
        // Content validation
        if (isset($data['content'])) {
            $data['content'] = wp_kses_post($data['content']);
        }
        
        // Questions validation
        if (empty($data['questions']) || !is_array($data['questions'])) {
            $errors[] = __('At least one question is required.', 'nuclear-engagement');
        } else {
            foreach ($data['questions'] as $index => $question) {
                $question_errors = self::validate_question($question, $index);
                $errors = array_merge($errors, $question_errors);
            }
        }
        
        // Settings validation
        if (isset($data['settings'])) {
            $settings_errors = self::validate_quiz_settings($data['settings']);
            $errors = array_merge($errors, $settings_errors);
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'sanitized_data' => $data
        ];
    }
    
    private static function validate_question($question, $index) {
        $errors = [];
        
        // Question text validation
        if (empty($question['question'])) {
            $errors[] = sprintf(__('Question %d: Question text is required.', 'nuclear-engagement'), $index + 1);
        }
        
        // Question type validation
        $valid_types = ['multiple_choice', 'true_false', 'short_answer', 'essay'];
        if (!in_array($question['type'] ?? '', $valid_types)) {
            $errors[] = sprintf(__('Question %d: Invalid question type.', 'nuclear-engagement'), $index + 1);
        }
        
        // Answers validation for multiple choice
        if (($question['type'] ?? '') === 'multiple_choice') {
            if (empty($question['answers']) || count($question['answers']) < 2) {
                $errors[] = sprintf(__('Question %d: At least 2 answers required.', 'nuclear-engagement'), $index + 1);
            }
            
            $correct_answers = array_filter($question['answers'], function($answer) {
                return !empty($answer['correct']);
            });
            
            if (empty($correct_answers)) {
                $errors[] = sprintf(__('Question %d: At least one correct answer required.', 'nuclear-engagement'), $index + 1);
            }
        }
        
        return $errors;
    }
}
```

### SQL Injection Prevention

```php
// Always use prepared statements
class QuizDatabase {
    public function get_quiz_results($quiz_id, $user_id = null) {
        global $wpdb;
        
        $query = "SELECT * FROM {$wpdb->prefix}nuclear_engagement_results WHERE quiz_id = %d";
        $params = [$quiz_id];
        
        if ($user_id !== null) {
            $query .= " AND user_id = %d";
            $params[] = $user_id;
        }
        
        $query .= " ORDER BY created_at DESC";
        
        return $wpdb->get_results($wpdb->prepare($query, $params));
    }
    
    public function insert_quiz_result($data) {
        global $wpdb;
        
        // Validate and sanitize data
        $sanitized_data = [
            'quiz_id' => absint($data['quiz_id']),
            'user_id' => absint($data['user_id']),
            'score' => floatval($data['score']),
            'time_taken' => absint($data['time_taken']),
            'answers' => wp_json_encode($data['answers']),
            'created_at' => current_time('mysql', true)
        ];
        
        return $wpdb->insert(
            $wpdb->prefix . 'nuclear_engagement_results',
            $sanitized_data,
            ['%d', '%d', '%f', '%d', '%s', '%s']
        );
    }
}
```

### XSS Prevention

```php
// Output escaping examples
class OutputEscaping {
    public static function render_quiz_title($title) {
        return '<h2>' . esc_html($title) . '</h2>';
    }
    
    public static function render_quiz_description($description) {
        return '<div class="quiz-description">' . wp_kses_post($description) . '</div>';
    }
    
    public static function render_quiz_url($quiz_id) {
        $url = add_query_arg('quiz_id', absint($quiz_id), get_permalink());
        return '<a href="' . esc_url($url) . '">' . __('Take Quiz', 'nuclear-engagement') . '</a>';
    }
    
    public static function render_quiz_data_attribute($data) {
        return 'data-quiz="' . esc_attr(wp_json_encode($data)) . '"';
    }
}
```

## API Security

### REST API Security

```php
// API authentication and permissions
class APIPermissions {
    public function permission_callback($request) {
        // Check nonce for authenticated requests
        if (is_user_logged_in()) {
            $nonce = $request->get_header('X-WP-Nonce');
            if (!wp_verify_nonce($nonce, 'wp_rest')) {
                return new WP_Error('invalid_nonce', 'Invalid nonce.', ['status' => 403]);
            }
        }
        
        // Check capabilities
        $endpoint = $request->get_route();
        $method = $request->get_method();
        
        $required_cap = $this->get_required_capability($endpoint, $method);
        if (!current_user_can($required_cap)) {
            return new WP_Error('insufficient_permissions', 'Insufficient permissions.', ['status' => 403]);
        }
        
        return true;
    }
    
    private function get_required_capability($endpoint, $method) {
        $capability_map = [
            'GET' => 'read',
            'POST' => 'edit_posts',
            'PUT' => 'edit_posts',
            'PATCH' => 'edit_posts',
            'DELETE' => 'delete_posts'
        ];
        
        // Admin endpoints require higher permissions
        if (strpos($endpoint, '/admin/') !== false) {
            return 'manage_options';
        }
        
        return $capability_map[$method] ?? 'read';
    }
}
```

### Rate Limiting

```php
// API rate limiting implementation
class RateLimiter {
    private $limits = [
        'default' => ['requests' => 100, 'window' => 3600], // 100 per hour
        'authenticated' => ['requests' => 1000, 'window' => 3600], // 1000 per hour
        'quiz_submit' => ['requests' => 10, 'window' => 3600] // 10 quiz submissions per hour
    ];
    
    public function check_rate_limit($identifier, $type = 'default') {
        $limit_config = $this->limits[$type] ?? $this->limits['default'];
        $key = "rate_limit_{$type}_{$identifier}";
        
        $current_count = get_transient($key) ?: 0;
        
        if ($current_count >= $limit_config['requests']) {
            return [
                'allowed' => false,
                'limit' => $limit_config['requests'],
                'remaining' => 0,
                'reset_time' => time() + $limit_config['window']
            ];
        }
        
        set_transient($key, $current_count + 1, $limit_config['window']);
        
        return [
            'allowed' => true,
            'limit' => $limit_config['requests'],
            'remaining' => $limit_config['requests'] - $current_count - 1,
            'reset_time' => time() + $limit_config['window']
        ];
    }
}
```

### API Response Security

```php
// Secure API responses
class SecureAPIResponse {
    public function prepare_quiz_response($quiz, $context = 'view') {
        $response_data = [];
        
        // Always include safe fields
        $safe_fields = ['id', 'title', 'status', 'date_created'];
        foreach ($safe_fields as $field) {
            if (isset($quiz[$field])) {
                $response_data[$field] = $quiz[$field];
            }
        }
        
        // Context-specific fields
        if ($context === 'edit' && current_user_can('edit_posts')) {
            $response_data['content'] = $quiz['content'];
            $response_data['settings'] = $quiz['settings'];
        }
        
        // Never expose sensitive data
        unset($response_data['internal_notes']);
        unset($response_data['gold_code']);
        
        return $response_data;
    }
}
```

## Database Security

### Database Access Control

```php
// Database security implementation
class DatabaseSecurity {
    public function create_tables_with_security() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Quiz results table with proper indexing
        $sql = "CREATE TABLE {$wpdb->prefix}nuclear_engagement_results (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            quiz_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            score decimal(5,2) NOT NULL,
            time_taken int unsigned NOT NULL,
            answers longtext NOT NULL,
            user_agent text,
            ip_address varchar(45),
            created_at datetime NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY quiz_user_idx (quiz_id, user_id),
            KEY created_at_idx (created_at),
            KEY user_id_idx (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Set proper permissions
        $this->set_table_permissions();
    }
    
    private function set_table_permissions() {
        global $wpdb;
        
        // Ensure tables are only accessible by WordPress user
        $tables = [
            $wpdb->prefix . 'nuclear_engagement_results',
            $wpdb->prefix . 'nuclear_engagement_analytics'
        ];
        
        foreach ($tables as $table) {
            $wpdb->query($wpdb->prepare(
                "GRANT SELECT, INSERT, UPDATE, DELETE ON %s TO %s",
                $table,
                DB_USER
            ));
        }
    }
}
```

### Data Anonymization

```php
// Anonymize sensitive data for analytics
class DataAnonymizer {
    public function anonymize_ip_address($ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // IPv4: Remove last octet
            return preg_replace('/\.\d+$/', '.0', $ip);
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // IPv6: Remove last 80 bits
            return preg_replace('/:[^:]+:[^:]+:[^:]+:[^:]+$/', '::', $ip);
        }
        
        return '';
    }
    
    public function hash_user_identifier($user_id) {
        $salt = get_option('nuclear_engagement_hash_salt');
        if (!$salt) {
            $salt = wp_generate_password(32, true, true);
            update_option('nuclear_engagement_hash_salt', $salt);
        }
        
        return hash_hmac('sha256', $user_id, $salt);
    }
}
```

## File System Security

### File Upload Security

```php
// Secure file upload handling
class FileUploadSecurity {
    private $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    private $max_file_size = 2097152; // 2MB
    
    public function validate_upload($file) {
        $errors = [];
        
        // Check file size
        if ($file['size'] > $this->max_file_size) {
            $errors[] = __('File size exceeds maximum allowed.', 'nuclear-engagement');
        }
        
        // Check file type
        $file_type = wp_check_filetype($file['name']);
        if (!in_array($file_type['type'], $this->allowed_types)) {
            $errors[] = __('File type not allowed.', 'nuclear-engagement');
        }
        
        // Check for malicious content
        if ($this->has_malicious_content($file['tmp_name'])) {
            $errors[] = __('File contains malicious content.', 'nuclear-engagement');
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    private function has_malicious_content($file_path) {
        $content = file_get_contents($file_path);
        
        // Check for PHP tags in image files
        $malicious_patterns = [
            '/<\?php/',
            '/<\?=/',
            '/<script/',
            '/javascript:/',
            '/vbscript:/',
            '/onload\s*=/',
            '/onerror\s*=/'
        ];
        
        foreach ($malicious_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false;
    }
}
```

### File Permission Management

```php
// Set secure file permissions
class FilePermissions {
    public function set_secure_permissions() {
        $upload_dir = wp_upload_dir();
        $plugin_upload_dir = $upload_dir['basedir'] . '/nuclear-engagement/';
        
        // Create directory with secure permissions
        if (!file_exists($plugin_upload_dir)) {
            wp_mkdir_p($plugin_upload_dir);
            chmod($plugin_upload_dir, 0755);
        }
        
        // Create .htaccess to prevent direct access
        $htaccess_content = "Options -Indexes\n";
        $htaccess_content .= "deny from all\n";
        $htaccess_content .= "<Files ~ \"\\.(jpg|jpeg|png|gif)$\">\n";
        $htaccess_content .= "    allow from all\n";
        $htaccess_content .= "</Files>\n";
        
        file_put_contents($plugin_upload_dir . '.htaccess', $htaccess_content);
    }
}
```

## Configuration Security

### Secure Configuration Management

```php
// Secure configuration settings
class SecureConfiguration {
    private $secure_options = [
        'nuclear_engagement_gold_code',
        'nuclear_engagement_api_key',
        'nuclear_engagement_encryption_key'
    ];
    
    public function update_secure_option($option_name, $value) {
        if (in_array($option_name, $this->secure_options)) {
            // Encrypt sensitive options
            $encrypted_value = $this->encrypt_option($value);
            update_option($option_name, $encrypted_value);
        } else {
            update_option($option_name, $value);
        }
    }
    
    public function get_secure_option($option_name, $default = false) {
        $value = get_option($option_name, $default);
        
        if (in_array($option_name, $this->secure_options) && $value !== $default) {
            return $this->decrypt_option($value);
        }
        
        return $value;
    }
    
    private function encrypt_option($value) {
        if (!defined('NUCLEAR_ENGAGEMENT_SECRET_KEY')) {
            define('NUCLEAR_ENGAGEMENT_SECRET_KEY', wp_generate_password(64, true, true));
        }
        
        $key = NUCLEAR_ENGAGEMENT_SECRET_KEY;
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    private function decrypt_option($encrypted_value) {
        if (!defined('NUCLEAR_ENGAGEMENT_SECRET_KEY')) {
            return false;
        }
        
        $key = NUCLEAR_ENGAGEMENT_SECRET_KEY;
        $data = base64_decode($encrypted_value);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
}
```

### Environment-Specific Security

```php
// Environment-based security configuration
class EnvironmentSecurity {
    public function configure_for_environment() {
        if ($this->is_development()) {
            // Development settings
            ini_set('display_errors', 1);
            define('NUCLEAR_ENGAGEMENT_DEBUG', true);
        } elseif ($this->is_staging()) {
            // Staging settings
            ini_set('display_errors', 0);
            define('NUCLEAR_ENGAGEMENT_DEBUG', false);
            define('NUCLEAR_ENGAGEMENT_LOG_LEVEL', 'warning');
        } else {
            // Production settings
            ini_set('display_errors', 0);
            define('NUCLEAR_ENGAGEMENT_DEBUG', false);
            define('NUCLEAR_ENGAGEMENT_LOG_LEVEL', 'error');
            
            // Enhanced security for production
            $this->enable_production_security();
        }
    }
    
    private function enable_production_security() {
        // Disable file editing
        if (!defined('DISALLOW_FILE_EDIT')) {
            define('DISALLOW_FILE_EDIT', true);
        }
        
        // Force SSL for admin
        if (!defined('FORCE_SSL_ADMIN')) {
            define('FORCE_SSL_ADMIN', true);
        }
        
        // Set secure headers
        add_action('send_headers', [$this, 'set_security_headers']);
    }
    
    public function set_security_headers() {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        
        if (is_ssl()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}
```

## Monitoring & Logging

### Security Event Logging

```php
// Comprehensive security logging
class SecurityLogger {
    private $log_file;
    
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->log_file = $upload_dir['basedir'] . '/nuclear-engagement-security.log';
    }
    
    public function log_security_event($event_type, $details, $severity = 'info') {
        $log_entry = [
            'timestamp' => current_time('c'),
            'event_type' => $event_type,
            'severity' => $severity,
            'user_id' => get_current_user_id(),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'details' => $details
        ];
        
        $log_line = json_encode($log_entry) . "\n";
        file_put_contents($this->log_file, $log_line, FILE_APPEND | LOCK_EX);
        
        // Alert on critical events
        if ($severity === 'critical') {
            $this->send_security_alert($log_entry);
        }
    }
    
    public function log_authentication_attempt($success, $username) {
        $this->log_security_event(
            'authentication_attempt',
            [
                'success' => $success,
                'username' => $username,
                'method' => 'gold_code'
            ],
            $success ? 'info' : 'warning'
        );
    }
    
    public function log_permission_violation($action, $resource) {
        $this->log_security_event(
            'permission_violation',
            [
                'action' => $action,
                'resource' => $resource,
                'required_capability' => $this->get_required_capability($action)
            ],
            'warning'
        );
    }
    
    private function send_security_alert($log_entry) {
        $admin_email = get_option('admin_email');
        $subject = sprintf('[Security Alert] %s - %s', get_bloginfo('name'), $log_entry['event_type']);
        
        $message = sprintf(
            "Security Event Detected:\n\n" .
            "Event: %s\n" .
            "Severity: %s\n" .
            "Time: %s\n" .
            "User: %s\n" .
            "IP: %s\n" .
            "Details: %s\n",
            $log_entry['event_type'],
            $log_entry['severity'],
            $log_entry['timestamp'],
            $log_entry['user_id'],
            $log_entry['ip_address'],
            json_encode($log_entry['details'])
        );
        
        wp_mail($admin_email, $subject, $message);
    }
}
```

### Intrusion Detection

```php
// Basic intrusion detection system
class IntrusionDetection {
    private $suspicious_patterns = [
        'sql_injection' => [
            '/union\s+select/i',
            '/drop\s+table/i',
            '/insert\s+into/i',
            '/update\s+set/i'
        ],
        'xss_attempt' => [
            '/<script/i',
            '/javascript:/i',
            '/onload\s*=/i',
            '/onerror\s*=/i'
        ],
        'path_traversal' => [
            '/\.\.\//i',
            '/\.\.\\\\/i',
            '/etc\/passwd/i',
            '/proc\/version/i'
        ]
    ];
    
    public function scan_request() {
        $suspicious_activity = [];
        
        $input_data = array_merge($_GET, $_POST, $_COOKIE);
        
        foreach ($input_data as $key => $value) {
            if (is_string($value)) {
                $detected_threats = $this->detect_threats($value);
                if (!empty($detected_threats)) {
                    $suspicious_activity[] = [
                        'parameter' => $key,
                        'value' => substr($value, 0, 100), // Truncate for logging
                        'threats' => $detected_threats
                    ];
                }
            }
        }
        
        if (!empty($suspicious_activity)) {
            $this->handle_suspicious_activity($suspicious_activity);
        }
        
        return empty($suspicious_activity);
    }
    
    private function detect_threats($input) {
        $threats = [];
        
        foreach ($this->suspicious_patterns as $threat_type => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $input)) {
                    $threats[] = $threat_type;
                    break;
                }
            }
        }
        
        return array_unique($threats);
    }
    
    private function handle_suspicious_activity($activities) {
        $logger = new SecurityLogger();
        $logger->log_security_event(
            'intrusion_attempt',
            ['activities' => $activities],
            'critical'
        );
        
        // Temporarily block IP for repeated attempts
        $ip = $this->get_client_ip();
        $this->temporarily_block_ip($ip);
    }
}
```

## Incident Response

### Security Incident Response Plan

```php
// Automated incident response
class IncidentResponse {
    public function handle_security_incident($incident_type, $details) {
        $response_plan = $this->get_response_plan($incident_type);
        
        foreach ($response_plan['actions'] as $action) {
            $this->execute_response_action($action, $details);
        }
        
        // Escalate if necessary
        if ($response_plan['escalate']) {
            $this->escalate_incident($incident_type, $details);
        }
    }
    
    private function get_response_plan($incident_type) {
        $plans = [
            'data_breach' => [
                'actions' => ['log_incident', 'notify_admin', 'secure_system'],
                'escalate' => true
            ],
            'unauthorized_access' => [
                'actions' => ['log_incident', 'block_ip', 'notify_admin'],
                'escalate' => false
            ],
            'malware_detected' => [
                'actions' => ['quarantine_file', 'scan_system', 'notify_admin'],
                'escalate' => true
            ]
        ];
        
        return $plans[$incident_type] ?? $plans['unauthorized_access'];
    }
    
    private function execute_response_action($action, $details) {
        switch ($action) {
            case 'log_incident':
                $this->log_incident($details);
                break;
            case 'notify_admin':
                $this->notify_administrators($details);
                break;
            case 'block_ip':
                $this->block_suspicious_ip($details);
                break;
            case 'secure_system':
                $this->enable_lockdown_mode();
                break;
        }
    }
    
    private function enable_lockdown_mode() {
        // Temporarily disable plugin functionality
        update_option('nuclear_engagement_lockdown', true);
        
        // Log all access attempts
        add_action('init', function() {
            error_log('Nuclear Engagement: Lockdown mode - Access attempt from ' . $_SERVER['REMOTE_ADDR']);
        });
    }
}
```

### Vulnerability Reporting

Create a clear process for reporting security vulnerabilities:

1. **Contact Information**: security@nuclearengagement.com
2. **Response Time**: 24-48 hours for acknowledgment
3. **Disclosure Timeline**: 90 days for responsible disclosure
4. **Bug Bounty**: Consider implementing a bug bounty program

## Security Checklist

### Development Security Checklist

- [ ] All user inputs are validated and sanitized
- [ ] SQL queries use prepared statements
- [ ] Output is properly escaped for context
- [ ] Authentication and authorization are implemented
- [ ] Sensitive data is encrypted at rest
- [ ] HTTPS is enforced for all communications
- [ ] File uploads are validated and restricted
- [ ] Error messages don't reveal sensitive information
- [ ] Logging captures security events
- [ ] Dependencies are regularly updated

### Deployment Security Checklist

- [ ] Debug mode is disabled in production
- [ ] File permissions are set correctly
- [ ] Database credentials are secure
- [ ] SSL certificates are valid and up to date
- [ ] Security headers are configured
- [ ] Monitoring and alerting are in place
- [ ] Backup and recovery procedures are tested
- [ ] Security scanning is automated
- [ ] Incident response plan is documented
- [ ] Team is trained on security procedures

### Regular Security Maintenance

- [ ] Weekly vulnerability scans
- [ ] Monthly dependency updates
- [ ] Quarterly security audits
- [ ] Annual penetration testing
- [ ] Continuous monitoring and logging
- [ ] Regular backup testing
- [ ] Security awareness training
- [ ] Incident response drills

## Conclusion

Security is an ongoing process that requires constant vigilance and regular updates. This guide provides the foundation for securing the Nuclear Engagement plugin, but security practices should be regularly reviewed and updated as new threats emerge.

For additional security resources and updates, refer to:

- [WordPress Security Documentation](https://wordpress.org/support/article/hardening-wordpress/)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [Nuclear Engagement Security Updates](https://nuclearengagement.com/security)

Remember: **When in doubt, err on the side of caution and deny access rather than allow it.**