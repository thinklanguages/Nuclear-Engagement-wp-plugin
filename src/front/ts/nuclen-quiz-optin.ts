
// ─────────────────────────────────────────────────────────────
// File: src/front/ts/nuclen-quiz-optin.ts
// -----------------------------------------------------------------------------
import type { OptinContext } from './nuclen-quiz-types';
import { isValidEmail, storeOptinLocally, submitToWebhook } from './nuclen-quiz-utils';

export const buildOptinInlineHTML = (ctx: OptinContext): string => `
  <div id="nuclen-optin-container" class="nuclen-optin-with-results">
    <p class="nuclen-fg"><strong>${ctx.promptText}</strong></p>
    <label for="nuclen-optin-name"  class="nuclen-fg">Name</label>
    <input  type="text"  id="nuclen-optin-name">
    <label for="nuclen-optin-email" class="nuclen-fg">Email</label>
    <input  type="email" id="nuclen-optin-email" required>
    <button type="button" id="nuclen-optin-submit">${ctx.submitLabel}</button>
  </div>`;

export function mountOptinBeforeResults(
  container: HTMLElement,
  ctx: OptinContext,
  onComplete: () => void,
  onSkip: () => void
): void {
  container.innerHTML = `
    <div id="nuclen-optin-container">
      <p class="nuclen-fg"><strong>${ctx.promptText}</strong></p>
      <label for="nuclen-optin-name"  class="nuclen-fg">Name</label>
      <input  type="text"  id="nuclen-optin-name">
      <label for="nuclen-optin-email" class="nuclen-fg">Email *</label>
      <input  type="email" id="nuclen-optin-email" required>
      <div class="nuclen-optin-btn-row">
        <button type="button" id="nuclen-optin-submit">${ctx.submitLabel}</button>
      </div>
      ${
        ctx.mandatory
          ? ''
          : '<div class="nuclen-optin-skip"><a href="#" id="nuclen-optin-skip">Skip &amp; view results</a></div>'
      }
    </div>`;

  document.getElementById('nuclen-optin-submit')?.addEventListener('click', async () => {
    const name  = (document.getElementById('nuclen-optin-name')  as HTMLInputElement).value.trim();
    const email = (document.getElementById('nuclen-optin-email') as HTMLInputElement).value.trim();
    if (!isValidEmail(email)) return alert('Please enter a valid email');
    await storeOptinLocally(name, email, window.location.href, ctx);
    try {
      await submitToWebhook(name, email, ctx);
      onComplete();
    } catch {
      alert('Network error – please try again later.');
    }
  });

  document.getElementById('nuclen-optin-skip')?.addEventListener('click', (e) => {
    e.preventDefault();
    onSkip();
  });
}

export function attachInlineOptinHandlers(ctx: OptinContext): void {
  document.getElementById('nuclen-optin-submit')?.addEventListener('click', async () => {
    const name  = (document.getElementById('nuclen-optin-name')  as HTMLInputElement).value.trim();
    const email = (document.getElementById('nuclen-optin-email') as HTMLInputElement).value.trim();
    if (!isValidEmail(email)) return alert('Please enter a valid email');
    await storeOptinLocally(name, email, window.location.href, ctx);
    try {
      await submitToWebhook(name, email, ctx);
    } catch {
      alert('Unable to submit. Please try later.');
    }
  });
}

