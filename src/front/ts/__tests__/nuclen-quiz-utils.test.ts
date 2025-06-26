import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import {
  shuffle,
  isValidEmail,
  escapeHtml,
  storeOptinLocally,
  submitToWebhook,
} from '../nuclen-quiz-utils';
import * as logger from '../logger';

const baseCtx = {
  position: 'with_results',
  mandatory: false,
  promptText: '',
  submitLabel: '',
  enabled: true,
  webhook: '',
  ajaxUrl: '',
  ajaxNonce: '',
};

beforeEach(() => {
  vi.restoreAllMocks();
});

describe('shuffle', () => {
  it('shuffles array without mutating original', () => {
    const arr = [1, 2, 3];
    const spy = vi.spyOn(Math, 'random');
    spy.mockReturnValueOnce(0.1).mockReturnValueOnce(0.9);
    const result = shuffle(arr);
    expect(result).toEqual([3, 2, 1]);
    expect(arr).toEqual([1, 2, 3]);
  });
});

describe('isValidEmail', () => {
  it('validates simple email addresses', () => {
    expect(isValidEmail('test@example.com')).toBe(true);
    expect(isValidEmail('bad-email')).toBe(false);
  });
});

describe('escapeHtml', () => {
  it('escapes special characters', () => {
    expect(escapeHtml("<div>&\"'")).toBe('&lt;div&gt;&amp;&quot;&#039;');
  });
});

describe('storeOptinLocally', () => {
  it('posts optin data and logs server error', async () => {
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({ ok: false, status: 500 });
    // @ts-ignore
    global.fetch = fetchMock;
    const errorSpy = vi.spyOn(logger, 'error');
    await storeOptinLocally(
      'name',
      'email',
      'https://example.com',
      {
        ...baseCtx,
        ajaxUrl: 'api',
        ajaxNonce: 'nonce',
      },
    );
    expect(fetchMock).toHaveBeenCalled();
    expect(errorSpy).toHaveBeenCalledWith('[NE] Local opt-in failed', 500);
  });

  it('logs network error', async () => {
    const fetchMock = vi.fn().mockRejectedValueOnce('oops');
    // @ts-ignore
    global.fetch = fetchMock;
    const errorSpy = vi.spyOn(logger, 'error');
    await storeOptinLocally(
      'name',
      'email',
      'https://example.com',
      {
        ...baseCtx,
        ajaxUrl: 'api',
        ajaxNonce: 'nonce',
      },
    );
    expect(errorSpy).toHaveBeenCalledWith('[NE] Local opt-in network error', 'oops');
  });
});

describe('submitToWebhook', () => {
  it('posts to webhook', async () => {
    const fetchMock = vi.fn().mockResolvedValueOnce({ ok: true });
    // @ts-ignore
    global.fetch = fetchMock;
    await submitToWebhook('n', 'e', { ...baseCtx, webhook: 'w' });
    expect(fetchMock).toHaveBeenCalledWith('w', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name: 'n', email: 'e' }),
    });
  });

  it('throws and logs on http error', async () => {
    const fetchMock = vi.fn().mockResolvedValueOnce({ ok: false, status: 400 });
    // @ts-ignore
    global.fetch = fetchMock;
    const errorSpy = vi.spyOn(logger, 'error');
    await expect(
      submitToWebhook('n', 'e', { ...baseCtx, webhook: 'w' }),
    ).rejects.toThrow('400');
    expect(errorSpy).toHaveBeenCalledWith('[NE] Webhook responded with', 400);
  });

  it('throws and logs on network error', async () => {
    const fetchMock = vi.fn().mockRejectedValueOnce('fail');
    // @ts-ignore
    global.fetch = fetchMock;
    const errorSpy = vi.spyOn(logger, 'error');
    await expect(
      submitToWebhook('n', 'e', { ...baseCtx, webhook: 'w' }),
    ).rejects.toBe('fail');
    expect(errorSpy).toHaveBeenCalledWith('[NE] Webhook request error', 'fail');
  });
});
