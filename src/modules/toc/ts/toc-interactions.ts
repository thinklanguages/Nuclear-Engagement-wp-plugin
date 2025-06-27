// modules/toc/ts/toc-interactions.ts

/**
 * Composes TOC behaviours like toggle buttons and scroll‑spy highlighting.
 */
import { initTocToggle } from './toc-toggle';
import { initClickToClose } from './toc-click-close';
import { initScrollSpy } from './toc-scroll-spy';

export function initTocInteractions(): void {
	initTocToggle();
	initClickToClose();
	initScrollSpy();
}

export { initTocToggle, initClickToClose, initScrollSpy };
