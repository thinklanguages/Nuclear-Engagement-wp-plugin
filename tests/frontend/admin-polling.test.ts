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

	// First poll happens immediately
	await Promise.resolve();
	expect(progress).toHaveBeenCalledWith(1, 2, expect.objectContaining({
		processed: 1,
		total: 2,
		workflow: 'test'
	}));
	expect(complete).not.toHaveBeenCalled();

	// Second poll - this should complete
	await vi.runOnlyPendingTimersAsync();
	await Promise.resolve();
	expect(progress).toHaveBeenCalledWith(2, 2, expect.objectContaining({
		processed: 2,
		total: 2,
		workflow: 'test',
		finalReport: { message: 'completed' }
	}));
	
	// onComplete should be called when processed >= total
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

  it('calls onError after 3 consecutive failures', async () => {
	vi.spyOn(api, 'nuclenFetchUpdates').mockRejectedValue(new Error('boom'));
	const onError = vi.fn();

	NuclenPollAndPullUpdates({ intervalMs: 1000, generationId: 'g', onError });

	// First attempt fails - polling starts immediately, so no timer needed for first attempt
	await Promise.resolve();
	expect(onError).not.toHaveBeenCalled(); // Retries on first error

	// Second attempt fails
	await vi.runOnlyPendingTimersAsync();
	await Promise.resolve();
	expect(onError).not.toHaveBeenCalled(); // Retries on second error

	// Third attempt fails - now it should call onError
	await vi.runOnlyPendingTimersAsync();
	await Promise.resolve();
	expect(onError).toHaveBeenCalledWith('polling-error:g');
  });
});
