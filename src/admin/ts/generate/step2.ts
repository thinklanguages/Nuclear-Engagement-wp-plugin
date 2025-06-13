import { NuclenStartGeneration, NuclenPollAndPullUpdates } from '../nuclen-admin-generate';
import {
  nuclenShowElement,
  nuclenHideElement,
  nuclenUpdateProgressBarStep,
} from './generate-page-utils';
import type { GeneratePageElements } from './elements';

const REST_ENDPOINT =
  (window as any).nuclenAdminVars?.rest_receive_content ||
  '/wp-json/nuclear-engagement/v1/receive-content';
const REST_NONCE = (window as any).nuclenAdminVars?.rest_nonce || '';

export function initStep2(elements: GeneratePageElements): void {
  elements.generateForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!(window as any).nuclenAdminVars || !(window as any).nuclenAdminVars.ajax_url) {
      alert('Error: WP Ajax config not found. Please check the plugin settings.');
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
              const payload = { workflow, results };
              const storeResp = await fetch(REST_ENDPOINT, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'X-WP-Nonce': REST_NONCE,
                },
                credentials: 'include',
                body: JSON.stringify(payload),
              });
              const storeData = await storeResp.json();
              if (storeResp.ok && !storeData.code) {
                console.log('Bulk content stored in WP meta successfully:', storeData);
              } else {
                console.error('Error storing bulk content in WP meta:', storeData);
              }
            } catch (err) {
              console.error('Error storing bulk content in WP meta:', err);
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
          if (errMsg.includes('Invalid API key')) {
            alert('Your API key is invalid. Please go to the Setup page and enter a new one.');
          } else if (errMsg.includes('Invalid WP App Password')) {
            alert('Your WP App Password is invalid. Please re-generate it on the Setup page.');
          } else if (errMsg.includes('Not enough credits')) {
            alert('Not enough credits. Please top up your account or reduce the number of posts.');
          } else {
            alert(`Error: ${errMsg}`);
          }
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
      if (error.message.includes('Invalid API key')) {
        alert('Your API key is invalid. Please go to the Setup page and enter a new one.');
      } else if (error.message.includes('Invalid WP App Password')) {
        alert('Your WP App Password is invalid. Please go to the Setup page and re-generate it.');
      } else if (error.message.includes('Not enough credits')) {
        alert('Not enough credits. Please top up or reduce posts.');
      } else {
        alert(`Error starting generation: ${error.message}`);
      }
      if (elements.submitBtn) {
        elements.submitBtn.disabled = false;
      }
      nuclenShowElement(elements.restartBtn);
    }
  });
}
