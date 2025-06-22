export interface NuclenFetchResult<T = any> {
  ok: boolean;
  status: number;
  data: T | null;
  error?: string;
}

export async function nuclenFetchWithRetry<T = any>(
  url: string,
  options: RequestInit,
  retries = 3
): Promise<NuclenFetchResult<T>> {
  try {
    const response = await fetch(url, options);
    const { status, ok } = response;

    if (ok) {
      try {
        const json = (await response.json()) as T;
        return { ok: true, status, data: json };
      } catch (err: any) {
        const text = await response.text().catch(() => '');
        return { ok: true, status, data: null, error: text || err?.message };
      }
    }

    const errText = await response.text().catch(() => '');
    return { ok: false, status, data: null, error: errText };
  } catch (error: any) {
    if (retries > 0) {
      console.warn(`Retrying... (${retries} attempts left)`);
      return nuclenFetchWithRetry(url, options, retries - 1);
    }
    console.error('Max retries reached:', error);
    return {
      ok: false,
      status: 0,
      data: null,
      error: error?.message || String(error),
    };
  }
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

  const result = await nuclenFetchWithRetry(window.nuclenAjax.ajax_url, {
    method: 'POST',
    body: formData,
    credentials: 'same-origin',
  });

  if (!result.ok) {
    throw new Error(result.error || `HTTP ${result.status}`);
  }

  return result.data;
}

export async function NuclenStartGeneration(
  dataToSend: Record<string, any>
): Promise<NuclenFetchResult<any>> {
  if (!window.nuclenAdminVars || !window.nuclenAdminVars.ajax_url) {
    return {
      ok: false,
      status: 0,
      data: null,
      error: 'Missing WP Ajax config (nuclenAdminVars.ajax_url).',
    };
  }

  const formData = new FormData();
  formData.append('action', 'nuclen_trigger_generation');
  formData.append('payload', JSON.stringify(dataToSend));
  formData.append('security', window.nuclenAjax?.nonce ?? '');

  const result = await nuclenFetchWithRetry(window.nuclenAdminVars.ajax_url, {
    method: 'POST',
    body: formData,
    credentials: 'same-origin',
  });

  if (!result.ok) {
    return result;
  }

  if (result.data && typeof result.data === 'object' && !result.data.success) {
    const msg =
      result.data.data?.message ||
      result.data.message ||
      'Generation start failed (unknown error).';
    return { ok: false, status: result.status, data: result.data, error: msg };
  }

  return result;
}
