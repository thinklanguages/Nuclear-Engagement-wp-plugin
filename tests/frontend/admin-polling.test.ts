import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { NuclenPollAndPullUpdates } from '../../src/admin/ts/generation/polling';
import * as api from '../../src/admin/ts/generation/api';

afterEach(() => {
  vi.restoreAllMocks();
});

describe('NuclenPollAndPullUpdates', () => {
  beforeEach(() => {
	vi.useFakeTimers();
  });

  afterEach(() => {
	vi.useRealTimers();
  });

  it('polls until processed >= total', async () => {
	const fetchMock = vi
	  .spyOn(api, 'nuclenFetchUpdates')
	  .mockResolvedValueOnce({ success: true, data: { processed: 1, total: 2, workflow: 'test' } })
	  .mockResolvedValueOnce({ success: true, data: { processed: 2, total: 2, finalReport: { message: 'completed' }, workflow: 'test' } });

	const progress = vi.fn();
	const complete = vi.fn();

	NuclenPollAndPullUpdates({ intervalMs: 1000, generationId: 'g', onProgress: progress, onComplete: complete });

	await vi.runOnlyPendingTimersAsync();
	await Promise.resolve();
	expect(progress).toHaveBeenCalledWith(1, 2);
	expect(complete).not.toHaveBeenCalled();

	await vi.runOnlyPendingTimersAsync();
	await Promise.resolve();
	expect(progress).toHaveBeenCalledWith(2, 2);
	expect(complete).toHaveBeenCalledWith({
	  processed: 2,
	  total: 2,
	  successCount: 2,
	  failCount: undefined,
	  finalReport: { message: 'completed' },
	  results: undefined,
	  workflow: 'test',
	});

	expect(fetchMock).toHaveBeenCalledTimes(2);
  });

  it('calls onError when request fails', async () => {
	vi.spyOn(api, 'nuclenFetchUpdates').mockRejectedValue(new Error('boom'));
	const onError = vi.fn();

	NuclenPollAndPullUpdates({ intervalMs: 1000, generationId: 'g', onError });

	await vi.runOnlyPendingTimersAsync();
	await Promise.resolve();
	expect(onError).toHaveBeenCalledWith('Polling failed after 1 attempts: boom');
  });
});
