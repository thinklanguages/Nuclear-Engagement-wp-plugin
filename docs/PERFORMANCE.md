# Performance Optimization Guide - Nuclear Engagement Plugin

## Overview

This guide provides comprehensive strategies for optimizing the performance of the Nuclear Engagement WordPress plugin. It covers database optimization, caching strategies, frontend performance, and monitoring techniques to ensure your quizzes and content load quickly and efficiently.

## Table of Contents

- [Performance Principles](#performance-principles)
- [Database Optimization](#database-optimization)
- [Caching Strategies](#caching-strategies)
- [Frontend Performance](#frontend-performance)
- [Server Optimization](#server-optimization)
- [Asset Optimization](#asset-optimization)
- [Monitoring & Profiling](#monitoring--profiling)
- [Performance Testing](#performance-testing)
- [Common Performance Issues](#common-performance-issues)
- [Advanced Optimizations](#advanced-optimizations)

## Performance Principles

### Core Performance Goals

1. **First Contentful Paint**: < 1.5 seconds
2. **Largest Contentful Paint**: < 2.5 seconds
3. **Cumulative Layout Shift**: < 0.1
4. **First Input Delay**: < 100ms
5. **Time to Interactive**: < 3.5 seconds

### Performance Budget

- **JavaScript Bundle**: < 200KB compressed
- **CSS Bundle**: < 50KB compressed
- **Images**: < 500KB per quiz
- **Database Queries**: < 50ms per request
- **Memory Usage**: < 64MB per request

## Database Optimization

### Index Optimization

Ensure proper database indexes for optimal query performance:

```sql
-- Core indexes for quiz results
ALTER TABLE wp_nuclear_engagement_results 
ADD INDEX idx_quiz_user (quiz_id, user_id),
ADD INDEX idx_user_created (user_id, created_at),
ADD INDEX idx_quiz_created (quiz_id, created_at),
ADD INDEX idx_score_created (score, created_at);

-- Analytics indexes
ALTER TABLE wp_nuclear_engagement_analytics
ADD INDEX idx_event_date (event_type, created_at),
ADD INDEX idx_quiz_date (quiz_id, created_at);

-- Composite indexes for common queries
ALTER TABLE wp_nuclear_engagement_results
ADD INDEX idx_quiz_user_score (quiz_id, user_id, score, created_at);
```

### Query Optimization

#### Efficient Data Retrieval

```php
<?php
/**
 * Optimized quiz data retrieval
 */
class OptimizedQuizQueries {
    
    /**
     * Get quiz results with pagination and caching
     */
    public function get_quiz_results($quiz_id, $page = 1, $per_page = 20) {
        global $wpdb;
        
        $cache_key = "quiz_results_{$quiz_id}_{$page}_{$per_page}";
        $results = wp_cache_get($cache_key, 'nuclear_engagement');
        
        if ($results === false) {
            $offset = ($page - 1) * $per_page;
            
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT r.*, u.display_name 
                 FROM {$wpdb->prefix}nuclear_engagement_results r
                 JOIN {$wpdb->users} u ON r.user_id = u.ID
                 WHERE r.quiz_id = %d
                 ORDER BY r.created_at DESC
                 LIMIT %d OFFSET %d",
                $quiz_id,
                $per_page,
                $offset
            ));
            
            wp_cache_set($cache_key, $results, 'nuclear_engagement', 3600);
        }
        
        return $results;
    }
    
    /**
     * Bulk metadata retrieval to reduce queries
     */
    public function get_quizzes_with_metadata($quiz_ids) {
        if (empty($quiz_ids)) {
            return [];
        }
        
        // Get posts in single query
        $posts = get_posts([
            'include' => $quiz_ids,
            'post_type' => 'nuclear_quiz',
            'post_status' => 'publish',
            'numberposts' => -1
        ]);
        
        // Bulk load metadata
        $metadata = $this->get_bulk_metadata($quiz_ids);
        
        // Combine posts with metadata
        $quizzes = [];
        foreach ($posts as $post) {
            $quizzes[$post->ID] = [
                'post' => $post,
                'metadata' => $metadata[$post->ID] ?? []
            ];
        }
        
        return $quizzes;
    }
    
    /**
     * Optimized bulk metadata retrieval
     */
    private function get_bulk_metadata($quiz_ids) {
        global $wpdb;
        
        $placeholders = implode(',', array_fill(0, count($quiz_ids), '%d'));
        
        $metadata = $wpdb->get_results($wpdb->prepare(
            "SELECT post_id, meta_key, meta_value 
             FROM {$wpdb->postmeta} 
             WHERE post_id IN ($placeholders) 
             AND meta_key LIKE '_nuclear_engagement_%'",
            ...$quiz_ids
        ));
        
        // Group by post ID
        $grouped_metadata = [];
        foreach ($metadata as $meta) {
            $grouped_metadata[$meta->post_id][$meta->meta_key] = $meta->meta_value;
        }
        
        return $grouped_metadata;
    }
    
    /**
     * Optimized analytics queries with aggregation
     */
    public function get_quiz_analytics($quiz_id, $start_date, $end_date) {
        global $wpdb;
        
        $cache_key = "quiz_analytics_{$quiz_id}_" . md5($start_date . $end_date);
        $analytics = wp_cache_get($cache_key, 'nuclear_engagement');
        
        if ($analytics === false) {
            $analytics = $wpdb->get_row($wpdb->prepare(
                "SELECT 
                    COUNT(*) as total_attempts,
                    COUNT(DISTINCT user_id) as unique_users,
                    AVG(score) as average_score,
                    AVG(time_taken) as average_time,
                    MAX(score) as highest_score,
                    MIN(score) as lowest_score,
                    SUM(CASE WHEN score >= 70 THEN 1 ELSE 0 END) as passed_count
                 FROM {$wpdb->prefix}nuclear_engagement_results
                 WHERE quiz_id = %d
                 AND created_at BETWEEN %s AND %s",
                $quiz_id,
                $start_date,
                $end_date
            ));
            
            wp_cache_set($cache_key, $analytics, 'nuclear_engagement', 1800);
        }
        
        return $analytics;
    }
}
```

### Database Maintenance

```php
<?php
/**
 * Database maintenance and optimization
 */
class DatabaseMaintenance {
    
    /**
     * Clean up old data and optimize tables
     */
    public function run_maintenance() {
        $this->cleanup_old_data();
        $this->optimize_tables();
        $this->update_statistics();
    }
    
    /**
     * Remove old quiz results and analytics data
     */
    private function cleanup_old_data() {
        global $wpdb;
        
        // Remove results older than 2 years
        $cutoff_date = date('Y-m-d', strtotime('-2 years'));
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}nuclear_engagement_results 
             WHERE created_at < %s",
            $cutoff_date
        ));
        
        // Remove orphaned analytics data
        $wpdb->query(
            "DELETE a FROM {$wpdb->prefix}nuclear_engagement_analytics a
             LEFT JOIN {$wpdb->posts} p ON a.quiz_id = p.ID
             WHERE p.ID IS NULL"
        );
    }
    
    /**
     * Optimize database tables
     */
    private function optimize_tables() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'nuclear_engagement_results',
            $wpdb->prefix . 'nuclear_engagement_analytics'
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("OPTIMIZE TABLE $table");
        }
    }
    
    /**
     * Update table statistics for query optimizer
     */
    private function update_statistics() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'nuclear_engagement_results',
            $wpdb->prefix . 'nuclear_engagement_analytics'
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("ANALYZE TABLE $table");
        }
    }
}

// Schedule maintenance
if (!wp_next_scheduled('nuclear_engagement_maintenance')) {
    wp_schedule_event(time(), 'weekly', 'nuclear_engagement_maintenance');
}

add_action('nuclear_engagement_maintenance', function() {
    $maintenance = new DatabaseMaintenance();
    $maintenance->run_maintenance();
});
```

## Caching Strategies

### Object Caching

```php
<?php
/**
 * Advanced caching implementation
 */
class CacheManager {
    
    private $cache_groups = [
        'quizzes' => 3600,        // 1 hour
        'results' => 1800,        // 30 minutes
        'analytics' => 7200,      // 2 hours
        'user_data' => 900        // 15 minutes
    ];
    
    /**
     * Get cached data with fallback
     */
    public function get($key, $group = 'default', $callback = null) {
        $cached_data = wp_cache_get($key, $group);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        if ($callback && is_callable($callback)) {
            $data = call_user_func($callback);
            $this->set($key, $data, $group);
            return $data;
        }
        
        return false;
    }
    
    /**
     * Set cached data with appropriate expiration
     */
    public function set($key, $data, $group = 'default') {
        $expiration = $this->cache_groups[$group] ?? 3600;
        return wp_cache_set($key, $data, $group, $expiration);
    }
    
    /**
     * Cache quiz data with dependencies
     */
    public function cache_quiz($quiz_id) {
        return $this->get(
            "quiz_$quiz_id",
            'quizzes',
            function() use ($quiz_id) {
                $quiz = get_post($quiz_id);
                $questions = get_post_meta($quiz_id, '_nuclear_engagement_questions', true);
                $settings = get_post_meta($quiz_id, '_nuclear_engagement_settings', true);
                
                return [
                    'quiz' => $quiz,
                    'questions' => $questions,
                    'settings' => $settings,
                    'cached_at' => time()
                ];
            }
        );
    }
    
    /**
     * Cache user quiz progress
     */
    public function cache_user_progress($user_id, $quiz_id) {
        $cache_key = "user_progress_{$user_id}_{$quiz_id}";
        
        return $this->get(
            $cache_key,
            'user_data',
            function() use ($user_id, $quiz_id) {
                global $wpdb;
                
                return $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}nuclear_engagement_results 
                     WHERE user_id = %d AND quiz_id = %d 
                     ORDER BY created_at DESC LIMIT 1",
                    $user_id,
                    $quiz_id
                ));
            }
        );
    }
    
    /**
     * Invalidate cache when content changes
     */
    public function invalidate_quiz_cache($quiz_id) {
        wp_cache_delete("quiz_$quiz_id", 'quizzes');
        wp_cache_delete("quiz_results_$quiz_id", 'results');
        wp_cache_delete("quiz_analytics_$quiz_id", 'analytics');
        
        // Clear related caches
        $this->clear_group_cache('quizzes', "quiz_$quiz_id");
    }
    
    /**
     * Clear cache group with pattern matching
     */
    private function clear_group_cache($group, $pattern) {
        global $wp_object_cache;
        
        if (method_exists($wp_object_cache, 'flush_group')) {
            $wp_object_cache->flush_group($group);
        }
    }
}
```

### Fragment Caching

```php
<?php
/**
 * Template fragment caching
 */
class FragmentCache {
    
    /**
     * Cache quiz output with automatic invalidation
     */
    public function cache_quiz_output($quiz_id, $user_id = null) {
        $cache_key = "quiz_output_{$quiz_id}";
        if ($user_id) {
            $cache_key .= "_user_{$user_id}";
        }
        
        $output = get_transient($cache_key);
        
        if ($output === false) {
            ob_start();
            $this->render_quiz($quiz_id, $user_id);
            $output = ob_get_clean();
            
            // Cache for 30 minutes
            set_transient($cache_key, $output, 1800);
        }
        
        return $output;
    }
    
    /**
     * Cache quiz results display
     */
    public function cache_quiz_results($quiz_id, $page = 1) {
        $cache_key = "quiz_results_display_{$quiz_id}_page_{$page}";
        
        $output = get_transient($cache_key);
        
        if ($output === false) {
            ob_start();
            $this->render_quiz_results($quiz_id, $page);
            $output = ob_get_clean();
            
            // Cache for 15 minutes
            set_transient($cache_key, $output, 900);
        }
        
        return $output;
    }
    
    /**
     * Smart cache invalidation on content changes
     */
    public function invalidate_fragment_cache($quiz_id) {
        global $wpdb;
        
        // Delete all related transients
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE %s",
            '_transient_quiz_output_' . $quiz_id . '%'
        ));
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE %s",
            '_transient_quiz_results_display_' . $quiz_id . '%'
        ));
    }
}
```

### Page Caching Integration

```php
<?php
/**
 * Integration with popular caching plugins
 */
class PageCacheIntegration {
    
    public function __construct() {
        // WP Rocket integration
        add_filter('rocket_exclude_post_taxonomy', [$this, 'exclude_quiz_taxonomy']);
        add_filter('rocket_cache_dynamic_cookie', [$this, 'add_dynamic_cookies']);
        
        // W3 Total Cache integration
        add_filter('w3tc_can_cache', [$this, 'w3tc_cache_control'], 10, 2);
        
        // WP Super Cache integration
        add_action('wp_cache_served_cache_file', [$this, 'wpsc_cache_control']);
    }
    
    /**
     * Exclude quiz-related content from aggressive caching
     */
    public function exclude_quiz_taxonomy($excluded_taxonomies) {
        $excluded_taxonomies[] = 'nuclear_quiz_category';
        return $excluded_taxonomies;
    }
    
    /**
     * Add dynamic cookies for personalized content
     */
    public function add_dynamic_cookies($cookies) {
        $cookies[] = 'nuclear_engagement_progress';
        $cookies[] = 'nuclear_engagement_user_id';
        return $cookies;
    }
    
    /**
     * Control W3 Total Cache behavior
     */
    public function w3tc_cache_control($can_cache, $request_uri) {
        // Don't cache quiz submission pages
        if (strpos($request_uri, 'quiz-submit') !== false) {
            return false;
        }
        
        // Don't cache user-specific quiz pages
        if (isset($_COOKIE['nuclear_engagement_progress'])) {
            return false;
        }
        
        return $can_cache;
    }
    
    /**
     * WP Super Cache control
     */
    public function wpsc_cache_control() {
        global $cache_enabled;
        
        if (is_singular('nuclear_quiz') && is_user_logged_in()) {
            $cache_enabled = false;
        }
    }
}
```

## Frontend Performance

### JavaScript Optimization

```javascript
// Optimized quiz loading with lazy loading
class QuizLoader {
    constructor() {
        this.loadedQuizzes = new Set();
        this.observeQuizElements();
    }
    
    /**
     * Use Intersection Observer for lazy loading
     */
    observeQuizElements() {
        const quizElements = document.querySelectorAll('.nuclear-quiz[data-lazy="true"]');
        
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.loadQuiz(entry.target);
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                rootMargin: '50px 0px',
                threshold: 0.1
            });
            
            quizElements.forEach(element => observer.observe(element));
        } else {
            // Fallback for older browsers
            quizElements.forEach(element => this.loadQuiz(element));
        }
    }
    
    /**
     * Load quiz content dynamically
     */
    async loadQuiz(element) {
        const quizId = element.dataset.quizId;
        
        if (this.loadedQuizzes.has(quizId)) {
            return;
        }
        
        try {
            // Show loading state
            element.innerHTML = this.getLoadingHTML();
            
            // Fetch quiz data
            const response = await fetch(`/wp-json/nuclear-engagement/v1/quizzes/${quizId}`, {
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.nuclear_engagement_nonce
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const quizData = await response.json();
            
            // Render quiz
            element.innerHTML = this.renderQuiz(quizData);
            
            // Initialize quiz functionality
            new QuizInterface(element);
            
            this.loadedQuizzes.add(quizId);
            
        } catch (error) {
            console.error('Failed to load quiz:', error);
            element.innerHTML = this.getErrorHTML();
        }
    }
    
    /**
     * Optimized quiz rendering
     */
    renderQuiz(quizData) {
        const { questions, settings } = quizData;
        
        // Use template literals for efficient string building
        const questionsHTML = questions.map((question, index) => `
            <div class="quiz-question" data-question="${index}">
                <h3>${this.escapeHtml(question.question)}</h3>
                <div class="quiz-answers">
                    ${question.answers.map((answer, answerIndex) => `
                        <button class="quiz-answer" data-answer="${answerIndex}">
                            ${this.escapeHtml(answer.text)}
                        </button>
                    `).join('')}
                </div>
            </div>
        `).join('');
        
        return `
            <div class="quiz-container">
                ${settings.show_progress ? this.getProgressHTML() : ''}
                <div class="quiz-questions">${questionsHTML}</div>
                <div class="quiz-actions">
                    <button class="quiz-submit" disabled>Submit Quiz</button>
                </div>
            </div>
        `;
    }
    
    /**
     * Efficient HTML escaping
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    getLoadingHTML() {
        return `
            <div class="quiz-loading">
                <div class="loading-spinner"></div>
                <p>Loading quiz...</p>
            </div>
        `;
    }
    
    getErrorHTML() {
        return `
            <div class="quiz-error">
                <p>Unable to load quiz. Please try again later.</p>
            </div>
        `;
    }
    
    getProgressHTML() {
        return `
            <div class="quiz-progress">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: 0%"></div>
                </div>
                <span class="progress-text">Question 1 of 0</span>
            </div>
        `;
    }
}

// Initialize with performance optimization
document.addEventListener('DOMContentLoaded', () => {
    // Defer initialization to avoid blocking main thread
    requestIdleCallback(() => {
        new QuizLoader();
    }, { timeout: 2000 });
});
```

### CSS Optimization

```css
/* Performance-optimized CSS */

/* Use CSS containment for better performance */
.nuclear-quiz {
    contain: layout style paint;
    will-change: transform; /* Hint for GPU optimization */
}

/* Optimize animations for 60fps */
.quiz-answer {
    transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1),
                background-color 0.2s ease;
}

.quiz-answer:hover {
    transform: translateY(-2px); /* Use transform instead of changing position */
}

/* Efficient loading states */
.quiz-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 200px;
}

.loading-spinner {
    width: 40px;
    height: 40px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #3498db;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Optimize for Largest Contentful Paint */
.quiz-question h3 {
    font-display: swap; /* Ensure text is visible during font load */
}

/* Reduce layout shifts */
.quiz-container {
    min-height: 400px; /* Reserve space to prevent layout shift */
}

/* Optimize critical rendering path */
.nuclear-quiz.above-fold {
    /* Styles for above-the-fold quizzes */
    opacity: 1;
    transform: none;
}

.nuclear-quiz.below-fold {
    /* Defer non-critical styles */
    opacity: 0;
    transform: translateY(20px);
    transition: opacity 0.3s ease, transform 0.3s ease;
}

.nuclear-quiz.below-fold.loaded {
    opacity: 1;
    transform: none;
}
```

### Asset Loading Optimization

```php
<?php
/**
 * Optimized asset loading
 */
class AssetOptimization {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_optimized_assets']);
        add_filter('script_loader_tag', [$this, 'add_async_defer'], 10, 3);
        add_filter('style_loader_tag', [$this, 'add_preload_hints'], 10, 4);
    }
    
    /**
     * Conditionally load assets only when needed
     */
    public function enqueue_optimized_assets() {
        global $post;
        
        // Only load quiz assets on pages that need them
        if (is_singular('nuclear_quiz') || $this->has_quiz_shortcode()) {
            // Critical CSS inline
            $this->inline_critical_css();
            
            // Main CSS with preload
            wp_enqueue_style(
                'nuclear-engagement-main',
                plugin_dir_url(__FILE__) . 'assets/css/main.min.css',
                [],
                $this->get_asset_version()
            );
            
            // JavaScript with defer
            wp_enqueue_script(
                'nuclear-engagement-quiz',
                plugin_dir_url(__FILE__) . 'assets/js/quiz.min.js',
                ['jquery'],
                $this->get_asset_version(),
                true
            );
            
            // Add preconnect for external APIs
            $this->add_preconnect_hints();
        }
        
        // Load admin assets only in admin
        if (is_admin()) {
            $this->enqueue_admin_assets();
        }
    }
    
    /**
     * Inline critical CSS for above-the-fold content
     */
    private function inline_critical_css() {
        $critical_css = $this->get_critical_css();
        if ($critical_css) {
            echo "<style id='nuclear-engagement-critical'>{$critical_css}</style>";
        }
    }
    
    /**
     * Add async/defer attributes to scripts
     */
    public function add_async_defer($tag, $handle, $src) {
        $async_scripts = [
            'nuclear-engagement-analytics',
            'nuclear-engagement-tracking'
        ];
        
        $defer_scripts = [
            'nuclear-engagement-quiz',
            'nuclear-engagement-toc'
        ];
        
        if (in_array($handle, $async_scripts)) {
            return str_replace('<script ', '<script async ', $tag);
        }
        
        if (in_array($handle, $defer_scripts)) {
            return str_replace('<script ', '<script defer ', $tag);
        }
        
        return $tag;
    }
    
    /**
     * Add preload hints for critical resources
     */
    public function add_preload_hints($html, $handle, $href, $media) {
        $preload_styles = [
            'nuclear-engagement-main',
            'nuclear-engagement-critical'
        ];
        
        if (in_array($handle, $preload_styles)) {
            $preload = sprintf(
                '<link rel="preload" href="%s" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">',
                esc_url($href)
            );
            return $preload . $html;
        }
        
        return $html;
    }
    
    /**
     * Add DNS preconnect for external resources
     */
    private function add_preconnect_hints() {
        echo '<link rel="preconnect" href="https://api.nuclearengagement.com">';
        echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
        echo '<link rel="dns-prefetch" href="//www.google-analytics.com">';
    }
    
    /**
     * Check if page contains quiz shortcode
     */
    private function has_quiz_shortcode() {
        global $post;
        
        if (!$post) {
            return false;
        }
        
        return has_shortcode($post->post_content, 'nuclear_quiz') ||
               has_shortcode($post->post_content, 'nuclear_toc');
    }
    
    /**
     * Get optimized asset version for cache busting
     */
    private function get_asset_version() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return time(); // Always fresh in development
        }
        
        static $version = null;
        if ($version === null) {
            $version = get_option('nuclear_engagement_asset_version', '1.0.0');
        }
        
        return $version;
    }
    
    /**
     * Load critical CSS content
     */
    private function get_critical_css() {
        $critical_css_file = plugin_dir_path(__FILE__) . 'assets/css/critical.css';
        
        if (file_exists($critical_css_file)) {
            return file_get_contents($critical_css_file);
        }
        
        return false;
    }
}
```

## Server Optimization

### PHP Performance

```php
<?php
/**
 * PHP performance optimizations
 */
class PHPOptimization {
    
    /**
     * Optimize PHP settings for Nuclear Engagement
     */
    public function optimize_php_settings() {
        // Increase memory limit for complex quizzes
        if (ini_get('memory_limit') < 256) {
            ini_set('memory_limit', '256M');
        }
        
        // Optimize opcache if available
        if (extension_loaded('opcache')) {
            ini_set('opcache.enable', 1);
            ini_set('opcache.memory_consumption', 128);
            ini_set('opcache.max_accelerated_files', 4000);
            ini_set('opcache.revalidate_freq', 60);
        }
        
        // Enable APCu for object caching if available
        if (extension_loaded('apcu') && !wp_using_ext_object_cache()) {
            wp_cache_init();
        }
    }
    
    /**
     * Optimize autoloader for better class loading
     */
    public function optimize_autoloader() {
        // Use optimized composer autoloader
        if (file_exists(plugin_dir_path(__FILE__) . 'vendor/autoload.php')) {
            require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';
        }
        
        // Custom autoloader for plugin classes
        spl_autoload_register([$this, 'plugin_autoloader'], true, true);
    }
    
    /**
     * Efficient class autoloading
     */
    public function plugin_autoloader($class_name) {
        if (strpos($class_name, 'NuclearEngagement\\') !== 0) {
            return;
        }
        
        $class_file = str_replace(
            ['NuclearEngagement\\', '\\'],
            ['', '/'],
            $class_name
        ) . '.php';
        
        $file_path = plugin_dir_path(__FILE__) . 'inc/' . $class_file;
        
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }
    
    /**
     * Reduce plugin overhead
     */
    public function reduce_overhead() {
        // Disable WordPress features not needed by the plugin
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'wp_generator');
        remove_action('wp_head', 'wp_shortlink_wp_head');
        
        // Optimize WordPress queries
        add_filter('posts_clauses', [$this, 'optimize_quiz_queries'], 10, 2);
    }
    
    /**
     * Optimize database queries for quizzes
     */
    public function optimize_quiz_queries($clauses, $query) {
        global $wpdb;
        
        if ($query->get('post_type') === 'nuclear_quiz') {
            // Add specific optimizations for quiz queries
            $clauses['join'] = $this->optimize_meta_joins($clauses['join']);
            $clauses['where'] = $this->optimize_meta_where($clauses['where']);
        }
        
        return $clauses;
    }
}
```

### Database Configuration

```sql
-- MySQL/MariaDB optimization for Nuclear Engagement
-- Add to my.cnf or equivalent

[mysql]
# Connection settings
max_connections = 200
max_user_connections = 50

# Buffer settings
innodb_buffer_pool_size = 1G
key_buffer_size = 256M
sort_buffer_size = 2M
read_buffer_size = 2M
read_rnd_buffer_size = 8M

# Query cache (MySQL 5.7 and earlier)
query_cache_type = 1
query_cache_size = 128M
query_cache_limit = 2M

# InnoDB settings
innodb_file_per_table = 1
innodb_flush_log_at_trx_commit = 2
innodb_log_buffer_size = 32M
innodb_log_file_size = 128M

# Slow query logging
slow_query_log = 1
long_query_time = 1
log_queries_not_using_indexes = 1
```

## Asset Optimization

### Image Optimization

```php
<?php
/**
 * Image optimization for quiz content
 */
class ImageOptimization {
    
    public function __construct() {
        add_filter('wp_handle_upload_prefilter', [$this, 'optimize_upload']);
        add_filter('wp_generate_attachment_metadata', [$this, 'generate_webp_versions'], 10, 2);
        add_filter('wp_get_attachment_image_src', [$this, 'serve_webp_if_supported'], 10, 4);
    }
    
    /**
     * Optimize images during upload
     */
    public function optimize_upload($file) {
        if (!$this->is_image($file['type'])) {
            return $file;
        }
        
        // Resize large images
        $max_width = 1920;
        $max_height = 1080;
        
        $image_info = getimagesize($file['tmp_name']);
        if ($image_info[0] > $max_width || $image_info[1] > $max_height) {
            $this->resize_image($file['tmp_name'], $max_width, $max_height, $file['type']);
        }
        
        // Compress image
        $this->compress_image($file['tmp_name'], $file['type']);
        
        return $file;
    }
    
    /**
     * Generate WebP versions of images
     */
    public function generate_webp_versions($metadata, $attachment_id) {
        $file_path = get_attached_file($attachment_id);
        
        if ($this->is_image(mime_content_type($file_path))) {
            $webp_path = $this->convert_to_webp($file_path);
            
            if ($webp_path) {
                $metadata['webp_versions'] = [$webp_path];
                
                // Generate WebP versions for all image sizes
                if (isset($metadata['sizes'])) {
                    foreach ($metadata['sizes'] as $size => $size_data) {
                        $size_path = path_join(dirname($file_path), $size_data['file']);
                        $webp_size_path = $this->convert_to_webp($size_path);
                        
                        if ($webp_size_path) {
                            $metadata['sizes'][$size]['webp'] = basename($webp_size_path);
                        }
                    }
                }
            }
        }
        
        return $metadata;
    }
    
    /**
     * Serve WebP images when supported
     */
    public function serve_webp_if_supported($image, $attachment_id, $size, $icon) {
        if (!$this->browser_supports_webp()) {
            return $image;
        }
        
        $metadata = wp_get_attachment_metadata($attachment_id);
        
        if (isset($metadata['webp_versions'])) {
            $webp_url = $this->get_webp_url($image[0]);
            if ($webp_url) {
                $image[0] = $webp_url;
            }
        }
        
        return $image;
    }
    
    /**
     * Check if browser supports WebP
     */
    private function browser_supports_webp() {
        return isset($_SERVER['HTTP_ACCEPT']) && 
               strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false;
    }
    
    /**
     * Convert image to WebP format
     */
    private function convert_to_webp($source_path) {
        if (!function_exists('imagewebp')) {
            return false;
        }
        
        $image_info = getimagesize($source_path);
        $mime_type = $image_info['mime'];
        
        switch ($mime_type) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($source_path);
                break;
            case 'image/png':
                $image = imagecreatefrompng($source_path);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($source_path);
                break;
            default:
                return false;
        }
        
        $webp_path = preg_replace('/\.(jpg|jpeg|png|gif)$/i', '.webp', $source_path);
        
        if (imagewebp($image, $webp_path, 80)) {
            imagedestroy($image);
            return $webp_path;
        }
        
        return false;
    }
    
    /**
     * Compress image based on type
     */
    private function compress_image($file_path, $mime_type) {
        switch ($mime_type) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($file_path);
                imagejpeg($image, $file_path, 85);
                break;
            case 'image/png':
                $image = imagecreatefrompng($file_path);
                imagepng($image, $file_path, 6);
                break;
        }
        
        if (isset($image)) {
            imagedestroy($image);
        }
    }
}
```

### JavaScript Bundling

```javascript
// webpack.config.js - Optimized build configuration
const path = require('path');
const TerserPlugin = require('terser-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const OptimizeCSSAssetsPlugin = require('optimize-css-assets-webpack-plugin');
const BundleAnalyzerPlugin = require('webpack-bundle-analyzer').BundleAnalyzerPlugin;

module.exports = (env, argv) => {
    const isProduction = argv.mode === 'production';
    
    return {
        entry: {
            quiz: './assets/js/quiz.js',
            admin: './assets/js/admin.js',
            toc: './assets/js/toc.js'
        },
        
        output: {
            path: path.resolve(__dirname, 'assets/dist'),
            filename: isProduction ? '[name].[contenthash].min.js' : '[name].js',
            clean: true
        },
        
        optimization: {
            minimizer: [
                new TerserPlugin({
                    terserOptions: {
                        compress: {
                            drop_console: isProduction,
                            drop_debugger: isProduction
                        }
                    }
                }),
                new OptimizeCSSAssetsPlugin()
            ],
            
            splitChunks: {
                chunks: 'all',
                cacheGroups: {
                    vendor: {
                        test: /[\\/]node_modules[\\/]/,
                        name: 'vendors',
                        chunks: 'all'
                    },
                    common: {
                        name: 'common',
                        minChunks: 2,
                        chunks: 'all',
                        enforce: true
                    }
                }
            }
        },
        
        plugins: [
            new MiniCssExtractPlugin({
                filename: isProduction ? '[name].[contenthash].min.css' : '[name].css'
            }),
            
            ...(process.env.ANALYZE ? [new BundleAnalyzerPlugin()] : [])
        ],
        
        module: {
            rules: [
                {
                    test: /\.js$/,
                    exclude: /node_modules/,
                    use: {
                        loader: 'babel-loader',
                        options: {
                            presets: ['@babel/preset-env'],
                            plugins: ['@babel/plugin-proposal-class-properties']
                        }
                    }
                },
                
                {
                    test: /\.css$/,
                    use: [
                        MiniCssExtractPlugin.loader,
                        'css-loader',
                        'postcss-loader'
                    ]
                }
            ]
        }
    };
};
```

## Monitoring & Profiling

### Performance Monitoring

```php
<?php
/**
 * Performance monitoring and profiling
 */
class PerformanceMonitor {
    
    private $metrics = [];
    private $start_time;
    private $start_memory;
    
    public function __construct() {
        $this->start_time = microtime(true);
        $this->start_memory = memory_get_usage();
        
        add_action('wp_footer', [$this, 'output_performance_data']);
        add_action('shutdown', [$this, 'log_performance_data']);
    }
    
    /**
     * Start timing a specific operation
     */
    public function start_timer($operation) {
        $this->metrics[$operation] = [
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage()
        ];
    }
    
    /**
     * End timing and record metrics
     */
    public function end_timer($operation) {
        if (!isset($this->metrics[$operation])) {
            return;
        }
        
        $this->metrics[$operation]['end_time'] = microtime(true);
        $this->metrics[$operation]['end_memory'] = memory_get_usage();
        $this->metrics[$operation]['duration'] = 
            $this->metrics[$operation]['end_time'] - $this->metrics[$operation]['start_time'];
        $this->metrics[$operation]['memory_used'] = 
            $this->metrics[$operation]['end_memory'] - $this->metrics[$operation]['start_memory'];
    }
    
    /**
     * Profile database queries
     */
    public function profile_queries() {
        global $wpdb;
        
        if (defined('SAVEQUERIES') && SAVEQUERIES) {
            $total_time = 0;
            $slow_queries = [];
            
            foreach ($wpdb->queries as $query) {
                $total_time += $query[1];
                
                if ($query[1] > 0.05) { // Queries slower than 50ms
                    $slow_queries[] = [
                        'sql' => $query[0],
                        'time' => $query[1],
                        'stack' => $query[2]
                    ];
                }
            }
            
            $this->metrics['database'] = [
                'total_queries' => count($wpdb->queries),
                'total_time' => $total_time,
                'slow_queries' => $slow_queries
            ];
        }
    }
    
    /**
     * Monitor memory usage patterns
     */
    public function monitor_memory() {
        $this->metrics['memory'] = [
            'current_usage' => memory_get_usage(),
            'peak_usage' => memory_get_peak_usage(),
            'current_real' => memory_get_usage(true),
            'peak_real' => memory_get_peak_usage(true)
        ];
    }
    
    /**
     * Output performance data for debugging
     */
    public function output_performance_data() {
        if (!current_user_can('manage_options') || !defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $this->profile_queries();
        $this->monitor_memory();
        
        $total_time = microtime(true) - $this->start_time;
        $total_memory = memory_get_peak_usage() - $this->start_memory;
        
        echo "<!-- Nuclear Engagement Performance Data\n";
        echo "Total Execution Time: " . round($total_time * 1000, 2) . "ms\n";
        echo "Peak Memory Usage: " . round($total_memory / 1024 / 1024, 2) . "MB\n";
        
        if (isset($this->metrics['database'])) {
            echo "Database Queries: " . $this->metrics['database']['total_queries'] . "\n";
            echo "Database Time: " . round($this->metrics['database']['total_time'] * 1000, 2) . "ms\n";
            
            if (!empty($this->metrics['database']['slow_queries'])) {
                echo "Slow Queries:\n";
                foreach ($this->metrics['database']['slow_queries'] as $query) {
                    echo "  - " . round($query['time'] * 1000, 2) . "ms: " . 
                         substr(str_replace(["\n", "\t"], ' ', $query['sql']), 0, 100) . "...\n";
                }
            }
        }
        
        foreach ($this->metrics as $operation => $data) {
            if (isset($data['duration'])) {
                echo "{$operation}: " . round($data['duration'] * 1000, 2) . "ms\n";
            }
        }
        
        echo "-->\n";
    }
    
    /**
     * Log performance data for analysis
     */
    public function log_performance_data() {
        if (!defined('NUCLEAR_ENGAGEMENT_PERFORMANCE_LOG') || !NUCLEAR_ENGAGEMENT_PERFORMANCE_LOG) {
            return;
        }
        
        $performance_data = [
            'timestamp' => current_time('c'),
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'execution_time' => microtime(true) - $this->start_time,
            'memory_usage' => memory_get_peak_usage(),
            'metrics' => $this->metrics
        ];
        
        error_log('Nuclear Engagement Performance: ' . json_encode($performance_data));
    }
}

// Initialize performance monitoring
if (defined('WP_DEBUG') && WP_DEBUG) {
    new PerformanceMonitor();
}
```

### Core Web Vitals Monitoring

```javascript
// Core Web Vitals monitoring
class WebVitalsMonitor {
    constructor() {
        this.metrics = {};
        this.initializeMonitoring();
    }
    
    async initializeMonitoring() {
        // Import web-vitals library
        const { getCLS, getFID, getFCP, getLCP, getTTFB } = await import('web-vitals');
        
        // Monitor all Core Web Vitals
        getCLS(this.handleMetric.bind(this, 'CLS'));
        getFID(this.handleMetric.bind(this, 'FID'));
        getFCP(this.handleMetric.bind(this, 'FCP'));
        getLCP(this.handleMetric.bind(this, 'LCP'));
        getTTFB(this.handleMetric.bind(this, 'TTFB'));
        
        // Send metrics after page lifecycle events
        this.setupReporting();
    }
    
    handleMetric(metricName, metric) {
        this.metrics[metricName] = {
            value: metric.value,
            rating: this.getRating(metricName, metric.value),
            entries: metric.entries
        };
        
        console.log(`${metricName}: ${metric.value} (${this.metrics[metricName].rating})`);
    }
    
    getRating(metricName, value) {
        const thresholds = {
            'CLS': { good: 0.1, poor: 0.25 },
            'FID': { good: 100, poor: 300 },
            'FCP': { good: 1800, poor: 3000 },
            'LCP': { good: 2500, poor: 4000 },
            'TTFB': { good: 800, poor: 1800 }
        };
        
        const threshold = thresholds[metricName];
        if (!threshold) return 'unknown';
        
        if (value <= threshold.good) return 'good';
        if (value <= threshold.poor) return 'needs-improvement';
        return 'poor';
    }
    
    setupReporting() {
        // Report on visibility change (tab switch, navigation)
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden') {
                this.sendMetrics();
            }
        });
        
        // Report on page unload
        window.addEventListener('beforeunload', () => {
            this.sendMetrics();
        });
        
        // Report periodically for long sessions
        setInterval(() => {
            this.sendMetrics();
        }, 30000); // Every 30 seconds
    }
    
    sendMetrics() {
        if (Object.keys(this.metrics).length === 0) return;
        
        const data = {
            url: window.location.pathname,
            user_agent: navigator.userAgent,
            metrics: this.metrics,
            timestamp: new Date().toISOString(),
            quiz_id: this.getQuizId()
        };
        
        // Use sendBeacon for reliable delivery
        if (navigator.sendBeacon) {
            navigator.sendBeacon('/wp-json/nuclear-engagement/v1/analytics/web-vitals', 
                                 JSON.stringify(data));
        } else {
            // Fallback to fetch
            fetch('/wp-json/nuclear-engagement/v1/analytics/web-vitals', {
                method: 'POST',
                body: JSON.stringify(data),
                headers: {
                    'Content-Type': 'application/json'
                },
                keepalive: true
            }).catch(console.error);
        }
    }
    
    getQuizId() {
        const quizElement = document.querySelector('.nuclear-quiz[data-quiz-id]');
        return quizElement ? quizElement.dataset.quizId : null;
    }
}

// Initialize monitoring
new WebVitalsMonitor();
```

This performance optimization guide provides a comprehensive approach to ensuring the Nuclear Engagement plugin runs efficiently at scale. Regular monitoring and optimization ensure users have the best possible experience with your quizzes and content.