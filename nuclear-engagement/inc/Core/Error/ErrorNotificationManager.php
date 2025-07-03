<?php
declare(strict_types=1);

namespace NuclearEngagement\Core\Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages error notifications to users and administrators.
 * 
 * @package NuclearEngagement\Core\Error
 */
final class ErrorNotificationManager {
	private bool $user_notified = false;
	private bool $admin_notified = false;
	private array $notifications_sent = [];

	public function notifyUser(ErrorData $error_data, ?string $user_message = null): void {
		if ($this->user_notified) {
			return;
		}

		$message = $user_message ?? $this->generateUserMessage($error_data);
		
		// Send user notification via admin notice or other means
		add_action('admin_notices', function() use ($message) {
			echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
		});

		$this->user_notified = true;
		$this->notifications_sent[] = [
			'type' => 'user',
			'message' => $message,
			'timestamp' => time()
		];
	}

	public function notifyAdmin(ErrorData $error_data, ?string $admin_message = null): void {
		if ($this->admin_notified) {
			return;
		}

		$message = $admin_message ?? $this->generateAdminMessage($error_data);
		
		// Log for admin review
		error_log("Nuclear Engagement Error [{$error_data->error_id}]: {$message}");
		
		// Could also send email to admin if configured
		if ($this->shouldEmailAdmin($error_data)) {
			$this->sendAdminEmail($error_data, $message);
		}

		$this->admin_notified = true;
		$this->notifications_sent[] = [
			'type' => 'admin',
			'message' => $message,
			'timestamp' => time()
		];
	}

	public function wasUserNotified(): bool {
		return $this->user_notified;
	}

	public function wasAdminNotified(): bool {
		return $this->admin_notified;
	}

	public function getNotificationsSent(): array {
		return $this->notifications_sent;
	}

	private function generateUserMessage(ErrorData $error_data): string {
		switch ($error_data->severity) {
			case 'critical':
				return __('A critical error occurred. Please contact the administrator.', 'nuclear-engagement');
			case 'warning':
				return __('A warning occurred during processing.', 'nuclear-engagement');
			default:
				return __('An error occurred. Please try again.', 'nuclear-engagement');
		}
	}

	private function generateAdminMessage(ErrorData $error_data): string {
		return sprintf(
			'[%s] %s in category %s. Error ID: %s',
			strtoupper($error_data->severity),
			$error_data->message,
			$error_data->category,
			$error_data->error_id
		);
	}

	private function shouldEmailAdmin(ErrorData $error_data): bool {
		return $error_data->severity === 'critical';
	}

	private function sendAdminEmail(ErrorData $error_data, string $message): void {
		$admin_email = get_option('admin_email');
		if ($admin_email) {
			wp_mail(
				$admin_email,
				'Nuclear Engagement Critical Error',
				$message
			);
		}
	}
}