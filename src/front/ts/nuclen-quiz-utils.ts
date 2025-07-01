// ─────────────────────────────────────────────────────────────
// File: src/front/ts/nuclen-quiz-utils.ts
// -----------------------------------------------------------------------------
import type { OptinContext } from './nuclen-quiz-types';
import * as logger from './logger';

export function shuffle<T>(arr: T[]): T[] {
	const a = [...arr];
	for (let i = a.length - 1; i > 0; i--) {
		const j = Math.floor(Math.random() * (i + 1));
		[a[i], a[j]] = [a[j], a[i]];
	}
	return a;
}

export const isValidEmail = (email: string): boolean => /.+@.+\..+/.test(email);

export const escapeHtml = (str: string): string =>
	str.replace(/[&<>"']/g, (c) =>
		({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c] || c),
	);

export const storeOptinLocally = async (
	name: string,
	email: string,
	url: string,
	ctx: OptinContext
): Promise<void> => {
	if (!ctx.ajaxUrl || !ctx.ajaxNonce) return;
	try {
		const res = await fetch(ctx.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams({
				action: 'nuclen_save_optin',
				nonce: ctx.ajaxNonce,
				name,
				email,
				url,
			}),
		});
		if (!res.ok) {
			logger.error('[NE] Local opt-in failed', res.status);
		}
	} catch (err) {
		logger.error('[NE] Local opt-in network error', err);
	}
};

export const submitToWebhook = async (
	name: string,
	email: string,
	ctx: OptinContext
): Promise<void> => {
	if (!ctx.webhook) return;
	try {
		const res = await fetch(ctx.webhook, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ name, email }),
		});
		if (!res.ok) {
			logger.error('[NE] Webhook responded with', res.status);
			throw new Error(String(res.status));
		}
	} catch (err) {
		logger.error('[NE] Webhook request error', err);
		throw err;
	}
};
