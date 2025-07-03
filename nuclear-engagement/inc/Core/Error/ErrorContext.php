<?php
declare(strict_types=1);

namespace NuclearEngagement\Core\Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simplified error context that composes smaller focused classes.
 * 
 * @package NuclearEngagement\Core\Error
 */
final class ErrorContext {
	private ErrorData $error_data;
	private ErrorRecovery $recovery_manager;
	private ErrorNotificationManager $notification_manager;
	private int $impact_score = 0;
	private array $related_errors = [];

	public function __construct(ErrorData $error_data) {
		$this->error_data = $error_data;
		$this->recovery_manager = new ErrorRecovery();
		$this->notification_manager = new ErrorNotificationManager();
	}

	public function getErrorData(): ErrorData {
		return $this->error_data;
	}

	public function getRecoveryManager(): ErrorRecovery {
		return $this->recovery_manager;
	}

	public function getNotificationManager(): ErrorNotificationManager {
		return $this->notification_manager;
	}

	public function setImpactScore(int $score): void {
		$this->impact_score = max(0, min(100, $score));
	}

	public function getImpactScore(): int {
		return $this->impact_score;
	}

	public function addRelatedError(string $error_id): void {
		if (!in_array($error_id, $this->related_errors, true)) {
			$this->related_errors[] = $error_id;
		}
	}

	public function getRelatedErrors(): array {
		return $this->related_errors;
	}

	public function toArray(): array {
		return [
			'error_data' => $this->error_data->toArray(),
			'recovery_attempted' => $this->recovery_manager->wasRecoveryAttempted(),
			'recovery_successful' => $this->recovery_manager->wasRecoverySuccessful(),
			'recovery_details' => $this->recovery_manager->getRecoveryDetails(),
			'suggested_actions' => $this->recovery_manager->getSuggestedActions(),
			'user_notified' => $this->notification_manager->wasUserNotified(),
			'admin_notified' => $this->notification_manager->wasAdminNotified(),
			'notifications_sent' => $this->notification_manager->getNotificationsSent(),
			'impact_score' => $this->impact_score,
			'related_errors' => $this->related_errors,
		];
	}

	public function handleError(): void {
		// Attempt recovery
		$this->recovery_manager->attemptRecovery($this->error_data);
		
		// Send notifications based on severity
		if ($this->error_data->severity === 'critical') {
			$this->notification_manager->notifyAdmin($this->error_data);
			$this->notification_manager->notifyUser($this->error_data);
		} elseif ($this->error_data->severity === 'warning') {
			$this->notification_manager->notifyAdmin($this->error_data);
		}
	}
}