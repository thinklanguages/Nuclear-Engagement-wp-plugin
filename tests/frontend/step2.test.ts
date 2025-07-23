import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { initStep2 } from '../../src/admin/ts/generate/step2';
import type { GeneratePageElements } from '../../src/admin/ts/generate/elements';
import * as generateApi from '../../src/admin/ts/nuclen-admin-generate';
import * as generatePageUtils from '../../src/admin/ts/generate/generate-page-utils';
import * as resultsModule from '../../src/admin/ts/generation/results';
import * as displayErrorModule from '../../src/admin/ts/utils/displayError';
import * as logger from '../../src/admin/ts/utils/logger';

vi.mock('../../src/admin/ts/nuclen-admin-generate');
vi.mock('../../src/admin/ts/generate/generate-page-utils');
vi.mock('../../src/admin/ts/generation/results');
vi.mock('../../src/admin/ts/utils/displayError');
vi.mock('../../src/admin/ts/utils/logger');

describe('step2', () => {
  let mockElements: GeneratePageElements;
  let formSubmitCallback: (event: Event) => void;

  beforeEach(() => {
    vi.clearAllMocks();
    
    // Mock window.nuclenAdminVars
    (global.window as any).nuclenAdminVars = {
      ajax_url: '/wp-admin/admin-ajax.php',
      security: 'test-nonce'
    };

    // Create mock form element
    const mockForm = {
      addEventListener: vi.fn((event: string, callback: (event: Event) => void) => {
        if (event === 'submit') {
          formSubmitCallback = callback;
        }
      })
    };

    // Create mock elements
    mockElements = {
      generateForm: mockForm as any,
      submitBtn: { disabled: false } as any,
      restartBtn: document.createElement('button'),
      updatesContent: { innerText: '' } as any,
      updatesSection: document.createElement('div'),
      step1: document.createElement('div'),
      getPostsBtn: document.createElement('button'),
      goBackBtn: document.createElement('button'),
      postsCountEl: null,
      creditsInfoEl: null,
      step2: document.createElement('div'),
      stepBar1: document.createElement('div'),
      stepBar2: document.createElement('div'),
    };
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  describe('initStep2', () => {
    it('should add submit event listener to form', () => {
      initStep2(mockElements);
      
      expect(mockElements.generateForm?.addEventListener).toHaveBeenCalledWith('submit', expect.any(Function));
    });

    it('should handle missing generateForm', () => {
      const elementsWithoutForm = { ...mockElements, generateForm: null };
      
      expect(() => initStep2(elementsWithoutForm)).not.toThrow();
    });

    it('should handle missing nuclenAdminVars', async () => {
      delete (window as any).nuclenAdminVars;
      const displayErrorSpy = vi.spyOn(displayErrorModule, 'displayError');
      
      initStep2(mockElements);
      
      const event = new Event('submit');
      event.preventDefault = vi.fn();
      await formSubmitCallback(event);
      
      expect(displayErrorSpy).toHaveBeenCalledWith('Error: WP Ajax config not found. Please check the plugin settings.');
    });

    it('should handle successful generation flow', async () => {
      const showElementSpy = vi.spyOn(generatePageUtils, 'nuclenShowElement');
      const hideElementSpy = vi.spyOn(generatePageUtils, 'nuclenHideElement');
      const updateProgressBarSpy = vi.spyOn(generatePageUtils, 'nuclenUpdateProgressBarStep');
      
      // Mock FormData
      global.FormData = vi.fn(() => ({
        entries: () => [['key', 'value']]
      })) as any;
      global.Object.fromEntries = vi.fn(() => ({ key: 'value' }));

      const mockStartResponse = {
        success: true,
        data: { generation_id: 'test-gen-123' }
      };

      vi.spyOn(generateApi, 'NuclenStartGeneration').mockResolvedValue(mockStartResponse);
      
      vi.spyOn(generateApi, 'NuclenPollAndPullUpdates').mockImplementation((options: any): any => {
        // Simulate immediate completion
        setTimeout(() => {
          options.onComplete({
            failCount: 0,
            finalReport: { message: 'Success' },
            results: { quiz: { data: 'test' } },
            workflow: 'quiz'
          });
        }, 0);
      });

      vi.spyOn(resultsModule, 'nuclenStoreGenerationResults').mockResolvedValue({
        ok: true,
        data: { success: true }
      });

      initStep2(mockElements);
      
      const event = new Event('submit');
      event.preventDefault = vi.fn();
      await formSubmitCallback(event);

      // Initial UI updates
      expect(updateProgressBarSpy).toHaveBeenCalledWith(mockElements.stepBar2, 'done');
      expect(showElementSpy).toHaveBeenCalledWith(mockElements.updatesSection);
      expect(hideElementSpy).toHaveBeenCalledWith(mockElements.step2);
      expect(mockElements.updatesContent!.innerText).toBe(`Processing posts... this can take a few minutes. Stay on this page to see progress updates in real time. Or else, you can safely leave this page - generation will continue in the background. You can track progress on the tasks page. The generated content will be available in the post editor and on the frontend when the process is complete.`);
      expect(mockElements.submitBtn!.disabled).toBe(true);

      // Wait for completion callback
      await new Promise(resolve => setTimeout(resolve, 10));

      // Check completion updates
      expect(mockElements.updatesContent!.innerText).toBe('All posts processed successfully! Your content has been saved.');
      expect(mockElements.submitBtn!.disabled).toBe(false);
      expect(showElementSpy).toHaveBeenCalledWith(mockElements.restartBtn);
    });

    it('should handle generation with failures', async () => {
      vi.spyOn(generatePageUtils, 'nuclenUpdateProgressBarStep');
      
      // Mock FormData
      global.FormData = vi.fn(() => ({
        entries: () => [['key', 'value']]
      })) as any;

      const mockStartResponse = {
        success: true,
        data: { generation_id: 'test-gen-123' }
      };

      vi.spyOn(generateApi, 'NuclenStartGeneration').mockResolvedValue(mockStartResponse);
      
      vi.spyOn(generateApi, 'NuclenPollAndPullUpdates').mockImplementation((options: any): any => {
        // Simulate completion with failures
        setTimeout(() => {
          options.onComplete({
            failCount: 2,
            finalReport: { message: '2 posts failed to process' },
            results: {},
            workflow: 'quiz'
          });
        }, 0);
      });

      initStep2(mockElements);
      
      const event = new Event('submit');
      event.preventDefault = vi.fn();
      await formSubmitCallback(event);

      // Wait for completion callback
      await new Promise(resolve => setTimeout(resolve, 10));

      expect(mockElements.updatesContent!.innerText).toBe('Some posts failed. 2 posts failed to process');
    });

    it('should handle API error in start generation', async () => {
      const alertApiErrorSpy = vi.spyOn(resultsModule, 'nuclenAlertApiError');
      
      // Mock FormData
      global.FormData = vi.fn(() => ({
        entries: () => [['key', 'value']]
      })) as any;

      vi.spyOn(generateApi, 'NuclenStartGeneration').mockRejectedValue(new Error('Invalid API key'));

      initStep2(mockElements);
      
      const event = new Event('submit');
      event.preventDefault = vi.fn();
      await formSubmitCallback(event);
      
      expect(alertApiErrorSpy).toHaveBeenCalledWith('Invalid API key');
      expect(mockElements.submitBtn!.disabled).toBe(false);
    });

    it('should handle polling updates during generation', async () => {
      // Mock FormData
      global.FormData = vi.fn(() => ({
        entries: () => [['key', 'value']]
      })) as any;

      const mockStartResponse = {
        success: true,
        generation_id: 'test-gen-123'
      };

      vi.spyOn(generateApi, 'NuclenStartGeneration').mockResolvedValue(mockStartResponse);
      
      let capturedCallbacks: any = {};
      vi.spyOn(generateApi, 'NuclenPollAndPullUpdates').mockImplementation((options: any): any => {
        capturedCallbacks = options;
      });

      initStep2(mockElements);
      
      const event = new Event('submit');
      event.preventDefault = vi.fn();
      await formSubmitCallback(event);

      // Simulate progress updates
      capturedCallbacks.onProgress(3, 10);
      expect(mockElements.updatesContent!.innerText).toBe('Processing: 3 of 10 posts completed (30%)');

      capturedCallbacks.onProgress(7, 10);
      expect(mockElements.updatesContent!.innerText).toBe('Processing: 7 of 10 posts completed (70%)');

      // Complete the process
      await capturedCallbacks.onComplete({
        failCount: 0,
        results: {},
        workflow: 'quiz'
      });

      expect(mockElements.updatesContent!.innerText).toContain('posts processed successfully');
    });

    it('should handle network error', async () => {
      const alertApiErrorSpy = vi.spyOn(resultsModule, 'nuclenAlertApiError');
      
      // Mock FormData
      global.FormData = vi.fn(() => ({
        entries: () => [['key', 'value']]
      })) as any;

      const mockStartResponse = {
        success: true,
        data: { generation_id: 'test-gen-123' }
      };

      vi.spyOn(generateApi, 'NuclenStartGeneration').mockResolvedValue(mockStartResponse);
      
      vi.spyOn(generateApi, 'NuclenPollAndPullUpdates').mockImplementation((options: any): any => {
        // Simulate error
        options.onError('Network error occurred');
      });

      initStep2(mockElements);
      
      const event = new Event('submit');
      event.preventDefault = vi.fn();
      await formSubmitCallback(event);
      
      expect(alertApiErrorSpy).toHaveBeenCalledWith('Network error occurred');
      expect(mockElements.updatesContent!.innerText).toBe('Error: Network error occurred');
      expect(mockElements.submitBtn!.disabled).toBe(false);
    });

    it('should disable/enable buttons during generation', async () => {
      // Mock FormData
      global.FormData = vi.fn(() => ({
        entries: () => [['key', 'value']]
      })) as any;

      const mockStartResponse = {
        success: true,
        data: { generation_id: 'test-gen-123' }
      };

      vi.spyOn(generateApi, 'NuclenStartGeneration').mockResolvedValue(mockStartResponse);
      vi.spyOn(generateApi, 'NuclenPollAndPullUpdates').mockImplementation((options: any): any => {
        setTimeout(() => {
          options.onComplete({
            failCount: 0,
            results: {},
            workflow: 'quiz'
          });
        }, 0);
      });

      initStep2(mockElements);
      
      const event = new Event('submit');
      event.preventDefault = vi.fn();
      
      expect(mockElements.submitBtn!.disabled).toBe(false);
      
      await formSubmitCallback(event);
      
      // Button should be disabled during generation
      expect(mockElements.submitBtn!.disabled).toBe(true);
      
      // Wait for completion
      await new Promise(resolve => setTimeout(resolve, 10));
      
      // Button should be re-enabled after completion
      expect(mockElements.submitBtn!.disabled).toBe(false);
    });

    it('should handle missing results in polling data', async () => {
      // Mock FormData
      global.FormData = vi.fn(() => ({
        entries: () => [['key', 'value']]
      })) as any;

      const mockStartResponse = {
        success: true,
        data: { generation_id: 'test-gen-123' }
      };

      vi.spyOn(generateApi, 'NuclenStartGeneration').mockResolvedValue(mockStartResponse);
      vi.spyOn(generateApi, 'NuclenPollAndPullUpdates').mockImplementation((options: any): any => {
        setTimeout(() => {
          options.onComplete({
            failCount: 0,
            // No results property
            workflow: 'quiz'
          });
        }, 0);
      });

      vi.spyOn(resultsModule, 'nuclenStoreGenerationResults').mockResolvedValue({
        ok: true,
        data: { success: true }
      });

      initStep2(mockElements);
      
      const event = new Event('submit');
      event.preventDefault = vi.fn();
      await formSubmitCallback(event);
      
      // Wait for completion
      await new Promise(resolve => setTimeout(resolve, 10));
      
      // Should complete successfully even without results
      expect(mockElements.updatesContent!.innerText).toBe('All posts processed successfully! Your content has been saved.');
    });

    it.skip('should log errors when storing results fails - obsolete test', async () => {
      const loggerErrorSpy = vi.spyOn(logger, 'error');
      
      // Mock FormData
      global.FormData = vi.fn(() => ({
        entries: () => [['key', 'value']]
      })) as any;

      const mockStartResponse = {
        success: true,
        data: { generation_id: 'test-gen-123' }
      };

      vi.spyOn(generateApi, 'NuclenStartGeneration').mockResolvedValue(mockStartResponse);
      vi.spyOn(generateApi, 'NuclenPollAndPullUpdates').mockImplementation((options: any): any => {
        setTimeout(() => {
          options.onComplete({
            failCount: 0,
            results: { quiz: { data: 'test' } },
            workflow: 'quiz'
          });
        }, 0);
      });

      vi.spyOn(resultsModule, 'nuclenStoreGenerationResults').mockResolvedValue({
        ok: false,
        data: { code: 'error', message: 'Storage failed' }
      });

      initStep2(mockElements);
      
      const event = new Event('submit');
      event.preventDefault = vi.fn();
      await formSubmitCallback(event);
      
      // Wait for completion
      await new Promise(resolve => setTimeout(resolve, 10));
      
      expect(loggerErrorSpy).toHaveBeenCalledWith(
        'Error storing bulk content in WP meta:',
        { code: 'error', message: 'Storage failed' }
      );
    });
  });
});