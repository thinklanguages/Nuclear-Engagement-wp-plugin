<?php
/**
 * DatabaseException.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Exceptions
 */

declare(strict_types=1);
/**
 * File: inc/Exceptions/DatabaseException.php
 *
 * Database exception class.
 *
 * @package NuclearEngagement\Exceptions
 */

namespace NuclearEngagement\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exception thrown when database operations fail.
 */
class DatabaseException extends BaseException {

	/** @var string */
	private string $query = '';

	/** @var string */
	private string $db_error = '';

	public function __construct( string $message, string $db_error = '', string $query = '', int $code = 500, ?\Throwable $previous = null ) {
		$this->db_error = $db_error;
		$this->query    = $query;

		$context = array(
			'db_error' => $db_error,
			'query'    => $query,
		);

		parent::__construct( $message, $code, $previous, $context );
		$this->error_code = 'DATABASE_ERROR';
	}

	/**
	 * Get database error message.
	 *
	 * @return string Database error.
	 */
	public function get_db_error(): string {
		return $this->db_error;
	}

	/**
	 * Get SQL query that caused the error.
	 *
	 * @return string SQL query.
	 */
	public function get_query(): string {
		return $this->query;
	}

	/**
	 * Get user-friendly message.
	 *
	 * @return string User-friendly message.
	 */
	public function get_user_message(): string {
		return __( 'A database error occurred. Please try again later.', 'nuclear-engagement' );
	}
}
