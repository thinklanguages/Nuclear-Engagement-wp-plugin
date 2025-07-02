<?php
declare(strict_types=1);
namespace NuclearEngagement\Repositories;

use NuclearEngagement\Models\Theme;
use NuclearEngagement\Database\Schema\ThemeSchema;

class ThemeRepository {
    private $table_name;

    public function __construct() {
        $this->table_name = ThemeSchema::get_table_name();
    }

    public function find($id) {
        global $wpdb;
        
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `%s` WHERE id = %d",
            $this->table_name,
            $id
        ), ARRAY_A);

        return $row ? new Theme($row) : null;
    }

    public function find_by_name($name) {
        global $wpdb;
        
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `%s` WHERE name = %s",
            $this->table_name,
            $name
        ), ARRAY_A);

        return $row ? new Theme($row) : null;
    }

    public function get_active() {
        global $wpdb;
        
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `%s` WHERE is_active = 1 LIMIT 1",
            $this->table_name
        ), ARRAY_A);

        return $row ? new Theme($row) : null;
    }

    public function get_all($type = null) {
        global $wpdb;
        
        if ($type) {
            $query = $wpdb->prepare(
                "SELECT * FROM `%s` WHERE type = %s ORDER BY name ASC",
                $this->table_name,
                $type
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT * FROM `%s` ORDER BY name ASC",
                $this->table_name
            );
        }

        $rows = $wpdb->get_results($query, ARRAY_A);
        
        return array_map(function($row) {
            return new Theme($row);
        }, $rows);
    }

    public function save(Theme $theme) {
        global $wpdb;

        $data = [
            'name' => $theme->name,
            'type' => $theme->type,
            'config_json' => json_encode($theme->config),
            'css_hash' => $theme->css_hash ?: $theme->generate_hash(),
            'css_path' => $theme->css_path,
            'is_active' => (int) $theme->is_active,
        ];

        if ($theme->id) {
            $result = $wpdb->update(
                "`{$this->table_name}`",
                $data,
                ['id' => $theme->id],
                ['%s', '%s', '%s', '%s', '%s', '%d'],
                ['%d']
            );
            
            if ($result === false) {
                return false;
            }
        } else {
            $result = $wpdb->insert(
                "`{$this->table_name}`",
                $data,
                ['%s', '%s', '%s', '%s', '%s', '%d']
            );
            
            if ($result === false) {
                return false;
            }
            
            $theme->id = $wpdb->insert_id;
        }

        return $theme;
    }

    public function delete($id) {
        global $wpdb;
        
        return $wpdb->delete(
            "`{$this->table_name}`",
            ['id' => $id],
            ['%d']
        );
    }

    public function set_active($id) {
        global $wpdb;
        
        $wpdb->query($wpdb->prepare(
            "UPDATE `%s` SET is_active = 0",
            $this->table_name
        ));
        
        return $wpdb->update(
            "`{$this->table_name}`",
            ['is_active' => 1],
            ['id' => $id],
            ['%d'],
            ['%d']
        );
    }

    public function deactivate_all() {
        global $wpdb;
        
        return $wpdb->query($wpdb->prepare(
            "UPDATE `%s` SET is_active = 0",
            $this->table_name
        ));
    }
}