<?php
/**
 * TasksControllerTest.php - Tests for TasksController
 *
 * @package NuclearEngagement_Tests
 */

declare(strict_types=1);

// WordPress helpers that BaseController + TasksController call unqualified.
// They resolve against this namespace first; we provide lightweight test
// doubles that read/write global flags so each test can flip them in setUp.
namespace NuclearEngagement\Admin\Controller\Ajax {

	if ( ! function_exists( __NAMESPACE__ . '\\current_user_can' ) ) {
		function current_user_can( $cap ) {
			return $GLOBALS['nuclen_test_cap'] ?? true;
		}
	}

	if ( ! function_exists( __NAMESPACE__ . '\\wp_verify_nonce' ) ) {
		function wp_verify_nonce( $nonce, $action = -1 ) {
			return $GLOBALS['nuclen_test_nonce_valid'] ?? true;
		}
	}

	if ( ! function_exists( __NAMESPACE__ . '\\check_ajax_referer' ) ) {
		function check_ajax_referer( $action, $query_arg = false, $die = true ) {
			return $GLOBALS['nuclen_test_nonce_valid'] ?? true;
		}
	}

	if ( ! function_exists( __NAMESPACE__ . '\\status_header' ) ) {
		function status_header( $code ) {
			$GLOBALS['nuclen_test_status_code'] = $code;
		}
	}

	if ( ! function_exists( __NAMESPACE__ . '\\wp_unslash' ) ) {
		function wp_unslash( $value ) {
			return is_string( $value ) ? stripslashes( $value ) : $value;
		}
	}

	if ( ! function_exists( __NAMESPACE__ . '\\sanitize_text_field' ) ) {
		function sanitize_text_field( $str ) {
			return is_string( $str ) ? trim( $str ) : '';
		}
	}

	if ( ! function_exists( __NAMESPACE__ . '\\get_current_user_id' ) ) {
		function get_current_user_id() {
			return $GLOBALS['nuclen_test_current_user_id'] ?? 1;
		}
	}

	if ( ! function_exists( __NAMESPACE__ . '\\__' ) ) {
		function __( $text, $domain = null ) {
			return $text;
		}
	}

	if ( ! function_exists( __NAMESPACE__ . '\\wp_send_json_success' ) ) {
		function wp_send_json_success( $data = null, $status_code = null ) {
			// In production wp_send_json_success calls exit; a subsequent
			// call cannot happen. Controllers here catch \Throwable though,
			// so our halt-surrogate gets caught and triggers a second call.
			// Gate actual output on a first-call flag to mimic the exit.
			if ( empty( $GLOBALS['nuclen_test_json_sent'] ) ) {
				echo json_encode( array( 'success' => true, 'data' => $data ) );
				$GLOBALS['nuclen_test_json_sent'] = true;
			}
			throw new \NuclearEngagement_Tests_JsonSent();
		}
	}

	if ( ! function_exists( __NAMESPACE__ . '\\wp_send_json_error' ) ) {
		function wp_send_json_error( $data = null, $status_code = null ) {
			if ( empty( $GLOBALS['nuclen_test_json_sent'] ) ) {
				echo json_encode( array( 'success' => false, 'data' => $data ) );
				$GLOBALS['nuclen_test_json_sent'] = true;
			}
			throw new \NuclearEngagement_Tests_JsonSent();
		}
	}

	if ( ! function_exists( __NAMESPACE__ . '\\get_transient' ) ) {
		function get_transient( $key ) {
			return $GLOBALS['wp_transients'][ $key ] ?? false;
		}
	}

	if ( ! function_exists( __NAMESPACE__ . '\\set_transient' ) ) {
		function set_transient( $key, $value, $expiry = 0 ) {
			$GLOBALS['wp_transients'][ $key ] = $value;
			return true;
		}
	}

	if ( ! function_exists( __NAMESPACE__ . '\\delete_transient' ) ) {
		function delete_transient( $key ) {
			unset( $GLOBALS['wp_transients'][ $key ] );
			return true;
		}
	}

	if ( ! function_exists( __NAMESPACE__ . '\\wp_clear_scheduled_hook' ) ) {
		function wp_clear_scheduled_hook( $hook, $args = array() ) {
			$GLOBALS['nuclen_test_clear_calls'][] = array( 'hook' => $hook, 'args' => $args );
			return true;
		}
	}

	if ( ! function_exists( __NAMESPACE__ . '\\do_action' ) ) {
		function do_action( $hook, ...$args ) {
			$GLOBALS['nuclen_test_actions'][] = array( 'hook' => $hook, 'args' => $args );
		}
	}
}

namespace {

	if ( ! class_exists( 'NuclearEngagement_Tests_JsonSent' ) ) {
		/**
		 * Thrown by the namespaced wp_send_json_* stubs to unwind the call
		 * stack. Extends \Error (not \Exception) so the controller's generic
		 * catch(\Exception) blocks don't swallow it and emit a second JSON.
		 */
		class NuclearEngagement_Tests_JsonSent extends \Error {}
	}

	use PHPUnit\Framework\TestCase;
	use NuclearEngagement\Admin\Controller\Ajax\TasksController;
	use NuclearEngagement\Core\ServiceContainer;
	use NuclearEngagement\Services\CentralizedPollingQueue;
	use NuclearEngagement\Services\AdminNoticeService;
	use NuclearEngagement\Tests\Support\TaskTransientStubTrait;

	/**
	 * Test TasksController
	 */
	class TasksControllerTest extends TestCase {
		use TaskTransientStubTrait;

		private TasksController $controller;
		private ServiceContainer $container;
		private $mock_polling_queue;
		private $mock_notice_service;

		public function setUp(): void {
			parent::setUp();

			\Brain\Monkey\setUp();

			$this->resetTransientStubs();
			$GLOBALS['nuclen_test_cap']             = true;
			$GLOBALS['nuclen_test_nonce_valid']     = true;
			$GLOBALS['nuclen_test_current_user_id'] = 1;
			$GLOBALS['nuclen_test_clear_calls']     = array();
			$GLOBALS['nuclen_test_actions']         = array();
			$GLOBALS['nuclen_test_status_code']     = null;
			$GLOBALS['nuclen_test_json_sent']       = false;

			$this->container           = $this->createMock(ServiceContainer::class);
			$this->mock_polling_queue  = $this->createMock(CentralizedPollingQueue::class);
			$this->mock_notice_service = $this->createMock(AdminNoticeService::class);

			$this->container->method('has')
				->willReturnCallback(function($service) {
					return in_array($service, ['centralized_polling_queue', 'admin_notice_service']);
				});

			$this->container->method('get')
				->willReturnCallback(function($service) {
					if ($service === 'centralized_polling_queue') {
						return $this->mock_polling_queue;
					}
					if ($service === 'admin_notice_service') {
						return $this->mock_notice_service;
					}
					return null;
				});

			$this->controller = new TasksController($this->container);
		}

		public function tearDown(): void {
			$this->resetTransientStubs();
			\Brain\Monkey\tearDown();
			parent::tearDown();
		}

		private function invokeController( callable $call ): void {
			try {
				$call();
			} catch ( \NuclearEngagement_Tests_JsonSent $e ) {
				// Expected "exit" surrogate; swallow and let the output assertion verify.
			}
		}

		private function expectJson( bool $success, $data ): void {
			$this->expectOutputString( json_encode( array( 'success' => $success, 'data' => $data ) ) );
		}

		public function testRunTaskWithoutPermissions() {
			$GLOBALS['nuclen_test_cap'] = false;
			$_POST['nonce']             = 'test_nonce';
			$_POST['task_id']           = 'test_task';

			$this->expectJson( false, array( 'message' => 'Insufficient permissions' ) );
			$this->invokeController( fn() => $this->controller->run_task() );
		}

		public function testRunTaskWithInvalidNonce() {
			$GLOBALS['nuclen_test_nonce_valid'] = false;
			$_POST['nonce']                     = 'invalid_nonce';
			$_POST['task_id']                   = 'test_task';

			$this->expectJson( false, array( 'message' => 'Security check failed' ) );
			$this->invokeController( fn() => $this->controller->run_task() );
		}

		public function testRunTaskWithEmptyTaskId() {
			$_POST['nonce']   = 'test_nonce';
			$_POST['task_id'] = '';

			$this->expectJson( false, array( 'message' => 'Invalid task ID' ) );
			$this->invokeController( fn() => $this->controller->run_task() );
		}

		public function testRunTaskRateLimiting() {
			$GLOBALS['wp_transients']['nuclen_task_action_1'] = true;

			$_POST['nonce']   = 'test_nonce';
			$_POST['task_id'] = 'test_task';

			$this->expectJson( false, array(
				'message' => 'Please wait a few seconds before performing another action.',
			) );
			$this->invokeController( fn() => $this->controller->run_task() );
		}

		public function testRunBatchTaskAlreadyProcessing() {
			$this->seedBatchTransient( 'nuclen_batch_123', array( 'status' => 'processing' ) );

			$_POST['nonce']   = 'test_nonce';
			$_POST['task_id'] = 'nuclen_batch_123';

			$this->expectJson( false, array(
				'message' => 'This task is already running. Please wait for it to complete.',
			) );
			$this->invokeController( fn() => $this->controller->run_task() );
		}

		public function testRunBatchTaskSuccess() {
			$this->seedBatchTransient( 'nuclen_batch_123', array( 'status' => 'pending' ) );

			$_POST['nonce']   = 'test_nonce';
			$_POST['task_id'] = 'nuclen_batch_123';

			$this->expectJson( true, array(
				'message' => 'Batch nuclen_batch_123 has been triggered for immediate processing.',
			) );

			$this->invokeController( fn() => $this->controller->run_task() );

			$actions = array_filter(
				$GLOBALS['nuclen_test_actions'],
				static fn( $a ) => $a['hook'] === 'nuclen_process_batch'
			);
			$this->assertCount( 1, $actions, 'nuclen_process_batch dispatched once' );
		}

		public function testRunGenerationTaskAlreadyProcessing() {
			$this->seedTaskTransient( 'test_gen_123', array(
				'workflow_type' => 'summary',
				'status'        => 'processing',
				'batch_jobs'    => array(),
			) );

			$_POST['nonce']   = 'test_nonce';
			$_POST['task_id'] = 'test_gen_123';

			// run_task rewraps inner \Exception as "Failed to retrieve task data: <msg>",
			// then its own outer catch logs and returns the generic user-facing error.
			$this->expectJson( false, array(
				'message' => 'An error occurred while processing the task. Please try again.',
			) );
			$this->invokeController( fn() => $this->controller->run_task() );
		}

		public function testRunGenerationTaskSuccess() {
			$this->seedTaskTransient( 'test_gen_123', array(
				'workflow_type' => 'summary',
				'status'        => 'pending',
				'batch_jobs'    => array(
					array( 'batch_id' => 'batch_1', 'status' => 'pending' ),
					array( 'batch_id' => 'batch_2', 'status' => 'pending' ),
				),
			) );

			$this->seedBatchTransient( 'batch_1', array(
				'posts' => array( array( 'post_id' => 1 ), array( 'post_id' => 2 ) ),
			) );

			$this->seedBatchTransient( 'batch_2', array(
				'posts' => array( array( 'id' => 3 ), array( 'id' => 4 ) ),
			) );

			$_POST['nonce']   = 'test_nonce';
			$_POST['task_id'] = 'test_gen_123';

			$this->mock_polling_queue->expects( $this->once() )
				->method( 'add_to_queue' )
				->with( 'test_gen_123', 'summary', array( 1, 2, 3, 4 ), 1 );

			$this->expectJson( true, array(
				'message' => 'Generation test_gen_123 has been queued for immediate processing.',
			) );
			$this->invokeController( fn() => $this->controller->run_task() );

			$actions = array_filter(
				$GLOBALS['nuclen_test_actions'],
				static fn( $a ) => $a['hook'] === 'nuclen_process_batch'
			);
			$this->assertCount( 2, $actions, 'nuclen_process_batch dispatched per batch' );
		}

		public function testCancelTaskWithoutPermissions() {
			$GLOBALS['nuclen_test_cap'] = false;
			$_POST['nonce']             = 'test_nonce';
			$_POST['task_id']           = 'test_task';

			$this->expectJson( false, array( 'message' => 'Insufficient permissions' ) );
			$this->invokeController( fn() => $this->controller->cancel_task() );
		}

		public function testCancelBatchTask() {
			$this->seedBatchTransient( 'nuclen_batch_123', array( 'status' => 'processing' ) );

			$_POST['nonce']   = 'test_nonce';
			$_POST['task_id'] = 'nuclen_batch_123';

			$this->expectJson( true, array(
				'message' => 'Task nuclen_batch_123 has been cancelled.',
			) );
			$this->invokeController( fn() => $this->controller->cancel_task() );

			$this->assertEquals( 'cancelled', $this->getBatchTransientRaw( 'nuclen_batch_123' )['status'] );
		}

		public function testCancelGenerationTask() {
			$this->seedTaskTransient( 'test_gen_123', array(
				'status'     => 'processing',
				'batch_jobs' => array(
					array( 'batch_id' => 'batch_1' ),
					array( 'batch_id' => 'batch_2' ),
				),
			) );

			$this->seedBatchTransient( 'batch_1', array( 'status' => 'processing' ) );
			$this->seedBatchTransient( 'batch_2', array( 'status' => 'pending' ) );

			$_POST['nonce']   = 'test_nonce';
			$_POST['task_id'] = 'test_gen_123';

			$this->mock_polling_queue->expects( $this->once() )
				->method( 'mark_generation_complete' )
				->with( 'test_gen_123' );

			$this->expectJson( true, array(
				'message' => 'Task test_gen_123 has been cancelled.',
			) );
			$this->invokeController( fn() => $this->controller->cancel_task() );

			$this->assertEquals( 'cancelled', $this->getTaskTransientRaw( 'test_gen_123' )['status'] );
			$this->assertEquals( 'cancelled', $this->getBatchTransientRaw( 'batch_1' )['status'] );
			$this->assertEquals( 'cancelled', $this->getBatchTransientRaw( 'batch_2' )['status'] );
		}

		public function testGetTaskStatusBatch() {
			$this->seedBatchTransient( 'nuclen_batch_123', array( 'status' => 'processing' ) );

			$_POST['nonce']   = 'test_nonce';
			$_POST['task_id'] = 'nuclen_batch_123';

			$this->expectJson( true, array(
				'status' => 'processing',
				'type'   => 'batch',
			) );
			$this->invokeController( fn() => $this->controller->get_task_status() );
		}

		public function testGetTaskStatusGenerationWithProgress() {
			$this->seedTaskTransient( 'test_gen_123', array(
				'status'      => 'processing',
				'total_posts' => 10,
				'batch_jobs'  => array(
					array( 'batch_id' => 'batch_1', 'post_count' => 5 ),
					array( 'batch_id' => 'batch_2', 'post_count' => 5 ),
				),
			) );

			$this->seedBatchTransient( 'batch_1', array( 'status' => 'completed' ) );
			$this->seedBatchTransient( 'batch_2', array( 'status' => 'failed' ) );

			$_POST['nonce']   = 'test_nonce';
			$_POST['task_id'] = 'test_gen_123';

			$this->expectJson( true, array(
				'status'    => 'processing',
				'type'      => 'generation',
				'progress'  => 50,
				'processed' => 5,
				'failed'    => 5,
				'total'     => 10,
			) );
			$this->invokeController( fn() => $this->controller->get_task_status() );
		}

		public function testGetTaskStatusNotFound() {
			$_POST['nonce']   = 'test_nonce';
			$_POST['task_id'] = 'non_existent';

			// get_task_current_status throws a plain \Exception which the
			// controller maps to the generic user-facing error (intentional:
			// internal messages aren't leaked through this endpoint).
			$this->expectJson( false, array(
				'message' => 'An error occurred while retrieving task status. Please try again.',
			) );
			$this->invokeController( fn() => $this->controller->get_task_status() );
		}

		public function testGetRecentCompletions() {
			$recent_data = array(
				array( 'task_id' => 'task_1', 'completed_at' => time() ),
				array( 'task_id' => 'task_2', 'completed_at' => time() - 60 ),
			);

			$GLOBALS['wp_transients']['nuclen_recent_completions'] = $recent_data;

			$_POST['nonce'] = 'test_nonce';

			$this->expectJson( true, $recent_data );
			$this->invokeController( fn() => $this->controller->get_recent_completions() );
		}

		public function testGetRecentCompletionsEmpty() {
			$_POST['nonce'] = 'test_nonce';

			$this->expectJson( true, array() );
			$this->invokeController( fn() => $this->controller->get_recent_completions() );
		}

		public function test_cancel_requires_valid_nonce() {
			$GLOBALS['nuclen_test_nonce_valid'] = false;

			$_POST['nonce']         = 'bad';
			$_POST['generation_id'] = 'gen_abc';

			$this->expectJson( false, array( 'message' => 'Security check failed' ) );
			$this->invokeController( fn() => $this->controller->handle_cancel() );
		}

		public function test_cancel_requires_capability() {
			$GLOBALS['nuclen_test_cap'] = false;

			$_POST['nonce']         = 'test_nonce';
			$_POST['generation_id'] = 'gen_abc';

			$this->expectJson( false, array( 'message' => 'Insufficient permissions' ) );
			$this->invokeController( fn() => $this->controller->handle_cancel() );
		}

		public function test_cancel_clears_crons_and_calls_server() {
			$this->seedTaskTransient( 'gen_parent', array(
				'status'     => 'processing',
				'batch_jobs' => array(
					array( 'batch_id' => 'gen_parent_batch_1', 'status' => 'processing' ),
					array( 'batch_id' => 'gen_parent_batch_2', 'status' => 'pending' ),
				),
			) );
			$this->seedBatchTransient( 'gen_parent_batch_1', array( 'status' => 'processing' ) );
			$this->seedBatchTransient( 'gen_parent_batch_2', array( 'status' => 'pending' ) );

			$remote_api = new class {
				public $called_with = null;
				public function cancel_generation( string $generation_id ) : array {
					$this->called_with = $generation_id;
					return array(
						'success'          => true,
						'status'           => 'cancelled',
						'refunded_credits' => 7,
					);
				}
			};

			$container     = $this->createMock( ServiceContainer::class );
			$polling_queue = $this->createMock( CentralizedPollingQueue::class );
			$container->method( 'has' )->willReturnCallback( function ( $service ) {
				return in_array( $service, array( 'remote_api', 'centralized_polling_queue' ), true );
			} );
			$container->method( 'get' )->willReturnCallback(
				function ( $service ) use ( $remote_api, $polling_queue ) {
					if ( 'remote_api' === $service ) {
						return $remote_api;
					}
					if ( 'centralized_polling_queue' === $service ) {
						return $polling_queue;
					}
					return null;
				}
			);

			$controller = new TasksController( $container );

			$_POST['nonce']         = 'test_nonce';
			$_POST['generation_id'] = 'gen_parent';

			$this->invokeController( fn() => $controller->handle_cancel() );

			$this->assertSame( 'gen_parent', $remote_api->called_with, 'remote cancel called with parent id' );

			$clears        = $GLOBALS['nuclen_test_clear_calls'];
			$poll_calls    = array_values( array_filter( $clears, static fn( $c ) => $c['hook'] === 'nuclen_poll_batch' ) );
			$process_calls = array_values( array_filter( $clears, static fn( $c ) => $c['hook'] === 'nuclen_process_batch' ) );
			$this->assertCount( 2, $poll_calls, 'poll cron cleared for each batch' );
			$this->assertCount( 2, $process_calls, 'process cron cleared for each batch' );
		}

		public function test_cancel_updates_status_and_returns_refund() {
			$this->seedTaskTransient( 'gen_x', array(
				'status'     => 'processing',
				'batch_jobs' => array(
					array( 'batch_id' => 'gen_x_batch_1', 'status' => 'processing' ),
				),
			) );
			$this->seedBatchTransient( 'gen_x_batch_1', array( 'status' => 'processing' ) );

			$remote_api = new class {
				public function cancel_generation( string $generation_id ) : array {
					return array(
						'success'          => true,
						'status'           => 'cancelled',
						'refunded_credits' => 42,
					);
				}
			};

			$container     = $this->createMock( ServiceContainer::class );
			$polling_queue = $this->createMock( CentralizedPollingQueue::class );
			$container->method( 'has' )->willReturnCallback( function ( $service ) {
				return in_array( $service, array( 'remote_api', 'centralized_polling_queue' ), true );
			} );
			$container->method( 'get' )->willReturnCallback(
				function ( $service ) use ( $remote_api, $polling_queue ) {
					return 'remote_api' === $service ? $remote_api : $polling_queue;
				}
			);

			$controller = new TasksController( $container );

			$_POST['nonce']         = 'test_nonce';
			$_POST['generation_id'] = 'gen_x';

			$this->invokeController( fn() => $controller->handle_cancel() );

			$this->assertSame( 'cancelled', $this->getTaskTransientRaw( 'gen_x' )['status'] );
			$this->assertSame( 42, $this->getTaskTransientRaw( 'gen_x' )['refunded_credits'] );
			$this->assertSame( 'cancelled', $this->getBatchTransientRaw( 'gen_x_batch_1' )['status'] );
		}
	}
}
