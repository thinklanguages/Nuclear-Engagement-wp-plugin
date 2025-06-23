export function nuclenShowElement(el: HTMLElement | null): void {
  if (!el) return;
  el.classList.remove('nuclen-hidden');
}

export function nuclenHideElement(el: HTMLElement | null): void {
  if (!el) return;
  el.classList.add('nuclen-hidden');
}

export function nuclenUpdateProgressBarStep(stepEl: HTMLElement | null, state: string): void {
  if (!stepEl) return;
  stepEl.classList.remove(
    'nuclen-step-todo',
    'nuclen-step-current',
    'nuclen-step-done',
    'nuclen-step-failed'
  );
  stepEl.classList.add(`nuclen-step-${state}`);
}

/**
 * Fetch remaining credits from the SaaS.
 */
export async function nuclenCheckCreditsAjax(): Promise<number> {
  if (!(window as any).nuclenAjax || !(window as any).nuclenAjax.ajax_url) {
    throw new Error('Missing nuclenAjax configuration (ajax_url).');
  }
  if (!(window as any).nuclenAjax.fetch_action) {
    throw new Error('Missing fetch_action in nuclenAjax configuration.');
  }
  const formData = new FormData();
  formData.append('action', (window as any).nuclenAjax.fetch_action);
  if ((window as any).nuclenAjax.nonce) {
    formData.append('security', (window as any).nuclenAjax.nonce);
  }
  const result = await nuclenFetchWithRetry<any>((window as any).nuclenAjax.ajax_url, {
    method: 'POST',
    body: formData,
    credentials: 'same-origin',
  });
  if (!result.ok) {
    throw new Error(result.error || `HTTP ${result.status}`);
  }
  const data = result.data;
  if (!data.success) {
    throw new Error(data.message || data.data?.message || 'Failed to fetch credits from SaaS');
  }
  if (typeof data.data.remaining_credits === 'number') {
    return data.data.remaining_credits;
  }
  throw new Error("No 'remaining_credits' in response");
}

/**
 * Toggle summary settings visibility.
 */
export function nuclenToggleSummaryFields(): void {
  const generateTypeEl = document.getElementById('nuclen_generate_workflow') as HTMLSelectElement | null;
  const summarySettingsEl = document.getElementById('nuclen-summary-settings') as HTMLDivElement | null;
  const summaryParagraphOptions = document.getElementById('nuclen-summary-paragraph-options') as HTMLDivElement | null;
  const summaryBulletOptions = document.getElementById('nuclen-summary-bullet-options') as HTMLDivElement | null;
  const summaryFormatEl = document.getElementById('nuclen_summary_format') as HTMLSelectElement | null;

  if (!generateTypeEl || !summarySettingsEl || !summaryParagraphOptions || !summaryBulletOptions || !summaryFormatEl) {
    return;
  }
  if (generateTypeEl.value === 'summary') {
    summarySettingsEl.classList.remove('nuclen-hidden');
    if (summaryFormatEl.value === 'paragraph') {
      summaryParagraphOptions.classList.remove('nuclen-hidden');
      summaryBulletOptions.classList.add('nuclen-hidden');
    } else {
      summaryParagraphOptions.classList.add('nuclen-hidden');
      summaryBulletOptions.classList.remove('nuclen-hidden');
    }
  } else {
    summarySettingsEl.classList.add('nuclen-hidden');
    summaryParagraphOptions.classList.add('nuclen-hidden');
    summaryBulletOptions.classList.add('nuclen-hidden');
  }
}

import { nuclenFetchWithRetry } from '../nuclen-admin-generate';
