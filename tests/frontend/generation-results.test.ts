import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import * as displayErrorModule from '../../src/admin/ts/utils/displayError';
import * as logger from '../../src/admin/ts/utils/logger';

vi.mock('../../src/admin/ts/utils/displayError');
vi.mock('../../src/admin/ts/utils/logger');

describe('generation/results', () => {
  let nuclenAlertApiError: any;
  let nuclenStoreGenerationResults: any;
  let REST_ENDPOINT: any;
  let REST_NONCE: any;
  
  beforeEach(async () => {
    vi.clearAllMocks();
    // Mock window.nuclenAdminVars
    (global.window as any).nuclenAdminVars = {
      rest_receive_content: '/wp-json/nuclear-engagement/v1/receive-content',
      rest_nonce: 'test-nonce-123'
    };
    
    // Need to re-import the module to get the updated constants
    vi.resetModules();
    const resultsModule = await import('../../src/admin/ts/generation/results');
    nuclenAlertApiError = resultsModule.nuclenAlertApiError;
    nuclenStoreGenerationResults = resultsModule.nuclenStoreGenerationResults;
    REST_ENDPOINT = resultsModule.REST_ENDPOINT;
    REST_NONCE = resultsModule.REST_NONCE;
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  describe('nuclenAlertApiError', () => {
    it('should display API key error for invalid API key message', () => {
      const displayErrorSpy = vi.spyOn(displayErrorModule, 'displayError');
      
      nuclenAlertApiError('Error: Invalid API key provided');
      
      expect(displayErrorSpy).toHaveBeenCalledWith(
        'Your API key is invalid. Please go to the Setup page and enter a new one.'
      );
    });

    it('should display WP App Password error', () => {
      const displayErrorSpy = vi.spyOn(displayErrorModule, 'displayError');
      
      nuclenAlertApiError('Invalid WP App Password detected');
      
      expect(displayErrorSpy).toHaveBeenCalledWith(
        'Your WP App Password is invalid. Please re-generate it on the Setup page.'
      );
    });

    it('should display credits error', () => {
      const displayErrorSpy = vi.spyOn(displayErrorModule, 'displayError');
      
      nuclenAlertApiError('Not enough credits available');
      
      expect(displayErrorSpy).toHaveBeenCalledWith(
        'Not enough credits. Please top up your account or reduce the number of posts.'
      );
    });

    it('should display generic error for other messages', () => {
      const displayErrorSpy = vi.spyOn(displayErrorModule, 'displayError');
      
      nuclenAlertApiError('Some other error occurred');
      
      expect(displayErrorSpy).toHaveBeenCalledWith('Error: Some other error occurred');
    });

    it('should strip HTML tags from error messages', () => {
      const displayErrorSpy = vi.spyOn(displayErrorModule, 'displayError');
      
      nuclenAlertApiError('<p>Error: <strong>Invalid API key</strong> provided</p>');
      
      expect(displayErrorSpy).toHaveBeenCalledWith(
        'Your API key is invalid. Please go to the Setup page and enter a new one.'
      );
    });

    it('should handle messages with multiple HTML tags', () => {
      const displayErrorSpy = vi.spyOn(displayErrorModule, 'displayError');
      
      nuclenAlertApiError('<div><span>Generic</span> <b>error</b> message</div>');
      
      expect(displayErrorSpy).toHaveBeenCalledWith('Error: Generic error message');
    });
  });

  describe('nuclenStoreGenerationResults', () => {
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
      fetchMock = vi.fn();
      global.fetch = fetchMock;
    });

    it('should successfully store generation results', async () => {
      const mockResponse = { success: true, data: { id: 123 } };
      fetchMock.mockResolvedValueOnce({
        ok: true,
        json: vi.fn().mockResolvedValueOnce(mockResponse)
      });

      const result = await nuclenStoreGenerationResults('quiz', { question: 'test' });

      expect(fetchMock).toHaveBeenCalledWith(REST_ENDPOINT, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': REST_NONCE
        },
        credentials: 'include',
        body: JSON.stringify({ workflow: 'quiz', results: { question: 'test' } })
      });
      expect(result).toEqual({ ok: true, data: mockResponse });
    });

    it('should handle network errors', async () => {
      const displayErrorSpy = vi.spyOn(displayErrorModule, 'displayError');
      const loggerErrorSpy = vi.spyOn(logger, 'error');
      
      fetchMock.mockRejectedValueOnce(new Error('Network failure'));

      const result = await nuclenStoreGenerationResults('summary', { content: 'test' });

      expect(loggerErrorSpy).toHaveBeenCalledWith(
        'Fetch failed in nuclenStoreGenerationResults:',
        expect.any(Error)
      );
      expect(displayErrorSpy).toHaveBeenCalledWith('Network error');
      expect(result).toEqual({ ok: false, data: { message: 'Network error' } });
    });

    it('should handle invalid JSON response', async () => {
      fetchMock.mockResolvedValueOnce({
        ok: true,
        json: vi.fn().mockRejectedValueOnce(new Error('Invalid JSON'))
      });

      const result = await nuclenStoreGenerationResults('toc', { items: [] });

      expect(result).toEqual({ ok: false, data: { message: 'Invalid JSON' } });
    });

    it('should handle non-ok response', async () => {
      const errorResponse = { error: 'Unauthorized' };
      fetchMock.mockResolvedValueOnce({
        ok: false,
        json: vi.fn().mockResolvedValueOnce(errorResponse)
      });

      const result = await nuclenStoreGenerationResults('quiz', { test: 'data' });

      expect(result).toEqual({ ok: false, data: null });
    });

    it('should use default values when nuclenAdminVars is not defined', async () => {
      // Remove nuclenAdminVars
      delete (global.window as any).nuclenAdminVars;
      
      // Re-import to get default values
      vi.resetModules();
      const resultsModule = await import('../../src/admin/ts/generation/results');
      const storeResults = resultsModule.nuclenStoreGenerationResults;

      const mockResponse = { success: true };
      fetchMock.mockResolvedValueOnce({
        ok: true,
        json: vi.fn().mockResolvedValueOnce(mockResponse)
      });

      await storeResults('quiz', {});

      expect(fetchMock).toHaveBeenCalledWith('/wp-json/nuclear-engagement/v1/receive-content', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': ''
        },
        credentials: 'include',
        body: JSON.stringify({ workflow: 'quiz', results: {} })
      });
    });

    it('should handle different workflow types', async () => {
      const workflows = ['quiz', 'summary', 'toc', 'custom'];
      
      for (const workflow of workflows) {
        fetchMock.mockResolvedValueOnce({
          ok: true,
          json: vi.fn().mockResolvedValueOnce({ success: true })
        });

        const result = await nuclenStoreGenerationResults(workflow, { data: workflow });
        
        expect(result.ok).toBe(true);
        expect(fetchMock).toHaveBeenLastCalledWith(
          expect.any(String),
          expect.objectContaining({
            body: JSON.stringify({ workflow, results: { data: workflow } })
          })
        );
      }
    });

    it('should handle complex result objects', async () => {
      const complexResults = {
        quiz: {
          questions: [
            { id: 1, text: 'Question 1', answers: ['A', 'B', 'C'] },
            { id: 2, text: 'Question 2', answers: ['X', 'Y', 'Z'] }
          ],
          metadata: {
            created: new Date().toISOString(),
            version: '1.0'
          }
        }
      };

      fetchMock.mockResolvedValueOnce({
        ok: true,
        json: vi.fn().mockResolvedValueOnce({ success: true })
      });

      await nuclenStoreGenerationResults('quiz', complexResults);

      expect(fetchMock).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({
          body: JSON.stringify({ workflow: 'quiz', results: complexResults })
        })
      );
    });
  });

  describe('REST constants', () => {
    it('should export REST_ENDPOINT with correct value', () => {
      expect(REST_ENDPOINT).toBe('/wp-json/nuclear-engagement/v1/receive-content');
    });

    it('should export REST_NONCE with correct value', () => {
      expect(REST_NONCE).toBe('test-nonce-123');
    });
  });
});