// modules/toc/ts/sticky-toc.ts

/**
 * Sticky Table of Contents behaviour.
 */
function applyStickyStyles(
	wrapper: HTMLElement,
	toc: HTMLElement,
	placeholder: HTMLElement,
	left: number,
	width: number,
	height: number,
	headerOffset: number,
	rectHeight: number,
): void {
	wrapper.classList.add('nuclen-toc-stuck');
	wrapper.style.position = 'fixed';
	wrapper.style.top = `${headerOffset}px`;
	wrapper.style.left = `${left}px`;
	wrapper.style.width = `${width}px`;
	wrapper.style.maxHeight = `${height}px`;
	wrapper.style.overflow = 'visible';

	toc.style.maxHeight = `${height}px`;
	toc.style.overflow = 'auto';

	placeholder.style.display = 'block';
	placeholder.style.width = `${width}px`;
	placeholder.style.height = `${rectHeight}px`;
}

function clearStickyStyles(
	wrapper: HTMLElement,
	toc: HTMLElement,
	placeholder: HTMLElement,
): void {
	wrapper.classList.remove('nuclen-toc-stuck');
	wrapper.style.cssText = 'transition: top 0.25s ease-out;';

	toc.style.maxHeight = '';
	toc.style.overflow = '';

	placeholder.style.display = 'none';
}

export function initStickyToc(): void {
	const wrappers = document.querySelectorAll<HTMLElement>('.nuclen-toc-sticky');
	if (!wrappers.length) {
		return;
	}

	wrappers.forEach((wrapper) => {
		const toc = wrapper.querySelector<HTMLElement>('.nuclen-toc');
		if (!toc) {
			return;
		}

		const headerOffset = parseInt(wrapper.dataset.offsetY || '20', 10);
		const sideMargin = parseInt(wrapper.dataset.offsetX || '20', 10);
		const showContent = wrapper.dataset.showContent;
		if (showContent === 'false') {
			toc.style.display = 'none';
		}

		let rect = wrapper.getBoundingClientRect();
		const originalTop = rect.top + window.pageYOffset;
		let originalLeft = rect.left;
		let originalWidth = rect.width;
		let isStuck = false;
		let raf: number | null = null;

		const dataMax = parseInt(wrapper.dataset.maxWidth || '0', 10); // 0 ⇒ unlimited.

		const ph = document.createElement('div');
		ph.className = 'nuclen-toc-placeholder';
		ph.style.height = `${rect.height}px`;
		ph.style.width = `${rect.width}px`;
		wrapper.insertAdjacentElement('afterend', ph);
		ph.style.display = 'none';

		wrapper.style.transition = 'top 0.25s ease-out';

		const calcLeft = (w: number): number => {
			const container = document.querySelector<HTMLElement>(
				'.entry-content, .post, .content-area, .site-main, main',
			);
			const contLeft = container ? container.getBoundingClientRect().left : originalLeft;
			const min = sideMargin;
			const max = window.innerWidth - w - sideMargin;
			return Math.max(min, Math.min(contLeft, max));
		};
		const availHeight = (): number => window.innerHeight - headerOffset * 2;

		toc.style.position = 'relative';
		toc.style.width = '100%';

		const setStuck = (stick: boolean): void => {
			if (stick === isStuck) {
				return;
			}
			isStuck = stick;

			if (isStuck) {
				const h = availHeight();
				const w = dataMax > 0 ? Math.min(originalWidth, dataMax) : originalWidth;
				applyStickyStyles(
					wrapper,
					toc,
					ph,
					calcLeft(w),
					w,
					h,
					headerOffset,
					rect.height,
				);
			} else {
				clearStickyStyles(wrapper, toc, ph);
			}
		};

		const onScroll = (): void => {
			if (raf) {
				return;
			}
			raf = window.requestAnimationFrame(() => {
				raf = null;
				const shouldStick = window.pageYOffset + headerOffset >= originalTop;
				setStuck(shouldStick);
			});
		};

		// Debounce resize handler
		let resizeTimeout: number | null = null;
		const onResize = (): void => {
			if (resizeTimeout !== null) {
				clearTimeout(resizeTimeout);
			}
			resizeTimeout = window.setTimeout(() => {
				if (raf) {
					window.cancelAnimationFrame(raf);
				}
				raf = window.requestAnimationFrame(() => {
					raf = null;
					rect = wrapper.getBoundingClientRect();
					originalLeft = rect.left;
					originalWidth = rect.width;

					const w = dataMax > 0 ? Math.min(originalWidth, dataMax) : originalWidth;

					ph.style.width = `${w}px`;
					ph.style.height = `${rect.height}px`;

					if (isStuck) {
						const h = availHeight();
						applyStickyStyles(
							wrapper,
							toc,
							ph,
							calcLeft(w),
							w,
							h,
							headerOffset,
							rect.height,
						);
					}
				});
			}, 150); // 150ms debounce
		};

		window.addEventListener('scroll', onScroll, { passive: true });
		window.addEventListener('resize', onResize);
		onScroll(); // initial determination.
	});
}
