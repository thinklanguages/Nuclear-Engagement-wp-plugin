# Deployment Guide - Nuclear Engagement Plugin

## Overview

This deployment guide provides step-by-step instructions for deploying the Nuclear Engagement WordPress plugin to production environments, including staging workflows, security considerations, and best practices for reliable deployments.

## Table of Contents

- [Deployment Overview](#deployment-overview)
- [Environment Setup](#environment-setup)
- [Pre-Deployment Checklist](#pre-deployment-checklist)
- [Deployment Methods](#deployment-methods)
- [Database Migration](#database-migration)
- [Security Hardening](#security-hardening)
- [Performance Optimization](#performance-optimization)
- [Monitoring Setup](#monitoring-setup)
- [Backup & Recovery](#backup--recovery)
- [Rollback Procedures](#rollback-procedures)
- [Maintenance Mode](#maintenance-mode)
- [Troubleshooting](#troubleshooting)

## Deployment Overview

### Deployment Pipeline

```
Development → Testing → Staging → Production
     ↓           ↓         ↓          ↓
   Feature    Integration  User      Live
   Testing     Testing   Acceptance Environment
```

### Environment Requirements

| Environment | Purpose | Requirements |
|-------------|---------|--------------|
| Development | Feature development | Local/minimal resources |
| Testing | Automated testing | CI/CD pipeline |
| Staging | Pre-production testing | Production-like setup |
| Production | Live environment | High availability, monitoring |

## Environment Setup

### Production Server Requirements

#### Minimum Requirements
- **PHP**: 7.4+ (8.1+ recommended)
- **WordPress**: 5.0+
- **MySQL/MariaDB**: 5.7+ / 10.3+
- **Memory**: 256MB (512MB+ recommended)
- **Disk Space**: 100MB for plugin
- **SSL Certificate**: Required for security

#### Recommended Production Setup
- **PHP**: 8.1+ with OPcache enabled
- **Memory**: 1GB+ RAM
- **CPU**: 2+ cores
- **Database**: Dedicated MySQL 8.0+ server
- **Caching**: Redis or Memcached
- **CDN**: CloudFlare or equivalent
- **Load Balancer**: For high-traffic sites

### Server Configuration

#### PHP Configuration (php.ini)

```ini
; Memory and execution limits
memory_limit = 512M
max_execution_time = 300
max_input_time = 300

; File upload limits
upload_max_filesize = 32M
post_max_size = 32M
max_file_uploads = 20

; OPcache settings
opcache.enable = 1
opcache.memory_consumption = 256
opcache.max_accelerated_files = 4000
opcache.revalidate_freq = 2
opcache.fast_shutdown = 1

; Session settings
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1

; Security settings
expose_php = Off
display_errors = Off
log_errors = On
```

#### MySQL Configuration (my.cnf)

```ini
[mysqld]
# Memory settings
innodb_buffer_pool_size = 1G
key_buffer_size = 256M
sort_buffer_size = 4M
read_buffer_size = 2M

# Connection settings
max_connections = 200
max_user_connections = 50

# Performance settings
innodb_file_per_table = 1
innodb_flush_log_at_trx_commit = 2
innodb_log_buffer_size = 32M

# Query cache (MySQL 5.7 and earlier)
query_cache_type = 1
query_cache_size = 128M
query_cache_limit = 2M

# Logging
slow_query_log = 1
long_query_time = 2
```

#### Apache Configuration (.htaccess)

```apache
# Security headers
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"
</IfModule>

# HSTS (if SSL is enabled)
<IfModule mod_headers.c>
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
</IfModule>

# Compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
</IfModule>

# Browser caching
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType image/jpg "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
    ExpiresByType image/gif "access plus 1 month"
    ExpiresByType image/svg+xml "access plus 1 month"
</IfModule>

# Protect plugin files
<Files "*.log">
    Order allow,deny
    Deny from all
</Files>

<Files "*.json">
    Order allow,deny
    Deny from all
</Files>
```

#### Nginx Configuration

```nginx
server {
    listen 443 ssl http2;
    server_name yoursite.com;
    root /var/www/html;
    index index.php;

    # SSL configuration
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512;
    ssl_prefer_server_ciphers off;

    # Security headers
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header X-XSS-Protection "1; mode=block";
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains";

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css application/json application/javascript application/xml+rss text/xml;

    # WordPress configuration
    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_intercept_errors on;
        fastcgi_pass php-fpm;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Cache static assets
    location ~* \.(css|js|png|jpg|jpeg|gif|svg|woff|woff2)$ {
        expires 1M;
        add_header Cache-Control "public, immutable";
    }

    # Protect sensitive files
    location ~* \.(log|json)$ {
        deny all;
    }
}
```

## Pre-Deployment Checklist

### Code Quality Checks

```bash
#!/bin/bash
# pre-deploy-checks.sh

echo "🔍 Running pre-deployment checks..."

# 1. Code syntax check
echo "Checking PHP syntax..."
find . -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"

# 2. Run tests
echo "Running unit tests..."
./vendor/bin/phpunit --testsuite=unit

echo "Running integration tests..."
./vendor/bin/phpunit --testsuite=integration

# 3. Code style check
echo "Checking code style..."
./vendor/bin/phpcs --standard=WordPress

# 4. Security scan
echo "Running security scan..."
./vendor/bin/psalm --show-info=false

# 5. Check for debugging code
echo "Checking for debug code..."
if grep -r "var_dump\|print_r\|die\|exit" --include="*.php" .; then
    echo "❌ Debug code found - please remove before deployment"
    exit 1
fi

# 6. Verify version numbers
echo "Checking version consistency..."
PLUGIN_VERSION=$(grep "Version:" nuclear-engagement.php | sed 's/.*Version: //')
PACKAGE_VERSION=$(grep '"version"' package.json | sed 's/.*"version": "//' | sed 's/".*//')

if [ "$PLUGIN_VERSION" != "$PACKAGE_VERSION" ]; then
    echo "❌ Version mismatch: Plugin ($PLUGIN_VERSION) vs Package ($PACKAGE_VERSION)"
    exit 1
fi

# 7. Build assets
echo "Building production assets..."
npm run build

# 8. Check file permissions
echo "Checking file permissions..."
find . -name "*.php" -perm 777 && echo "❌ Found files with 777 permissions" && exit 1

echo "✅ All pre-deployment checks passed!"
```

### Configuration Verification

```php
<?php
/**
 * Deployment configuration checker
 */
class DeploymentChecker {
    
    public function run_checks() {
        $checks = [
            'check_wp_config',
            'check_database_connection',
            'check_file_permissions',
            'check_ssl_configuration',
            'check_caching_setup',
            'check_security_settings'
        ];
        
        $results = [];
        foreach ($checks as $check) {
            $results[$check] = $this->$check();
        }
        
        return $results;
    }
    
    private function check_wp_config() {
        $issues = [];
        
        // Check debug settings
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            $issues[] = 'WP_DEBUG is enabled - should be disabled in production';
        }
        
        if (defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY === true) {
            $issues[] = 'WP_DEBUG_DISPLAY is enabled - should be disabled in production';
        }
        
        // Check security constants
        if (!defined('DISALLOW_FILE_EDIT') || DISALLOW_FILE_EDIT !== true) {
            $issues[] = 'DISALLOW_FILE_EDIT should be set to true';
        }
        
        if (!defined('FORCE_SSL_ADMIN') || FORCE_SSL_ADMIN !== true) {
            $issues[] = 'FORCE_SSL_ADMIN should be set to true';
        }
        
        // Check salts
        $salts = ['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY'];
        foreach ($salts as $salt) {
            if (!defined($salt) || strlen(constant($salt)) < 32) {
                $issues[] = "Weak or missing salt: $salt";
            }
        }
        
        return [
            'status' => empty($issues) ? 'pass' : 'fail',
            'issues' => $issues
        ];
    }
    
    private function check_database_connection() {
        global $wpdb;
        
        $start_time = microtime(true);
        $result = $wpdb->get_var("SELECT 1");
        $connection_time = microtime(true) - $start_time;
        
        $issues = [];
        
        if ($result !== '1') {
            $issues[] = 'Database connection failed';
        }
        
        if ($connection_time > 0.1) {
            $issues[] = 'Slow database connection: ' . round($connection_time * 1000, 2) . 'ms';
        }
        
        // Check required tables
        $required_tables = [
            $wpdb->prefix . 'nuclear_engagement_results',
            $wpdb->prefix . 'nuclear_engagement_analytics'
        ];
        
        foreach ($required_tables as $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
            if (!$exists) {
                $issues[] = "Missing table: $table";
            }
        }
        
        return [
            'status' => empty($issues) ? 'pass' : 'fail',
            'issues' => $issues,
            'connection_time' => $connection_time
        ];
    }
    
    private function check_file_permissions() {
        $issues = [];
        
        // Check plugin directory permissions
        $plugin_dir = plugin_dir_path(__FILE__);
        
        if (!is_readable($plugin_dir)) {
            $issues[] = 'Plugin directory is not readable';
        }
        
        // Check uploads directory
        $upload_dir = wp_upload_dir();
        if (!wp_is_writable($upload_dir['basedir'])) {
            $issues[] = 'Uploads directory is not writable';
        }
        
        // Check for overly permissive permissions
        $php_files = glob($plugin_dir . '**/*.php');
        foreach ($php_files as $file) {
            $perms = fileperms($file) & 0777;
            if ($perms > 0644) {
                $issues[] = "File has overly permissive permissions: $file";
            }
        }
        
        return [
            'status' => empty($issues) ? 'pass' : 'fail',
            'issues' => $issues
        ];
    }
    
    private function check_ssl_configuration() {
        $issues = [];
        
        if (!is_ssl()) {
            $issues[] = 'SSL is not enabled';
        }
        
        if (!wp_is_https_supported()) {
            $issues[] = 'HTTPS is not properly configured';
        }
        
        return [
            'status' => empty($issues) ? 'pass' : 'fail',
            'issues' => $issues
        ];
    }
    
    private function check_caching_setup() {
        $issues = [];
        $recommendations = [];
        
        // Check object caching
        if (!wp_using_ext_object_cache()) {
            $recommendations[] = 'Consider enabling external object caching (Redis/Memcached)';
        }
        
        // Check OPcache
        if (!extension_loaded('opcache') || !opcache_get_status()['opcache_enabled']) {
            $recommendations[] = 'Enable OPcache for better PHP performance';
        }
        
        // Check page caching
        $caching_plugins = [
            'wp-rocket/wp-rocket.php',
            'w3-total-cache/w3-total-cache.php',
            'wp-super-cache/wp-cache.php'
        ];
        
        $has_caching = false;
        foreach ($caching_plugins as $plugin) {
            if (is_plugin_active($plugin)) {
                $has_caching = true;
                break;
            }
        }
        
        if (!$has_caching) {
            $recommendations[] = 'Consider installing a page caching plugin';
        }
        
        return [
            'status' => 'info',
            'recommendations' => $recommendations
        ];
    }
    
    private function check_security_settings() {
        $issues = [];
        
        // Check for security plugins
        $security_plugins = [
            'wordfence/wordfence.php',
            'sucuri-scanner/sucuri.php',
            'better-wp-security/better-wp-security.php'
        ];
        
        $has_security = false;
        foreach ($security_plugins as $plugin) {
            if (is_plugin_active($plugin)) {
                $has_security = true;
                break;
            }
        }
        
        if (!$has_security) {
            $issues[] = 'No security plugin detected - consider installing one';
        }
        
        // Check user accounts
        $admin_users = get_users(['role' => 'administrator']);
        foreach ($admin_users as $user) {
            if ($user->user_login === 'admin') {
                $issues[] = 'Default "admin" username found - should be changed';
            }
        }
        
        return [
            'status' => empty($issues) ? 'pass' : 'warning',
            'issues' => $issues
        ];
    }
}
```

## Deployment Methods

### Method 1: Manual Deployment

#### Step-by-step Process

1. **Backup Current Installation**
   ```bash
   # Create backup
   tar -czf nuclear-engagement-backup-$(date +%Y%m%d).tar.gz \
       wp-content/plugins/nuclear-engagement/
   
   # Database backup
   mysqldump -u username -p database_name > backup-$(date +%Y%m%d).sql
   ```

2. **Upload New Files**
   ```bash
   # Upload via SFTP/SCP
   scp -r nuclear-engagement/ user@server:/var/www/html/wp-content/plugins/
   
   # Or using rsync
   rsync -avz --exclude='node_modules' --exclude='.git' \
       nuclear-engagement/ user@server:/var/www/html/wp-content/plugins/nuclear-engagement/
   ```

3. **Set Permissions**
   ```bash
   # Set correct permissions
   find /var/www/html/wp-content/plugins/nuclear-engagement/ -type f -exec chmod 644 {} \;
   find /var/www/html/wp-content/plugins/nuclear-engagement/ -type d -exec chmod 755 {} \;
   ```

4. **Run Database Updates**
   ```bash
   # WordPress CLI
   wp plugin activate nuclear-engagement
   wp nuclear-engagement migrate-database
   ```

### Method 2: Git-based Deployment

#### Setup Git Hooks

```bash
#!/bin/bash
# hooks/post-receive

# Production deployment hook
TARGET="/var/www/html/wp-content/plugins/nuclear-engagement"
TEMP_DIR="/tmp/nuclear-engagement-deploy"

echo "Starting deployment..."

# Create temporary directory
rm -rf $TEMP_DIR
git clone $PWD $TEMP_DIR

cd $TEMP_DIR

# Install dependencies
composer install --no-dev --optimize-autoloader
npm ci --production

# Build assets
npm run build:production

# Run tests
npm test

# Copy to production directory
rsync -av --exclude='.git' --exclude='node_modules' --exclude='tests' \
    $TEMP_DIR/ $TARGET/

# Set permissions
chown -R www-data:www-data $TARGET
find $TARGET -type f -exec chmod 644 {} \;
find $TARGET -type d -exec chmod 755 {} \;

# Clear caches
wp cache flush

echo "Deployment completed successfully!"

# Cleanup
rm -rf $TEMP_DIR
```

#### Deploy Script

```bash
#!/bin/bash
# deploy.sh

set -e

ENVIRONMENT=${1:-production}
BRANCH=${2:-main}

echo "🚀 Deploying Nuclear Engagement Plugin to $ENVIRONMENT..."

# Validate environment
if [[ ! "$ENVIRONMENT" =~ ^(staging|production)$ ]]; then
    echo "❌ Invalid environment: $ENVIRONMENT"
    echo "Usage: ./deploy.sh [staging|production] [branch]"
    exit 1
fi

# Load environment configuration
source "config/${ENVIRONMENT}.env"

# Pre-deployment checks
echo "🔍 Running pre-deployment checks..."
./scripts/pre-deploy-checks.sh

# Backup current deployment
echo "💾 Creating backup..."
ssh $DEPLOY_USER@$DEPLOY_HOST "
    cd $DEPLOY_PATH
    tar -czf backups/nuclear-engagement-backup-\$(date +%Y%m%d-%H%M%S).tar.gz nuclear-engagement/
"

# Deploy to server
echo "📦 Deploying to server..."
git push $ENVIRONMENT $BRANCH

# Run post-deployment tasks
echo "⚙️  Running post-deployment tasks..."
ssh $DEPLOY_USER@$DEPLOY_HOST "
    cd $DEPLOY_PATH/nuclear-engagement
    wp plugin activate nuclear-engagement
    wp nuclear-engagement migrate-database
    wp cache flush
"

# Health check
echo "🏥 Running health check..."
./scripts/health-check.sh $ENVIRONMENT

echo "✅ Deployment completed successfully!"
```

### Method 3: Docker Deployment

#### Dockerfile

```dockerfile
FROM wordpress:6.2-php8.1-apache

# Install additional PHP extensions
RUN docker-php-ext-install mysqli pdo_mysql opcache

# Configure OPcache
RUN { \
    echo 'opcache.memory_consumption=256'; \
    echo 'opcache.max_accelerated_files=4000'; \
    echo 'opcache.revalidate_freq=2'; \
    echo 'opcache.fast_shutdown=1'; \
    } > /usr/local/etc/php/conf.d/opcache-recommended.ini

# Copy plugin files
COPY nuclear-engagement/ /var/www/html/wp-content/plugins/nuclear-engagement/

# Set permissions
RUN chown -R www-data:www-data /var/www/html/wp-content/plugins/nuclear-engagement/
RUN find /var/www/html/wp-content/plugins/nuclear-engagement/ -type f -exec chmod 644 {} \;
RUN find /var/www/html/wp-content/plugins/nuclear-engagement/ -type d -exec chmod 755 {} \;

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1
```

#### Docker Compose

```yaml
version: '3.8'

services:
  wordpress:
    build:
      context: .
      dockerfile: Dockerfile
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    environment:
      WORDPRESS_DB_HOST: db
      WORDPRESS_DB_USER: wp_user
      WORDPRESS_DB_PASSWORD: ${DB_PASSWORD}
      WORDPRESS_DB_NAME: wordpress
    volumes:
      - wordpress_data:/var/www/html
      - ./ssl:/etc/ssl/certs
    depends_on:
      - db
      - redis
    networks:
      - nuclear_engagement

  db:
    image: mysql:8.0
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: wordpress
      MYSQL_USER: wp_user
      MYSQL_PASSWORD: ${DB_PASSWORD}
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
    volumes:
      - db_data:/var/lib/mysql
      - ./mysql.cnf:/etc/mysql/conf.d/custom.cnf
    networks:
      - nuclear_engagement

  redis:
    image: redis:7-alpine
    restart: unless-stopped
    volumes:
      - redis_data:/data
    networks:
      - nuclear_engagement

  nginx:
    image: nginx:alpine
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx.conf:/etc/nginx/nginx.conf
      - ./ssl:/etc/ssl/certs
      - wordpress_data:/var/www/html
    depends_on:
      - wordpress
    networks:
      - nuclear_engagement

volumes:
  wordpress_data:
  db_data:
  redis_data:

networks:
  nuclear_engagement:
    driver: bridge
```

## Database Migration

### Migration Strategy

```php
<?php
/**
 * Database migration manager
 */
class DatabaseMigration {
    
    private $current_version;
    private $target_version;
    
    public function __construct() {
        $this->current_version = get_option('nuclear_engagement_db_version', '1.0.0');
        $this->target_version = NUCLEAR_ENGAGEMENT_VERSION;
    }
    
    public function needs_migration() {
        return version_compare($this->current_version, $this->target_version, '<');
    }
    
    public function run_migration() {
        if (!$this->needs_migration()) {
            return true;
        }
        
        global $wpdb;
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            $migrations = $this->get_pending_migrations();
            
            foreach ($migrations as $migration) {
                $this->run_single_migration($migration);
            }
            
            // Update version
            update_option('nuclear_engagement_db_version', $this->target_version);
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            return true;
            
        } catch (Exception $e) {
            // Rollback on error
            $wpdb->query('ROLLBACK');
            error_log('Nuclear Engagement migration failed: ' . $e->getMessage());
            return false;
        }
    }
    
    private function get_pending_migrations() {
        $migrations = [
            '1.1.0' => 'add_analytics_table',
            '1.2.0' => 'add_user_progress_indexes',
            '1.3.0' => 'add_quiz_categories',
            '1.4.0' => 'migrate_old_results_format'
        ];
        
        $pending = [];
        foreach ($migrations as $version => $migration) {
            if (version_compare($this->current_version, $version, '<')) {
                $pending[] = [
                    'version' => $version,
                    'migration' => $migration
                ];
            }
        }
        
        return $pending;
    }
    
    private function run_single_migration($migration) {
        $method = $migration['migration'];
        
        if (!method_exists($this, $method)) {
            throw new Exception("Migration method not found: $method");
        }
        
        $this->$method();
        
        // Log migration
        error_log("Nuclear Engagement: Applied migration {$migration['version']}: $method");
    }
    
    private function add_analytics_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$wpdb->prefix}nuclear_engagement_analytics (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            quiz_id bigint(20) unsigned,
            user_id bigint(20) unsigned,
            event_data longtext,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY event_type_idx (event_type),
            KEY quiz_date_idx (quiz_id, created_at),
            KEY user_date_idx (user_id, created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    private function add_user_progress_indexes() {
        global $wpdb;
        
        $wpdb->query("
            ALTER TABLE {$wpdb->prefix}nuclear_engagement_results 
            ADD INDEX idx_user_quiz_progress (user_id, quiz_id, created_at)
        ");
    }
    
    private function migrate_old_results_format() {
        global $wpdb;
        
        // Migrate old JSON format to new structure
        $old_results = $wpdb->get_results("
            SELECT id, answers FROM {$wpdb->prefix}nuclear_engagement_results 
            WHERE answers LIKE '{%' AND JSON_VALID(answers) = 0
        ");
        
        foreach ($old_results as $result) {
            $old_format = maybe_unserialize($result->answers);
            $new_format = $this->convert_answer_format($old_format);
            
            $wpdb->update(
                $wpdb->prefix . 'nuclear_engagement_results',
                ['answers' => wp_json_encode($new_format)],
                ['id' => $result->id],
                ['%s'],
                ['%d']
            );
        }
    }
}
```

### Database Backup Strategy

```bash
#!/bin/bash
# backup-database.sh

set -e

DB_NAME=${1:-"wordpress"}
BACKUP_DIR="/var/backups/nuclear-engagement"
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/nuclear_engagement_$DATE.sql"

# Create backup directory
mkdir -p $BACKUP_DIR

# Create backup
mysqldump --single-transaction --routines --triggers \
    --user=$DB_USER --password=$DB_PASSWORD \
    $DB_NAME > $BACKUP_FILE

# Compress backup
gzip $BACKUP_FILE

# Keep only last 7 days of backups
find $BACKUP_DIR -name "*.sql.gz" -mtime +7 -delete

echo "Database backup created: ${BACKUP_FILE}.gz"
```

## Security Hardening

### WordPress Security Configuration

```php
<?php
// wp-config.php security additions

// Disable file editing
define('DISALLOW_FILE_EDIT', true);
define('DISALLOW_FILE_MODS', true);

// Force SSL
define('FORCE_SSL_ADMIN', true);

// Security keys (generate unique keys)
define('AUTH_KEY',         'your-unique-key-here');
define('SECURE_AUTH_KEY',  'your-unique-key-here');
define('LOGGED_IN_KEY',    'your-unique-key-here');
define('NONCE_KEY',        'your-unique-key-here');
define('AUTH_SALT',        'your-unique-key-here');
define('SECURE_AUTH_SALT', 'your-unique-key-here');
define('LOGGED_IN_SALT',   'your-unique-key-here');
define('NONCE_SALT',       'your-unique-key-here');

// Database security
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', 'utf8mb4_unicode_ci');

// Limit post revisions
define('WP_POST_REVISIONS', 3);

// Increase memory limit
define('WP_MEMORY_LIMIT', '256M');

// Disable debug in production
define('WP_DEBUG', false);
define('WP_DEBUG_LOG', false);
define('WP_DEBUG_DISPLAY', false);

// Additional security measures
define('AUTOMATIC_UPDATER_DISABLED', true);
define('WP_AUTO_UPDATE_CORE', false);
```

### Server Security Script

```bash
#!/bin/bash
# security-hardening.sh

echo "🔒 Applying security hardening..."

# 1. Update system
apt update && apt upgrade -y

# 2. Configure firewall
ufw --force reset
ufw default deny incoming
ufw default allow outgoing
ufw allow ssh
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

# 3. Configure fail2ban
apt install fail2ban -y
cat > /etc/fail2ban/jail.local << EOF
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 3

[ssh]
enabled = true

[apache-auth]
enabled = true

[apache-badbots]
enabled = true

[apache-noscript]
enabled = true
EOF

systemctl enable fail2ban
systemctl start fail2ban

# 4. Secure shared memory
echo "tmpfs /run/shm tmpfs defaults,noexec,nosuid 0 0" >> /etc/fstab

# 5. Disable unnecessary services
systemctl disable bluetooth
systemctl disable cups

# 6. Configure automatic security updates
apt install unattended-upgrades -y
dpkg-reconfigure -plow unattended-upgrades

echo "✅ Security hardening completed!"
```

## Performance Optimization

### Production Optimizations

```php
<?php
/**
 * Production performance optimizations
 */
class ProductionOptimizations {
    
    public function __construct() {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            $this->apply_optimizations();
        }
    }
    
    private function apply_optimizations() {
        // Remove unnecessary WordPress features
        $this->remove_wp_overhead();
        
        // Optimize database queries
        $this->optimize_queries();
        
        // Enable compression
        $this->enable_compression();
        
        // Optimize assets
        $this->optimize_assets();
    }
    
    private function remove_wp_overhead() {
        // Remove version from scripts and styles
        add_filter('style_loader_src', [$this, 'remove_version'], 9999);
        add_filter('script_loader_src', [$this, 'remove_version'], 9999);
        
        // Remove unnecessary head elements
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'wp_generator');
        remove_action('wp_head', 'wp_shortlink_wp_head');
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');
        
        // Disable embeds
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        remove_action('wp_head', 'wp_oembed_add_host_js');
    }
    
    private function optimize_queries() {
        // Limit post revisions
        add_filter('wp_revisions_to_keep', function() { return 3; });
        
        // Remove unnecessary queries
        remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10);
        
        // Optimize admin queries
        add_action('admin_init', function() {
            wp_deregister_script('heartbeat');
        });
    }
    
    private function enable_compression() {
        // Enable Gzip compression
        if (!ob_get_level()) {
            ob_start('ob_gzhandler');
        }
    }
    
    private function optimize_assets() {
        // Combine and minify CSS
        add_action('wp_enqueue_scripts', [$this, 'optimize_css'], 100);
        
        // Defer JavaScript
        add_filter('script_loader_tag', [$this, 'defer_scripts'], 10, 3);
    }
    
    public function remove_version($src) {
        return remove_query_arg('ver', $src);
    }
    
    public function defer_scripts($tag, $handle, $src) {
        $defer_scripts = [
            'nuclear-engagement-quiz',
            'nuclear-engagement-analytics'
        ];
        
        if (in_array($handle, $defer_scripts)) {
            return str_replace('<script ', '<script defer ', $tag);
        }
        
        return $tag;
    }
}

new ProductionOptimizations();
```

## Monitoring Setup

### Application Monitoring

```php
<?php
/**
 * Production monitoring setup
 */
class ProductionMonitoring {
    
    public function __construct() {
        add_action('wp_loaded', [$this, 'setup_monitoring']);
        add_action('shutdown', [$this, 'log_performance_metrics']);
    }
    
    public function setup_monitoring() {
        // Error tracking
        set_error_handler([$this, 'error_handler']);
        set_exception_handler([$this, 'exception_handler']);
        
        // Performance monitoring
        if (defined('NUCLEAR_ENGAGEMENT_MONITOR_PERFORMANCE')) {
            $this->start_performance_monitoring();
        }
    }
    
    public function error_handler($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $error_data = [
            'severity' => $severity,
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'timestamp' => current_time('c'),
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];
        
        $this->log_error($error_data);
        
        return false; // Let WordPress handle the error too
    }
    
    public function exception_handler($exception) {
        $error_data = [
            'type' => 'exception',
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'timestamp' => current_time('c')
        ];
        
        $this->log_error($error_data);
    }
    
    private function log_error($error_data) {
        // Log to file
        error_log('Nuclear Engagement Error: ' . json_encode($error_data));
        
        // Send to external monitoring service (if configured)
        if (defined('SENTRY_DSN')) {
            $this->send_to_sentry($error_data);
        }
    }
    
    private function send_to_sentry($error_data) {
        // Sentry integration example
        if (function_exists('Sentry\captureException')) {
            \Sentry\captureException(new Exception($error_data['message']));
        }
    }
    
    public function log_performance_metrics() {
        $metrics = [
            'memory_usage' => memory_get_peak_usage(true) / 1024 / 1024, // MB
            'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            'timestamp' => current_time('c'),
            'url' => $_SERVER['REQUEST_URI'] ?? ''
        ];
        
        // Log slow requests
        if ($metrics['execution_time'] > 2.0) {
            error_log('Slow request: ' . json_encode($metrics));
        }
        
        // Log high memory usage
        if ($metrics['memory_usage'] > 128) {
            error_log('High memory usage: ' . json_encode($metrics));
        }
    }
}

new ProductionMonitoring();
```

### Health Check Endpoint

```php
<?php
/**
 * Health check endpoint for monitoring
 */
add_action('rest_api_init', function() {
    register_rest_route('nuclear-engagement/v1', '/health', [
        'methods' => 'GET',
        'callback' => 'nuclear_engagement_health_check',
        'permission_callback' => '__return_true'
    ]);
});

function nuclear_engagement_health_check() {
    $health_data = [
        'status' => 'healthy',
        'timestamp' => current_time('c'),
        'version' => NUCLEAR_ENGAGEMENT_VERSION,
        'checks' => []
    ];
    
    // Database check
    global $wpdb;
    $db_start = microtime(true);
    $db_result = $wpdb->get_var("SELECT 1");
    $db_time = microtime(true) - $db_start;
    
    $health_data['checks']['database'] = [
        'status' => $db_result === '1' ? 'pass' : 'fail',
        'response_time' => round($db_time * 1000, 2) . 'ms'
    ];
    
    // File system check
    $upload_dir = wp_upload_dir();
    $health_data['checks']['filesystem'] = [
        'status' => wp_is_writable($upload_dir['basedir']) ? 'pass' : 'fail',
        'uploads_writable' => wp_is_writable($upload_dir['basedir'])
    ];
    
    // Memory check
    $memory_usage = memory_get_usage(true) / 1024 / 1024;
    $memory_limit = ini_get('memory_limit');
    $health_data['checks']['memory'] = [
        'status' => $memory_usage < 200 ? 'pass' : 'warning',
        'usage_mb' => round($memory_usage, 2),
        'limit' => $memory_limit
    ];
    
    // Overall status
    $failed_checks = array_filter($health_data['checks'], function($check) {
        return $check['status'] === 'fail';
    });
    
    if (!empty($failed_checks)) {
        $health_data['status'] = 'unhealthy';
        return new WP_Error('health_check_failed', 'Health check failed', [
            'status' => 503,
            'health_data' => $health_data
        ]);
    }
    
    return $health_data;
}
```

This deployment guide provides comprehensive instructions for safely and reliably deploying the Nuclear Engagement plugin to production environments with proper security, monitoring, and performance considerations.