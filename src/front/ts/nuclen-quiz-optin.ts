
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
		const submitBtn = document.getElementById('nuclen-optin-submit') as HTMLButtonElement;
		const nameInput = document.getElementById('nuclen-optin-name') as HTMLInputElement;
		const emailInput = document.getElementById('nuclen-optin-email') as HTMLInputElement;
		
		const name = nameInput.value.trim();
		const email = emailInput.value.trim();
		
		// Clear previous error states
		nameInput.classList.remove('nuclen-error');
		emailInput.classList.remove('nuclen-error');
		
		// Validation
		if (!name) {
			nameInput.classList.add('nuclen-error');
			nameInput.focus();
			return;
		}
		
		if (!isValidEmail(email)) {
			emailInput.classList.add('nuclen-error');
			emailInput.focus();
			return;
		}
		
		// Disable button and show loading state
		submitBtn.disabled = true;
		const originalText = submitBtn.textContent;
		submitBtn.textContent = 'Submitting...';
		
		try {
			await storeOptinLocally(name, email, window.location.href, ctx);
			await submitToWebhook(name, email, ctx);
			onComplete();
		} catch (error) {
			
			// Show user-friendly error message
			const errorMsg = document.createElement('div');
			errorMsg.className = 'nuclen-error-message';
			errorMsg.textContent = 'Unable to submit. Please check your connection and try again.';
			submitBtn.parentElement?.appendChild(errorMsg);
			
			// Remove error message after 5 seconds
			setTimeout(() => errorMsg.remove(), 5000);
			
			// Re-enable button
			submitBtn.disabled = false;
			submitBtn.textContent = originalText || ctx.submitLabel;
		}
	});

	document.getElementById('nuclen-optin-skip')?.addEventListener('click', (e) => {
		e.preventDefault();
		onSkip();
	});
}

export function attachInlineOptinHandlers(ctx: OptinContext): void {
	document.getElementById('nuclen-optin-submit')?.addEventListener('click', async () => {
		const submitBtn = document.getElementById('nuclen-optin-submit') as HTMLButtonElement;
		const nameInput = document.getElementById('nuclen-optin-name') as HTMLInputElement;
		const emailInput = document.getElementById('nuclen-optin-email') as HTMLInputElement;
		
		const name = nameInput.value.trim();
		const email = emailInput.value.trim();
		
		// Clear previous error states
		nameInput.classList.remove('nuclen-error');
		emailInput.classList.remove('nuclen-error');
		
		// Validation
		if (!name) {
			nameInput.classList.add('nuclen-error');
			nameInput.focus();
			return;
		}
		
		if (!isValidEmail(email)) {
			emailInput.classList.add('nuclen-error');
			emailInput.focus();
			return;
		}
		
		// Disable button and show loading state
		submitBtn.disabled = true;
		const originalText = submitBtn.textContent;
		submitBtn.textContent = 'Submitting...';
		
		try {
			await storeOptinLocally(name, email, window.location.href, ctx);
			await submitToWebhook(name, email, ctx);
			
			// Show success message if configured
			if (window.NuclenOptinSuccessMessage) {
				const successMsg = document.createElement('div');
				successMsg.className = 'nuclen-success-message';
				successMsg.textContent = window.NuclenOptinSuccessMessage;
				submitBtn.parentElement?.appendChild(successMsg);
			}
		} catch (error) {
			
			// Show user-friendly error message
			const errorMsg = document.createElement('div');
			errorMsg.className = 'nuclen-error-message';
			errorMsg.textContent = 'Unable to submit. Please check your connection and try again.';
			submitBtn.parentElement?.appendChild(errorMsg);
			
			// Remove error message after 5 seconds
			setTimeout(() => errorMsg.remove(), 5000);
			
			// Re-enable button
			submitBtn.disabled = false;
			submitBtn.textContent = originalText || ctx.submitLabel;
		}
	});
}

