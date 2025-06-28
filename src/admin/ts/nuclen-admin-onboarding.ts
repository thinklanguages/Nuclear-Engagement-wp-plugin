// nuclen-admin-onboarding.ts
import { nuclenFetchWithRetry } from './nuclen-admin-generate';
import { displayError } from './utils/displayError';
import * as logger from './utils/logger';

declare const jQuery: any;

// 1) Declare the global shape of window.nePointerData.
declare global {
	interface Window {
		nePointerData?: NuclenPointerData;
	}
}

// 2) Describe the structure of the pointer data
export interface NuclenPointerData {
	pointers: NuclenPointer[];
	ajaxurl: string;
	nonce: string | undefined;
}

// 3) Each pointer has the properties your PHP code provides
export interface NuclenPointer {
	id: string;
	target: string;
	title: string;
	content: string;
	position: {
		edge: 'top' | 'bottom' | 'left' | 'right';
		align: 'top' | 'bottom' | 'left' | 'right' | 'center';
	};
}

// Wrap our logic in an IIFE
(function ($: any) {
	$(document).ready(() => {
		const pointerData = window.nePointerData;

		if (!pointerData || !pointerData.pointers || pointerData.pointers.length === 0) {
			return;
		}

		let currentIndex = 0;
		const pointers = pointerData.pointers;
		const ajaxurl = pointerData.ajaxurl;
		const nonce = pointerData.nonce;

		async function dismissPointer(id: string) {
			const form = new URLSearchParams();
			form.append('action', 'nuclen_dismiss_pointer');
			form.append('pointer', id);
			if (nonce) {
				form.append('nonce', nonce);
			}

			try {
				const result = await nuclenFetchWithRetry(ajaxurl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: form.toString(),
				});
				if (!result.ok) {
					logger.error('Failed to dismiss pointer:', result.error);
					displayError('Failed to dismiss pointer.');
				}
			} catch (err) {
				logger.error('Error dismissing pointer:', err);
				displayError('Network error while dismissing pointer.');
			}
		}

		function showNextPointer() {
			if (currentIndex >= pointers.length) {
				return;
			}

			const ptr = pointers[currentIndex];
			const $target = $(ptr.target);

			if (!$target.length) {
				currentIndex++;
				showNextPointer();
				return;
			}

			$target.pointer({
				content: `<h3>${ptr.title}</h3><p>${ptr.content}</p>`,
				position: ptr.position,
				close: async () => {
					await dismissPointer(ptr.id);
					currentIndex++;
					showNextPointer();
				},
			}).pointer('open');
		}

		showNextPointer();
	});
})(jQuery);
