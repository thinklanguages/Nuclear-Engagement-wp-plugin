import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { initTocToggle } from '../../src/modules/toc/ts/toc-toggle';
import { initClickToClose } from '../../src/modules/toc/ts/toc-click-close';
import { initScrollSpy } from '../../src/modules/toc/ts/toc-scroll-spy';

let originalIO: typeof IntersectionObserver;
let ioCallback: any;
let observed: Element[] = [];

beforeEach(() => {
  document.body.innerHTML = `
    <div class="nuclen-toc-sticky">
      <button class="nuclen-toc-toggle" aria-controls="tocnav" aria-expanded="false">Show</button>
      <nav id="tocnav" class="nuclen-toc" data-highlight="true">
        <a href="#h1">H1</a>
      </nav>
    </div>
    <h2 id="h1">Heading</h2>`;
  (window as any).nuclenTocL10n = { show: 'Show', hide: 'Hide' };
  originalIO = global.IntersectionObserver;
  observed = [];
  global.IntersectionObserver = vi.fn(function (this: any, cb: any) {
    ioCallback = cb;
    this.observe = (el: Element) => observed.push(el);
  }) as unknown as typeof IntersectionObserver;
  vi.useFakeTimers();
});

afterEach(() => {
  global.IntersectionObserver = originalIO;
  vi.useRealTimers();
  vi.restoreAllMocks();
  delete (window as any).nuclenTocL10n;
  document.body.innerHTML = '';
});

describe('toc interactions modules', () => {
  it('toggles nav visibility', () => {
    initTocToggle();
    const btn = document.querySelector('.nuclen-toc-toggle') as HTMLElement;
    const nav = document.getElementById('tocnav') as HTMLElement;
    btn.click();
    expect(btn.getAttribute('aria-expanded')).toBe('true');
    expect(nav.style.display).toBe('');
    expect(btn.textContent).toBe('Hide');
    btn.click();
    expect(btn.getAttribute('aria-expanded')).toBe('false');
    expect(nav.style.display).toBe('none');
    expect(btn.textContent).toBe('Show');
  });

  it('closes on link click and outside click', () => {
    initTocToggle();
    initClickToClose();
    const btn = document.querySelector('.nuclen-toc-toggle') as HTMLElement;
    const link = document.querySelector('.nuclen-toc a') as HTMLElement;
    btn.click();
    link.click();
    vi.runAllTimers();
    expect(btn.getAttribute('aria-expanded')).toBe('false');

    btn.click();
    document.body.click();
    expect(btn.getAttribute('aria-expanded')).toBe('false');
  });

  it('highlights links on intersection', () => {
    initScrollSpy();
    expect(observed).toContain(document.getElementById('h1'));
    const link = document.querySelector('.nuclen-toc a') as HTMLElement;
    ioCallback([{ target: document.getElementById('h1'), isIntersecting: true }]);
    expect(link.classList.contains('is-active')).toBe(true);
    expect(link.getAttribute('aria-current')).toBe('location');
  });
});
