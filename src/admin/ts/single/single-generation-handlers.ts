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
      const startResp: StartGenerationResponse | {
        ok: boolean;
        status: number;
        error?: string;
        data?: { generation_id?: string; [key: string]: unknown };
        generation_id?: string;
      } = await NuclenStartGeneration({
        nuclen_selected_post_ids: JSON.stringify([postId]),
        nuclen_selected_generate_workflow: workflow,
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

      NuclenPollAndPullUpdates({
        intervalMs: 5000,
        generationId,
        onProgress() {
          btn.textContent = 'Generating...';
        },
        async onComplete({ results, workflow: wf }: PollingUpdateData) {
          if (results && typeof results === 'object') {
            try {
              const { ok, data } = await storeGenerationResults(wf, results);
              const respData = data as Record<string, unknown>;
              if (ok && !('code' in respData)) {
                  const postResult = results[postId] as PostResult;
                const finalDate = respData.finalDate && typeof respData.finalDate === 'string' ? (respData.finalDate as string) : undefined;
                if (postResult) {
                  if (wf === 'quiz') {
                      populateQuizMetaBox(postResult, finalDate);
                  } else if (wf === 'summary') {
                      populateSummaryMetaBox(postResult, finalDate);
                  }
                }
                btn.textContent = 'Stored!';
              } else {
                logger.error('Error storing single-generation results in WP:', respData);
                btn.textContent = 'Generation failed!';
              }
            } catch (err) {
              logger.error('Fetch error calling /receive-content endpoint:', err);
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
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Unknown error';
      alertApiError(message);
      btn.textContent = 'Generate';
      btn.disabled = false;
    }
  });
}
