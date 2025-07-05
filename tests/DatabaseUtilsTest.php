<?php

use NuclearEngagement\Utils\DatabaseUtils;
use PHPUnit\Framework\TestCase;

class DatabaseUtilsTest extends TestCase {

	public function setUp(): void {
		\WP_Mock::setUp();
		
		// Mock global $wpdb
		global $wpdb;
		$wpdb = \Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->posts = 'wp_posts';
		$wpdb->postmeta = 'wp_postmeta';
		$wpdb->last_error = '';
		$wpdb->insert_id = 0;
	}

	public function tearDown(): void {
		\WP_Mock::tearDown();
		\Mockery::close();
	}

	public function test_table_exists() {
		global $wpdb;
		
		$wpdb->shouldReceive( 'get_var' )
			->with( \Mockery::pattern( '/SHOW TABLES LIKE/' ) )
			->once()
			->andReturn( 'wp_posts' );
		
		$wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( "SHOW TABLES LIKE 'wp_posts'" );
		
		$result = DatabaseUtils::table_exists( 'wp_posts' );
		$this->assertTrue( $result );
	}

	public function test_table_does_not_exist() {
		global $wpdb;
		
		$wpdb->shouldReceive( 'get_var' )
			->with( \Mockery::pattern( '/SHOW TABLES LIKE/' ) )
			->once()
			->andReturn( null );
		
		$wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( "SHOW TABLES LIKE 'nonexistent_table'" );
		
		$result = DatabaseUtils::table_exists( 'nonexistent_table' );
		$this->assertFalse( $result );
	}

	public function test_sanitize_table_name() {
		$tests = array(
			'valid_table' => 'valid_table',
			'table-with-dashes' => 'table_with_dashes',
			'table with spaces' => 'table_with_spaces',
			'TABLE_UPPERCASE' => 'table_uppercase',
			'123numeric_start' => 'numeric_start', // Remove leading numbers
			'table@special#chars!' => 'table_special_chars',
			'' => 'default_table',
		);
		
		foreach ( $tests as $input => $expected ) {
			$result = DatabaseUtils::sanitize_table_name( $input );
			$this->assertEquals( $expected, $result );
		}
	}

	public function test_safe_insert() {
		global $wpdb;
		
		$table = 'wp_test_table';
		$data = array(
			'name' => 'Test Name',
			'value' => 123,
			'created_at' => '2023-01-01 12:00:00',
		);
		
		$wpdb->shouldReceive( 'insert' )
			->with( $table, $data, array( '%s', '%d', '%s' ) )
			->once()
			->andReturn( 1 );
		
		$wpdb->insert_id = 456;
		
		$result = DatabaseUtils::safe_insert( $table, $data );
		$this->assertEquals( 456, $result );
	}

	public function test_safe_insert_failure() {
		global $wpdb;
		
		$table = 'wp_test_table';
		$data = array( 'invalid' => 'data' );
		
		$wpdb->shouldReceive( 'insert' )
			->once()
			->andReturn( false );
		
		$wpdb->last_error = 'Duplicate entry';
		
		$result = DatabaseUtils::safe_insert( $table, $data );
		$this->assertFalse( $result );
	}

	public function test_safe_update() {
		global $wpdb;
		
		$table = 'wp_test_table';
		$data = array( 'name' => 'Updated Name' );
		$where = array( 'id' => 123 );
		
		$wpdb->shouldReceive( 'update' )
			->with( $table, $data, $where, array( '%s' ), array( '%d' ) )
			->once()
			->andReturn( 1 );
		
		$result = DatabaseUtils::safe_update( $table, $data, $where );
		$this->assertEquals( 1, $result );
	}

	public function test_safe_delete() {
		global $wpdb;
		
		$table = 'wp_test_table';
		$where = array( 'id' => 123 );
		
		$wpdb->shouldReceive( 'delete' )
			->with( $table, $where, array( '%d' ) )
			->once()
			->andReturn( 1 );
		
		$result = DatabaseUtils::safe_delete( $table, $where );
		$this->assertEquals( 1, $result );
	}

	public function test_get_table_size() {
		global $wpdb;
		
		$wpdb->shouldReceive( 'get_var' )
			->with( \Mockery::pattern( '/information_schema\.tables/' ) )
			->once()
			->andReturn( '1024' );
		
		$wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared_query' );
		
		$size = DatabaseUtils::get_table_size( 'wp_posts' );
		$this->assertEquals( 1024, $size );
	}

	public function test_optimize_table() {
		global $wpdb;
		
		$wpdb->shouldReceive( 'query' )
			->with( 'OPTIMIZE TABLE wp_posts' )
			->once()
			->andReturn( 1 );
		
		$result = DatabaseUtils::optimize_table( 'wp_posts' );
		$this->assertTrue( $result );
	}

	public function test_analyze_table() {
		global $wpdb;
		
		$wpdb->shouldReceive( 'query' )
			->with( 'ANALYZE TABLE wp_posts' )
			->once()
			->andReturn( 1 );
		
		$result = DatabaseUtils::analyze_table( 'wp_posts' );
		$this->assertTrue( $result );
	}

	public function test_check_table() {
		global $wpdb;
		
		$wpdb->shouldReceive( 'get_results' )
			->with( 'CHECK TABLE wp_posts' )
			->once()
			->andReturn( array(
				(object) array(
					'Table' => 'wp_posts',
					'Op' => 'check',
					'Msg_type' => 'status',
					'Msg_text' => 'OK',
				),
			) );
		
		$result = DatabaseUtils::check_table( 'wp_posts' );
		$this->assertTrue( $result );
	}

	public function test_repair_table() {
		global $wpdb;
		
		$wpdb->shouldReceive( 'query' )
			->with( 'REPAIR TABLE wp_posts' )
			->once()
			->andReturn( 1 );
		
		$result = DatabaseUtils::repair_table( 'wp_posts' );
		$this->assertTrue( $result );
	}

	public function test_get_index_info() {
		global $wpdb;
		
		$mock_indexes = array(
			(object) array(
				'Key_name' => 'PRIMARY',
				'Column_name' => 'ID',
				'Index_type' => 'BTREE',
			),
			(object) array(
				'Key_name' => 'idx_post_name',
				'Column_name' => 'post_name',
				'Index_type' => 'BTREE',
			),
		);
		
		$wpdb->shouldReceive( 'get_results' )
			->with( 'SHOW INDEX FROM wp_posts' )
			->once()
			->andReturn( $mock_indexes );
		
		$indexes = DatabaseUtils::get_index_info( 'wp_posts' );
		$this->assertCount( 2, $indexes );
		$this->assertEquals( 'PRIMARY', $indexes[0]->Key_name );
	}

	public function test_index_exists() {
		global $wpdb;
		
		$wpdb->shouldReceive( 'get_var' )
			->with( \Mockery::pattern( '/information_schema\.statistics/' ) )
			->once()
			->andReturn( 1 );
		
		$wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared_query' );
		
		$exists = DatabaseUtils::index_exists( 'wp_posts', 'idx_post_name' );
		$this->assertTrue( $exists );
	}

	public function test_create_index() {
		global $wpdb;
		
		$wpdb->shouldReceive( 'query' )
			->with( 'CREATE INDEX idx_test ON wp_posts (post_title)' )
			->once()
			->andReturn( 1 );
		
		$result = DatabaseUtils::create_index( 'wp_posts', 'idx_test', 'post_title' );
		$this->assertTrue( $result );
	}

	public function test_drop_index() {
		global $wpdb;
		
		$wpdb->shouldReceive( 'query' )
			->with( 'DROP INDEX idx_test ON wp_posts' )
			->once()
			->andReturn( 1 );
		
		$result = DatabaseUtils::drop_index( 'wp_posts', 'idx_test' );
		$this->assertTrue( $result );
	}

	public function test_get_database_version() {
		global $wpdb;
		
		$wpdb->shouldReceive( 'get_var' )
			->with( 'SELECT VERSION()' )
			->once()
			->andReturn( '8.0.25' );
		
		$version = DatabaseUtils::get_database_version();
		$this->assertEquals( '8.0.25', $version );
	}

	public function test_transaction_operations() {
		global $wpdb;
		
		// Test start transaction
		$wpdb->shouldReceive( 'query' )
			->with( 'START TRANSACTION' )
			->once()
			->andReturn( 1 );
		
		$start_result = DatabaseUtils::start_transaction();
		$this->assertTrue( $start_result );
		
		// Test commit
		$wpdb->shouldReceive( 'query' )
			->with( 'COMMIT' )
			->once()
			->andReturn( 1 );
		
		$commit_result = DatabaseUtils::commit();
		$this->assertTrue( $commit_result );
		
		// Test rollback
		$wpdb->shouldReceive( 'query' )
			->with( 'ROLLBACK' )
			->once()
			->andReturn( 1 );
		
		$rollback_result = DatabaseUtils::rollback();
		$this->assertTrue( $rollback_result );
	}

	public function test_batch_insert() {
		global $wpdb;
		
		$table = 'wp_test_table';
		$data = array(
			array( 'name' => 'Item 1', 'value' => 100 ),
			array( 'name' => 'Item 2', 'value' => 200 ),
			array( 'name' => 'Item 3', 'value' => 300 ),
		);
		
		$expected_query = "INSERT INTO wp_test_table (name, value) VALUES ('Item 1', 100), ('Item 2', 200), ('Item 3', 300)";
		
		$wpdb->shouldReceive( 'query' )
			->with( \Mockery::pattern( '/INSERT INTO.*VALUES/' ) )
			->once()
			->andReturn( 3 );
		
		$result = DatabaseUtils::batch_insert( $table, $data );
		$this->assertEquals( 3, $result );
	}

	public function test_escape_like() {
		global $wpdb;
		
		$wpdb->shouldReceive( 'esc_like' )
			->with( 'test%_string' )
			->once()
			->andReturn( 'test\\%\\_string' );
		
		$escaped = DatabaseUtils::escape_like( 'test%_string' );
		$this->assertEquals( 'test\\%\\_string', $escaped );
	}

	public function test_get_column_info() {
		global $wpdb;
		
		$mock_columns = array(
			(object) array(
				'Field' => 'ID',
				'Type' => 'bigint(20) unsigned',
				'Null' => 'NO',
				'Key' => 'PRI',
				'Default' => null,
				'Extra' => 'auto_increment',
			),
			(object) array(
				'Field' => 'post_title',
				'Type' => 'text',
				'Null' => 'NO',
				'Key' => '',
				'Default' => '',
				'Extra' => '',
			),
		);
		
		$wpdb->shouldReceive( 'get_results' )
			->with( 'DESCRIBE wp_posts' )
			->once()
			->andReturn( $mock_columns );
		
		$columns = DatabaseUtils::get_column_info( 'wp_posts' );
		$this->assertCount( 2, $columns );
		$this->assertEquals( 'ID', $columns[0]->Field );
	}

	public function test_column_exists() {
		global $wpdb;
		
		$wpdb->shouldReceive( 'get_var' )
			->with( \Mockery::pattern( '/information_schema\.columns/' ) )
			->once()
			->andReturn( 1 );
		
		$wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared_query' );
		
		$exists = DatabaseUtils::column_exists( 'wp_posts', 'post_title' );
		$this->assertTrue( $exists );
	}

	public function test_add_column() {
		global $wpdb;
		
		$wpdb->shouldReceive( 'query' )
			->with( 'ALTER TABLE wp_posts ADD COLUMN new_column VARCHAR(255) DEFAULT NULL' )
			->once()
			->andReturn( 1 );
		
		$result = DatabaseUtils::add_column( 'wp_posts', 'new_column', 'VARCHAR(255) DEFAULT NULL' );
		$this->assertTrue( $result );
	}

	public function test_drop_column() {
		global $wpdb;
		
		$wpdb->shouldReceive( 'query' )
			->with( 'ALTER TABLE wp_posts DROP COLUMN old_column' )
			->once()
			->andReturn( 1 );
		
		$result = DatabaseUtils::drop_column( 'wp_posts', 'old_column' );
		$this->assertTrue( $result );
	}

	public function test_error_handling() {
		global $wpdb;
		
		// Test insert with error
		$wpdb->shouldReceive( 'insert' )
			->once()
			->andReturn( false );
		
		$wpdb->last_error = 'Table does not exist';
		
		$result = DatabaseUtils::safe_insert( 'nonexistent_table', array() );
		$this->assertFalse( $result );
		
		// Get last error
		$error = DatabaseUtils::get_last_error();
		$this->assertEquals( 'Table does not exist', $error );
	}

	public function test_query_performance() {
		global $wpdb;
		
		$slow_query = 'SELECT * FROM wp_posts WHERE post_content LIKE "%test%"';
		$execution_time = 0.5; // 500ms
		
		DatabaseUtils::log_slow_query( $slow_query, $execution_time );
		
		$slow_queries = DatabaseUtils::get_slow_queries();
		$this->assertIsArray( $slow_queries );
	}

	public function test_charset_and_collation() {
		global $wpdb;
		
		$wpdb->shouldReceive( 'get_var' )
			->with( \Mockery::pattern( '/information_schema\.tables/' ) )
			->once()
			->andReturn( 'utf8mb4_unicode_ci' );
		
		$wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared_query' );
		
		$collation = DatabaseUtils::get_table_collation( 'wp_posts' );
		$this->assertEquals( 'utf8mb4_unicode_ci', $collation );
	}

	public function test_foreign_key_operations() {
		global $wpdb;
		
		// Test adding foreign key
		$wpdb->shouldReceive( 'query' )
			->with( \Mockery::pattern( '/ALTER TABLE.*ADD CONSTRAINT.*FOREIGN KEY/' ) )
			->once()
			->andReturn( 1 );
		
		$result = DatabaseUtils::add_foreign_key( 
			'wp_postmeta', 
			'fk_postmeta_post', 
			'post_id', 
			'wp_posts', 
			'ID' 
		);
		$this->assertTrue( $result );
		
		// Test dropping foreign key
		$wpdb->shouldReceive( 'query' )
			->with( 'ALTER TABLE wp_postmeta DROP FOREIGN KEY fk_postmeta_post' )
			->once()
			->andReturn( 1 );
		
		$result = DatabaseUtils::drop_foreign_key( 'wp_postmeta', 'fk_postmeta_post' );
		$this->assertTrue( $result );
	}

	public function test_connection_status() {
		global $wpdb;
		
		$wpdb->shouldReceive( 'get_var' )
			->with( 'SELECT 1' )
			->once()
			->andReturn( '1' );
		
		$is_connected = DatabaseUtils::test_connection();
		$this->assertTrue( $is_connected );
	}

	public function test_deadlock_detection() {
		global $wpdb;
		
		$wpdb->last_error = 'Deadlock found when trying to get lock';
		
		$is_deadlock = DatabaseUtils::is_deadlock_error();
		$this->assertTrue( $is_deadlock );
		
		// Test non-deadlock error
		$wpdb->last_error = 'Table does not exist';
		$is_deadlock = DatabaseUtils::is_deadlock_error();
		$this->assertFalse( $is_deadlock );
	}
}