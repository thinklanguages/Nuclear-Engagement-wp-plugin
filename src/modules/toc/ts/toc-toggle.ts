// modules/toc/ts/toc-toggle.ts

/**
 * Handles click events on TOC toggle buttons.
 */
export function initTocToggle(): void {
	document.addEventListener('click', (e) => {
		const btn = (e.target as HTMLElement).closest('.nuclen-toc-toggle');
		if (!btn) {
			return;
		}
		const navId = btn.getAttribute('aria-controls');
		const nav = navId ? document.getElementById(navId) : null;
		const expanded = btn.getAttribute('aria-expanded') === 'true';
		btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
		if (nav) {
			nav.style.display = expanded ? 'none' : '';
		}
		// @ts-expect-error defined via wp_localize_script
		const l10n = window.nuclenTocL10n as { show: string; hide: string };
		btn.textContent = expanded ? l10n.show : l10n.hide;
	});
}
