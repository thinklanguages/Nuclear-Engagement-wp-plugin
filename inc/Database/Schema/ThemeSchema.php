<?php
declare(strict_types=1);
namespace NuclearEngagement\Database\Schema;

class ThemeSchema {
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'nuclear_themes';
    }

    public static function get_schema() {
        $table_name = self::get_table_name();
        $charset_collate = $GLOBALS['wpdb']->get_charset_collate();

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
        global $wpdb;
        $table_name = self::get_table_name();
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
    }
}