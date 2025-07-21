import {
	NuclenStartGeneration,
	// NuclenPollAndPullUpdates, // Commented out - polling disabled for now
} from '../nuclen-admin-generate';
import type {
	StartGenerationResponse,
	// PollingUpdateData, // Commented out - polling disabled for now
} from '../generation/api';
import {
	// nuclenShowElement, // Commented out - not needed anymore
	nuclenHideElement,
	nuclenUpdateProgressBarStep,
} from './generate-page-utils';
import type { GeneratePageElements } from './elements';
import { error } from '../../../shared/logger';
import {
	nuclenAlertApiError,
} from '../generation/results';
import { displayError } from '../utils/displayError';
import { displaySuccess, displayPageMessage } from '../utils/displaySuccess';

// Store active polling cleanup function
let activePollingCleanup: (() => void) | null = null;

// Function removed - results are stored by backend during polling
// This function is no longer needed since we're redirecting to tasks page

export function initStep2(elements: GeneratePageElements): void {
	elements.generateForm?.addEventListener('submit', async (event) => {
		event.preventDefault();
		
		// Clean up any existing polling - commented out since polling is disabled
		/*
		if (activePollingCleanup) {
			activePollingCleanup();
			activePollingCleanup = null;
		}
		*/
		if (!window.nuclenAdminVars || !window.nuclenAdminVars.ajax_url) {
			displayError('Error: WP Ajax config not found. Please check the plugin settings.');
			return;
		}
		nuclenUpdateProgressBarStep(elements.stepBar2, 'done');
		// Remove step 3 & 4 - no longer showing real-time progress
		nuclenHideElement(elements.step2);
		if (elements.submitBtn) {
			elements.submitBtn.disabled = true;
			elements.submitBtn.innerHTML = '<span class="spinner is-active" style="float: none; margin: 0 8px 0 0;"></span>Starting generation...';
		}
		
		// Show loading message in the page
		displayPageMessage('Starting content generation...', 'info');
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
			
			// Show success message in the page
			displayPageMessage('✅ Generation task started successfully! Content is being generated in the background.', 'success');
			
			// Also show a success toast
			displaySuccess('Redirecting to tasks page in a moment...');
			
			// Redirect to tasks page after 3 seconds
			setTimeout(() => {
				const tasksUrl = `${window.nuclenAdminVars?.admin_url || '/wp-admin/'}admin.php?page=nuclear-engagement-tasks`;
				window.location.href = tasksUrl;
			}, 3000);
			
			// Comment out the polling code for later use
			/*
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
						window.location.href = `${window.nuclenAdminVars?.admin_url || '/wp-admin/'}admin.php?page=nuclear-engagement-tasks`;
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
			*/
		} catch (error: unknown) {
			// Handle error without step 3 references
			const message = error instanceof Error ? error.message : 'Unknown error';
			nuclenAlertApiError(message);
			if (elements.submitBtn) {
				elements.submitBtn.disabled = false;
			}
			// Don't show restart button since we're redirecting
		}
	});
	
	// Clean up active polling when page unloads - commented out since polling is disabled
	/*
	window.addEventListener('beforeunload', () => {
		if (activePollingCleanup) {
			activePollingCleanup();
			activePollingCleanup = null;
		}
	});
	*/
}
