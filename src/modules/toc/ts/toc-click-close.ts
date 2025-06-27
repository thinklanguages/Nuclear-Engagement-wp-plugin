// modules/toc/ts/toc-click-close.ts

/**
 * Closes the open TOC when clicking a link or outside the sticky container.
 */
export function initClickToClose(): void {
	document.addEventListener('click', (e) => {
		const link = (e.target as HTMLElement).closest('.nuclen-toc a');
		const wrapper = link ? link.closest('.nuclen-toc-sticky') : null;
		if (!link || !wrapper) {
			return;
		}
		setTimeout(() => {
			const btn = wrapper!.querySelector<HTMLElement>(
				'.nuclen-toc-toggle[aria-expanded="true"]',
			);
			if (btn) {
				btn.click();
			}
		}, 120);
	});

	document.addEventListener('click', (e) => {
		const stuck = document.querySelector<HTMLElement>(
			'.nuclen-toc-sticky.nuclen-toc-stuck',
		);
		if (!stuck) {
			return;
		}
		const target = e.target as HTMLElement;
		if (!stuck.contains(target) && !target.closest('.nuclen-toc-toggle')) {
			const btn = stuck.querySelector<HTMLElement>(
				'.nuclen-toc-toggle[aria-expanded="true"]',
			);
			if (btn) {
				btn.click();
			}
		}
	});
}
