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
  (window as any).nePointerData = {
    pointers: [{ id: 'p1', target: '#target', title: 'T', content: 'C', position: { edge: 'top', align: 'center' } }],
    ajaxurl: 'ajax.php',
    nonce: 'n'
  };
});

afterEach(() => {
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
