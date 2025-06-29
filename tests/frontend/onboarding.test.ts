import { describe, it, beforeEach, afterEach, expect, vi } from 'vitest';

vi.mock('../../src/admin/ts/nuclen-admin-generate', () => ({
  nuclenFetchWithRetry: vi.fn().mockResolvedValue({ ok: true })
}));
vi.mock('../../src/admin/ts/utils/displayError', () => ({
  displayError: vi.fn()
}));
vi.mock('../../src/admin/ts/utils/logger', () => ({
  error: vi.fn()
}));

const { nuclenFetchWithRetry } = await import('../../src/admin/ts/nuclen-admin-generate');

beforeEach(() => {
  vi.resetModules();
  document.body.innerHTML = '<div id="target"></div>';
  (global as any).jQuery = (selector: any) => {
    if (typeof selector === 'function') {
      selector(jQuery);
      return;
    }
    if (typeof selector !== 'string') {
      return { 
        pointer: () => ({}),
        ready: (fn: Function) => fn(),
        length: 1
      };
    }
    const element = document.querySelector(selector);
    const api: any = {
      length: element ? 1 : 0,
      pointer(opts: any) {
        if (typeof opts === 'object' && element) {
          const wrapper = document.createElement('div');
          wrapper.className = 'wp-pointer';
          wrapper.innerHTML = `${opts.content}<a class="close" href="#">Dismiss</a>`;
          element.appendChild(wrapper);
          if (opts.close) {
            wrapper.querySelector('.close')?.addEventListener('click', opts.close);
          }
        }
        return api;
      },
      ready(fn: Function) {
        fn();
        return api;
      },
    };
    api.pointer['open'] = () => {};
    return api;
  };
  (global as any).$ = (global as any).jQuery;
  (window as any).nePointerData = {
    pointers: [{ id: 'p1', target: '#target', title: 'T', content: 'C', position: { edge: 'top', align: 'center' } }],
    ajaxurl: 'ajax.php',
    nonce: 'n'
  };
});

afterEach(() => {
  delete (global as any).jQuery;
  delete (global as any).$;
  delete (window as any).nePointerData;
  document.body.innerHTML = '';
});


describe('nuclen-admin-onboarding', () => {
  it('renders pointer from window data', async () => {
	await import('../../src/admin/ts/nuclen-admin-onboarding');
	document.dispatchEvent(new Event('DOMContentLoaded'));
	expect(document.querySelector('.wp-pointer')).not.toBeNull();
  });

  it('sends AJAX dismissal request', async () => {
	await import('../../src/admin/ts/nuclen-admin-onboarding');
	document.dispatchEvent(new Event('DOMContentLoaded'));
	const close = document.querySelector<HTMLAnchorElement>('.wp-pointer .close')!;
	close.click();
	await Promise.resolve();
	expect(nuclenFetchWithRetry).toHaveBeenCalled();
  });
});
