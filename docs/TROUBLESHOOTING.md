# Troubleshooting Guide - Nuclear Engagement Plugin

## Overview

This comprehensive troubleshooting guide helps you diagnose and resolve common issues with the Nuclear Engagement WordPress plugin. Issues are organized by category with step-by-step solutions and diagnostic tools.

## Table of Contents

- [Quick Diagnostics](#quick-diagnostics)
- [Installation Issues](#installation-issues)
- [Quiz Functionality Problems](#quiz-functionality-problems)
- [Table of Contents Issues](#table-of-contents-issues)
- [Authentication & Gold Code Problems](#authentication--gold-code-problems)
- [Performance Issues](#performance-issues)
- [Database & Data Issues](#database--data-issues)
- [Styling & Display Problems](#styling--display-problems)
- [Integration Issues](#integration-issues)
- [Error Messages Reference](#error-messages-reference)
- [Debug Mode & Logging](#debug-mode--logging)
- [Getting Help](#getting-help)

## Quick Diagnostics

### Plugin Health Check

First, run these quick checks to identify common issues:

#### 1. Plugin Status Check

```php
// Add to your theme's functions.php temporarily
function ne_health_check() {
    if (current_user_can('manage_options')) {
        $health = [];
        
        // Check if plugin is active
        $health['plugin_active'] = is_plugin_active('nuclear-engagement/nuclear-engagement.php');
        
        // Check WordPress version compatibility
        $health['wp_version_ok'] = version_compare(get_bloginfo('version'), '5.0', '>=');
        
        // Check PHP version
        $health['php_version_ok'] = version_compare(PHP_VERSION, '7.4', '>=');
        
        // Check database tables
        global $wpdb;
        $tables_exist = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}nuclear_engagement_results'");
        $health['database_ok'] = !empty($tables_exist);
        
        // Check file permissions
        $upload_dir = wp_upload_dir();
        $health['uploads_writable'] = wp_is_writable($upload_dir['basedir']);
        
        echo '<pre>Nuclear Engagement Health Check:\n';
        foreach ($health as $check => $status) {
            echo sprintf("%s: %s\n", $check, $status ? 'OK' : 'FAIL');
        }
        echo '</pre>';
    }
}
add_action('wp_footer', 'ne_health_check');
```

#### 2. JavaScript Console Check

Open your browser's developer tools (F12) and check for JavaScript errors:

- Red errors indicate critical issues
- Yellow warnings may indicate minor problems
- Look for errors containing "nuclear-engagement" or "ne-"

#### 3. Network Tab Analysis

In browser developer tools, check the Network tab:

- Failed requests (red) indicate API or resource loading issues
- Slow requests (>2 seconds) suggest performance problems
- 404 errors indicate missing files

## Installation Issues

### Plugin Won't Activate

**Symptoms:**
- Plugin appears in list but won't activate
- "Plugin could not be activated" error message
- White screen when trying to activate

**Solutions:**

1. **Check PHP Version**
   ```bash
   php -v
   ```
   Ensure PHP 7.4 or higher is installed.

2. **Verify File Permissions**
   ```bash
   chmod 755 wp-content/plugins/nuclear-engagement/
   chmod 644 wp-content/plugins/nuclear-engagement/*.php
   ```

3. **Check for Plugin Conflicts**
   - Deactivate all other plugins
   - Try activating Nuclear Engagement
   - If successful, reactivate plugins one by one to identify conflicts

4. **Review Error Logs**
   ```bash
   tail -f /path/to/wordpress/wp-content/debug.log
   ```

5. **Memory Limit Check**
   Add to wp-config.php:
   ```php
   ini_set('memory_limit', '256M');
   define('WP_MEMORY_LIMIT', '256M');
   ```

### Database Tables Not Created

**Symptoms:**
- Plugin activates but functionality doesn't work
- "Database error" messages
- Missing quiz data

**Solutions:**

1. **Manual Table Creation**
   Run this in phpMyAdmin or WP-CLI:
   ```sql
   CREATE TABLE IF NOT EXISTS wp_nuclear_engagement_results (
       id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
       quiz_id bigint(20) unsigned NOT NULL,
       user_id bigint(20) unsigned NOT NULL,
       score decimal(5,2) NOT NULL,
       time_taken int unsigned NOT NULL,
       answers longtext NOT NULL,
       created_at datetime NOT NULL,
       PRIMARY KEY (id),
       KEY quiz_user_idx (quiz_id, user_id)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
   ```

2. **Force Database Update**
   ```php
   // Add to functions.php temporarily
   function force_ne_db_update() {
       if (current_user_can('manage_options')) {
           require_once plugin_dir_path(__FILE__) . 'inc/Core/DatabaseManager.php';
           $db_manager = new \NuclearEngagement\Core\DatabaseManager();
           $db_manager->create_tables();
           echo 'Database tables created.';
       }
   }
   add_action('wp_footer', 'force_ne_db_update');
   ```

### File Upload Issues

**Symptoms:**
- Cannot upload images for quizzes
- "Permission denied" errors
- Files not appearing after upload

**Solutions:**

1. **Check Upload Directory Permissions**
   ```bash
   ls -la wp-content/uploads/
   chmod 755 wp-content/uploads/
   chown www-data:www-data wp-content/uploads/
   ```

2. **Verify PHP Upload Settings**
   ```php
   // Check current settings
   echo 'Max upload size: ' . ini_get('upload_max_filesize') . "\n";
   echo 'Max post size: ' . ini_get('post_max_size') . "\n";
   echo 'Memory limit: ' . ini_get('memory_limit') . "\n";
   ```

3. **WordPress Upload Limits**
   Add to wp-config.php:
   ```php
   @ini_set('upload_max_size', '32M');
   @ini_set('post_max_size', '32M');
   @ini_set('memory_limit', '256M');
   ```

## Quiz Functionality Problems

### Quizzes Not Displaying

**Symptoms:**
- Quiz shortcode shows nothing
- Blank space where quiz should appear
- Loading indicator never disappears

**Diagnostic Steps:**

1. **Check Shortcode Syntax**
   ```php
   // Correct syntax
   [nuclear_quiz id="123"]
   
   // Common mistakes
   [nuclear_quiz id=123]      // Missing quotes
   [nuclear-quiz id="123"]    // Wrong hyphen
   [nuclear_quiz="123"]       // Wrong format
   ```

2. **Verify Quiz Exists**
   ```sql
   SELECT * FROM wp_posts WHERE post_type = 'nuclear_quiz' AND ID = 123;
   ```

3. **Check User Permissions**
   ```php
   // Debug user capabilities
   $user = wp_get_current_user();
   echo 'User can read: ' . ($user->has_cap('read') ? 'Yes' : 'No');
   echo 'Quiz post status: ' . get_post_status(123);
   ```

**Solutions:**

1. **Clear Cache**
   - Clear all caching plugins
   - Clear browser cache
   - Clear CDN cache if applicable

2. **Check Theme Compatibility**
   ```php
   // Test with default theme
   // Switch to Twenty Twenty-Three temporarily
   ```

3. **JavaScript Dependencies**
   ```html
   <!-- Check if jQuery is loaded -->
   <script>
   if (typeof jQuery === 'undefined') {
       console.error('jQuery not loaded - Nuclear Engagement requires jQuery');
   }
   </script>
   ```

### Quiz Results Not Saving

**Symptoms:**
- Quiz completes but no results stored
- "Error saving results" message
- Results appear temporarily then disappear

**Diagnostic Steps:**

1. **Check AJAX Requests**
   In browser developer tools:
   - Network tab → Look for POST requests to `/wp-admin/admin-ajax.php`
   - Check response status (should be 200)
   - Examine response content for errors

2. **Verify Database Permissions**
   ```sql
   SHOW GRANTS FOR 'your_db_user'@'localhost';
   ```

3. **Test Manual Save**
   ```php
   // Add to functions.php for testing
   function test_quiz_save() {
       if (current_user_can('manage_options')) {
           global $wpdb;
           $result = $wpdb->insert(
               $wpdb->prefix . 'nuclear_engagement_results',
               ['quiz_id' => 1, 'user_id' => 1, 'score' => 85, 'time_taken' => 300, 'answers' => '{}', 'created_at' => current_time('mysql')],
               ['%d', '%d', '%f', '%d', '%s', '%s']
           );
           echo $result ? 'Database write successful' : 'Database write failed: ' . $wpdb->last_error;
       }
   }
   add_action('wp_footer', 'test_quiz_save');
   ```

**Solutions:**

1. **Database Connection Issues**
   ```php
   // Check database connection
   global $wpdb;
   if ($wpdb->last_error) {
       error_log('Database error: ' . $wpdb->last_error);
   }
   ```

2. **Nonce Verification Problems**
   ```javascript
   // Check if nonce is present
   console.log('WP Nonce:', window.nuclear_engagement_ajax.nonce);
   ```

3. **PHP Session Issues**
   ```php
   // Ensure sessions are working
   if (session_status() === PHP_SESSION_NONE) {
       session_start();
   }
   ```

### Quiz Timer Not Working

**Symptoms:**
- Timer doesn't appear
- Timer shows incorrect time
- Timer doesn't enforce time limits

**Solutions:**

1. **JavaScript Timezone Issues**
   ```javascript
   // Check timezone consistency
   console.log('Local time:', new Date());
   console.log('UTC time:', new Date().toISOString());
   ```

2. **Server Time Synchronization**
   ```bash
   # Check server time
   date
   ntpq -p  # Check NTP synchronization
   ```

3. **Timer Configuration**
   ```php
   // Verify timer settings
   $quiz_settings = get_post_meta($quiz_id, '_nuclear_engagement_settings', true);
   echo 'Time limit: ' . ($quiz_settings['time_limit'] ?? 'Not set');
   ```

## Table of Contents Issues

### TOC Not Generating

**Symptoms:**
- No table of contents appears
- TOC shows empty
- "No headings found" message

**Diagnostic Steps:**

1. **Check Post Content**
   ```php
   // Verify headings exist
   $content = get_post_field('post_content', $post_id);
   preg_match_all('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/i', $content, $matches);
   echo 'Found ' . count($matches[0]) . ' headings';
   ```

2. **Verify TOC Settings**
   ```php
   $toc_settings = get_option('nuclear_engagement_toc_settings');
   print_r($toc_settings);
   ```

**Solutions:**

1. **Heading Structure Issues**
   - Ensure headings follow proper hierarchy (H2, H3, H4...)
   - Check for missing heading tags
   - Verify headings have text content

2. **Plugin Conflicts**
   ```php
   // Check for conflicting TOC plugins
   $active_plugins = get_option('active_plugins');
   $toc_plugins = array_filter($active_plugins, function($plugin) {
       return strpos($plugin, 'toc') !== false || strpos($plugin, 'table-of-contents') !== false;
   });
   ```

3. **Content Filtering**
   ```php
   // Check if content is being filtered
   add_filter('the_content', function($content) {
       error_log('Content before TOC processing: ' . substr($content, 0, 200));
       return $content;
   }, 5);
   ```

### TOC Styling Issues

**Symptoms:**
- TOC appears but looks broken
- Links not working
- Formatting problems

**Solutions:**

1. **CSS Conflicts**
   ```css
   /* Check for CSS conflicts in browser dev tools */
   .nuclear-engagement .toc-wrapper {
       all: initial; /* Reset styles */
       font-family: inherit;
   }
   ```

2. **Anchor Generation**
   ```javascript
   // Verify anchors are being created
   document.querySelectorAll('.toc-link').forEach(link => {
       const target = document.querySelector(link.getAttribute('href'));
       if (!target) {
           console.warn('TOC link target not found:', link.getAttribute('href'));
       }
   });
   ```

## Authentication & Gold Code Problems

### Gold Code Not Working

**Symptoms:**
- "Invalid Gold Code" error
- Code accepted but features don't unlock
- Authentication timeouts

**Diagnostic Steps:**

1. **Verify Gold Code Format**
   ```php
   function validate_gold_code_format($code) {
       // Gold codes should be 12 alphanumeric characters
       return preg_match('/^[A-Z0-9]{12}$/', $code);
   }
   ```

2. **Check API Connectivity**
   ```bash
   curl -I https://api.nuclearengagement.com/health
   ```

3. **Test Manual Validation**
   ```php
   // Test API connection
   $response = wp_remote_get('https://api.nuclearengagement.com/validate', [
       'headers' => ['Authorization' => 'Bearer your-gold-code'],
       'timeout' => 30
   ]);
   
   echo 'Response code: ' . wp_remote_retrieve_response_code($response);
   echo 'Response body: ' . wp_remote_retrieve_body($response);
   ```

**Solutions:**

1. **Network Connectivity Issues**
   ```php
   // Check if outbound connections are allowed
   $test_response = wp_remote_get('https://httpbin.org/ip');
   if (is_wp_error($test_response)) {
       echo 'Outbound connections blocked: ' . $test_response->get_error_message();
   }
   ```

2. **Firewall/Security Plugin Blocking**
   - Temporarily disable security plugins
   - Check server firewall rules
   - Whitelist Nuclear Engagement API domains

3. **SSL Certificate Issues**
   ```php
   // Test SSL connectivity
   $response = wp_remote_get('https://api.nuclearengagement.com/', [
       'sslverify' => false  // Temporarily disable SSL verification for testing
   ]);
   ```

### API Rate Limiting

**Symptoms:**
- "Too many requests" errors
- Authentication randomly fails
- Delayed responses

**Solutions:**

1. **Check Rate Limit Headers**
   ```php
   $response = wp_remote_get('https://api.nuclearengagement.com/endpoint');
   $headers = wp_remote_retrieve_headers($response);
   echo 'Rate limit remaining: ' . $headers['X-RateLimit-Remaining'];
   ```

2. **Implement Backoff Strategy**
   ```php
   function api_request_with_backoff($url, $args = []) {
       $max_retries = 3;
       $retry_delay = 1; // seconds
       
       for ($i = 0; $i < $max_retries; $i++) {
           $response = wp_remote_get($url, $args);
           $code = wp_remote_retrieve_response_code($response);
           
           if ($code !== 429) {
               return $response;
           }
           
           sleep($retry_delay * pow(2, $i)); // Exponential backoff
       }
       
       return new WP_Error('rate_limited', 'Rate limit exceeded after retries');
   }
   ```

## Performance Issues

### Slow Loading Quizzes

**Symptoms:**
- Quizzes take >3 seconds to load
- Page becomes unresponsive
- High memory usage

**Diagnostic Steps:**

1. **Enable Query Debugging**
   ```php
   define('SAVEQUERIES', true);
   
   // Add to footer to see queries
   add_action('wp_footer', function() {
       if (current_user_can('manage_options')) {
           global $wpdb;
           echo '<pre>Total queries: ' . count($wpdb->queries) . "\n";
           foreach ($wpdb->queries as $query) {
               if ($query[1] > 0.1) { // Show slow queries
                   echo 'Slow query (' . $query[1] . 's): ' . $query[0] . "\n";
               }
           }
           echo '</pre>';
       }
   });
   ```

2. **Memory Usage Check**
   ```php
   // Monitor memory usage
   echo 'Memory usage: ' . memory_get_usage(true) / 1024 / 1024 . ' MB';
   echo 'Peak memory: ' . memory_get_peak_usage(true) / 1024 / 1024 . ' MB';
   ```

**Solutions:**

1. **Database Optimization**
   ```sql
   -- Add missing indexes
   ALTER TABLE wp_nuclear_engagement_results ADD INDEX idx_quiz_user (quiz_id, user_id);
   ALTER TABLE wp_nuclear_engagement_results ADD INDEX idx_created_at (created_at);
   
   -- Optimize tables
   OPTIMIZE TABLE wp_nuclear_engagement_results;
   ```

2. **Caching Implementation**
   ```php
   // Enable object caching
   function get_quiz_with_cache($quiz_id) {
       $cache_key = "nuclear_quiz_$quiz_id";
       $quiz = wp_cache_get($cache_key);
       
       if ($quiz === false) {
           $quiz = get_post($quiz_id);
           wp_cache_set($cache_key, $quiz, '', 3600); // Cache for 1 hour
       }
       
       return $quiz;
   }
   ```

3. **Lazy Loading**
   ```javascript
   // Implement intersection observer for quiz loading
   const quizObserver = new IntersectionObserver((entries) => {
       entries.forEach(entry => {
           if (entry.isIntersecting) {
               loadQuizContent(entry.target);
               quizObserver.unobserve(entry.target);
           }
       });
   });
   
   document.querySelectorAll('.nuclear-quiz-placeholder').forEach(quiz => {
       quizObserver.observe(quiz);
   });
   ```

### High Memory Usage

**Solutions:**

1. **Increase PHP Memory Limit**
   ```php
   ini_set('memory_limit', '512M');
   ```

2. **Optimize Data Loading**
   ```php
   // Load only necessary quiz data
   function get_quiz_minimal($quiz_id) {
       global $wpdb;
       return $wpdb->get_row($wpdb->prepare(
           "SELECT ID, post_title, post_status FROM {$wpdb->posts} WHERE ID = %d",
           $quiz_id
       ));
   }
   ```

## Database & Data Issues

### Data Corruption

**Symptoms:**
- Quiz results showing incorrect scores
- Missing quiz questions
- Database errors in logs

**Solutions:**

1. **Database Integrity Check**
   ```sql
   CHECK TABLE wp_nuclear_engagement_results;
   REPAIR TABLE wp_nuclear_engagement_results;
   ```

2. **Data Validation Script**
   ```php
   function validate_quiz_data() {
       global $wpdb;
       
       // Check for orphaned results
       $orphaned = $wpdb->get_results("
           SELECT r.* FROM {$wpdb->prefix}nuclear_engagement_results r
           LEFT JOIN {$wpdb->posts} p ON r.quiz_id = p.ID
           WHERE p.ID IS NULL
       ");
       
       echo 'Found ' . count($orphaned) . ' orphaned results';
       
       // Check for invalid scores
       $invalid_scores = $wpdb->get_results("
           SELECT * FROM {$wpdb->prefix}nuclear_engagement_results
           WHERE score < 0 OR score > 100
       ");
       
       echo 'Found ' . count($invalid_scores) . ' invalid scores';
   }
   ```

3. **Data Recovery**
   ```php
   // Restore from backup if available
   function restore_quiz_data_from_backup($backup_file) {
       if (!file_exists($backup_file)) {
           return false;
       }
       
       $backup_data = json_decode(file_get_contents($backup_file), true);
       
       global $wpdb;
       foreach ($backup_data as $record) {
           $wpdb->insert(
               $wpdb->prefix . 'nuclear_engagement_results',
               $record,
               ['%d', '%d', '%f', '%d', '%s', '%s']
           );
       }
       
       return true;
   }
   ```

### Migration Issues

**Symptoms:**
- Data missing after update
- Format errors in migrated data
- Performance degradation after migration

**Solutions:**

1. **Backup Before Migration**
   ```bash
   # Create database backup
   mysqldump -u username -p database_name > nuclear_engagement_backup.sql
   ```

2. **Gradual Migration**
   ```php
   function migrate_data_in_batches($batch_size = 100) {
       global $wpdb;
       $offset = 0;
       
       do {
           $results = $wpdb->get_results($wpdb->prepare(
               "SELECT * FROM old_table LIMIT %d OFFSET %d",
               $batch_size, $offset
           ));
           
           foreach ($results as $result) {
               // Migrate each record
               migrate_single_record($result);
           }
           
           $offset += $batch_size;
           
           // Prevent timeout
           if ($offset % 1000 === 0) {
               sleep(1);
           }
           
       } while (count($results) === $batch_size);
   }
   ```

## Styling & Display Problems

### CSS Not Loading

**Symptoms:**
- Plugin appears unstyled
- Custom theme conflicts
- Missing styles on specific pages

**Diagnostic Steps:**

1. **Verify CSS File Loading**
   ```javascript
   // Check if CSS files are loaded
   const stylesheets = Array.from(document.styleSheets);
   const neStyles = stylesheets.filter(sheet => 
       sheet.href && sheet.href.includes('nuclear-engagement')
   );
   console.log('Nuclear Engagement stylesheets loaded:', neStyles.length);
   ```

2. **Check File Permissions**
   ```bash
   ls -la wp-content/plugins/nuclear-engagement/assets/css/
   ```

**Solutions:**

1. **Force CSS Reload**
   ```php
   // Clear CSS cache
   function force_css_reload() {
       wp_dequeue_style('nuclear-engagement-styles');
       wp_enqueue_style(
           'nuclear-engagement-styles',
           plugin_dir_url(__FILE__) . 'assets/css/main.css',
           [],
           time() // Force reload with timestamp
       );
   }
   ```

2. **CSS Specificity Issues**
   ```css
   /* Increase specificity for theme conflicts */
   .nuclear-engagement-wrapper .quiz-container {
       background: #fff !important;
   }
   ```

### Responsive Design Issues

**Solutions:**

1. **Viewport Meta Tag**
   ```html
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   ```

2. **CSS Media Query Debugging**
   ```javascript
   // Test media queries
   const mediaQueries = [
       '(max-width: 768px)',
       '(max-width: 480px)',
       '(min-width: 769px)'
   ];
   
   mediaQueries.forEach(query => {
       console.log(`${query}: ${window.matchMedia(query).matches}`);
   });
   ```

## Integration Issues

### Third-Party Plugin Conflicts

**Common Conflicting Plugins:**
- Caching plugins (WP Rocket, W3 Total Cache)
- Security plugins (Wordfence, Sucuri)
- Other quiz plugins
- Page builders (Elementor, Divi)

**Solutions:**

1. **Conflict Detection**
   ```php
   function detect_plugin_conflicts() {
       $active_plugins = get_option('active_plugins');
       $known_conflicts = [
           'wp-rocket/wp-rocket.php' => 'WP Rocket caching may interfere with AJAX',
           'wordfence/wordfence.php' => 'Wordfence may block API requests',
           'quiz-master-next/mlw_quizmaster2.php' => 'Multiple quiz plugins may conflict'
       ];
       
       foreach ($known_conflicts as $plugin => $issue) {
           if (in_array($plugin, $active_plugins)) {
               echo "Potential conflict: $plugin - $issue\n";
           }
       }
   }
   ```

2. **Plugin-Specific Solutions**

   **WP Rocket:**
   ```php
   // Exclude Nuclear Engagement from caching
   add_filter('rocket_exclude_post_taxonomy', function($excluded_taxonomies) {
       $excluded_taxonomies[] = 'nuclear_quiz_category';
       return $excluded_taxonomies;
   });
   ```

   **Wordfence:**
   ```php
   // Whitelist Nuclear Engagement API endpoints
   add_filter('wordfence_ls_whitelist_ips', function($ips) {
       $ips[] = 'api.nuclearengagement.com';
       return $ips;
   });
   ```

### Theme Compatibility

**Solutions:**

1. **Theme Detection**
   ```php
   function check_theme_compatibility() {
       $theme = wp_get_theme();
       $theme_name = $theme->get('Name');
       
       $known_issues = [
           'Divi' => 'May require additional CSS for builder compatibility',
           'Avada' => 'Fusion Builder may conflict with quiz display',
           'GeneratePress' => 'Usually compatible, minimal issues'
       ];
       
       if (isset($known_issues[$theme_name])) {
           echo "Theme note: {$known_issues[$theme_name]}";
       }
   }
   ```

2. **CSS Reset for Themes**
   ```css
   .nuclear-engagement {
       /* Reset common theme interference */
       box-sizing: border-box;
       line-height: 1.5;
       color: #333;
       background: transparent;
   }
   
   .nuclear-engagement * {
       box-sizing: inherit;
   }
   ```

## Error Messages Reference

### Common Error Codes

| Error Code | Description | Solution |
|------------|-------------|----------|
| `NE_001` | Database connection failed | Check database credentials |
| `NE_002` | Invalid quiz ID | Verify quiz exists and is published |
| `NE_003` | Permission denied | Check user capabilities |
| `NE_004` | Invalid Gold Code | Verify code format and API connectivity |
| `NE_005` | Rate limit exceeded | Wait and retry, implement backoff |
| `NE_006` | File upload failed | Check permissions and file size |
| `NE_007` | JSON parsing error | Validate data format |
| `NE_008` | Session expired | Re-authenticate user |
| `NE_009` | Cache write failed | Check file permissions |
| `NE_010` | API timeout | Check network connectivity |

### Error Message Solutions

#### "Fatal error: Cannot redeclare class"

```php
// Cause: Plugin loaded multiple times
// Solution: Check for class existence
if (!class_exists('NuclearEngagement\\Core\\Plugin')) {
    require_once plugin_dir_path(__FILE__) . 'inc/Core/Plugin.php';
}
```

#### "Call to undefined function"

```php
// Cause: Missing dependency or incorrect load order
// Solution: Check plugin dependencies
if (!function_exists('wp_get_current_user')) {
    require_once(ABSPATH . 'wp-includes/pluggable.php');
}
```

#### "Headers already sent"

```php
// Cause: Output before headers
// Solution: Check for whitespace or echo statements
// Enable output buffering if necessary
ob_start();
```

## Debug Mode & Logging

### Enable Debug Mode

Add to wp-config.php:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', true);

// Nuclear Engagement specific debugging
define('NUCLEAR_ENGAGEMENT_DEBUG', true);
define('NUCLEAR_ENGAGEMENT_LOG_LEVEL', 'debug');
```

### Logging Configuration

```php
// Custom logging function
function ne_log($message, $level = 'info') {
    if (!defined('NUCLEAR_ENGAGEMENT_DEBUG') || !NUCLEAR_ENGAGEMENT_DEBUG) {
        return;
    }
    
    $log_levels = ['debug' => 1, 'info' => 2, 'warning' => 3, 'error' => 4];
    $current_level = $log_levels[NUCLEAR_ENGAGEMENT_LOG_LEVEL] ?? 2;
    
    if ($log_levels[$level] >= $current_level) {
        $timestamp = current_time('Y-m-d H:i:s');
        $log_entry = "[$timestamp] [$level] $message\n";
        
        $log_file = WP_CONTENT_DIR . '/nuclear-engagement-debug.log';
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
}

// Usage examples
ne_log('Quiz loaded successfully', 'info');
ne_log('Database query failed: ' . $wpdb->last_error, 'error');
ne_log('User answered question 1', 'debug');
```

### Database Query Logging

```php
// Enable query logging for debugging
add_action('pre_get_posts', function() {
    if (defined('NUCLEAR_ENGAGEMENT_DEBUG') && NUCLEAR_ENGAGEMENT_DEBUG) {
        add_filter('query', function($query) {
            if (strpos($query, 'nuclear_engagement') !== false) {
                ne_log('Database query: ' . $query, 'debug');
            }
            return $query;
        });
    }
});
```

### AJAX Debugging

```javascript
// Client-side AJAX debugging
jQuery(document).ajaxError(function(event, xhr, settings, error) {
    if (settings.url.includes('nuclear-engagement')) {
        console.error('Nuclear Engagement AJAX Error:', {
            url: settings.url,
            status: xhr.status,
            error: error,
            response: xhr.responseText
        });
    }
});
```

## Getting Help

### Before Contacting Support

1. **Gather Information:**
   - WordPress version
   - PHP version
   - Plugin version
   - Active theme
   - List of active plugins
   - Error messages (exact text)
   - Steps to reproduce the issue

2. **Run Diagnostics:**
   - Plugin health check
   - Browser console check
   - Database connectivity test
   - File permissions check

3. **Try Basic Solutions:**
   - Deactivate/reactivate plugin
   - Clear all caches
   - Test with default theme
   - Disable other plugins temporarily

### Support Channels

1. **Documentation:** [docs.nuclearengagement.com](https://docs.nuclearengagement.com)
2. **Community Forum:** [community.nuclearengagement.com](https://community.nuclearengagement.com)
3. **GitHub Issues:** [github.com/thinklanguages/Nuclear-Engagement-plugin](https://github.com/thinklanguages/Nuclear-Engagement-plugin)
4. **Email Support:** support@nuclearengagement.com

### Information to Include

When contacting support, please include:

```
WordPress Version: 6.x.x
PHP Version: 8.x.x
Nuclear Engagement Version: x.x.x
Theme: Theme Name (Version)
Active Plugins: (List of plugins)

Issue Description:
[Detailed description of the problem]

Steps to Reproduce:
1. Step one
2. Step two
3. Expected vs actual result

Error Messages:
[Exact error messages]

Diagnostic Information:
[Results from health check and logs]
```

### Emergency Issues

For critical security issues or site-breaking bugs:

1. **Immediate Actions:**
   - Deactivate the plugin
   - Restore from backup if necessary
   - Document the issue with screenshots

2. **Contact Support:**
   - Use "URGENT" in subject line
   - Include your license key
   - Provide temporary admin access if possible

Remember: Most issues can be resolved by following this troubleshooting guide systematically. Take time to work through the diagnostic steps before escalating to support.