/**
 * nuclen-admin-generate.ts
 * In this file, we have:
 *  - nuclenFetchWithRetry: retries fetch requests up to N times
 *  - nuclenFetchUpdates: calls your 'nuclen_fetch_app_updates' AJAX action to poll progress
 *  - NuclenPollAndPullUpdates: repeatedly calls nuclenFetchUpdates() until processed >= total
 *  - NuclenStartGeneration: starts the generation process via your 'nuclen_trigger_generation' AJAX action
 */

/**
 * A small helper to retry fetch calls up to 'retries' times.
 */
export async function nuclenFetchWithRetry(
  url: string,
  options: RequestInit,
  retries = 3
): Promise<Response> {
  try {
    const response = await fetch(url, options);
    if (!response.ok) {
      throw new Error(`Network response was not ok (HTTP ${response.status})`);
    }
    return response;
  } catch (error) {
    if (retries > 0) {
      console.warn(`Retrying... (${retries} attempts left)`);
      return nuclenFetchWithRetry(url, options, retries - 1);
    } else {
      console.error('Max retries reached:', error);
      throw error;
    }
  }
}

/**
 * nuclenFetchUpdates(generationId?)
 * Uses nuclenFetchWithRetry to call your WP plugin's 'nuclen_fetch_app_updates' action (nuclenAjax.fetch_action).
 * Returns a JSON object with { success: boolean, data: {...} } or throws an Error.
 */
async function nuclenFetchUpdates(generationId?: string) {
  if (!window.nuclenAjax || !window.nuclenAjax.ajax_url) {
    throw new Error('Missing nuclenAjax configuration (ajax_url).');
  }
  if (!window.nuclenAjax.fetch_action) {
    throw new Error('Missing fetch_action in nuclenAjax configuration.');
  }

  const formData = new FormData();
  formData.append('action', window.nuclenAjax.fetch_action);

  // IMPORTANT: Also append the same 'security' field your PHP expects:
  if (window.nuclenAjax.nonce) {
    formData.append('security', window.nuclenAjax.nonce);
  }

  // Pass the generation ID so the server can look up progress for that task
  if (generationId) {
    formData.append('generation_id', generationId);
  }

  const response = await nuclenFetchWithRetry(window.nuclenAjax.ajax_url, {
    method: 'POST',
    body: formData,
    credentials: 'same-origin',
  });

  const data = await response.json();
  // Typically { success: boolean, data: {...}, message?: string }
  return data;
}

/**
 * NuclenPollAndPullUpdates
 * Polls progress every intervalMs. If processed >= total, we call onComplete(...).
 * If there's an error, we call onError(...).
 */
export function NuclenPollAndPullUpdates({
  intervalMs = 10000,
  generationId,
  onProgress = (_processed: number, _total: number) => {},
  onComplete = (_finalData: any) => {},
  onError = (_errMsg: string) => {},
}: {
  intervalMs?: number;
  generationId: string; 
  onProgress?: (processed: number, total: number) => void;
  onComplete?: (finalData: any) => void;
  onError?: (errMsg: string) => void;
}) {
  const pollInterval = setInterval(async () => {
    try {
      const formData = new FormData();
      formData.append('action', 'nuclen_generation_progress');
      formData.append('generation_id', generationId);
      formData.append('security', window.nuclenAjax?.nonce ?? '');

      const resp = await fetch(window.nuclenAdminVars.ajax_url, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
      });

      const progressData = await resp.json();
      if (!progressData.success || !progressData.data.generations.length) {
        return;
      }

      const generation = progressData.data.generations[0];
      const processed = generation.processed || 0;
      const total = generation.total || 0;

      onProgress(processed, total);

      if (generation.status === 'complete') {
        clearInterval(pollInterval);

        const pollResults = await nuclenFetchUpdates(generationId);
        if (!pollResults.success) {
          const errMsg = pollResults.message || 'Polling error';
          throw new Error(errMsg);
        }

      // The server response data might look like:
      // { processed: number, total: number, failCount: number, results: object, workflow: string, ... }
        const { processed: p, total: t, results, workflow } = pollResults.data;
        onComplete({ processed: p, total: t, results, workflow });
      } else if (generation.status === 'failed') {
        clearInterval(pollInterval);
        onError('Generation failed');
      }
    } catch (err: any) {
      clearInterval(pollInterval);
      onError(err.message);
    }
  }, intervalMs);
}

/**
 * NuclenStartGeneration
 * Kicks off generation by calling WP AJAX action: nuclen_trigger_generation.
 * The payload param is typically an object like:
 * {
 *   nuclen_selected_post_ids: JSON.stringify([...]),
 *   nuclen_selected_generate_workflow: 'quiz' | 'summary',
 *   ...
 * }
 */
export async function NuclenStartGeneration(dataToSend: Record<string, any>) {
  if (!window.nuclenAdminVars || !window.nuclenAdminVars.ajax_url) {
    throw new Error('Missing WP Ajax config (nuclenAdminVars.ajax_url).');
  }

  const formData = new FormData();
  formData.append('action', 'nuclen_trigger_generation');
  formData.append('payload', JSON.stringify(dataToSend));
  formData.append('security', window.nuclenAjax?.nonce ?? '');

  const response = await fetch(window.nuclenAdminVars.ajax_url, {
    method: 'POST',
    body: formData,
    credentials: 'same-origin',
  });

  if (!response.ok) {
    throw new Error(`Generation start failed: HTTP ${response.status}`);
  }

  const data = await response.json();
  if (!data.success) {
    if (typeof data.data === 'object' && data.data.message) {
      throw new Error(data.data.message);
    } else if (data.message) {
      throw new Error(data.message);
    } else {
      throw new Error('Generation start failed (unknown error).');
    }
  }

  return data;
}
