// modules/toc/ts/toc-interactions.ts

/**
 * Handles TOC toggle buttons and scroll‑spy highlighting.
 */
export function initTocInteractions(): void {
  document.addEventListener('click', (e) => {
    const btn = (e.target as HTMLElement).closest('.nuclen-toc-toggle');
    if (!btn) {
      return;
    }
    const navId = (btn as HTMLElement).getAttribute('aria-controls');
    const nav = navId ? document.getElementById(navId) : null;
    const expanded = (btn as HTMLElement).getAttribute('aria-expanded') === 'true';
    (btn as HTMLElement).setAttribute('aria-expanded', expanded ? 'false' : 'true');
    if (nav) {
      nav.style.display = expanded ? 'none' : '';
    }
    // @ts-expect-error defined via wp_localize_script
    const l10n = window.nuclenTocL10n as { show: string; hide: string };
    (btn as HTMLElement).textContent = expanded ? l10n.show : l10n.hide;
  });

  document.addEventListener('click', (e) => {
    const link = (e.target as HTMLElement).closest('.nuclen-toc a');
    const wrapper = link ? (link as HTMLElement).closest('.nuclen-toc-sticky') : null;
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
