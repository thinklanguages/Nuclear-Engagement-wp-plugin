// modules/toc/ts/toc-interactions.ts

/**
 * Composes TOC behaviours like toggle buttons and scroll‑spy highlighting.
 */
import { initTocToggle } from './toc-toggle';
import { initClickToClose } from './toc-click-close';
import { initScrollSpy } from './toc-scroll-spy';
import { TocAnalytics } from './toc-analytics';

export function initTocInteractions(): void {
	initTocToggle();
	initClickToClose();
	initScrollSpy();
	
	// Initialize analytics tracking for each TOC
	const tocWrappers = document.querySelectorAll('.nuclen-toc-wrapper');
	tocWrappers.forEach(wrapper => {
		new TocAnalytics(wrapper as HTMLElement);
	});
}

export { initTocToggle, initClickToClose, initScrollSpy };
