// ─────────────────────────────────────────────────────────────
// File: src/front/ts/nuclen-quiz-utils.ts
// -----------------------------------------------------------------------------
import type { OptinContext } from './nuclen-quiz-types';

export function shuffle<T>(arr: T[]): T[] {
  const a = [...arr];
  for (let i = a.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [a[i], a[j]] = [a[j], a[i]];
  }
  return a;
}

export const isValidEmail = (email: string): boolean => /.+@.+\..+/.test(email);

export const storeOptinLocally = async (
  name: string,
  email: string,
  url: string,
  ctx: OptinContext
): Promise<void> => {
  if (!ctx.ajaxUrl || !ctx.ajaxNonce) return;
  try {
    await fetch(ctx.ajaxUrl, {
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
  } catch {
    /* swallow error */
  }
};

export const submitToWebhook = async (
  name: string,
  email: string,
  ctx: OptinContext
): Promise<void> => {
  if (!ctx.webhook) return;
  await fetch(ctx.webhook, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name, email }),
  });
};