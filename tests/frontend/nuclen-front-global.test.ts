import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

beforeEach(() => {
  vi.resetModules();
  document.body.innerHTML = `
    <div class="nuclen-toc-sticky" data-offset-y="20" data-offset-x="20">
      <nav class="nuclen-toc"></nav>
    </div>`;
  (window as any).nuclenTocL10n = { show: 'Show', hide: 'Hide' };
});

afterEach(() => {
  vi.restoreAllMocks();
  delete (window as any).nuclenTocL10n;
});

describe('nuclen-toc-front lazy boot', () => {
  it('registers DOMContentLoaded listener and attaches window handlers', async () => {
    Object.defineProperty(document, 'readyState', { value: 'loading', configurable: true });
    const docSpy = vi.spyOn(document, 'addEventListener');
    const winSpy = vi.spyOn(window, 'addEventListener');

    await import('../../src/modules/toc/ts/nuclen-toc-front');

    expect(docSpy).toHaveBeenCalledWith('DOMContentLoaded', expect.any(Function));
    const cb = docSpy.mock.calls.find(c => c[0] === 'DOMContentLoaded')?.[1] as () => void;
    expect(cb).toBeTypeOf('function');

    cb();

    expect(winSpy).toHaveBeenCalledWith('scroll', expect.any(Function), { passive: true });
    expect(winSpy).toHaveBeenCalledWith('resize', expect.any(Function));
  });
});
