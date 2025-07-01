export const REST_ENDPOINT =
	window.nuclenAdminVars?.rest_receive_content ||
	'/wp-json/nuclear-engagement/v1/receive-content';

export const REST_NONCE = window.nuclenAdminVars?.rest_nonce || '';
import { displayError } from '../utils/displayError';
import * as logger from '../utils/logger';

export function nuclenAlertApiError(errMsg: string): void {
	const cleanMsg = errMsg.replace(/<[^>]+>/g, '');
	if (cleanMsg.includes('Invalid API key')) {
		displayError('Your API key is invalid. Please go to the Setup page and enter a new one.');
	} else if (cleanMsg.includes('Invalid WP App Password')) {
		displayError('Your WP App Password is invalid. Please re-generate it on the Setup page.');
	} else if (cleanMsg.includes('Not enough credits')) {
		displayError('Not enough credits. Please top up your account or reduce the number of posts.');
	} else {
		displayError(`Error: ${cleanMsg}`);
	}
}

export async function nuclenStoreGenerationResults(workflow: string, results: unknown) {
	const payload = { workflow, results };
	let resp: Response;
	try {
		resp = await fetch(REST_ENDPOINT, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': REST_NONCE,
			},
			credentials: 'include',
			body: JSON.stringify(payload),
		});
	} catch (err) {
		logger.error('Fetch failed in nuclenStoreGenerationResults:', err);
		displayError('Network error');
		return { ok: false, data: { message: 'Network error' } };
	}
	let data: unknown = null;
	if (resp.ok) {
		try {
			data = await resp.json();
		} catch (err) {
			return { ok: false, data: { message: 'Invalid JSON' } };
		}
	}
	return { ok: resp.ok, data };
}
