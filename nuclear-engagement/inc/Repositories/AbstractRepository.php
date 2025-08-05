<?php
/**
 * AbstractRepository.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Repositories
 */

declare(strict_types=1);
/**
 * File: inc/Repositories/AbstractRepository.php
 *
 * Abstract repository implementation.
 *
 * @package NuclearEngagement\Repositories
 */

namespace NuclearEngagement\Repositories;

use NuclearEngagement\Contracts\RepositoryInterface;
use NuclearEngagement\Contracts\CacheInterface;
use NuclearEngagement\Contracts\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base repository with common functionality.
 */
abstract class AbstractRepository implements RepositoryInterface {

	/** @var CacheInterface */
	protected CacheInterface $cache;

	/** @var LoggerInterface */
	protected LoggerInterface $logger;

	/** @var string */
	protected string $cache_group;

	/** @var int */
	protected int $cache_ttl = 3600;

	public function __construct( CacheInterface $cache, LoggerInterface $logger ) {
		$this->cache       = $cache;
		$this->logger      = $logger;
		$this->cache_group = $this->get_cache_group();
	}

	/**
	 * Get cache group for this repository.
	 */
	abstract protected function get_cache_group(): string;

	/**
	 * Get table name for this repository.
	 */
	abstract protected function get_table_name(): string;

	/**
	 * Get primary key column name.
	 */
	protected function get_primary_key(): string {
		return 'id';
	}

	/**
	 * Create cache key.
	 *
	 * @param string $suffix Key suffix.
	 * @return string Cache key.
	 */
	protected function cache_key( string $suffix ): string {
		return $this->cache_group . '_' . $suffix;
	}

	/**
	 * Get WordPress database instance.
	 */
	protected function get_wpdb(): \wpdb {
		global $wpdb;
		return $wpdb;
	}

	/**
	 * Find entity by ID with caching.
	 *
	 * @param mixed $id Entity ID.
	 * @return mixed|null Entity or null if not found.
	 */
	public function find( $id ) {
		$cache_key = $this->cache_key( 'find_' . $id );
		$cached    = $this->cache->get( $cache_key );

		if ( $cached !== null ) {
			return $cached;
		}

		$wpdb        = $this->get_wpdb();
		$table       = $this->get_table_name();
		$primary_key = $this->get_primary_key();

		$sql    = $wpdb->prepare( "SELECT * FROM {$table} WHERE {$primary_key} = %s", $id );
		$result = // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->get_row( $sql );

		if ( $wpdb->last_error ) {
			$this->logger->error(
				'Database query failed',
				array(
					'operation'   => 'find_by_id',
					'table'       => $table,
					'primary_key' => $primary_key,
					'id'          => $id,
					'error'       => $wpdb->last_error,
					'query'       => $sql,
					'mysql_errno' => $wpdb->last_error_no ?? null,
					'caller'      => debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2 )[1]['function'] ?? 'unknown',
				)
			);
			return null;
		}

		$entity = $result ? $this->hydrate( $result ) : null;
		$this->cache->set( $cache_key, $entity, $this->cache_ttl );

		return $entity;
	}

	/**
	 * Find entities by criteria.
	 *
	 * @param array $criteria Search criteria.
	 * @param array $order_by Order by criteria.
	 * @param int   $limit    Limit results.
	 * @param int   $offset   Offset results.
	 * @return array Found entities.
	 */
	public function find_by( array $criteria = array(), array $order_by = array(), int $limit = 0, int $offset = 0 ): array {
		$cache_key = $this->cache_key( 'find_by_' . md5( maybe_serialize( func_get_args() ) ) );
		$cached    = $this->cache->get( $cache_key );

		if ( $cached !== null ) {
			return $cached;
		}

		$wpdb  = $this->get_wpdb();
		$table = $this->get_table_name();

		$sql          = "SELECT * FROM {$table}";
		$where_params = array();

		if ( ! empty( $criteria ) ) {
			$where_conditions = array();
			foreach ( $criteria as $column => $value ) {
				$where_conditions[] = $wpdb->prepare( "{$column} = %s", $value );
			}
			$sql .= ' WHERE ' . implode( ' AND ', $where_conditions );
		}

		if ( ! empty( $order_by ) ) {
			$order_conditions = array();
			foreach ( $order_by as $column => $direction ) {
				$direction          = strtoupper( $direction ) === 'DESC' ? 'DESC' : 'ASC';
				$order_conditions[] = "{$column} {$direction}";
			}
			$sql .= ' ORDER BY ' . implode( ', ', $order_conditions );
		}

		if ( $limit > 0 ) {
			$sql .= $wpdb->prepare( ' LIMIT %d', $limit );
			if ( $offset > 0 ) {
				$sql .= $wpdb->prepare( ' OFFSET %d', $offset );
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->get_results( $sql );

		if ( $wpdb->last_error ) {
			$this->logger->error(
				'Database error in find_by',
				array(
					'error' => $wpdb->last_error,
					'query' => $sql,
				)
			);
			return array();
		}

		$entities = array_map( array( $this, 'hydrate' ), $results );
		$this->cache->set( $cache_key, $entities, $this->cache_ttl );

		return $entities;
	}

	/**
	 * Find one entity by criteria.
	 *
	 * @param array $criteria Search criteria.
	 * @return mixed|null Entity or null if not found.
	 */
	public function find_one_by( array $criteria ): ?object {
		$results = $this->find_by( $criteria, array(), 1 );
		return $results[0] ?? null;
	}

	/**
	 * Count entities matching criteria.
	 *
	 * @param array $criteria Search criteria.
	 * @return int Count of entities.
	 */
	public function count( array $criteria = array() ): int {
		$cache_key = $this->cache_key( 'count_' . md5( maybe_serialize( $criteria ) ) );
		$cached    = $this->cache->get( $cache_key );

		if ( $cached !== null ) {
			return (int) $cached;
		}

		$wpdb  = $this->get_wpdb();
		$table = $this->get_table_name();

		$sql = "SELECT COUNT(*) FROM {$table}";

		if ( ! empty( $criteria ) ) {
			$where_conditions = array();
			foreach ( $criteria as $column => $value ) {
				$where_conditions[] = $wpdb->prepare( "{$column} = %s", $value );
			}
			$sql .= ' WHERE ' . implode( ' AND ', $where_conditions );
		}

		$count = (int) // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->get_var( $sql );

		if ( $wpdb->last_error ) {
			$this->logger->error(
				'Database error in count',
				array(
					'error' => $wpdb->last_error,
					'query' => $sql,
				)
			);
			return 0;
		}

		$this->cache->set( $cache_key, $count, $this->cache_ttl );

		return $count;
	}

	/**
	 * Save entity.
	 *
	 * @param mixed $entity Entity to save.
	 * @return mixed Saved entity.
	 */
	public function save( $entity ) {
		$data        = $this->extract( $entity );
		$wpdb        = $this->get_wpdb();
		$table       = $this->get_table_name();
		$primary_key = $this->get_primary_key();

		if ( isset( $data[ $primary_key ] ) && $data[ $primary_key ] ) {
			// Update existing entity.
			$id = $data[ $primary_key ];
			unset( $data[ $primary_key ] );

			$result = $wpdb->update( $table, $data, array( $primary_key => $id ) );

			if ( $result === false ) {
				$this->logger->error(
					'Database error in update',
					array(
						'error' => $wpdb->last_error,
						'table' => $table,
						'data'  => $data,
					)
				);
				throw new \RuntimeException( 'Failed to update entity: ' . $wpdb->last_error );
			}

			$this->invalidate_cache( $id );
			return $this->find( $id );
		} else {
			// Insert new entity.
			$result = $wpdb->insert( $table, $data );

			if ( $result === false ) {
				$this->logger->error(
					'Database error in insert',
					array(
						'error' => $wpdb->last_error,
						'table' => $table,
						'data'  => $data,
					)
				);
				throw new \RuntimeException( 'Failed to insert entity: ' . $wpdb->last_error );
			}

			$id = $wpdb->insert_id;
			$this->invalidate_cache();
			return $this->find( $id );
		}
	}

	/**
	 * Delete entity.
	 *
	 * @param mixed $entity Entity to delete.
	 * @return bool Success status.
	 */
	public function delete( $entity ): bool {
		$data        = $this->extract( $entity );
		$wpdb        = $this->get_wpdb();
		$table       = $this->get_table_name();
		$primary_key = $this->get_primary_key();

		if ( ! isset( $data[ $primary_key ] ) ) {
			return false;
		}

		$id     = $data[ $primary_key ];
		$result = $wpdb->delete( $table, array( $primary_key => $id ) );

		if ( $result === false ) {
			$this->logger->error(
				'Database error in delete',
				array(
					'error' => $wpdb->last_error,
					'table' => $table,
					'id'    => $id,
				)
			);
			return false;
		}

		$this->invalidate_cache( $id );
		return true;
	}

	/**
	 * Delete entities matching criteria.
	 *
	 * @param array $criteria Deletion criteria.
	 * @return int Number of deleted entities.
	 */
	public function delete_by( array $criteria ): int {
		if ( empty( $criteria ) ) {
			return 0; // Safety check - don't delete all.
		}

		$wpdb  = $this->get_wpdb();
		$table = $this->get_table_name();

		$result = $wpdb->delete( $table, $criteria );

		if ( $result === false ) {
			$this->logger->error(
				'Database error in delete_by',
				array(
					'error'    => $wpdb->last_error,
					'table'    => $table,
					'criteria' => $criteria,
				)
			);
			return 0;
		}

		$this->invalidate_cache();
		return (int) $result;
	}

	/**
	 * Hydrate database row into entity object.
	 *
	 * @param object $row Database row.
	 * @return mixed Entity object.
	 */
	abstract protected function hydrate( object $row );

	/**
	 * Extract entity data for database storage.
	 *
	 * @param mixed $entity Entity object.
	 * @return array Entity data.
	 */
	abstract protected function extract( $entity ): array;

	/**
	 * Invalidate cache for this repository.
	 *
	 * @param mixed $id Optional specific ID to invalidate.
	 */
	protected function invalidate_cache( $id = null ): void {
		if ( $id !== null ) {
			$this->cache->delete( $this->cache_key( 'find_' . $id ) );
		}

		// Clear all find_by and count caches.
		$this->cache->flush_group( $this->cache_group );
	}
}
