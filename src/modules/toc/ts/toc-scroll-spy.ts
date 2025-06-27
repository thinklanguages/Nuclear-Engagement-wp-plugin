// modules/toc/ts/toc-scroll-spy.ts

/**
 * Highlights TOC links based on scroll position.
 */
export function initScrollSpy(): void {
	const navs = document.querySelectorAll<HTMLElement>(
		'.nuclen-toc[data-highlight="true"]',
	);
	if (!navs.length || !('IntersectionObserver' in window)) {
		return;
	}
	const ioOpts: IntersectionObserverInit = { rootMargin: '0px 0px -60%', threshold: 0 };

	navs.forEach((nav) => {
		const map = new Map<Element, HTMLAnchorElement>();
		nav.querySelectorAll<HTMLAnchorElement>('a[href^="#"]').forEach((a) => {
			const id = a.getAttribute('href')!.slice(1);
			const tgt = id && document.getElementById(id);
			if (tgt) map.set(tgt, a);
		});
		if (!map.size) {
			return;
		}

		const io = new IntersectionObserver((entries) => {
			entries.forEach((en) => {
				const link = map.get(en.target);
				if (!link) {
					return;
				}
				if (en.isIntersecting) {
					nav.querySelectorAll<HTMLAnchorElement>('a.is-active').forEach((el) => {
						el.classList.remove('is-active');
						el.removeAttribute('aria-current');
					});
					link.classList.add('is-active');
					link.setAttribute('aria-current', 'location');
				}
			});
		}, ioOpts);
		map.forEach((_l, tgt) => io.observe(tgt));
	});
}
