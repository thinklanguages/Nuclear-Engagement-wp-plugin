export function nuclenShowElement(el: HTMLElement | null): void {
	if (!el) return;
	el.classList.remove('nuclen-hidden');
	
	// Scroll to progress bar when showing step 2
	if (el.id === 'nuclen-step-2') {
		const progressBar = document.getElementById('nuclen-progress-bar');
		if (progressBar) {
			// Get the position of the progress bar and scroll to it with some offset
			const rect = progressBar.getBoundingClientRect();
			const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
			const targetPosition = rect.top + scrollTop - 32; // 32px offset for WordPress admin bar
			
			window.scrollTo({ 
				top: targetPosition, 
				behavior: 'smooth' 
			});
		} else {
			// Fallback to scrolling to top if progress bar not found
			window.scrollTo({ top: 0, behavior: 'smooth' });
		}
	}
}

export function nuclenHideElement(el: HTMLElement | null): void {
	if (!el) return;
	el.classList.add('nuclen-hidden');
}

export function nuclenUpdateProgressBarStep(stepEl: HTMLElement | null, state: string): void {
	if (!stepEl) return;
	stepEl.classList.remove(
		'ne-step-bar__step--todo',
		'ne-step-bar__step--current',
		'ne-step-bar__step--done',
		'ne-step-bar__step--failed'
	);
	stepEl.classList.add(`ne-step-bar__step--${state}`);
}

/**
 * Fetch remaining credits from the SaaS.
 */
interface CreditsResponse {
	success: boolean;
	message?: string;
	data: {
	remaining_credits?: number;
	message?: string;
	[key: string]: unknown;
	};
}

export async function nuclenCheckCreditsAjax(): Promise<number> {
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
	const result = await nuclenFetchWithRetry<CreditsResponse>(
		window.nuclenAjax.ajax_url,
		{
			method: 'POST',
			body: formData,
			credentials: 'same-origin',
		}
	);
	if (!result.ok) {
		throw new Error(result.error || `HTTP ${result.status}`);
	}
	const data = result.data as CreditsResponse;
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
	const summaryFormatRow = document.getElementById('nuclen-summary-format-row') as HTMLElement | null;
	const summaryParagraphRow = document.getElementById('nuclen-summary-paragraph-row') as HTMLElement | null;
	const summaryBulletRow = document.getElementById('nuclen-summary-bullet-row') as HTMLElement | null;
	const summaryFormatEl = document.getElementById('nuclen_format') as HTMLSelectElement | null;

	if (!generateTypeEl || !summaryFormatRow) {
		return;
	}
	
	if (generateTypeEl.value === 'summary') {
		summaryFormatRow.classList.remove('nuclen-hidden');
		if (summaryFormatEl && summaryParagraphRow && summaryBulletRow) {
			if (summaryFormatEl.value === 'paragraph') {
				summaryParagraphRow.classList.remove('nuclen-hidden');
				summaryBulletRow.classList.add('nuclen-hidden');
			} else {
				summaryParagraphRow.classList.add('nuclen-hidden');
				summaryBulletRow.classList.remove('nuclen-hidden');
			}
		}
	} else {
		summaryFormatRow.classList.add('nuclen-hidden');
		if (summaryParagraphRow) summaryParagraphRow.classList.add('nuclen-hidden');
		if (summaryBulletRow) summaryBulletRow.classList.add('nuclen-hidden');
	}
}

import { nuclenFetchWithRetry } from '../nuclen-admin-generate';
