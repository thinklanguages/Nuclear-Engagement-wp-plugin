import { nuclenFetchUpdates } from './api';
import type { PollingUpdateData, PollingUpdateResponse } from './api';
import { API_CONFIG } from '../../../shared/constants';
import * as logger from '../utils/logger';

export function NuclenPollAndPullUpdates({
	intervalMs = API_CONFIG.POLLING_INTERVAL_MS,
	generationId,
	onProgress = (() => {}) as (processed: number, total: number, data?: any) => void,
	onComplete = () => {},
	onError = () => {},
	maxAttempts = API_CONFIG.MAX_POLLING_ATTEMPTS,
	timeoutMs = API_CONFIG.MAX_POLLING_TIMEOUT_MS,
	useExponentialBackoff = false, // Disabled in favor of progressive intervals
	maxIntervalMs = 60000, // Maximum interval between polls (60 seconds)
	useProgressiveIntervals = true, // Use progressive polling intervals
}: {
	intervalMs?: number;
	generationId: string;
	onProgress?: (processed: number, total: number, data?: any) => void;
	onComplete?: (finalData: PollingUpdateData) => void;
	onError?: (errMsg: string) => void;
	maxAttempts?: number;
	timeoutMs?: number;
	useExponentialBackoff?: boolean;
	maxIntervalMs?: number;
	useProgressiveIntervals?: boolean;
}) {
	// Validate generation ID
	if (!generationId || generationId.trim() === '') {
		logger.error('[ERROR] Polling failed | Missing generation ID');
		onError('No generation ID provided for polling');
		return () => {}; // Return empty cleanup function
	}
	
	let attemptCount = 0;
	const startTime = Date.now();
	let currentInterval = intervalMs;
	let consecutiveErrors = 0;
	let timeoutId: ReturnType<typeof setTimeout> | null = null;
	
	// Calculate current interval based on elapsed time for progressive intervals
	const getProgressiveInterval = (elapsed: number): number => {
		if (!useProgressiveIntervals) return intervalMs;
		
		let accumulatedDuration = 0;
		for (const stage of API_CONFIG.POLLING_INTERVALS) {
			if (elapsed < accumulatedDuration + stage.duration) {
				return stage.interval;
			}
			accumulatedDuration += stage.duration;
		}
		
		// Return the last interval for anything beyond defined stages
		return API_CONFIG.POLLING_INTERVALS[API_CONFIG.POLLING_INTERVALS.length - 1].interval;
	};
	
	const schedulePoll = () => {
		if (timeoutId) {
			clearTimeout(timeoutId);
		}
		
		const elapsed = Date.now() - startTime;
		
		// Use progressive intervals if enabled
		if (useProgressiveIntervals) {
			currentInterval = getProgressiveInterval(elapsed);
		}
		// Apply exponential backoff if enabled and there are errors
		else if (useExponentialBackoff && consecutiveErrors > 0) {
			currentInterval = Math.min(
				intervalMs * Math.pow(1.5, consecutiveErrors),
				maxIntervalMs
			);
		}
		
		timeoutId = setTimeout(poll, currentInterval);
	};
	
	const poll = async () => {
		attemptCount++;
		const elapsed = Date.now() - startTime;
		
		// Check for timeout conditions
		if (attemptCount > maxAttempts) {
			if (timeoutId) clearTimeout(timeoutId);
			logger.warn(`[WARNING] Polling timeout | GenID: ${generationId} | Attempts: ${attemptCount}/${maxAttempts}`);
			onError(`polling-timeout:${generationId}`);
			return;
		}
		
		if (elapsed > timeoutMs) {
			if (timeoutId) clearTimeout(timeoutId);
			logger.warn(`[WARNING] Polling timeout | GenID: ${generationId} | Elapsed: ${elapsed}ms/${timeoutMs}ms`);
			onError(`polling-timeout:${generationId}`);
			return;
		}
		
		try {
			const pollResults: PollingUpdateResponse = await nuclenFetchUpdates(generationId);
			if (!pollResults.success) {
				const errMsg = pollResults.message || 'Failed to fetch updates. The task may have been cancelled, or there has been an error. Please see the log file on the setup page or contact support.';
				
				// Check if task has been cancelled
				if (errMsg.includes('Task has been cancelled') || errMsg.includes('cancelled')) {
					// Task was cancelled - stop polling gracefully
					if (timeoutId) clearTimeout(timeoutId);
					logger.log(`[INFO] Task cancelled | GenID: ${generationId}`);
					onError(`task-cancelled:${generationId}`);
					return;
				}
				
				// Check if this is a "Polling failed after X attempts" error from the API
				if (errMsg.includes('Polling failed after') && errMsg.includes('attempts')) {
					// This is a terminal error from the API - trigger redirect
					if (timeoutId) clearTimeout(timeoutId);
					// This is expected behavior - don't throw an error
					onError(`polling-timeout:${generationId}`);
					return;
				}
				
				// Only log as error if it's not an expected polling limit
				logger.error(`[ERROR] Poll failed | GenID: ${generationId} | Error: ${errMsg}`);
				throw new Error(errMsg);
			}
			
			// Reset error count on successful response
			consecutiveErrors = 0;
			currentInterval = intervalMs;

			const {
				processed,
				total,
				successCount = processed,
				failCount,
				finalReport,
				results,
				workflow,
			} = pollResults.data;

			logger.log(`[DEBUG] Poll progress | GenID: ${generationId} | Progress: ${processed}/${total} | Success: ${successCount} | Failed: ${failCount || 0}`);
			onProgress(processed, total, pollResults.data);

			if (processed >= total) {
				if (timeoutId) clearTimeout(timeoutId);
				logger.log(`[SUCCESS] Polling complete | GenID: ${generationId} | Total: ${total} | Success: ${successCount} | Failed: ${failCount || 0}`);
				onComplete({
					processed,
					total,
					successCount,
					failCount,
					finalReport,
					results,
					workflow,
				});
			} else {
				// Schedule next poll
				schedulePoll();
			}
		} catch (err: unknown) {
			consecutiveErrors++;
			
			// Check if we should continue retrying
			if (consecutiveErrors >= 3) {
				if (timeoutId) clearTimeout(timeoutId);
				const message = err instanceof Error ? err.message : 'Unknown error';
				logger.error(`[ERROR] Polling failed | GenID: ${generationId} | Attempts: ${attemptCount} | Consecutive errors: ${consecutiveErrors} | Error: ${message}`);
				onError(`polling-error:${generationId}`);
			} else {
				const message = err instanceof Error ? err.message : 'Unknown error';
				logger.warn(`[RETRY] Polling error | GenID: ${generationId} | Consecutive errors: ${consecutiveErrors}/3 | Error: ${message}`);
				// Continue polling with backoff
				schedulePoll();
			}
		}
	};
	
	// Start polling
	logger.log(`[INFO] Starting polling | GenID: ${generationId} | Initial interval: ${currentInterval}ms | Max attempts: ${maxAttempts} | Progressive: ${useProgressiveIntervals}`);
	poll();

	// Return a cleanup function to allow manual cancellation
	return () => {
		if (timeoutId) {
			clearTimeout(timeoutId);
		}
	};
}
