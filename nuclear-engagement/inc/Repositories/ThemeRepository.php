<?php
/**
 * ThemeRepository.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Repositories
 */

namespace NuclearEngagement\Repositories;

use NuclearEngagement\Models\Theme;
use NuclearEngagement\Database\Schema\ThemeSchema;

class ThemeRepository {
	private $table_name;

	public function __construct() {
		$this->table_name = ThemeSchema::get_table_name();
	}

	public function find( $id ) {
		global $wpdb;

		$row = // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d",
				$id
			),
			ARRAY_A
		);

		return $row ? new Theme( $row ) : null;
	}

	public function find_by_name( $name ) {
		global $wpdb;

		$row = // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE name = %s",
				$name
			),
			ARRAY_A
		);

		return $row ? new Theme( $row ) : null;
	}

	public function get_active() {
		global $wpdb;

		$row = // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->get_row(
			"SELECT * FROM {$this->table_name} WHERE is_active = 1 LIMIT 1",
			ARRAY_A
		);

		return $row ? new Theme( $row ) : null;
	}

	public function get_all( $type = null ) {
		global $wpdb;

		$query = "SELECT * FROM {$this->table_name}";
		if ( $type ) {
			$query = $wpdb->prepare( $query . ' WHERE type = %s', $type );
		}
		$query .= ' ORDER BY name ASC';

		$rows = // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->get_results( $query, ARRAY_A );

		return array_map(
			function ( $row ) {
				return new Theme( $row );
			},
			$rows
		);
	}

	public function save( Theme $theme ) {
		global $wpdb;

		$data = array(
			'name'        => $theme->name,
			'type'        => $theme->type,
			'config_json' => wp_json_encode( $theme->config ),
			'css_hash'    => $theme->css_hash ?: $theme->generate_hash(),
			'css_path'    => $theme->css_path,
			'is_active'   => (int) $theme->is_active,
		);

		if ( $theme->id ) {
			$result = $wpdb->update(
				$this->table_name,
				$data,
				array( 'id' => $theme->id ),
				array( '%s', '%s', '%s', '%s', '%s', '%d' ),
				array( '%d' )
			);

			if ( $result === false ) {
				return false;
			}
		} else {
			$result = $wpdb->insert(
				$this->table_name,
				$data,
				array( '%s', '%s', '%s', '%s', '%s', '%d' )
			);

			if ( $result === false ) {
				return false;
			}

			$theme->id = $wpdb->insert_id;
		}

		return $theme;
	}

	public function delete( $id ) {
		global $wpdb;

		return $wpdb->delete(
			$this->table_name,
			array( 'id' => $id ),
			array( '%d' )
		);
	}

	public function set_active( $id ) {
		global $wpdb;

		// Use prepared statement for security.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table_name} SET is_active = 0"
			)
		);

		return $wpdb->update(
			$this->table_name,
			array( 'is_active' => 1 ),
			array( 'id' => $id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	public function deactivate_all() {
		global $wpdb;

		// Use prepared statement for security.
		return // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table_name} SET is_active = 0"
			)
		);
	}
}
