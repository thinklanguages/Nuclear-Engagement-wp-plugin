import { describe, it, expect, vi, afterEach } from 'vitest';
import { shuffle, isValidEmail, escapeHtml, storeOptinLocally, submitToWebhook } from '../../src/front/ts/nuclen-quiz-utils';
import * as logger from '../../src/front/ts/logger';
import type { OptinContext } from '../../src/front/ts/nuclen-quiz-types';

describe('shuffle', () => {
  it('shuffles deterministically', () => {
	const arr = [1, 2, 3];
	const rand = vi.spyOn(Math, 'random')
	  .mockReturnValueOnce(0.5) // j=1
	  .mockReturnValueOnce(0);  // j=0
	const res = shuffle(arr);
	expect(res).toEqual([3, 1, 2]);
	expect(res).not.toBe(arr);
	rand.mockRestore();
  });
});

describe('isValidEmail', () => {
  it('validates basic email format', () => {
	expect(isValidEmail('a@b.com')).toBe(true);
	expect(isValidEmail('invalid')).toBe(false);
  });
});

describe('escapeHtml', () => {
  it('escapes HTML characters', () => {
	const res = escapeHtml('<div>"O\'H&"</div>');
	expect(res).toBe('&lt;div&gt;&quot;O&#039;H&amp;&quot;&lt;/div&gt;');
  });
});

describe('storeOptinLocally', () => {
  const baseCtx: OptinContext = {
	position: 'with_results',
	mandatory: false,
	promptText: '',
	submitLabel: '',
	enabled: true,
	webhook: '',
	ajaxUrl: 'https://example.com',
	ajaxNonce: '123'
  };

  afterEach(() => {
	vi.restoreAllMocks();
	delete (global as any).fetch;
  });

  it('posts to ajax url', async () => {
	const fetchMock = vi.fn().mockResolvedValue({ ok: true });
	(global as any).fetch = fetchMock;
	const log = vi.spyOn(logger, 'error').mockImplementation(() => {});
	await storeOptinLocally('n', 'e', 'u', baseCtx);
	expect(fetchMock).toHaveBeenCalled();
	expect(log).not.toHaveBeenCalled();
  });

  it('logs when response not ok', async () => {
	const fetchMock = vi.fn().mockResolvedValue({ ok: false, status: 500 });
	(global as any).fetch = fetchMock;
	const log = vi.spyOn(logger, 'error').mockImplementation(() => {});
	await storeOptinLocally('n', 'e', 'u', baseCtx);
	expect(log).toHaveBeenCalledWith('[NE] Local opt-in failed', 500);
  });

  it('logs network error', async () => {
	const err = new Error('fail');
	const fetchMock = vi.fn().mockRejectedValue(err);
	(global as any).fetch = fetchMock;
	const log = vi.spyOn(logger, 'error').mockImplementation(() => {});
	await storeOptinLocally('n', 'e', 'u', baseCtx);
	expect(log).toHaveBeenCalledWith('[NE] Local opt-in network error', err);
  });

  it('skips when ajax info missing', async () => {
	const fetchMock = vi.fn();
	(global as any).fetch = fetchMock;
	const ctx = { ...baseCtx, ajaxUrl: '', ajaxNonce: '' };
	await storeOptinLocally('n', 'e', 'u', ctx);
	expect(fetchMock).not.toHaveBeenCalled();
  });
});

describe('submitToWebhook', () => {
  const baseCtx: OptinContext = {
	position: 'with_results',
	mandatory: false,
	promptText: '',
	submitLabel: '',
	enabled: true,
	webhook: 'https://example.com',
	ajaxUrl: '',
	ajaxNonce: ''
  };

  afterEach(() => {
	vi.restoreAllMocks();
	delete (global as any).fetch;
  });

  it('posts to webhook', async () => {
	const fetchMock = vi.fn().mockResolvedValue({ ok: true });
	(global as any).fetch = fetchMock;
	const log = vi.spyOn(logger, 'error').mockImplementation(() => {});
	await submitToWebhook('n', 'e', baseCtx);
	expect(fetchMock).toHaveBeenCalledWith('https://example.com', expect.objectContaining({ method: 'POST' }));
	expect(log).not.toHaveBeenCalled();
  });

  it('skips when webhook missing', async () => {
	const fetchMock = vi.fn();
	(global as any).fetch = fetchMock;
	await submitToWebhook('n', 'e', { ...baseCtx, webhook: '' });
	expect(fetchMock).not.toHaveBeenCalled();
  });

  it('throws on non ok response', async () => {
	const fetchMock = vi.fn().mockResolvedValue({ ok: false, status: 400 });
	(global as any).fetch = fetchMock;
	const log = vi.spyOn(logger, 'error').mockImplementation(() => {});
	await expect(submitToWebhook('n', 'e', baseCtx)).rejects.toBeTruthy();
	expect(log).toHaveBeenCalledWith('[NE] Webhook responded with', 400);
  });

  it('throws on fetch error', async () => {
	const err = new Error('net');
	const fetchMock = vi.fn().mockRejectedValue(err);
	(global as any).fetch = fetchMock;
	const log = vi.spyOn(logger, 'error').mockImplementation(() => {});
	await expect(submitToWebhook('n', 'e', baseCtx)).rejects.toBe(err);
	expect(log).toHaveBeenCalledWith('[NE] Webhook request error', err);
  });
});
