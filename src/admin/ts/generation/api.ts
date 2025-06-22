import * as logger from '../utils/logger';

export async function nuclenFetchWithRetry(
  url: string,
  options: RequestInit,
  retries = 3,
  initialDelayMs = 500
): Promise<Response> {
  let attempt = 0;
  let delay = initialDelayMs;
  let lastError: Error | undefined;

  while (attempt <= retries) {
    try {
      const response = await fetch(url, options);
      if (!response.ok) {
        throw new Error(`Network response was not ok (HTTP ${response.status})`);
      }
      return response;
    } catch (error: any) {
      lastError = error as Error;
      if (attempt === retries) {
        break;
      }

      logger.warn(
        `Retrying request to ${url} with method ${options.method || 'GET'} (${retries - attempt} attempts left).`,
        lastError
      );
      await new Promise((resolve) => setTimeout(resolve, delay));
      delay *= 2;
    }
    attempt += 1;
  }

  logger.error(
    `Max retries reached for ${url} with method ${options.method || 'GET'}:`,
    lastError
  );
  throw lastError;
}

export async function nuclenFetchUpdates(generationId?: string) {
  if (!window.nuclenAjax || !window.nuclenAjax.ajax_url) {
    throw new Error('Missing nuclenAjax configuration (ajax_url).');
  }
  if (!window.nuclenAjax.fetch_action) {
    throw new Error('Missing fetch_action in nuclenAjax configuration.');
  }

  const formData = new FormData();
  formData.append('action', window.nuclenAjax.fetch_action);

  if (window.nuclenAjax.nonce) {
    formData.append('security', window.nuclenAjax.nonce);
  }

  if (generationId) {
    formData.append('generation_id', generationId);
  }

  const response = await nuclenFetchWithRetry(window.nuclenAjax.ajax_url, {
    method: 'POST',
    body: formData,
    credentials: 'same-origin',
  });

  const data = await response.json();
  return data;
}

export async function NuclenStartGeneration(dataToSend: Record<string, any>) {
  if (!window.nuclenAdminVars || !window.nuclenAdminVars.ajax_url) {
    throw new Error('Missing WP Ajax config (nuclenAdminVars.ajax_url).');
  }

  const formData = new FormData();
  formData.append('action', 'nuclen_trigger_generation');
  formData.append('payload', JSON.stringify(dataToSend));
  if (!window.nuclenAjax?.nonce) {
    throw new Error('Missing security nonce.');
  }
  formData.append('security', window.nuclenAjax.nonce);

  const response = await nuclenFetchWithRetry(window.nuclenAdminVars.ajax_url, {
    method: 'POST',
    body: formData,
    credentials: 'same-origin',
  });

  const data = await response.json();
  if (!data.success) {
    const errMsg =
      data.message || data.data?.message || 'Generation start failed (unknown error).';
    throw new Error(errMsg);
  }

  return data;
}
