// nuclen-admin-onboarding.ts
import { nuclenFetchWithRetry } from './nuclen-admin-generate';
import { displayError } from './utils/displayError';
import * as logger from './utils/logger';

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
(function() {
	// Wait for DOM ready
	document.addEventListener('DOMContentLoaded', () => {
	const pointerData = window.nePointerData;

	// Check that pointerData exists and has pointers
	if (!pointerData || !pointerData.pointers || pointerData.pointers.length === 0) {
		return;
	}

	let currentIndex = 0;
	const pointers = pointerData.pointers;
	const ajaxurl = pointerData.ajaxurl;
	const nonce = pointerData.nonce; // We'll send this in our AJAX

	function nuclenShowNextPointer() {
		if (currentIndex >= pointers.length) {
		return;
		}

		const ptr = pointers[currentIndex];
const target = document.querySelector(ptr.target) as HTMLElement | null;

		if (!target) {
		currentIndex++;
		nuclenShowNextPointer();
		return;
		}

		const wrapper = document.createElement('div');
		wrapper.className = `wp-pointer pointer-${ptr.position.edge}`;
		wrapper.style.position = 'absolute';
		wrapper.innerHTML = `
		<div class="wp-pointer-content">
			<h3>${ptr.title}</h3>
			<p>${ptr.content}</p>
			<a class="close" href="#">Dismiss</a>
		</div>
		`;
		document.body.appendChild(wrapper);

		const rect = target.getBoundingClientRect();
		let top = window.scrollY + rect.top;
		let left = window.scrollX + rect.left;

		switch (ptr.position.edge) {
		case 'top':
			top -= wrapper.offsetHeight;
			break;
		case 'bottom':
			top += rect.height;
			break;
		case 'left':
			left -= wrapper.offsetWidth;
			break;
		case 'right':
			left += rect.width;
			break;
		}

		if (ptr.position.align === 'center') {
		if (ptr.position.edge === 'top' || ptr.position.edge === 'bottom') {
			left += (rect.width - wrapper.offsetWidth) / 2;
		} else {
			top += (rect.height - wrapper.offsetHeight) / 2;
		}
		} else if (ptr.position.align === 'right' || ptr.position.align === 'bottom') {
		if (ptr.position.edge === 'top' || ptr.position.edge === 'bottom') {
			left += rect.width - wrapper.offsetWidth;
		} else {
			top += rect.height - wrapper.offsetHeight;
		}
		}

		wrapper.style.top = `${Math.max(top, 0)}px`;
		wrapper.style.left = `${Math.max(left, 0)}px`;

const close = wrapper.querySelector('.close') as HTMLAnchorElement | null;
if ( close ) {
close.addEventListener('click', async (e) => {
		e.preventDefault();

		const form = new URLSearchParams();
		form.append('action', 'nuclen_dismiss_pointer');
		form.append('pointer', ptr.id);
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

		wrapper.remove();
		currentIndex++;
nuclenShowNextPointer();
});
}

// End nuclenShowNextPointer()
}

// Start the sequence
nuclenShowNextPointer();
});
})();
