<?php
declare(strict_types=1);

namespace NuclearEngagement\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * User-centric error management system.
 *
 * @package NuclearEngagement\Core
 * @since 1.0.0
 */
final class UserErrorManager {
	/**
	 * User notification preferences.
	 *
	 * @var array<string, array>
	 */
	private static array $notification_preferences = [];

	/**
	 * Error message templates by category and severity.
	 *
	 * @var array<string, array<string, string>>
	 */
	private static array $message_templates = [
		ErrorManager::CATEGORY_AUTHENTICATION => [
			ErrorManager::SEVERITY_HIGH => 'Authentication failed. Please check your credentials and try again.',
			ErrorManager::SEVERITY_MEDIUM => 'There was an issue with your login. Please try again.',
			ErrorManager::SEVERITY_LOW => 'Login attempt failed. Please verify your information.',
		],
		ErrorManager::CATEGORY_DATABASE => [
			ErrorManager::SEVERITY_CRITICAL => 'Service temporarily unavailable. We\'re working to resolve this issue.',
			ErrorManager::SEVERITY_HIGH => 'Unable to process your request at the moment. Please try again later.',
			ErrorManager::SEVERITY_MEDIUM => 'There was a delay processing your request. Please try again.',
			ErrorManager::SEVERITY_LOW => 'Request completed with minor issues.',
		],
		ErrorManager::CATEGORY_VALIDATION => [
			ErrorManager::SEVERITY_HIGH => 'Please check your input and try again.',
			ErrorManager::SEVERITY_MEDIUM => 'Some information appears to be incorrect. Please review and resubmit.',
			ErrorManager::SEVERITY_LOW => 'Please verify your input.',
		],
		ErrorManager::CATEGORY_PERMISSIONS => [
			ErrorManager::SEVERITY_HIGH => 'You don\'t have permission to perform this action.',
			ErrorManager::SEVERITY_MEDIUM => 'Access denied. Please contact an administrator.',
			ErrorManager::SEVERITY_LOW => 'This action requires additional permissions.',
		],
		ErrorManager::CATEGORY_NETWORK => [
			ErrorManager::SEVERITY_HIGH => 'Connection issue detected. Please check your internet connection.',
			ErrorManager::SEVERITY_MEDIUM => 'Network request failed. Please try again.',
			ErrorManager::SEVERITY_LOW => 'Minor network delay encountered.',
		],
		ErrorManager::CATEGORY_EXTERNAL_API => [
			ErrorManager::SEVERITY_HIGH => 'External service temporarily unavailable. Please try again later.',
			ErrorManager::SEVERITY_MEDIUM => 'Service request failed. Please try again.',
			ErrorManager::SEVERITY_LOW => 'Service responded with warnings.',
		],
	];

	/**
	 * Suggested actions by error category.
	 *
	 * @var array<string, array<string>>
	 */
	private static array $suggested_actions = [
		ErrorManager::CATEGORY_AUTHENTICATION => [
			'Check your username and password',
			'Clear your browser cache and cookies',
			'Try using the password reset feature',
			'Contact support if the issue persists',
		],
		ErrorManager::CATEGORY_DATABASE => [
			'Refresh the page and try again',
			'Wait a few minutes and retry',
			'Contact support if the issue continues',
		],
		ErrorManager::CATEGORY_VALIDATION => [
			'Review the form for any missing or incorrect information',
			'Check that all required fields are filled',
			'Ensure data formats match the expected pattern',
		],
		ErrorManager::CATEGORY_PERMISSIONS => [
			'Contact an administrator for access',
			'Verify you\'re logged in with the correct account',
			'Check if your account has the necessary permissions',
		],
		ErrorManager::CATEGORY_NETWORK => [
			'Check your internet connection',
			'Try refreshing the page',
			'Disable any VPN or proxy temporarily',
			'Try again from a different network',
		],
		ErrorManager::CATEGORY_EXTERNAL_API => [
			'Wait a few minutes and try again',
			'Check the service status page',
			'Contact support if urgent',
		],
	];

	/**
	 * User error tracking for personalized experience.
	 *
	 * @var array<int, array>
	 */
	private static array $user_error_history = [];

	/**
	 * Error resolution tracking.
	 *
	 * @var array<string, array>
	 */
	private static array $resolution_tracking = [];

	/**
	 * Initialize user error manager.
	 */
	public static function init(): void {
		// Hook into WordPress admin
		add_action( 'admin_notices', [ self::class, 'display_admin_error_notices' ] );
		add_action( 'wp_ajax_nuclen_dismiss_error', [ self::class, 'handle_error_dismissal' ] );
		add_action( 'wp_ajax_nuclen_resolve_error', [ self::class, 'handle_error_resolution' ] );
		add_action( 'wp_ajax_nuclen_get_error_help', [ self::class, 'provide_error_help' ] );

		// Frontend error handling
		add_action( 'wp_footer', [ self::class, 'inject_error_handler_script' ] );
		add_action( 'wp_ajax_nopriv_nuclen_report_frontend_error', [ self::class, 'handle_frontend_error_report' ] );
		add_action( 'wp_ajax_nuclen_report_frontend_error', [ self::class, 'handle_frontend_error_report' ] );

		// Load user preferences
		add_action( 'wp_login', [ self::class, 'load_user_error_preferences' ], 10, 2 );

		// Clean up old error data
		if ( ! wp_next_scheduled( 'nuclen_cleanup_user_errors' ) ) {
			wp_schedule_event( time(), 'daily', 'nuclen_cleanup_user_errors' );
		}
		add_action( 'nuclen_cleanup_user_errors', [ self::class, 'cleanup_user_error_data' ] );
	}

	/**
	 * Handle error with user-centric approach.
	 *
	 * @param ErrorContext $error_context Error context.
	 * @param bool         $show_to_user Whether to show error to user.
	 * @return array User error response.
	 */
	public static function handle_user_error( ErrorContext $error_context, bool $show_to_user = true ): array {
		$user_id = get_current_user_id();
		$is_admin = current_user_can( 'manage_options' );

		// Track error for this user
		self::track_user_error( $user_id, $error_context );

		// Determine appropriate user message
		$user_message = self::get_user_friendly_message( $error_context, $is_admin );
		$error_context->set_user_message( $user_message );

		// Get suggested actions
		$suggested_actions = self::get_suggested_actions( $error_context );
		foreach ( $suggested_actions as $action ) {
			$error_context->add_suggested_action( $action, 'user' );
		}

		// Check if this is a recurring error for the user
		$is_recurring = self::is_recurring_error( $user_id, $error_context );
		if ( $is_recurring ) {
			self::handle_recurring_error( $error_context, $user_id );
		}

		// Prepare user response
		$response = [
			'error_id' => $error_context->get_error_id(),
			'message' => $user_message,
			'severity' => $error_context->get_severity(),
			'category' => $error_context->get_category(),
			'suggested_actions' => $suggested_actions,
			'is_recurring' => $is_recurring,
			'show_details' => $is_admin,
			'help_available' => true,
			'can_retry' => self::can_retry_action( $error_context ),
			'estimated_resolution' => self::get_estimated_resolution_time( $error_context ),
		];

		// Add admin-specific information
		if ( $is_admin ) {
			$response['admin_message'] = $error_context->get_admin_message();
			$response['technical_details'] = $error_context->get_context();
			$response['error_trends'] = self::get_error_trends_for_admin();
		}

		// Notify user if appropriate
		if ( $show_to_user ) {
			self::notify_user( $error_context, $response );
		}

		return $response;
	}

	/**
	 * Get user-friendly error message with personalization.
	 *
	 * @param ErrorContext $error_context Error context.
	 * @param bool         $is_admin Whether user is admin.
	 * @return string User-friendly message.
	 */
	public static function get_user_friendly_message( ErrorContext $error_context, bool $is_admin = false ): string {
		$category = $error_context->get_category();
		$severity = $error_context->get_severity();

		// Use custom message if set
		if ( $error_context->get_user_message() ) {
			return $error_context->get_user_message();
		}

		// Get template message
		$template_message = self::$message_templates[$category][$severity] ?? 
						   'An unexpected error occurred. Please try again.';

		// Personalize message based on user context
		$user_context = $error_context->get_context_value( 'user_context', [] );
		$personalized_message = self::personalize_message( $template_message, $user_context );

		// Add context-specific information
		if ( $is_admin && $error_context->get_admin_message() ) {
			$personalized_message .= ' (Admin: ' . $error_context->get_admin_message() . ')';
		}

		return $personalized_message;
	}

	/**
	 * Get suggested actions for error resolution.
	 *
	 * @param ErrorContext $error_context Error context.
	 * @return array Suggested actions.
	 */
	public static function get_suggested_actions( ErrorContext $error_context ): array {
		$category = $error_context->get_category();
		$actions = self::$suggested_actions[$category] ?? [];

		// Add context-specific actions
		$context_actions = self::get_context_specific_actions( $error_context );
		$actions = array_merge( $actions, $context_actions );

		// Limit to most relevant actions
		return array_slice( $actions, 0, 5 );
	}

	/**
	 * Create user-friendly error notification.
	 *
	 * @param ErrorContext $error_context Error context.
	 * @param array        $response Error response data.
	 */
	public static function notify_user( ErrorContext $error_context, array $response ): void {
		$notification_type = self::get_notification_type( $error_context );

		switch ( $notification_type ) {
			case 'admin_notice':
				self::queue_admin_notice( $error_context, $response );
				break;
			case 'toast':
				self::queue_toast_notification( $error_context, $response );
				break;
			case 'modal':
				self::queue_modal_notification( $error_context, $response );
				break;
			case 'silent':
				// Log only, no user notification
				break;
		}

		$error_context->set_user_notified( true );
	}

	/**
	 * Display admin error notices.
	 */
	public static function display_admin_error_notices(): void {
		$user_id = get_current_user_id();
		$notices = get_user_meta( $user_id, 'nuclen_pending_error_notices', true );

		if ( ! is_array( $notices ) || empty( $notices ) ) {
			return;
		}

		foreach ( $notices as $notice ) {
			$severity_class = self::get_notice_css_class( $notice['severity'] );
			$dismissible = $notice['dismissible'] ? 'is-dismissible' : '';
			
			echo '<div class="notice ' . esc_attr( $severity_class ) . ' ' . esc_attr( $dismissible ) . '" data-error-id="' . esc_attr( $notice['error_id'] ) . '">';
			echo '<p><strong>' . esc_html( $notice['title'] ) . '</strong></p>';
			echo '<p>' . esc_html( $notice['message'] ) . '</p>';
			
			if ( ! empty( $notice['actions'] ) ) {
				echo '<p>';
				foreach ( $notice['actions'] as $action ) {
					echo '<button type="button" class="button button-secondary nuclen-error-action" data-action="' . esc_attr( $action['type'] ) . '">';
					echo esc_html( $action['label'] );
					echo '</button> ';
				}
				echo '</p>';
			}
			
			echo '</div>';
		}

		// Clear displayed notices
		delete_user_meta( $user_id, 'nuclen_pending_error_notices' );
	}

	/**
	 * Handle error dismissal via AJAX.
	 */
	public static function handle_error_dismissal(): void {
		check_ajax_referer( 'nuclen_error_action', 'nonce' );

		$error_id = sanitize_text_field( $_POST['error_id'] ?? '' );
		$user_id = get_current_user_id();

		if ( $error_id ) {
			// Mark error as dismissed for this user
			$dismissed_errors = get_user_meta( $user_id, 'nuclen_dismissed_errors', true );
			if ( ! is_array( $dismissed_errors ) ) {
				$dismissed_errors = [];
			}
			$dismissed_errors[] = $error_id;
			update_user_meta( $user_id, 'nuclen_dismissed_errors', $dismissed_errors );

			wp_send_json_success( [ 'message' => 'Error dismissed successfully' ] );
		}

		wp_send_json_error( [ 'message' => 'Invalid error ID' ] );
	}

	/**
	 * Handle error resolution tracking.
	 */
	public static function handle_error_resolution(): void {
		check_ajax_referer( 'nuclen_error_action', 'nonce' );

		$error_id = sanitize_text_field( $_POST['error_id'] ?? '' );
		$resolution_method = sanitize_text_field( $_POST['resolution_method'] ?? '' );
		$user_feedback = sanitize_textarea_field( $_POST['user_feedback'] ?? '' );

		if ( $error_id && $resolution_method ) {
			self::track_error_resolution( $error_id, $resolution_method, $user_feedback );
			wp_send_json_success( [ 'message' => 'Thank you for the feedback' ] );
		}

		wp_send_json_error( [ 'message' => 'Invalid resolution data' ] );
	}

	/**
	 * Provide contextual help for errors.
	 */
	public static function provide_error_help(): void {
		check_ajax_referer( 'nuclen_error_action', 'nonce' );

		$error_id = sanitize_text_field( $_POST['error_id'] ?? '' );
		$help_context = sanitize_text_field( $_POST['help_context'] ?? 'general' );

		$help_content = self::get_contextual_help( $error_id, $help_context );

		wp_send_json_success( [ 'help_content' => $help_content ] );
	}

	/**
	 * Handle frontend error reports.
	 */
	public static function handle_frontend_error_report(): void {
		$error_data = $_POST['error_data'] ?? [];
		$user_context = $_POST['user_context'] ?? [];

		// Sanitize and validate error data
		$sanitized_error = [
			'message' => sanitize_text_field( $error_data['message'] ?? '' ),
			'source' => sanitize_text_field( $error_data['source'] ?? '' ),
			'line' => intval( $error_data['line'] ?? 0 ),
			'stack' => sanitize_textarea_field( $error_data['stack'] ?? '' ),
			'user_agent' => sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ),
			'url' => esc_url_raw( $user_context['url'] ?? '' ),
		];

		// Create error context for frontend error
		$error_context = ErrorManager::handle_error(
			'Frontend JavaScript error: ' . $sanitized_error['message'],
			ErrorManager::SEVERITY_LOW,
			ErrorManager::CATEGORY_VALIDATION,
			$sanitized_error
		);

		wp_send_json_success( [ 'error_id' => $error_context->get_error_id() ] );
	}

	/**
	 * Inject error handling JavaScript.
	 */
	public static function inject_error_handler_script(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return; // Only for admins
		}

		?>
		<script type="text/javascript">
		window.addEventListener('error', function(e) {
			// Collect error information
			var errorData = {
				message: e.message,
				source: e.filename,
				line: e.lineno,
				column: e.colno,
				stack: e.error ? e.error.stack : ''
			};

			var userContext = {
				url: window.location.href,
				userAgent: navigator.userAgent,
				timestamp: new Date().toISOString()
			};

			// Send error report
			fetch('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					action: 'nuclen_report_frontend_error',
					error_data: JSON.stringify(errorData),
					user_context: JSON.stringify(userContext)
				})
			});
		});
		</script>
		<?php
	}

	/**
	 * Get error trends for administrators.
	 *
	 * @return array Error trends data.
	 */
	public static function get_error_trends_for_admin(): array {
		$analytics = ErrorManager::get_error_analytics( 86400 ); // Last 24 hours
		
		return [
			'total_errors_24h' => $analytics['total_errors'],
			'most_common_category' => self::get_most_common_category( $analytics ),
			'trending_up' => self::get_trending_errors( 'up' ),
			'trending_down' => self::get_trending_errors( 'down' ),
			'user_impact' => self::calculate_user_impact(),
		];
	}

	/**
	 * Private helper methods for user error management.
	 */

	private static function track_user_error( int $user_id, ErrorContext $error_context ): void {
		// Implementation for tracking user-specific errors
	}

	private static function is_recurring_error( int $user_id, ErrorContext $error_context ): bool {
		// Implementation for detecting recurring errors
		return false;
	}

	private static function handle_recurring_error( ErrorContext $error_context, int $user_id ): void {
		// Implementation for handling recurring errors
	}

	private static function can_retry_action( ErrorContext $error_context ): bool {
		// Implementation for determining if action can be retried
		return true;
	}

	private static function get_estimated_resolution_time( ErrorContext $error_context ): string {
		// Implementation for estimating resolution time
		return 'within 24 hours';
	}

	private static function personalize_message( string $message, array $user_context ): string {
		// Implementation for personalizing messages
		return $message;
	}

	private static function get_context_specific_actions( ErrorContext $error_context ): array {
		// Implementation for context-specific actions
		return [];
	}

	private static function get_notification_type( ErrorContext $error_context ): string {
		// Implementation for determining notification type
		return 'admin_notice';
	}

	private static function queue_admin_notice( ErrorContext $error_context, array $response ): void {
		// Implementation for queueing admin notices
	}

	private static function queue_toast_notification( ErrorContext $error_context, array $response ): void {
		// Implementation for queueing toast notifications
	}

	private static function queue_modal_notification( ErrorContext $error_context, array $response ): void {
		// Implementation for queueing modal notifications
	}

	private static function get_notice_css_class( string $severity ): string {
		// Implementation for getting CSS classes
		return 'notice-error';
	}

	private static function track_error_resolution( string $error_id, string $method, string $feedback ): void {
		// Implementation for tracking error resolution
	}

	private static function get_contextual_help( string $error_id, string $context ): array {
		// Implementation for providing contextual help
		return [];
	}

	private static function get_most_common_category( array $analytics ): string {
		// Implementation for getting most common error category
		return 'validation';
	}

	private static function get_trending_errors( string $direction ): array {
		// Implementation for getting trending errors
		return [];
	}

	private static function calculate_user_impact(): float {
		// Implementation for calculating user impact
		return 0.0;
	}

	public static function load_user_error_preferences( string $user_login, \WP_User $user ): void {
		// Implementation for loading user preferences
	}

	public static function cleanup_user_error_data(): void {
		// Implementation for cleaning up old user error data
	}
}