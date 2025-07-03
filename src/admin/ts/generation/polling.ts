import { nuclenFetchUpdates } from './api';
import type { PollingUpdateData, PollingUpdateResponse } from './api';
import { API_CONFIG } from '../../../shared/constants';

export function NuclenPollAndPullUpdates({
	intervalMs = API_CONFIG.POLLING_INTERVAL_MS,
	generationId,
	onProgress = (() => {}) as (processed: number, total: number) => void,
	onComplete = () => {},
	onError = () => {},
	maxAttempts = 120, // Maximum polling attempts (default: 10 minutes at 5s intervals)
	timeoutMs = 600000, // Maximum total timeout (10 minutes)
}: {
	intervalMs?: number;
	generationId: string;
	onProgress?: (processed: number, total: number) => void;
	onComplete?: (finalData: PollingUpdateData) => void;
	onError?: (errMsg: string) => void;
	maxAttempts?: number;
	timeoutMs?: number;
}) {
	let attemptCount = 0;
	const startTime = Date.now();
	
	const pollInterval = setInterval(async () => {
		attemptCount++;
		const elapsed = Date.now() - startTime;
		
		// Check for timeout conditions
		if (attemptCount > maxAttempts) {
			clearInterval(pollInterval);
			onError(`Polling timeout: Exceeded maximum attempts (${maxAttempts})`);
			return;
		}
		
		if (elapsed > timeoutMs) {
			clearInterval(pollInterval);
			onError(`Polling timeout: Exceeded maximum time (${timeoutMs}ms)`);
			return;
		}
		
		try {
			const pollResults: PollingUpdateResponse = await nuclenFetchUpdates(generationId);
			if (!pollResults.success) {
				const errMsg = pollResults.message || 'Polling error';
				throw new Error(errMsg);
			}

			const {
				processed,
				total,
				successCount = processed,
				failCount,
				finalReport,
				results,
				workflow,
			} = pollResults.data;

			onProgress(processed, total);

			if (processed >= total) {
				clearInterval(pollInterval);
				onComplete({
					processed,
					total,
					successCount,
					failCount,
					finalReport,
					results,
					workflow,
				});
			}
		} catch (err: unknown) {
			clearInterval(pollInterval);
			const message = err instanceof Error ? err.message : 'Unknown error';
			onError(`Polling failed after ${attemptCount} attempts: ${message}`);
		}
	}, intervalMs);

	// Return a cleanup function to allow manual cancellation
	return () => {
		clearInterval(pollInterval);
	};
}
