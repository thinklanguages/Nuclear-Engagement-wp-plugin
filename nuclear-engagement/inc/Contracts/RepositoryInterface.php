<?php
declare(strict_types=1);
/**
 * File: inc/Contracts/RepositoryInterface.php
 *
 * Repository interface for data access.
 *
 * @package NuclearEngagement\Contracts
 */

namespace NuclearEngagement\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defines repository pattern contract for data access.
 */
interface RepositoryInterface {
	
	/**
	 * Find entity by ID.
	 *
	 * @param mixed $id Entity ID.
	 * @return mixed|null Entity or null if not found.
	 */
	public function find( $id );
	
	/**
	 * Find all entities matching criteria.
	 *
	 * @param array $criteria Search criteria.
	 * @param array $order_by Order by criteria.
	 * @param int   $limit    Limit results.
	 * @param int   $offset   Offset results.
	 * @return array Found entities.
	 */
	public function find_by( array $criteria = array(), array $order_by = array(), int $limit = 0, int $offset = 0 ): array;
	
	/**
	 * Find one entity matching criteria.
	 *
	 * @param array $criteria Search criteria.
	 * @return mixed|null Entity or null if not found.
	 */
	public function find_one_by( array $criteria ): ?object;
	
	/**
	 * Count entities matching criteria.
	 *
	 * @param array $criteria Search criteria.
	 * @return int Count of entities.
	 */
	public function count( array $criteria = array() ): int;
	
	/**
	 * Save entity.
	 *
	 * @param mixed $entity Entity to save.
	 * @return mixed Saved entity.
	 */
	public function save( $entity );
	
	/**
	 * Delete entity.
	 *
	 * @param mixed $entity Entity to delete.
	 * @return bool Success status.
	 */
	public function delete( $entity ): bool;
	
	/**
	 * Delete entities matching criteria.
	 *
	 * @param array $criteria Deletion criteria.
	 * @return int Number of deleted entities.
	 */
	public function delete_by( array $criteria ): int;
}