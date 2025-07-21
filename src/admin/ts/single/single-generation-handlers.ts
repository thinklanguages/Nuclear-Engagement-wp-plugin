import {
	alertApiError,
	populateQuizMetaBox,
	populateSummaryMetaBox,
	storeGenerationResults,
	type PostResult,
} from './single-generation-utils';
import {
	NuclenStartGeneration,
	NuclenPollAndPullUpdates,
} from '../nuclen-admin-generate';
import type { StartGenerationResponse, PollingUpdateData } from '../generation/api';
import { displayError } from '../utils/displayError';
import * as logger from '../utils/logger';

// Store active polling cleanup functions
const activePollingCleanups = new Map<string, () => void>();

export function initSingleGenerationButtons(): void {
	// Use passive listener for better scroll performance
	document.addEventListener('click', async (event: MouseEvent) => {
		const target = event.target;
		if (!(target instanceof HTMLElement)) return;
		if (!target.classList.contains('nuclen-generate-single')) return;

		const btn = target as HTMLButtonElement;
		const postId = btn.dataset.postId;
		const workflow = btn.dataset.workflow;
		if (!postId || !workflow) {
			displayError('Missing data attributes: postId or workflow not found.');
			return;
		}

		// Clean up any existing polling for this button
		const buttonKey = `${postId}-${workflow}`;
		const existingCleanup = activePollingCleanups.get(buttonKey);
		if (existingCleanup) {
			existingCleanup();
			activePollingCleanups.delete(buttonKey);
		}

		btn.disabled = true;
		btn.textContent = 'Generating...';

		try {
			const startResp: StartGenerationResponse | {
		ok: boolean;
		status: number;
		error?: string;
		data?: { generation_id?: string; [key: string]: unknown };
		generation_id?: string;
		} = await NuclenStartGeneration({
			nuclen_selected_post_ids: JSON.stringify([postId]),
			nuclen_selected_generate_workflow: workflow,
			source: 'single',
		});

			if ('ok' in startResp && !startResp.ok) {
				alertApiError((startResp as { error?: string }).error || 'Generation failed');
				btn.textContent = 'Generate';
				btn.disabled = false;
				return;
			}

			const generationId =
		startResp.data?.generation_id ||
		startResp.generation_id ||
		'gen_' + Math.random().toString(36).substring(2);

			const cleanupPolling = NuclenPollAndPullUpdates({
				intervalMs: 5000,
				generationId,
				onProgress() {
					btn.textContent = 'Generating...';
				},
				async onComplete({ results, workflow: wf }: PollingUpdateData) {
					// Remove cleanup function since polling completed
					activePollingCleanups.delete(buttonKey);
					logger.log(`[DEBUG] onComplete called | PostID: ${postId} | Workflow: ${wf} | Has results: ${!!results} | Results type: ${typeof results}`);
					if (results) {
						logger.log(`[DEBUG] Results content: ${JSON.stringify(results)}`);
					}
					if (results && typeof results === 'object') {
						try {
							const { ok, data } = await storeGenerationResults(wf, results);
							const respData = data as Record<string, unknown>;
							if (ok && !('code' in respData)) {
								const postResult = results[postId] as PostResult;
								logger.log(`[DEBUG] Post result for ${postId}: ${JSON.stringify(postResult)}`);
								const finalDate = respData.finalDate && typeof respData.finalDate === 'string' ? (respData.finalDate as string) : undefined;
								if (postResult) {
									if (wf === 'quiz') {
										populateQuizMetaBox(postResult, finalDate);
									} else if (wf === 'summary') {
										populateSummaryMetaBox(postResult, finalDate);
									}
									btn.textContent = 'Stored!';
								} else {
									logger.warn(`[WARNING] No result found for post ${postId} in results object`);
									btn.textContent = 'Done (no data for this post)!';
								}
							} else {
								logger.error('Error storing single-generation results in WP:', respData);
								btn.textContent = 'Generation failed!';
							}
						} catch (err) {
							logger.error('Fetch error calling /receive-content endpoint:', err);
							btn.textContent = 'Generation failed!';
						}
					} else {
						logger.warn(`[WARNING] No results received | PostID: ${postId} | Results: ${results}`);
						btn.textContent = 'Done (no data)!';
					}
					btn.disabled = false;
				},
				onError(errMsg) {
					// Remove cleanup function since polling errored
					activePollingCleanups.delete(buttonKey);
					
					// Check if this is a polling timeout or error
					if (errMsg.startsWith('polling-timeout:') || errMsg.startsWith('polling-error:')) {
						const generationId = errMsg.split(':')[1];
						// Redirect to tasks page
						window.location.href = `${window.nuclenAdminVars?.admin_url || '/wp-admin/'}admin.php?page=nuclear-engagement-tasks`;
					} else {
						alertApiError(errMsg);
						btn.textContent = 'Generate';
						btn.disabled = false;
					}
				},
			});
			
			// Store the cleanup function
			activePollingCleanups.set(buttonKey, cleanupPolling);
		} catch (err: unknown) {
			const message = err instanceof Error ? err.message : 'Unknown error';
			alertApiError(message);
			btn.textContent = 'Generate';
			btn.disabled = false;
		}
	});
	
	// Clean up all active polling when page unloads
	window.addEventListener('beforeunload', () => {
		activePollingCleanups.forEach(cleanup => cleanup());
		activePollingCleanups.clear();
	});
}
