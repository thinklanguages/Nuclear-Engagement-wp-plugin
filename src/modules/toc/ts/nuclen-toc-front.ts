// modules/toc/ts/nuclen-toc-front.ts

/**
 * Public behaviour for the TOC shortcode.
 * Handles sticky sidebar, toggle buttons and scroll‑spy highlighting.
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
    // @ts-ignore defined via wp_localize_script
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

// Boot
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    initStickyToc();
    initTocInteractions();
  });
} else {
  initStickyToc();
  initTocInteractions();
}
