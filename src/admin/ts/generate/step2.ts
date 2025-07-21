import {
	NuclenStartGeneration,
	NuclenPollAndPullUpdates,
} from '../nuclen-admin-generate';
import type {
	StartGenerationResponse,
	PollingUpdateData,
} from '../generation/api';
import {
	nuclenShowElement,
	nuclenHideElement,
	nuclenUpdateProgressBarStep,
} from './generate-page-utils';
import type { GeneratePageElements } from './elements';
import { error } from '../../../shared/logger';
import {
	nuclenAlertApiError,
} from '../generation/results';
import { displayError } from '../utils/displayError';

// Store active polling cleanup function
let activePollingCleanup: (() => void) | null = null;

// Function removed - results are stored by backend during polling

function updateCompletionUi(
	elements: GeneratePageElements,
	failCount: number | undefined,
	finalReport: { message?: string } | undefined,
): void {
	if (elements.updatesContent) {
		// Disabled for now - will be re-enabled later
		// if (failCount && finalReport) {
		// 	elements.updatesContent.innerText = `Some posts failed. ${finalReport.message || ''}`;
		// 	nuclenUpdateProgressBarStep(elements.stepBar4, 'failed');
		// } else {
		// 	elements.updatesContent.innerText = 'All posts processed successfully! Your content has been saved.';
		// 	nuclenUpdateProgressBarStep(elements.stepBar4, 'done');
		// }
		// Keep progress bar updates but clear the text
		elements.updatesContent.innerText = '';
		if (failCount && finalReport) {
			nuclenUpdateProgressBarStep(elements.stepBar4, 'failed');
		} else {
			nuclenUpdateProgressBarStep(elements.stepBar4, 'done');
		}
	}
	if (elements.submitBtn) {
		elements.submitBtn.disabled = false;
	}
	nuclenShowElement(elements.restartBtn);
}

export function initStep2(elements: GeneratePageElements): void {
	elements.generateForm?.addEventListener('submit', async (event) => {
		event.preventDefault();
		
		// Clean up any existing polling
		if (activePollingCleanup) {
			activePollingCleanup();
			activePollingCleanup = null;
		}
		if (!window.nuclenAdminVars || !window.nuclenAdminVars.ajax_url) {
			displayError('Error: WP Ajax config not found. Please check the plugin settings.');
			return;
		}
		nuclenUpdateProgressBarStep(elements.stepBar2, 'done');
		nuclenUpdateProgressBarStep(elements.stepBar3, 'current');
		nuclenShowElement(elements.updatesSection);
		if (elements.updatesContent) {
			elements.updatesContent.innerHTML = `<span class="spinner is-active"></span><b>Processing posts... this can take a few minutes.</b> Stay on this page to see progress updates in real time. Or else, you can safely leave this page - generation will continue in the background. You can track progress on the tasks page. The generated content will be available in the post editor and on the frontend when the process is complete.`;
		}
		nuclenHideElement(elements.step2);
		if (elements.submitBtn) {
			elements.submitBtn.disabled = true;
		}
		try {
			const formDataObj = Object.fromEntries(new FormData(elements.generateForm!).entries());
			const startResp: StartGenerationResponse = await NuclenStartGeneration(
				formDataObj
			);
			// Extract generation_id - WordPress returns it in data object
			let generationId = '';
			
			// The actual structure from WordPress wp_send_json_success is:
			// { success: true, data: { generation_id: "...", ... } }
			if (startResp && startResp.data && startResp.data.generation_id) {
				generationId = String(startResp.data.generation_id);
			}
			// Fallback checks for other possible structures
			else if (startResp && startResp.generation_id) {
				generationId = String(startResp.generation_id);
			}
			
			// Final fallback to random ID if not found
			if (!generationId) {
				error('Generation ID not found in response, using fallback');
				generationId = 'gen_' + Math.random().toString(36).substring(2);
			}
			activePollingCleanup = NuclenPollAndPullUpdates({
				intervalMs: 5000,
				generationId,
				onProgress: (processed, total) => {
					const safeProcessed = processed === undefined ? 0 : processed;
					const safeTotal = total === undefined ? 0 : total;
					if (elements.updatesContent) {
						// Show simple progress message
						const progressPercent = safeTotal > 0 ? Math.round((safeProcessed / safeTotal) * 100) : 0;
						elements.updatesContent.innerHTML = `<span class="spinner is-active"></span> Processing: ${safeProcessed} of ${safeTotal} posts completed (${progressPercent}%)`;
					}
				},
				onComplete: async ({ failCount, finalReport }: PollingUpdateData) => {
					// Clear the cleanup function since polling completed
					activePollingCleanup = null;
					nuclenUpdateProgressBarStep(elements.stepBar3, 'done');
					nuclenUpdateProgressBarStep(elements.stepBar4, 'current');
					// Results are already stored by the backend during polling
					// await storeResults(workflow, results);
					updateCompletionUi(elements, failCount, finalReport);
				},
				onError: (errMsg: string) => {
					// Clear the cleanup function since polling errored
					activePollingCleanup = null;
					
					// Check if this is a polling timeout or error
					if (errMsg.startsWith('polling-timeout:') || errMsg.startsWith('polling-error:')) {
						const generationId = errMsg.split(':')[1];
						// Redirect to tasks page
						window.location.href = `${window.nuclenAdminVars?.admin_url || '/wp-admin/'}admin.php?page=nuclear-engagement-tasks&highlight=${generationId}`;
					} else {
						// Handle other errors as failures
						nuclenUpdateProgressBarStep(elements.stepBar3, 'failed');
						nuclenAlertApiError(errMsg);
						
						if (elements.updatesContent) {
							elements.updatesContent.innerText = `Error: ${errMsg}`;
						}
						
						if (elements.submitBtn) {
							elements.submitBtn.disabled = false;
						}
						nuclenShowElement(elements.restartBtn);
					}
				},
			});
		} catch (error: unknown) {
			nuclenUpdateProgressBarStep(elements.stepBar3, 'failed');
			const message = error instanceof Error ? error.message : 'Unknown error';
			nuclenAlertApiError(message);
			if (elements.submitBtn) {
				elements.submitBtn.disabled = false;
			}
			nuclenShowElement(elements.restartBtn);
		}
	});
	
	// Clean up active polling when page unloads
	window.addEventListener('beforeunload', () => {
		if (activePollingCleanup) {
			activePollingCleanup();
			activePollingCleanup = null;
		}
	});
}
