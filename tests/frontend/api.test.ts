import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { apiRequest } from '../../src/admin/ts/utils/api';

describe('apiRequest', () => {
  let fetchMock: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    fetchMock = vi.fn();
    global.fetch = fetchMock;
    // Mock window.location.origin
    Object.defineProperty(window, 'location', {
      value: { origin: 'http://localhost:3000' },
      writable: true
    });
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('should make successful request to allowed origin', async () => {
    const mockResponse = new Response('{"data": "test"}', {
      status: 200,
      headers: { 'Content-Type': 'application/json' }
    });
    fetchMock.mockResolvedValueOnce(mockResponse);

    const result = await apiRequest('https://app.nuclearengagement.com/api/test', {
      method: 'GET'
    });

    expect(fetchMock).toHaveBeenCalledWith('https://app.nuclearengagement.com/api/test', {
      method: 'GET'
    });
    expect(result).toBe(mockResponse);
  });

  it('should throw error for invalid origin', async () => {
    await expect(
      apiRequest('https://malicious-site.com/api/test', { method: 'GET' })
    ).rejects.toThrow('Invalid URL origin');

    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('should throw error for non-ok response', async () => {
    fetchMock.mockResolvedValueOnce(new Response('Error', {
      status: 404,
      statusText: 'Not Found'
    }));

    await expect(
      apiRequest('https://app.nuclearengagement.com/api/test', { method: 'GET' })
    ).rejects.toThrow('HTTP 404');
  });

  it('should throw error for network failure', async () => {
    fetchMock.mockRejectedValueOnce(new Error('Network failure'));

    await expect(
      apiRequest('https://app.nuclearengagement.com/api/test', { method: 'GET' })
    ).rejects.toThrow('Network failure');
  });

  it('should handle non-Error rejection', async () => {
    fetchMock.mockRejectedValueOnce('String error');

    await expect(
      apiRequest('https://app.nuclearengagement.com/api/test', { method: 'GET' })
    ).rejects.toThrow('Network error');
  });

  it('should handle relative URLs with current origin', async () => {
    const mockResponse = new Response('{"data": "test"}', { status: 200 });
    fetchMock.mockResolvedValueOnce(mockResponse);

    await expect(
      apiRequest('/api/local', { method: 'POST' })
    ).rejects.toThrow('Invalid URL origin');
  });

  it('should handle HTTP 500 errors', async () => {
    fetchMock.mockResolvedValueOnce(new Response('Server Error', {
      status: 500,
      statusText: 'Internal Server Error'
    }));

    await expect(
      apiRequest('https://app.nuclearengagement.com/api/test', { method: 'GET' })
    ).rejects.toThrow('HTTP 500');
  });

  it('should pass through request options correctly', async () => {
    const mockResponse = new Response('{"data": "test"}', { status: 200 });
    fetchMock.mockResolvedValueOnce(mockResponse);

    const options = {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer token123'
      },
      body: JSON.stringify({ test: 'data' })
    };

    await apiRequest('https://app.nuclearengagement.com/api/test', options);

    expect(fetchMock).toHaveBeenCalledWith(
      'https://app.nuclearengagement.com/api/test',
      options
    );
  });

  it('should handle errors without message property', async () => {
    const customError = { code: 'NETWORK_ERROR' };
    fetchMock.mockRejectedValueOnce(customError);

    await expect(
      apiRequest('https://app.nuclearengagement.com/api/test', { method: 'GET' })
    ).rejects.toThrow('Network error');
  });
});