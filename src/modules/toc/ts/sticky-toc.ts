// modules/toc/ts/sticky-toc.ts

/**
 * Sticky Table of Contents behaviour.
 */
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
	let originalTop = rect.top + window.pageYOffset;
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

		wrapper.classList.add('nuclen-toc-stuck');
		wrapper.style.position = 'fixed';
		wrapper.style.top = `${headerOffset}px`;
		wrapper.style.left = `${calcLeft(w)}px`;
		wrapper.style.width = `${w}px`;
		wrapper.style.maxHeight = `${h}px`;
		wrapper.style.overflow = 'visible';

		toc.style.maxHeight = `${h}px`;
		toc.style.overflow = 'auto';

		ph.style.display = 'block';
		ph.style.width = `${w}px`;
		ph.style.height = `${rect.height}px`;
		} else {
		wrapper.classList.remove('nuclen-toc-stuck');
		wrapper.style.cssText = 'transition: top 0.25s ease-out;';

		toc.style.maxHeight = '';
		toc.style.overflow = '';

		ph.style.display = 'none';
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

	const onResize = (): void => {
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
			wrapper.style.left = `${calcLeft(w)}px`;
			wrapper.style.width = `${w}px`;
			wrapper.style.maxHeight = `${h}px`;
			toc.style.maxHeight = `${h}px`;
		}
		});
	};

	window.addEventListener('scroll', onScroll, { passive: true });
	window.addEventListener('resize', onResize);
	onScroll(); // initial determination.
	});
}
