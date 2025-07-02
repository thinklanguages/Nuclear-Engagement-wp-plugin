<?php
declare(strict_types=1);

namespace NuclearEngagement\Repositories;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Repository for optin data operations.
 * 
 * Provides database abstraction for optin-related operations,
 * replacing direct $wpdb usage in OptinData class.
 *
 * @package NuclearEngagement\Repositories
 * @since 1.0.0
 */
class OptinRepository extends DatabaseRepository {
	/**
	 * Table name constant.
	 */
	private const TABLE_SLUG = 'nuclen_optins';

	/**
	 * Cached table existence flag.
	 *
	 * @var bool|null
	 */
	private static ?bool $table_exists_cache = null;

	/**
	 * Get the full table name with prefix.
	 *
	 * @return string Full table name.
	 */
	public function get_optin_table_name(): string {
		return $this->get_table_name( self::TABLE_SLUG );
	}

	/**
	 * Check if optin table exists.
	 *
	 * @return bool True if table exists, false otherwise.
	 */
	public function optin_table_exists(): bool {
		if ( self::$table_exists_cache !== null ) {
			return self::$table_exists_cache;
		}

		self::$table_exists_cache = $this->table_exists( $this->get_optin_table_name() );
		return self::$table_exists_cache;
	}

	/**
	 * Create optin table if it doesn't exist.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function create_optin_table(): bool {
		if ( $this->optin_table_exists() ) {
			return true;
		}

		$table_name = $this->get_optin_table_name();
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			email varchar(100) NOT NULL,
			post_id bigint(20) unsigned NOT NULL,
			post_title text DEFAULT NULL,
			quiz_score varchar(20) DEFAULT NULL,
			user_agent text DEFAULT NULL,
			ip_address varchar(45) DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_email (email),
			KEY idx_post_id (post_id),
			KEY idx_created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Clear cache and recheck
		self::$table_exists_cache = null;
		return $this->optin_table_exists();
	}

	/**
	 * Insert optin data.
	 *
	 * @param array $data Optin data to insert.
	 * @return int|false Insert ID on success, false on error.
	 */
	public function insert_optin( array $data ) {
		$sanitized_data = [
			'email' => sanitize_email( $data['email'] ?? '' ),
			'post_id' => absint( $data['post_id'] ?? 0 ),
			'post_title' => sanitize_text_field( $data['post_title'] ?? '' ),
			'quiz_score' => sanitize_text_field( $data['quiz_score'] ?? '' ),
			'user_agent' => sanitize_text_field( $data['user_agent'] ?? '' ),
			'ip_address' => sanitize_text_field( $data['ip_address'] ?? '' ),
		];

		// Validate required fields
		if ( empty( $sanitized_data['email'] ) || empty( $sanitized_data['post_id'] ) ) {
			return false;
		}

		// Check if email already exists for this post
		if ( $this->optin_exists( $sanitized_data['email'], $sanitized_data['post_id'] ) ) {
			return false; // Duplicate entry
		}

		return $this->insert( $this->get_optin_table_name(), $sanitized_data );
	}

	/**
	 * Check if optin already exists for email and post.
	 *
	 * @param string $email Email address.
	 * @param int $post_id Post ID.
	 * @return bool True if exists, false otherwise.
	 */
	public function optin_exists( string $email, int $post_id ): bool {
		$query = "SELECT COUNT(*) FROM {$this->get_optin_table_name()} WHERE email = %s AND post_id = %d";
		$count = $this->get_var( $query, [ $email, $post_id ] );
		return (int) $count > 0;
	}

	/**
	 * Get optin data by ID.
	 *
	 * @param int $id Optin ID.
	 * @return object|null Optin data or null if not found.
	 */
	public function get_optin_by_id( int $id ): ?object {
		$query = "SELECT * FROM {$this->get_optin_table_name()} WHERE id = %d";
		return $this->get_row( $query, [ $id ] );
	}

	/**
	 * Get all optins with pagination.
	 *
	 * @param int $limit Number of records to retrieve.
	 * @param int $offset Offset for pagination.
	 * @param string $order_by Column to order by.
	 * @param string $order Order direction (ASC or DESC).
	 * @return array Array of optin records.
	 */
	public function get_optins( int $limit = 50, int $offset = 0, string $order_by = 'created_at', string $order = 'DESC' ): array {
		// Validate order direction
		$order = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';
		
		// Validate order_by column (whitelist)
		$allowed_columns = [ 'id', 'email', 'post_id', 'post_title', 'quiz_score', 'created_at' ];
		if ( ! in_array( $order_by, $allowed_columns, true ) ) {
			$order_by = 'created_at';
		}

		$query = "SELECT * FROM {$this->get_optin_table_name()} 
				  ORDER BY {$order_by} {$order} 
				  LIMIT %d OFFSET %d";
		
		return $this->get_results( $query, [ $limit, $offset ] );
	}

	/**
	 * Get optin count.
	 *
	 * @param array $filters Optional filters.
	 * @return int Total count of optins.
	 */
	public function get_optin_count( array $filters = [] ): int {
		$where_clause = '';
		$params = [];

		if ( ! empty( $filters['post_id'] ) ) {
			$where_clause .= ' WHERE post_id = %d';
			$params[] = absint( $filters['post_id'] );
		}

		if ( ! empty( $filters['email'] ) ) {
			$where_clause .= empty( $where_clause ) ? ' WHERE' : ' AND';
			$where_clause .= ' email = %s';
			$params[] = sanitize_email( $filters['email'] );
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$where_clause .= empty( $where_clause ) ? ' WHERE' : ' AND';
			$where_clause .= ' created_at >= %s';
			$params[] = sanitize_text_field( $filters['date_from'] );
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where_clause .= empty( $where_clause ) ? ' WHERE' : ' AND';
			$where_clause .= ' created_at <= %s';
			$params[] = sanitize_text_field( $filters['date_to'] );
		}

		$query = "SELECT COUNT(*) FROM {$this->get_optin_table_name()}{$where_clause}";
		return (int) $this->get_var( $query, $params );
	}

	/**
	 * Get optins for export with filters.
	 *
	 * @param array $filters Export filters.
	 * @return array Array of optin records for export.
	 */
	public function get_optins_for_export( array $filters = [] ): array {
		$where_conditions = [];
		$params = [];

		if ( ! empty( $filters['post_id'] ) ) {
			$where_conditions[] = 'post_id = %d';
			$params[] = absint( $filters['post_id'] );
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$where_conditions[] = 'created_at >= %s';
			$params[] = sanitize_text_field( $filters['date_from'] );
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where_conditions[] = 'created_at <= %s';
			$params[] = sanitize_text_field( $filters['date_to'] );
		}

		$where_clause = empty( $where_conditions ) ? '' : ' WHERE ' . implode( ' AND ', $where_conditions );

		$query = "SELECT email, post_id, post_title, quiz_score, created_at 
				  FROM {$this->get_optin_table_name()}{$where_clause} 
				  ORDER BY created_at DESC";

		return $this->get_results( $query, $params );
	}

	/**
	 * Delete optin by ID.
	 *
	 * @param int $id Optin ID to delete.
	 * @return bool True on success, false on failure.
	 */
	public function delete_optin( int $id ): bool {
		$result = $this->delete( $this->get_optin_table_name(), [ 'id' => $id ], [ '%d' ] );
		return $result !== false;
	}

	/**
	 * Delete optins by post ID.
	 *
	 * @param int $post_id Post ID.
	 * @return int|false Number of rows deleted or false on error.
	 */
	public function delete_optins_by_post( int $post_id ) {
		return $this->delete( $this->get_optin_table_name(), [ 'post_id' => $post_id ], [ '%d' ] );
	}

	/**
	 * Delete old optins based on retention period.
	 *
	 * @param int $days Number of days to retain.
	 * @return int|false Number of rows deleted or false on error.
	 */
	public function cleanup_old_optins( int $days = 365 ) {
		$cutoff_date = date( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		$query = "DELETE FROM {$this->get_optin_table_name()} WHERE created_at < %s";
		return $this->execute_query( $query, [ $cutoff_date ] );
	}

	/**
	 * Get optin statistics.
	 *
	 * @param int $days Number of days to analyze.
	 * @return array Statistics array.
	 */
	public function get_optin_stats( int $days = 30 ): array {
		$since = date( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		
		$total_query = "SELECT COUNT(*) FROM {$this->get_optin_table_name()}";
		$recent_query = "SELECT COUNT(*) FROM {$this->get_optin_table_name()} WHERE created_at >= %s";
		$by_post_query = "SELECT post_id, post_title, COUNT(*) as count 
						  FROM {$this->get_optin_table_name()} 
						  WHERE created_at >= %s 
						  GROUP BY post_id, post_title 
						  ORDER BY count DESC 
						  LIMIT 10";

		return [
			'total_optins' => (int) $this->get_var( $total_query ),
			'recent_optins' => (int) $this->get_var( $recent_query, [ $since ] ),
			'top_posts' => $this->get_results( $by_post_query, [ $since ] ),
		];
	}

	/**
	 * Drop the optin table.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function drop_optin_table(): bool {
		$query = "DROP TABLE IF EXISTS {$this->get_optin_table_name()}";
		$result = $this->execute_query( $query );
		
		// Clear cache
		self::$table_exists_cache = null;
		
		return $result !== false;
	}
}