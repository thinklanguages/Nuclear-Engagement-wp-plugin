import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

let originalIO: typeof IntersectionObserver;
let originalMO: typeof MutationObserver;
const ioInstances: any[] = [];
let moInstance: any;

beforeEach(() => {
  vi.resetModules();
  ioInstances.length = 0;
  originalIO = global.IntersectionObserver;
  originalMO = global.MutationObserver;

  global.IntersectionObserver = vi.fn(function (this: any, cb: any, options: any) {
    this.callback = cb;
    this.options = options;
    this.observe = vi.fn();
    this.unobserve = vi.fn();
    this.disconnect = vi.fn();
    ioInstances.push(this);
  }) as unknown as typeof IntersectionObserver;

  global.MutationObserver = vi.fn(function (this: any, cb: any) {
    this.cb = cb;
    this.observe = vi.fn();
    this.disconnect = vi.fn();
    moInstance = this;
  }) as unknown as typeof MutationObserver;

  (global as any).gtag = vi.fn();
})

afterEach(() => {
  global.IntersectionObserver = originalIO;
  global.MutationObserver = originalMO;
  vi.restoreAllMocks();
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  delete (global as any).NuclenLazyLoadComponent;
});

describe('nuclen-front-lazy', () => {
  it('lazy loads component and calls init on intersection', async () => {
    await import('../../src/front/ts/nuclen-front-lazy');
    document.body.innerHTML = '<div id="lazy-el"></div>';
    const initFn = vi.fn();
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    (window as any).testInit = initFn;
    window.NuclenLazyLoadComponent!('lazy-el', 'testInit');

    expect(ioInstances).toHaveLength(1);
    const inst = ioInstances[0];
    expect(inst.options).toEqual({ rootMargin: '0px 0px -200px 0px', threshold: 0.1 });
    expect(inst.observe).toHaveBeenCalledWith(document.getElementById('lazy-el'));

    inst.callback([{ isIntersecting: true }]);
    expect(initFn).toHaveBeenCalled();
    expect(inst.disconnect).toHaveBeenCalled();
  });

  it('dispatches GA event when element fully visible', async () => {
    await import('../../src/front/ts/nuclen-front-lazy');
    document.body.innerHTML = '<div id="nuclen-quiz-container"></div><div id="nuclen-summary-container"></div>';
    moInstance.cb([], moInstance);

    expect(ioInstances).toHaveLength(2);
    const summaryIO = ioInstances[0];
    const summaryEl = document.getElementById('nuclen-summary-container');
    summaryIO.callback([{ isIntersecting: true, intersectionRatio: 1, target: summaryEl }], summaryIO);
    expect((global as any).gtag).toHaveBeenCalledWith('event', 'nuclen_summary_view');
    expect(summaryIO.unobserve).toHaveBeenCalledWith(summaryEl);
  });
});
