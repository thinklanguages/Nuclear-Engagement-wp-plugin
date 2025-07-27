<?php
/**
 * AdminNoticeService.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Services
 */

declare(strict_types=1);
/**
	* Handles admin notices for the plugin.
	*/

namespace NuclearEngagement\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminNoticeService {
	/**
	 * Option name for storing persistent notices
	 */
	private const NOTICES_OPTION = 'nuclen_admin_notices';

	/**
	 * Option name for storing permanently dismissed notices
	 */
	private const DISMISSED_NOTICES_OPTION = 'nuclen_dismissed_notices';

	/**
	 * @var array<string>
	 */
	private array $messages = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		\add_action( 'admin_notices', array( $this, 'display_notices' ) );
		\add_action( 'wp_ajax_nuclen_dismiss_notice', array( $this, 'ajax_dismiss_notice' ) );
		
		// Clean up any empty notices on admin init
		\add_action( 'admin_init', array( $this, 'cleanup_empty_notices' ) );
	}

	/**
	 * Add a notice (legacy method for backward compatibility)
	 *
	 * @param string $message
	 */
	public function add( string $message ): void {
		// Skip empty messages
		if ( empty( trim( $message ) ) ) {
			return;
		}
		
		$this->messages[] = $message;
		if ( count( $this->messages ) === 1 ) {
			add_action( 'admin_notices', array( $this, 'render' ) );
		}
	}

	/**
	 * Add a persistent notice that survives page loads
	 *
	 * @param string $message The notice message
	 * @param string $type    Notice type: 'success', 'error', 'warning', 'info'
	 * @param string $id      Unique ID for the notice (for dismissal tracking)
	 * @param int    $user_id User ID to show the notice to (0 for all users)
	 */
	public function add_persistent_notice( string $message, string $type = 'info', string $id = '', int $user_id = 0 ): void {
		// Skip empty messages
		if ( empty( trim( $message ) ) ) {
			return;
		}

		if ( empty( $id ) ) {
			$id = 'nuclen_' . md5( $message . $type . time() );
		}

		$notices = get_option( self::NOTICES_OPTION, array() );

		// Check if a notice with this ID already exists and skip if it does
		if ( isset( $notices[ $id ] ) ) {
			return;
		}

		$notices[ $id ] = array(
			'message'   => $message,
			'type'      => $type,
			'id'        => $id,
			'user_id'   => $user_id,
			'created'   => time(),
			'dismissed' => array(), // Array of user IDs who dismissed this notice
		);

		update_option( self::NOTICES_OPTION, $notices, false );
	}

	/**
	 * Display all persistent notices
	 */
	public function display_notices(): void {
		$notices               = get_option( self::NOTICES_OPTION, array() );
		$permanently_dismissed = get_option( self::DISMISSED_NOTICES_OPTION, array() );
		$current_user_id       = get_current_user_id();

		foreach ( $notices as $notice_id => $notice ) {
			// Skip if notice is permanently dismissed
			if ( in_array( $notice_id, $permanently_dismissed, true ) ) {
				continue;
			}

			// Skip if notice is for specific user and current user doesn't match
			if ( $notice['user_id'] > 0 && $notice['user_id'] !== $current_user_id ) {
				continue;
			}

			// Skip if user has dismissed this notice
			if ( in_array( $current_user_id, $notice['dismissed'], true ) ) {
				continue;
			}

			// Display the notice
			$this->display_notice( $notice );
		}
	}

	/**
	 * Display a single notice
	 *
	 * @param array{message: string, type: string, id?: string} $notice
	 */
	private function display_notice( array $notice ): void {
		// Skip if message is empty
		if ( empty( $notice['message'] ) || empty( trim( $notice['message'] ) ) ) {
			return;
		}

		$class  = 'notice notice-' . esc_attr( $notice['type'] );
		$class .= ' is-dismissible';

		$content = '<strong>[NUCLEAR ENGAGEMENT]</strong> ' . wp_kses_post( $notice['message'] );

		// Add permanent dismissal checkbox for the cron notice
		if ( $notice['id'] === 'nuclen_cron_disabled' ) {
			$content .= '<p><label><input type="checkbox" class="nuclen-dismiss-forever" /> ' .
				esc_html__( 'Don\'t show this message again', 'nuclear-engagement' ) .
				'</label></p>';
		}

		printf(
			'<div class="%s" data-notice-id="%s">%s</div>',
			esc_attr( $class ),
			esc_attr( $notice['id'] ),
			$content
		);

		// Add inline script for dismissal handling
		\add_action( 'admin_footer', array( $this, 'enqueue_dismiss_script' ) );
	}

	/**
	 * Render legacy notices (backward compatibility)
	 */
	public function render(): void {
		foreach ( $this->messages as $msg ) {
			// Skip empty messages to prevent blank notices
			if ( ! empty( trim( $msg ) ) ) {
				\load_template(
					dirname( __DIR__, 2 ) . '/templates/admin/notice.php',
					false,  // Use require instead of require_once
					array( 'msg' => $msg )
				);
			}
		}
		$this->messages = array();
	}

	/**
	 * Enqueue JavaScript for notice dismissal
	 */
	public function enqueue_dismiss_script(): void {
		static $enqueued = false;
		if ( $enqueued ) {
			return;
		}
		$enqueued = true;
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('.notice.is-dismissible[data-notice-id]').on('click', '.notice-dismiss', function() {
				var $notice = $(this).parent();
				var noticeId = $notice.data('notice-id');
				var dismissForever = $notice.find('.nuclen-dismiss-forever').is(':checked');
				
				$.post(ajaxurl, {
					action: 'nuclen_dismiss_notice',
					notice_id: noticeId,
					dismiss_forever: dismissForever ? 1 : 0,
					_ajax_nonce: '<?php echo esc_js( wp_create_nonce( 'nuclen_dismiss_notice' ) ); ?>'
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Handle AJAX notice dismissal
	 */
	public function ajax_dismiss_notice(): void {
		check_ajax_referer( 'nuclen_dismiss_notice' );

		$notice_id = sanitize_text_field( $_POST['notice_id'] ?? '' );
		if ( empty( $notice_id ) ) {
			wp_die();
		}

		$dismiss_forever = isset( $_POST['dismiss_forever'] ) && $_POST['dismiss_forever'] === '1';

		// Handle permanent dismissal
		if ( $dismiss_forever && $notice_id === 'nuclen_cron_disabled' ) {
			$permanently_dismissed = get_option( self::DISMISSED_NOTICES_OPTION, array() );
			if ( ! in_array( $notice_id, $permanently_dismissed, true ) ) {
				$permanently_dismissed[] = $notice_id;
				update_option( self::DISMISSED_NOTICES_OPTION, $permanently_dismissed, false );
			}
		}

		$notices         = get_option( self::NOTICES_OPTION, array() );
		$current_user_id = get_current_user_id();

		if ( isset( $notices[ $notice_id ] ) ) {
			$notices[ $notice_id ]['dismissed'][] = $current_user_id;

			// Clean up if all users have dismissed or it's been dismissed by an admin
			if ( current_user_can( 'manage_options' ) && $notices[ $notice_id ]['user_id'] === 0 ) {
				unset( $notices[ $notice_id ] );
			}

			update_option( self::NOTICES_OPTION, $notices, false );
		}

		wp_die();
	}

	/**
	 * Add generation completion notice
	 *
	 * @param string $generation_id
	 * @param int    $total_posts
	 * @param int    $success_count
	 * @param int    $fail_count
	 * @param string $workflow_type
	 */
	public function add_generation_complete_notice( string $generation_id, int $total_posts, int $success_count, int $fail_count, string $workflow_type ): void {
		$dashboard_url = admin_url( 'admin.php?page=nuclear-engagement' );

		if ( $fail_count === 0 ) {
			// All successful
			$message = sprintf(
				__( 'Generation completed successfully! Generated %1$s for %2$d posts. <a href="%3$s">View generation tasks</a>', 'nuclear-engagement' ),
				$workflow_type === 'quiz' ? __( 'quizzes', 'nuclear-engagement' ) : __( 'summaries', 'nuclear-engagement' ),
				$total_posts,
				esc_url( $dashboard_url )
			);
			$type    = 'success';
		} else {
			// Some failures
			$message = sprintf(
				__( 'Generation completed with errors. Successfully generated %1$d, failed %2$d. <a href="%3$s">View generation tasks</a>', 'nuclear-engagement' ),
				$success_count,
				$fail_count,
				esc_url( $dashboard_url )
			);
			$type    = 'error';
		}

		// Disabled for now - will be re-enabled later
		// $this->add_persistent_notice( $message, $type, 'gen_complete_' . $generation_id );
	}

	/**
	 * Add generation failure notice
	 *
	 * @param string $generation_id
	 * @param string $error_message
	 */
	public function add_generation_failure_notice( string $generation_id, string $error_message ): void {
		$dashboard_url = admin_url( 'admin.php?page=nuclear-engagement' );

		$message = sprintf(
			__( 'Generation failed: %1$s. <a href="%2$s">View generation tasks</a>', 'nuclear-engagement' ),
			esc_html( $error_message ),
			esc_url( $dashboard_url )
		);

		// Disabled for now - will be re-enabled later
		// $this->add_persistent_notice( $message, 'error', 'gen_fail_' . $generation_id );
	}

	/**
	 * Check if cron is enabled and add notice if disabled
	 *
	 * @return bool True if cron is enabled, false otherwise
	 */
	public function check_cron_and_notify(): bool {
		// Check if WP-Cron is disabled
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON === true ) {
			$notice_id = 'nuclen_cron_disabled';

			// Check if this notice has been permanently dismissed
			$permanently_dismissed = get_option( self::DISMISSED_NOTICES_OPTION, array() );
			if ( in_array( $notice_id, $permanently_dismissed, true ) ) {
				return false;
			}

			// Check if this notice already exists
			$notices = get_option( self::NOTICES_OPTION, array() );

			// Only add if it doesn't already exist
			if ( ! isset( $notices[ $notice_id ] ) ) {
				$message  = '<p>' . __( 'Nuclear Engagement requires cron jobs to process large amounts of posts automatically.', 'nuclear-engagement' ) . '</p>';
				$message .= '<p>' . __( 'Without cron jobs enabled, large content generation tasks will remain incomplete and you will need to run them manually (only takes 1 click).', 'nuclear-engagement' ) . '</p>';
				$message .= '<p>' . __( 'WP Cron seems to be disabled on your site. However, if you have real cron jobs running, you can safely ignore this message. Set your real cron jobs frequency to "every minute" for best performance.', 'nuclear-engagement' ) . '</p>';
				$this->add_persistent_notice( $message, 'warning', $notice_id );
			}

			return false;
		}

		return true;
	}

	/**
	 * Clean up any empty notices from the database
	 */
	public function cleanup_empty_notices(): void {
		$notices = get_option( self::NOTICES_OPTION, array() );
		$cleaned = false;

		foreach ( $notices as $id => $notice ) {
			// Remove empty notices
			if ( empty( $notice['message'] ) || empty( trim( $notice['message'] ) ) ) {
				unset( $notices[ $id ] );
				$cleaned = true;
				continue;
			}
			
			// Remove 0-post generation notices
			if ( strpos( $notice['message'], 'Generated quizzes for 0 posts' ) !== false ||
			     strpos( $notice['message'], 'Generated summaries for 0 posts' ) !== false ) {
				unset( $notices[ $id ] );
				$cleaned = true;
			}
		}

		if ( $cleaned ) {
			update_option( self::NOTICES_OPTION, $notices, false );
		}
	}
}
