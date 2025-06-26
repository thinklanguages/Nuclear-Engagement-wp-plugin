import * as logger from '../utils/logger';

export interface NuclenFetchResult<T> {
  ok: boolean;
  status: number;
  data: T | null;
  error?: string;
}

export interface PollUpdate {
  processed: number;
  total: number;
  successCount?: number;
  failCount?: number;
  finalReport?: { message?: string };
  results?: Record<string, any>;
  workflow: string;
}

export interface PollResponse {
  success: boolean;
  message?: string;
  data: PollUpdate;
}

export interface StartGenerationResponse {
  success: boolean;
  message?: string;
  generation_id?: string;
  data?: { generation_id?: string; message?: string } & Record<string, unknown>;
}

export async function nuclenFetchWithRetry<T = unknown>(
  url: string,
  options: RequestInit,
  retries = 3,
  initialDelayMs = 500
): Promise<NuclenFetchResult<T>> {
  let attempt = 0;
  let delay = initialDelayMs;
  let lastError: Error | undefined;

  while (attempt <= retries) {
    try {
      const response = await fetch(url, options);
      const { status, ok } = response;
      const bodyText = await response.text().catch(() => '');
      let data: T | null = null;
      if (bodyText) {
        try {
          data = JSON.parse(bodyText) as T;
        } catch {
          // body is not JSON
        }
      }

      if (ok) {
        return { ok: true, status, data };
      }

      return { ok: false, status, data, error: bodyText };
    } catch (error: unknown) {
      lastError = error instanceof Error ? error : new Error(String(error));
      if (attempt === retries) {
        break;
      }

      logger.warn(
        `Retrying request to ${url} with method ${options.method || 'GET'} (${retries - attempt} attempts left). Error: ${lastError.message}`,
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

export async function nuclenFetchUpdates(generationId?: string): Promise<PollResponse> {
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

  const result = await nuclenFetchWithRetry<PollResponse>(window.nuclenAjax.ajax_url, {
    method: 'POST',
    body: formData,
    credentials: 'same-origin',
  });

  if (!result.ok) {
    throw new Error(result.error || `HTTP ${result.status}`);
  }

  return result.data as PollResponse;
}

export async function NuclenStartGeneration(
  dataToSend: Record<string, unknown>,
): Promise<StartGenerationResponse> {
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

  const result = await nuclenFetchWithRetry<StartGenerationResponse>(window.nuclenAdminVars.ajax_url, {
    method: 'POST',
    body: formData,
    credentials: 'same-origin',
  });

  if (!result.ok) {
    throw new Error(result.error || `HTTP ${result.status}`);
  }

  const data = result.data;
  if (!data?.success) {
    const errMsg = data?.message || data?.data?.message || 'Generation start failed (unknown error).';
    throw new Error(errMsg);
  }

  return data as StartGenerationResponse;
}
