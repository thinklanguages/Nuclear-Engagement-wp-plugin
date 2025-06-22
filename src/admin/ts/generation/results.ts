export const REST_ENDPOINT =
  (window as any).nuclenAdminVars?.rest_receive_content ||
  '/wp-json/nuclear-engagement/v1/receive-content';

export const REST_NONCE = (window as any).nuclenAdminVars?.rest_nonce || '';
import { displayError } from '../utils/displayError';

export function nuclenAlertApiError(errMsg: string): void {
  if (errMsg.includes('Invalid API key')) {
    displayError('Your API key is invalid. Please go to the Setup page and enter a new one.');
  } else if (errMsg.includes('Invalid WP App Password')) {
    displayError('Your WP App Password is invalid. Please re-generate it on the Setup page.');
  } else if (errMsg.includes('Not enough credits')) {
    displayError('Not enough credits. Please top up your account or reduce the number of posts.');
  } else {
    displayError(`Error: ${errMsg}`);
  }
}

export async function nuclenStoreGenerationResults(workflow: string, results: any) {
  const payload = { workflow, results };
  const resp = await fetch(REST_ENDPOINT, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': REST_NONCE,
    },
    credentials: 'include',
    body: JSON.stringify(payload),
  });
  const data = await resp.json();
  return { ok: resp.ok, data };
}
