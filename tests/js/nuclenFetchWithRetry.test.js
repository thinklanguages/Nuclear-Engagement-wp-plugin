import test from 'node:test';
import assert from 'node:assert/strict';
import { nuclenFetchWithRetry } from '../../tests/jsbuild/api.js';

// Helper to stub global fetch
function mockFetch(impl) {
  global.fetch = impl;
}

test('returns JSON on success', async () => {
  mockFetch(async () => ({
    ok: true,
    status: 200,
    json: async () => ({ success: true }),
    text: async () => ''
  }));
  const res = await nuclenFetchWithRetry('u', {});
  assert.equal(res.ok, true);
  assert.deepEqual(res.data, { success: true });
});

test('parses error text on HTTP error', async () => {
  mockFetch(async () => ({
    ok: false,
    status: 400,
    text: async () => 'Bad request'
  }));
  const res = await nuclenFetchWithRetry('u', {});
  assert.equal(res.ok, false);
  assert.equal(res.status, 400);
  assert.equal(res.error, 'Bad request');
});

test('retries on network error', async () => {
  let calls = 0;
  mockFetch(async () => {
    calls++;
    if (calls < 3) {
      throw new Error('net');
    }
    return { ok: true, status: 200, json: async () => ({ ok: true }), text: async () => '' };
  });
  const res = await nuclenFetchWithRetry('u', {}, 2);
  assert.equal(calls, 3);
  assert.equal(res.ok, true);
});
