import { describe, it, expect, vi, afterEach } from 'vitest';
import { log, warn, error } from '../../src/front/ts/logger';

afterEach(() => {
  vi.restoreAllMocks();
});

describe('logger functions', () => {
  it('log function is disabled in production', () => {
	const spy = vi.spyOn(console, 'log').mockImplementation(() => {});
	log('a', 1);
	expect(spy).not.toHaveBeenCalled();
  });

  it('warn function is disabled in production', () => {
	const spy = vi.spyOn(console, 'warn').mockImplementation(() => {});
	warn('b', 2);
	expect(spy).not.toHaveBeenCalled();
  });

  it('forwards to console.error', () => {
	const spy = vi.spyOn(console, 'error').mockImplementation(() => {});
	error('c', 3);
	expect(spy).toHaveBeenCalledWith('c', 3);
  });
});
