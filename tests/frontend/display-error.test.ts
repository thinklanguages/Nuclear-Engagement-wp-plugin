import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { displayError } from '../../src/admin/ts/utils/displayError';
import * as logger from '../../src/admin/ts/utils/logger';

describe('displayError', () => {
  beforeEach(() => {
	vi.useFakeTimers();
  });

  afterEach(() => {
	vi.useRealTimers();
	vi.restoreAllMocks();
	document.body.innerHTML = '';
  });

  it('adds and removes toast and logs to console', async () => {
	const log = vi.spyOn(logger, 'error').mockImplementation(() => {});
	displayError('boom');
	const toast = document.querySelector('.nuclen-error-toast');
	expect(toast).not.toBeNull();
	expect(log).toHaveBeenCalledWith('[ERROR] UI Error | boom');
	vi.advanceTimersByTime(5000);
	await Promise.resolve();
	expect(document.querySelector('.nuclen-error-toast')).toBeNull();
  });
});
