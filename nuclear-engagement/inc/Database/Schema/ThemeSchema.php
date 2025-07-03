<?php
namespace NuclearEngagement\Database\Schema;

use NuclearEngagement\Utils\DatabaseUtils;

class ThemeSchema {
    public static function get_table_name() {
        return DatabaseUtils::get_table_name('nuclear_themes');
    }

    public static function get_schema() {
        $table_name = self::get_table_name();
        $charset_collate = DatabaseUtils::get_charset_collate();

        return "CREATE TABLE {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            type varchar(20) NOT NULL DEFAULT 'custom',
            config_json longtext NOT NULL,
            css_hash varchar(64) NOT NULL,
            css_path varchar(255) DEFAULT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY name (name),
            KEY type (type),
            KEY css_hash (css_hash),
            KEY is_active (is_active)
        ) $charset_collate;";
    }

    public static function create_table() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta(self::get_schema());
    }

    public static function drop_table() {
        $table_name = self::get_table_name();
        $escaped_table = DatabaseUtils::escape_table_name($table_name);
        
        global $wpdb;
        // Use prepared statement for security
        $query = "DROP TABLE IF EXISTS {$escaped_table}";
        return DatabaseUtils::execute_query($query, 'drop_theme_table');
    }
}