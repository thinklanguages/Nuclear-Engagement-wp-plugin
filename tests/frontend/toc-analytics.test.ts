import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { TocAnalytics } from '../../src/modules/toc/ts/toc-analytics';

// Type declarations
type DOMRectReadOnly = {
  readonly bottom: number;
  readonly height: number;
  readonly left: number;
  readonly right: number;
  readonly top: number;
  readonly width: number;
  readonly x: number;
  readonly y: number;
};

type IntersectionObserverEntry = {
  readonly boundingClientRect: DOMRectReadOnly;
  readonly intersectionRatio: number;
  readonly intersectionRect: DOMRectReadOnly;
  readonly isIntersecting: boolean;
  readonly rootBounds: DOMRectReadOnly | null;
  readonly target: Element;
  readonly time: number;
};

type IntersectionObserverCallback = (entries: IntersectionObserverEntry[], observer: IntersectionObserver) => void;

// Mock gtag
declare global {
  function gtag(...args: unknown[]): void;
}

// Mock IntersectionObserver
class MockIntersectionObserver {
  callback: IntersectionObserverCallback;
  options?: IntersectionObserverInit;

  constructor(callback: IntersectionObserverCallback, options?: IntersectionObserverInit) {
    this.callback = callback;
    this.options = options;
    MockIntersectionObserver.instances.push(this);
  }

  observe = vi.fn();
  unobserve = vi.fn();
  disconnect = vi.fn();

  static instances: MockIntersectionObserver[] = [];
  static resetInstances() {
    this.instances = [];
  }
}

global.IntersectionObserver = MockIntersectionObserver as any;

describe('TocAnalytics', () => {
  let tocWrapper: HTMLElement;
  let gtagSpy: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    vi.clearAllMocks();
    MockIntersectionObserver.resetInstances();
    
    // Setup gtag mock
    gtagSpy = vi.fn();
    global.gtag = gtagSpy;

    // Mock window.location.pathname
    Object.defineProperty(window, 'location', {
      value: { pathname: '/test-page' },
      writable: true
    });

    // Create DOM structure
    tocWrapper = document.createElement('div');
    tocWrapper.innerHTML = `
      <nav class="nuclen-toc">
        <a href="#section1">Section 1</a>
        <a href="#section2">Section 2</a>
        <a href="#section3">Section 3</a>
      </nav>
      <button class="nuclen-toc-toggle" aria-expanded="false">Toggle TOC</button>
    `;
    document.body.appendChild(tocWrapper);
  });

  afterEach(() => {
    document.body.innerHTML = '';
    vi.restoreAllMocks();
  });

  describe('constructor', () => {
    it('should initialize when TOC nav exists', () => {
      new TocAnalytics(tocWrapper);
      
      expect(MockIntersectionObserver.instances.length).toBe(1);
      expect(MockIntersectionObserver.instances[0].observe).toHaveBeenCalled();
    });

    it('should not initialize when TOC nav is missing', () => {
      const emptyWrapper = document.createElement('div');
      
      new TocAnalytics(emptyWrapper);
      
      expect(MockIntersectionObserver.instances.length).toBe(0);
    });

    it('should handle missing toggle button', () => {
      tocWrapper.innerHTML = '<nav class="nuclen-toc"></nav>';
      
      expect(() => new TocAnalytics(tocWrapper)).not.toThrow();
    });
  });

  describe('view tracking', () => {
    it('should track view when TOC is 80% visible', () => {
      new TocAnalytics(tocWrapper);
      
      const observer = MockIntersectionObserver.instances[0];
      const tocNav = tocWrapper.querySelector('.nuclen-toc') as HTMLElement;
      
      // Simulate intersection
      observer.callback([{
        isIntersecting: true,
        intersectionRatio: 0.8,
        target: tocNav,
        boundingClientRect: {} as DOMRectReadOnly,
        intersectionRect: {} as DOMRectReadOnly,
        rootBounds: {} as DOMRectReadOnly,
        time: 0
      }], observer as any);

      expect(gtagSpy).toHaveBeenCalledWith('event', 'nuclen_toc_view', {
        event_category: 'TOC',
        event_label: 'TOC fully visible',
        value: '/test-page'
      });
    });

    it('should not track view when TOC is less than 80% visible', () => {
      new TocAnalytics(tocWrapper);
      
      const observer = MockIntersectionObserver.instances[0];
      const tocNav = tocWrapper.querySelector('.nuclen-toc') as HTMLElement;
      
      // Simulate partial intersection
      observer.callback([{
        isIntersecting: true,
        intersectionRatio: 0.5,
        target: tocNav,
        boundingClientRect: {} as DOMRectReadOnly,
        intersectionRect: {} as DOMRectReadOnly,
        rootBounds: {} as DOMRectReadOnly,
        time: 0
      }], observer as any);

      expect(gtagSpy).not.toHaveBeenCalled();
    });

    it('should only track view once', () => {
      new TocAnalytics(tocWrapper);
      
      const observer = MockIntersectionObserver.instances[0];
      const tocNav = tocWrapper.querySelector('.nuclen-toc') as HTMLElement;
      
      // Simulate multiple intersections
      const entry = {
        isIntersecting: true,
        intersectionRatio: 0.8,
        target: tocNav,
        boundingClientRect: {} as DOMRectReadOnly,
        intersectionRect: {} as DOMRectReadOnly,
        rootBounds: {} as DOMRectReadOnly,
        time: 0
      };

      observer.callback([entry], observer as any);
      observer.callback([entry], observer as any);

      expect(gtagSpy).toHaveBeenCalledTimes(1);
    });

    it('should observe with correct threshold', () => {
      new TocAnalytics(tocWrapper);
      
      const observer = MockIntersectionObserver.instances[0];
      expect(observer.options?.threshold).toBe(0.8);
    });
  });

  describe('click tracking', () => {
    it('should track clicks on TOC links', () => {
      new TocAnalytics(tocWrapper);
      
      const link = tocWrapper.querySelector('a[href="#section1"]') as HTMLAnchorElement;
      link.click();

      expect(gtagSpy).toHaveBeenCalledWith('event', 'nuclen_toc_click', {
        event_category: 'TOC',
        event_label: 'Section 1',
        value: '#section1'
      });
    });

    it('should track all links', () => {
      new TocAnalytics(tocWrapper);
      
      const links = tocWrapper.querySelectorAll('a');
      links.forEach(link => link.click());

      expect(gtagSpy).toHaveBeenCalledTimes(3);
      expect(gtagSpy).toHaveBeenCalledWith('event', 'nuclen_toc_click', {
        event_category: 'TOC',
        event_label: 'Section 1',
        value: '#section1'
      });
      expect(gtagSpy).toHaveBeenCalledWith('event', 'nuclen_toc_click', {
        event_category: 'TOC',
        event_label: 'Section 2',
        value: '#section2'
      });
      expect(gtagSpy).toHaveBeenCalledWith('event', 'nuclen_toc_click', {
        event_category: 'TOC',
        event_label: 'Section 3',
        value: '#section3'
      });
    });

    it('should handle links without text content', () => {
      tocWrapper.innerHTML = `
        <nav class="nuclen-toc">
          <a href="#empty"></a>
        </nav>
      `;
      
      new TocAnalytics(tocWrapper);
      
      const link = tocWrapper.querySelector('a') as HTMLAnchorElement;
      link.click();

      expect(gtagSpy).toHaveBeenCalledWith('event', 'nuclen_toc_click', {
        event_category: 'TOC',
        event_label: '',
        value: '#empty'
      });
    });

    it('should handle links without href', () => {
      tocWrapper.innerHTML = `
        <nav class="nuclen-toc">
          <a>No href link</a>
        </nav>
      `;
      
      new TocAnalytics(tocWrapper);
      
      const link = tocWrapper.querySelector('a') as HTMLAnchorElement;
      link.click();

      expect(gtagSpy).toHaveBeenCalledWith('event', 'nuclen_toc_click', {
        event_category: 'TOC',
        event_label: 'No href link',
        value: ''
      });
    });
  });

  describe('toggle tracking', () => {
    it('should track toggle button clicks to show', () => {
      new TocAnalytics(tocWrapper);
      
      const toggleButton = tocWrapper.querySelector('.nuclen-toc-toggle') as HTMLButtonElement;
      toggleButton.click();

      expect(gtagSpy).toHaveBeenCalledWith('event', 'nuclen_toc_toggle', {
        event_category: 'TOC',
        event_label: 'show'
      });
    });

    it('should track toggle button clicks to hide', () => {
      new TocAnalytics(tocWrapper);
      
      const toggleButton = tocWrapper.querySelector('.nuclen-toc-toggle') as HTMLButtonElement;
      toggleButton.setAttribute('aria-expanded', 'true');
      toggleButton.click();

      expect(gtagSpy).toHaveBeenCalledWith('event', 'nuclen_toc_toggle', {
        event_category: 'TOC',
        event_label: 'hide'
      });
    });

    it('should not add toggle tracking when button is missing', () => {
      tocWrapper.innerHTML = '<nav class="nuclen-toc"></nav>';
      
      new TocAnalytics(tocWrapper);
      
      // Should not throw
      expect(gtagSpy).not.toHaveBeenCalled();
    });

    it('should handle multiple toggle clicks', () => {
      new TocAnalytics(tocWrapper);
      
      const toggleButton = tocWrapper.querySelector('.nuclen-toc-toggle') as HTMLButtonElement;
      
      // First click - show
      toggleButton.click();
      expect(gtagSpy).toHaveBeenLastCalledWith('event', 'nuclen_toc_toggle', {
        event_category: 'TOC',
        event_label: 'show'
      });

      // Update aria-expanded
      toggleButton.setAttribute('aria-expanded', 'true');
      
      // Second click - hide
      toggleButton.click();
      expect(gtagSpy).toHaveBeenLastCalledWith('event', 'nuclen_toc_toggle', {
        event_category: 'TOC',
        event_label: 'hide'
      });

      expect(gtagSpy).toHaveBeenCalledTimes(2);
    });
  });

  describe('edge cases', () => {
    it('should handle whitespace in link text', () => {
      tocWrapper.innerHTML = `
        <nav class="nuclen-toc">
          <a href="#section">  Section with spaces  </a>
        </nav>
      `;
      
      new TocAnalytics(tocWrapper);
      
      const link = tocWrapper.querySelector('a') as HTMLAnchorElement;
      link.click();

      expect(gtagSpy).toHaveBeenCalledWith('event', 'nuclen_toc_click', {
        event_category: 'TOC',
        event_label: 'Section with spaces',
        value: '#section'
      });
    });

    it('should handle nested elements in TOC', () => {
      tocWrapper.innerHTML = `
        <nav class="nuclen-toc">
          <ul>
            <li><a href="#nested">Nested Link</a></li>
          </ul>
        </nav>
      `;
      
      new TocAnalytics(tocWrapper);
      
      const link = tocWrapper.querySelector('a') as HTMLAnchorElement;
      link.click();

      expect(gtagSpy).toHaveBeenCalledWith('event', 'nuclen_toc_click', {
        event_category: 'TOC',
        event_label: 'Nested Link',
        value: '#nested'
      });
    });
  });
});