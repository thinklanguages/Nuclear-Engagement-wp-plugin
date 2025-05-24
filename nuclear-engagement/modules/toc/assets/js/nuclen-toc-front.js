/* ---------- Nuclen TOC – public JS ----------
File: modules/toc/assets/js/front.js

Loaded only when toggle or scroll-spy is active.
Expects nuclenTocL10n.hide / .show strings injected by wp_localize_script.
------------------------------------------------- */

(() => {
	/* Collapse / expand */
	document.addEventListener('click', (e) => {
		const btn = e.target.closest('.nuclen-toc-toggle');
		if (!btn) return;

		const nav = document.getElementById(btn.getAttribute('aria-controls'));
		if (!nav) return;

		const expanded = btn.getAttribute('aria-expanded') === 'true';
		btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
		nav.style.display = expanded ? 'none' : '';
		btn.textContent   = expanded ? nuclenTocL10n.show : nuclenTocL10n.hide;
	});

	/* Scroll-spy highlight */
	const navs = document.querySelectorAll('.nuclen-toc[data-highlight="true"]');
	if (!navs.length || !('IntersectionObserver' in window)) return;

	const opts = { rootMargin: '0px 0px -60%', threshold: 0 };

	navs.forEach((nav) => {
		const map = new Map();

		nav.querySelectorAll('a[href^="#"]').forEach((a) => {
			const target = document.getElementById(a.getAttribute('href').slice(1));
			if (target) map.set(target, a);
		});

		const io = new IntersectionObserver((entries) => {
			entries.forEach((en) => {
				const link = map.get(en.target);
				if (!link) return;

				if (en.isIntersecting) {
					nav.querySelectorAll('a.is-active').forEach((el) => {
						el.classList.remove('is-active');
						el.removeAttribute('aria-current');
					});
					link.classList.add('is-active');
					link.setAttribute('aria-current', 'location');
				}
			});
		}, opts);

		map.forEach((_l, h) => io.observe(h));
	});
})();
