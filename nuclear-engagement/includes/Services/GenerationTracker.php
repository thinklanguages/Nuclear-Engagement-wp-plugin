<?php
/**
 * File: includes/Services/GenerationTracker.php
 *
 * Generation Tracker Service
 *
 * @package NuclearEngagement\Services
 */

namespace NuclearEngagement\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service for tracking generation progress.
 */
class GenerationTracker {
    /**
     * Database table name.
     *
     * @var string
     */
    private string $table;

    /**
     * Constructor.
     */
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'nuclen_generations';
    }

    /**
     * Create the generations table if needed.
     */
    public function createTable(): void {
        global $wpdb;

        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            generation_id VARCHAR(100) NOT NULL,
            workflow_type VARCHAR(20) NOT NULL,
            post_ids TEXT NOT NULL,
            total INT(11) NOT NULL DEFAULT 0,
            processed INT(11) NOT NULL DEFAULT 0,
            failed INT(11) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            started_at DATETIME NOT NULL,
            completed_at DATETIME DEFAULT NULL,
            next_poll DATETIME DEFAULT NULL,
            attempt INT(11) NOT NULL DEFAULT 1,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY generation_id (generation_id),
            KEY status (status),
            KEY next_poll (next_poll),
            KEY user_id (user_id)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Insert a generation record.
     *
     * @param array $data Data to insert.
     * @return int|false
     */
    public function create(array $data) {
        global $wpdb;

        $defaults = [
            'status'     => 'pending',
            'processed'  => 0,
            'failed'     => 0,
            'attempt'    => 1,
            'started_at' => current_time('mysql'),
            'user_id'    => get_current_user_id(),
        ];

        $data = wp_parse_args($data, $defaults);

        if (isset($data['post_ids']) && is_array($data['post_ids'])) {
            $data['post_ids'] = wp_json_encode($data['post_ids']);
        }

        $wpdb->insert(
            $this->table,
            $data,
            [
                '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d'
            ]
        );

        return $wpdb->insert_id ?: false;
    }

    /**
     * Get generation record by ID.
     */
    public function get(string $generation_id) {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE generation_id = %s", $generation_id));
        if ($row && !empty($row->post_ids)) {
            $row->post_ids = json_decode($row->post_ids, true);
        }
        return $row;
    }

    /**
     * Update a generation record.
     */
    public function update(string $generation_id, array $data): bool {
        global $wpdb;

        if (isset($data['post_ids']) && is_array($data['post_ids'])) {
            $data['post_ids'] = wp_json_encode($data['post_ids']);
        }

        if (isset($data['status']) && 'complete' === $data['status'] && !isset($data['completed_at'])) {
            $data['completed_at'] = current_time('mysql');
        }

        $result = $wpdb->update(
            $this->table,
            $data,
            ['generation_id' => $generation_id],
            null,
            ['%s']
        );

        return $result !== false;
    }

    /**
     * Get active generations (pending or processing).
     */
    public function getActive(): array {
        global $wpdb;

        $rows = $wpdb->get_results("SELECT * FROM {$this->table} WHERE status IN ('pending','processing') ORDER BY started_at DESC");
        foreach ($rows as $row) {
            if (!empty($row->post_ids)) {
                $row->post_ids = json_decode($row->post_ids, true);
            }
        }
        return $rows ?: [];
    }

    /**
     * Get generations needing polling.
     */
    public function getForPolling(): array {
        global $wpdb;
        $now = current_time('mysql');
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE status IN ('pending','processing') AND (next_poll IS NULL OR next_poll <= %s)", $now));
        foreach ($rows as $row) {
            if (!empty($row->post_ids)) {
                $row->post_ids = json_decode($row->post_ids, true);
            }
        }
        return $rows ?: [];
    }

    /**
     * Remove completed generations older than given days.
     */
    public function cleanup(int $days = 30): int {
        global $wpdb;
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        return $wpdb->query($wpdb->prepare("DELETE FROM {$this->table} WHERE status = 'complete' AND completed_at < %s", $cutoff));
    }

    /**
     * Mark generation dismissed by user.
     */
    public function dismiss(string $generation_id): bool {
        return $this->update($generation_id, ['status' => 'dismissed']);
    }
}
