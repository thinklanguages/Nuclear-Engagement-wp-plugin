import { NuclenStartGeneration, NuclenPollAndPullUpdates } from '../nuclen-admin-generate';
import {
  nuclenShowElement,
  nuclenHideElement,
  nuclenUpdateProgressBarStep,
} from './generate-page-utils';
import type { GeneratePageElements } from './elements';
import {
  nuclenAlertApiError,
  nuclenStoreGenerationResults,
} from '../generation/results';
import { displayError } from '../utils/displayError';
import * as logger from '../utils/logger';

export function initStep2(elements: GeneratePageElements): void {
  elements.generateForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!window.nuclenAdminVars || !window.nuclenAdminVars.ajax_url) {
      displayError('Error: WP Ajax config not found. Please check the plugin settings.');
      return;
    }
    nuclenUpdateProgressBarStep(elements.stepBar2, 'done');
    nuclenUpdateProgressBarStep(elements.stepBar3, 'current');
    nuclenShowElement(elements.updatesSection);
    if (elements.updatesContent) {
      elements.updatesContent.innerText = 'Processing posts... Do NOT leave this page until the process is complete.';
    }
    nuclenHideElement(elements.step2);
    if (elements.submitBtn) {
      elements.submitBtn.disabled = true;
    }
    try {
      const formDataObj = Object.fromEntries(new FormData(elements.generateForm!).entries());
      const startResp = await NuclenStartGeneration(formDataObj);
      const generationId =
        startResp.data?.generation_id || startResp.generation_id || 'gen_' + Math.random().toString(36).substring(2);
      NuclenPollAndPullUpdates({
        intervalMs: 5000,
        generationId,
        onProgress: (processed, total) => {
          const safeProcessed = processed === undefined ? 0 : processed;
          const safeTotal = total === undefined ? '' : total;
          if (elements.updatesContent) {
            elements.updatesContent.innerText = `Processed ${safeProcessed} of ${safeTotal} posts so far...`;
          }
        },
        onComplete: async ({ failCount, finalReport, results, workflow }) => {
          nuclenUpdateProgressBarStep(elements.stepBar3, 'done');
          nuclenUpdateProgressBarStep(elements.stepBar4, 'current');
          if (results && typeof results === 'object') {
            try {
              const { ok, data } = await nuclenStoreGenerationResults(workflow, results);
              if (ok && !data.code) {
                logger.log('Bulk content stored in WP meta successfully:', data);
              } else {
                logger.error('Error storing bulk content in WP meta:', data);
              }
            } catch (err) {
              logger.error('Error storing bulk content in WP meta:', err);
            }
          }
          if (elements.updatesContent) {
            if (failCount && finalReport) {
              elements.updatesContent.innerText = `Some posts failed. ${finalReport.message || ''}`;
              nuclenUpdateProgressBarStep(elements.stepBar4, 'failed');
            } else {
              elements.updatesContent.innerText = 'All posts processed successfully! Your content has been saved.';
              nuclenUpdateProgressBarStep(elements.stepBar4, 'done');
            }
          }
          if (elements.submitBtn) {
            elements.submitBtn.disabled = false;
          }
          nuclenShowElement(elements.restartBtn);
        },
        onError: (errMsg: string) => {
          nuclenUpdateProgressBarStep(elements.stepBar3, 'failed');
          nuclenAlertApiError(errMsg);
          if (elements.updatesContent) {
            elements.updatesContent.innerText = `Error: ${errMsg}`;
          }
          if (elements.submitBtn) {
            elements.submitBtn.disabled = false;
          }
          nuclenShowElement(elements.restartBtn);
        },
      });
    } catch (error: any) {
      nuclenUpdateProgressBarStep(elements.stepBar3, 'failed');
      nuclenAlertApiError(error.message);
      if (elements.submitBtn) {
        elements.submitBtn.disabled = false;
      }
      nuclenShowElement(elements.restartBtn);
    }
  });
}
