// modules/toc/ts/nuclen-toc-front.ts

/**
 * Public behaviour for the TOC shortcode.
 * Handles sticky sidebar, toggle buttons and scroll‑spy highlighting.
 */

import { initStickyToc } from './sticky-toc';
import { initTocInteractions } from './toc-interactions';

export { initStickyToc, initTocInteractions };

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
