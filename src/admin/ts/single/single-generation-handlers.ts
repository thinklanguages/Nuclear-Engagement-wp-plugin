import {
  alertApiError,
  populateQuizMetaBox,
  populateSummaryMetaBox,
  storeGenerationResults,
} from './single-generation-utils';
import {
  NuclenStartGeneration,
  NuclenPollAndPullUpdates,
} from '../nuclen-admin-generate';
import { displayError } from '../utils/displayError';

export function initSingleGenerationButtons(): void {
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

    btn.disabled = true;
    btn.textContent = 'Generating...';

    try {
      const startResp = await NuclenStartGeneration({
        nuclen_selected_post_ids: JSON.stringify([postId]),
        nuclen_selected_generate_workflow: workflow,
      });
      const generationId =
        startResp.data?.generation_id ||
        startResp.generation_id ||
        'gen_' + Math.random().toString(36).substring(2);

      NuclenPollAndPullUpdates({
        intervalMs: 5000,
        generationId,
        onProgress() {
          btn.textContent = 'Generating...';
        },
        async onComplete({ results, workflow: wf }) {
          if (results && typeof results === 'object') {
            try {
              const { ok, data } = await storeGenerationResults(wf, results);
              if (ok && !data.code) {
                const postResult = results[postId];
                const finalDate = data.finalDate && typeof data.finalDate === 'string' ? data.finalDate : undefined;
                if (postResult) {
                  if (wf === 'quiz') {
                    populateQuizMetaBox(postResult, finalDate);
                  } else if (wf === 'summary') {
                    populateSummaryMetaBox(postResult, finalDate);
                  }
                }
                btn.textContent = 'Stored!';
              } else {
                console.error('Error storing single-generation results in WP:', data);
                btn.textContent = 'Generation failed!';
              }
            } catch (err) {
              console.error('Fetch error calling /receive-content endpoint:', err);
              btn.textContent = 'Generation failed!';
            }
          } else {
            btn.textContent = 'Done (no data)!';
          }
          btn.disabled = false;
        },
        onError(errMsg) {
          alertApiError(errMsg);
          btn.textContent = 'Generate';
          btn.disabled = false;
        },
      });
    } catch (err: any) {
      alertApiError(err.message);
      btn.textContent = 'Generate';
      btn.disabled = false;
    }
  });
}
