/* ---------- Nuclen TOC – public JS ----------
File: modules/toc/assets/js/nuclen-toc-front.js
-------------------------------------------------
Handles:
• Sticky sidebar behaviour
• Toggle button (mobile)
• Scroll‑spy highlighting
------------------------------------------------- */

/* =============================================================
   Sticky TOC helper
   ============================================================= */
   const HEADER_OFFSET = 20;   /* vertical gap from top */
   const SIDE_MARGIN   = 20;   /* keep away from viewport edge */
   
   function initStickyToc() {
       const wrappers = document.querySelectorAll('.nuclen-toc-sticky');
       if (!wrappers.length) return;
   
       wrappers.forEach((wrapper) => {
           const toc = wrapper.querySelector('.nuclen-toc');
           if (!toc) return;
   
           /* Capture starting geometry */
           let rect          = wrapper.getBoundingClientRect();
           let originalTop   = rect.top + window.pageYOffset;
           let originalLeft  = rect.left;
           let originalWidth = rect.width;
           let isStuck       = false;
           let raf           = null;
   
           /* Read per-instance width limit set by PHP */
           const dataMax     = parseInt(wrapper.dataset.maxWidth || '0', 10); // 0 ⇒ unlimited
   
           /* Placeholder preserves layout */
           const ph = document.createElement('div');
           ph.className    = 'nuclen-toc-placeholder';
           ph.style.height = `${rect.height}px`;
           ph.style.width  = `${rect.width}px`;
           wrapper.insertAdjacentElement('afterend', ph);
           ph.style.display = 'none';
   
           /* Smooth snap */
           wrapper.style.transition = 'top 0.25s ease-out';
   
           /* Helpers */
           const calcLeft = (w) => {
               const container = document.querySelector('.entry-content, .post, .content-area, .site-main, main');
               const contLeft  = container ? container.getBoundingClientRect().left : originalLeft;
               const min = SIDE_MARGIN;
               const max = window.innerWidth - w - SIDE_MARGIN;
               return Math.max(min, Math.min(contLeft, max));
           };
           const availHeight = () => window.innerHeight - HEADER_OFFSET * 2;
   
           /* Ensure toc participates in layout (some themes set nav absolute) */
           toc.style.position = 'relative';
           toc.style.width    = '100%';
   
           /* Mode toggle */
           const setStuck = (stick) => {
               if (stick === isStuck) return;
               isStuck = stick;
   
               if (isStuck) {
                   const h = availHeight();
                   const w = dataMax > 0 ? Math.min(originalWidth, dataMax) : originalWidth;
   
                   wrapper.classList.add('nuclen-toc-stuck');
                   wrapper.style.position  = 'fixed';
                   wrapper.style.top       = `${HEADER_OFFSET}px`;
                   wrapper.style.left      = `${calcLeft(w)}px`;
                   wrapper.style.width     = `${w}px`;
                   wrapper.style.maxHeight = `${h}px`;
                   wrapper.style.overflow  = 'visible'; // wrapper just positions
   
                   /* Scroll inside nav */
                   toc.style.maxHeight = `${h}px`;
                   toc.style.overflow  = 'auto';
   
                   /* Update placeholder to keep column width */
                   ph.style.display = 'block';
                   ph.style.width   = `${w}px`;
                   ph.style.height  = `${rect.height}px`;
               } else {
                   wrapper.classList.remove('nuclen-toc-stuck');
                   wrapper.style.cssText = 'transition: top 0.25s ease-out;';
   
                   toc.style.maxHeight = '';
                   toc.style.overflow  = '';
   
                   ph.style.display = 'none';
               }
           };
   
           /* Event handlers */
           const onScroll = () => {
               if (raf) return;
               raf = requestAnimationFrame(() => {
                   raf = null;
                   const shouldStick = window.pageYOffset + HEADER_OFFSET >= originalTop;
                   setStuck(shouldStick);
               });
           };
   
           const onResize = () => {
               if (raf) cancelAnimationFrame(raf);
               raf = requestAnimationFrame(() => {
                   raf = null;
                   rect          = wrapper.getBoundingClientRect();
                   originalLeft  = rect.left;
                   originalWidth = rect.width;
   
                   const w = dataMax > 0 ? Math.min(originalWidth, dataMax) : originalWidth;
   
                   ph.style.width  = `${w}px`;
                   ph.style.height = `${rect.height}px`;
   
                   if (isStuck) {
                       const h = availHeight();
                       wrapper.style.left      = `${calcLeft(w)}px`;
                       wrapper.style.width     = `${w}px`;
                       wrapper.style.maxHeight = `${h}px`;
                       toc.style.maxHeight     = `${h}px`;
                   }
               });
           };
   
           window.addEventListener('scroll', onScroll, { passive: true });
           window.addEventListener('resize', onResize);
           onScroll(); // initial determination
       });
   }
      
   /* =============================================================
      Toggle button & scroll‑spy
      ============================================================= */
   function initTocInteractions() {
       /* Toggle btn */
       document.addEventListener('click', (e) => {
           const btn = e.target.closest('.nuclen-toc-toggle');
           if (!btn) return;
           const nav      = document.getElementById(btn.getAttribute('aria-controls'));
           const expanded = btn.getAttribute('aria-expanded') === 'true';
           btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
           if (nav) nav.style.display = expanded ? 'none' : '';
           btn.textContent = expanded ? nuclenTocL10n.show : nuclenTocL10n.hide;
       });
   
       /* Close on link click (mobile) */
       document.addEventListener('click', (e) => {
           const link    = e.target.closest('.nuclen-toc a');
           const wrapper = link ? link.closest('.nuclen-toc-sticky') : null;
           if (!link || !wrapper) return;
           setTimeout(() => {
               const btn = wrapper.querySelector('.nuclen-toc-toggle[aria-expanded="true"]');
               if (btn) btn.click();
           }, 120);
       });
   
       /* Click outside closes */
       document.addEventListener('click', (e) => {
           const stuck = document.querySelector('.nuclen-toc-sticky.nuclen-toc-stuck');
           if (!stuck) return;
           if (!stuck.contains(e.target) && !e.target.closest('.nuclen-toc-toggle')) {
               const btn = stuck.querySelector('.nuclen-toc-toggle[aria-expanded="true"]');
               if (btn) btn.click();
           }
       });
   
       /* Scroll‑spy */
       const navs = document.querySelectorAll('.nuclen-toc[data-highlight="true"]');
       if (!navs.length || !('IntersectionObserver' in window)) return;
       const ioOpts = { rootMargin: '0px 0px -60%', threshold: 0 };
   
       navs.forEach((nav) => {
           const map = new Map();
           nav.querySelectorAll('a[href^="#"]').forEach((a) => {
               const id = a.getAttribute('href').slice(1);
               const tgt = id && document.getElementById(id);
               if (tgt) map.set(tgt, a);
           });
           if (!map.size) return;
   
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
           }, ioOpts);
           map.forEach((_l, tgt) => io.observe(tgt));
       });
   }
   
   /* =============================================================
      Boot
      ============================================================= */
   document.addEventListener('DOMContentLoaded', () => {
       initStickyToc();
       initTocInteractions();
   });
   