import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { nuclenFetchWithRetry, nuclenFetchUpdates, NuclenStartGeneration } from '../../src/admin/ts/generation/api';
import * as logger from '../../src/admin/ts/utils/logger';

// helper to build a mock Response-like object
function mockResponse(body: string, ok = true, status = 200) {
  return {
	ok,
	status,
	text: vi.fn().mockResolvedValue(body)
  } as unknown as Response;
}

afterEach(() => {
  vi.restoreAllMocks();
  delete (global as any).fetch;
  delete (window as any).nuclenAjax;
  delete (window as any).nuclenAdminVars;
});

describe('nuclenFetchWithRetry', () => {
  it('returns parsed data when successful', async () => {
	const fetchMock = vi.fn().mockResolvedValue(mockResponse('{"a":1}'));
	(global as any).fetch = fetchMock;
	const result = await nuclenFetchWithRetry<any>('u', { method: 'GET' }, 3);
	expect(result).toEqual({ ok: true, status: 200, data: { a: 1 } });
	expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it('retries on failure then succeeds', async () => {
	vi.useFakeTimers();
	const err = new Error('net');
	const fetchMock = vi
	  .fn()
	  .mockRejectedValueOnce(err)
	  .mockResolvedValueOnce(mockResponse(''));
	(global as any).fetch = fetchMock;
	vi.spyOn(logger, 'warn').mockImplementation(() => {});
	vi.spyOn(logger, 'error').mockImplementation(() => {});
	const promise = nuclenFetchWithRetry('u', {}, 3, 500);
	await vi.runAllTimersAsync();
	const result = await promise;
	expect(result.ok).toBe(true);
	expect(fetchMock).toHaveBeenCalledTimes(2);
	vi.useRealTimers();
  });
});

describe('nuclenFetchUpdates', () => {
  beforeEach(() => {
	(window as any).nuclenAjax = { ajax_url: 'a', fetch_action: 'f', nonce: 'n' };
  });

  it('returns response data when ok', async () => {
	const data = { success: true };
	const fetchMock = vi.fn().mockResolvedValue(mockResponse(JSON.stringify(data)));
	(global as any).fetch = fetchMock;
	const res = await nuclenFetchUpdates('id');
	expect(res).toEqual(data);
  });

  it('throws on error response', async () => {
	const fetchMock = vi.fn().mockResolvedValue(mockResponse('bad', false, 500));
	(global as any).fetch = fetchMock;
	await expect(nuclenFetchUpdates()).rejects.toThrow('bad');
  });
});

describe('NuclenStartGeneration', () => {
  beforeEach(() => {
	(window as any).nuclenAdminVars = { ajax_url: 'a' };
	(window as any).nuclenAjax = { nonce: 'n' };
  });

  it('throws message from response when generation fails', async () => {
	const body = { success: false, data: { message: 'nope' } };
	const fetchMock = vi.fn().mockResolvedValue(mockResponse(JSON.stringify(body)));
	(global as any).fetch = fetchMock;
	await expect(NuclenStartGeneration({})).rejects.toThrow('nope');
  });
});
