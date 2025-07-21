<?php
/**
 * TransactionManager.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Database
 */

declare(strict_types=1);

namespace NuclearEngagement\Database;

use NuclearEngagement\Exceptions\DatabaseException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database transaction manager with proper error handling and rollback
 */
class TransactionManager {

	/** @var \wpdb */
	private \wpdb $wpdb;

	/** @var bool */
	private bool $in_transaction = false;

	/** @var int */
	private int $transaction_level = 0;

	/** @var array */
	private array $savepoints = array();

	/** @var bool */
	private bool $auto_rollback = true;

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	/**
	 * Begin transaction
	 *
	 * @throws DatabaseException
	 */
	public function begin(): void {
		if ( $this->transaction_level === 0 ) {
			$result = $this->wpdb->query( 'START TRANSACTION' );

			if ( $result === false ) {
				throw new DatabaseException(
					'Failed to start database transaction',
					$this->wpdb->last_error,
					'START TRANSACTION'
				);
			}

			$this->in_transaction = true;
		} else {
			// Nested transaction - use savepoint with proper escaping
			$savepoint_id = 'sp_' . intval( $this->transaction_level );
			// Use backticks to escape identifier and validate format
			if ( ! preg_match( '/^sp_\d+$/', $savepoint_id ) ) {
				throw new DatabaseException( 'Invalid savepoint identifier format' );
			}
			$result = $this->wpdb->query( sprintf( 'SAVEPOINT `%s`', $savepoint_id ) );

			if ( $result === false ) {
				throw new DatabaseException(
					'Failed to create savepoint',
					$this->wpdb->last_error,
					sprintf( 'SAVEPOINT `%s`', $savepoint_id )
				);
			}

			$this->savepoints[] = $savepoint_id;
		}

		++$this->transaction_level;
	}

	/**
	 * Commit transaction
	 *
	 * @throws DatabaseException
	 */
	public function commit(): void {
		if ( $this->transaction_level === 0 ) {
			throw new DatabaseException( 'No active transaction to commit' );
		}

		--$this->transaction_level;

		if ( $this->transaction_level === 0 ) {
			$result = $this->wpdb->query( 'COMMIT' );

			if ( $result === false ) {
				throw new DatabaseException(
					'Failed to commit transaction',
					$this->wpdb->last_error,
					'COMMIT'
				);
			}

			$this->in_transaction = false;
			$this->savepoints     = array();
		} else {
			// Just decrement level for nested transaction
			array_pop( $this->savepoints );
		}
	}

	/**
	 * Rollback transaction
	 *
	 * @throws DatabaseException
	 */
	public function rollback(): void {
		if ( $this->transaction_level === 0 ) {
			throw new DatabaseException( 'No active transaction to rollback' );
		}

		--$this->transaction_level;

		if ( $this->transaction_level === 0 ) {
			$result = $this->wpdb->query( 'ROLLBACK' );

			if ( $result === false ) {
				throw new DatabaseException(
					'Failed to rollback transaction',
					$this->wpdb->last_error,
					'ROLLBACK'
				);
			}

			$this->in_transaction = false;
			$this->savepoints     = array();
		} else {
			// Rollback to savepoint with proper escaping
			$savepoint = array_pop( $this->savepoints );
			// Validate savepoint format before using
			if ( ! preg_match( '/^sp_\d+$/', $savepoint ) ) {
				throw new DatabaseException( 'Invalid savepoint identifier format for rollback' );
			}
			$result = $this->wpdb->query( sprintf( 'ROLLBACK TO SAVEPOINT `%s`', $savepoint ) );

			if ( $result === false ) {
				throw new DatabaseException(
					'Failed to rollback to savepoint',
					$this->wpdb->last_error,
					sprintf( 'ROLLBACK TO SAVEPOINT `%s`', $savepoint )
				);
			}
		}
	}

	/**
	 * Execute callback within transaction
	 *
	 * @param callable $callback
	 * @param int      $max_retries Maximum number of retries for deadlock
	 * @return mixed Result from callback
	 * @throws DatabaseException
	 * @throws \Throwable
	 */
	public function execute( callable $callback, int $max_retries = 3 ) {
		$attempts       = 0;
		$last_exception = null;

		while ( $attempts < $max_retries ) {
			++$attempts;

			try {
				$this->begin();

				$result = $callback( $this->wpdb );

				$this->commit();

				return $result;

			} catch ( \Throwable $e ) {
				$last_exception = $e;

				// Always try to rollback
				try {
					if ( $this->in_transaction ) {
						$this->rollback();
					}
				} catch ( \Throwable $rollback_exception ) {
					// Log rollback failure but throw original exception
					\NuclearEngagement\Services\LoggingService::log(
						sprintf(
							'Transaction rollback failed - Rollback error: %s | Original error: %s | Transaction level: %d',
							$rollback_exception->getMessage(),
							$e->getMessage(),
							$this->transaction_level
						)
					);
				}

				// Check if this is a deadlock that we should retry
				if ( $this->is_deadlock_error( $e ) && $attempts < $max_retries ) {
					// Wait before retry with exponential backoff
					usleep( $attempts * 100000 ); // 0.1s, 0.2s, 0.3s
					continue;
				}

				// Re-throw the exception
				throw $e;
			}
		}

		// If we got here, all retries failed
		if ( $last_exception ) {
			throw new DatabaseException(
				'Transaction failed after ' . $attempts . ' attempts: ' . $last_exception->getMessage(),
				'',
				'',
				0,
				$last_exception
			);
		}

		throw new DatabaseException( 'Transaction failed after ' . $attempts . ' attempts' );
	}

	/**
	 * Execute multiple queries in transaction
	 *
	 * @param array $queries Array of SQL queries
	 * @return array Results from each query
	 * @throws DatabaseException
	 */
	public function execute_batch( array $queries ): array {
		return $this->execute(
			function ( $wpdb ) use ( $queries ) {
				$results = array();

				foreach ( $queries as $index => $query ) {
					$result = $wpdb->query( $query );

					if ( $result === false ) {
						throw new DatabaseException(
							sprintf( 'Query %d failed in batch: %s', $index, $wpdb->last_error ),
							$wpdb->last_error,
							$query
						);
					}

					$results[] = $result;
				}

				return $results;
			}
		);
	}

	/**
	 * Check if in transaction
	 */
	public function in_transaction(): bool {
		return $this->in_transaction;
	}

	/**
	 * Get transaction level
	 */
	public function get_transaction_level(): int {
		return $this->transaction_level;
	}

	/**
	 * Check if error is a deadlock
	 */
	private function is_deadlock_error( \Throwable $e ): bool {
		$message = $e->getMessage();

		// MySQL deadlock error codes
		return strpos( $message, '1213' ) !== false || // Deadlock found
				strpos( $message, '1205' ) !== false || // Lock wait timeout
				stripos( $message, 'deadlock' ) !== false ||
				stripos( $message, 'lock wait timeout' ) !== false;
	}

	/**
	 * Ensure rollback on destruct if transaction is still active
	 */
	public function __destruct() {
		if ( $this->in_transaction && $this->auto_rollback ) {
			try {
				while ( $this->transaction_level > 0 ) {
					$this->rollback();
				}
			} catch ( \Throwable $e ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'Nuclear Engagement: Failed to rollback transaction in destructor: ' . $e->getMessage() );
			}
		}
	}
}
