import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { initGoBack, initRestart, initSummaryToggle } from '../../src/admin/ts/generate/navigation';
import * as generatePageUtils from '../../src/admin/ts/generate/generate-page-utils';
import type { GeneratePageElements } from '../../src/admin/ts/generate/elements';

vi.mock('../../src/admin/ts/generate/generate-page-utils');

describe('navigation', () => {
  let mockElements: GeneratePageElements;

  beforeEach(() => {
    vi.clearAllMocks();
    
    // Mock all the utility functions
    (generatePageUtils as any).nuclenShowElement = vi.fn();
    (generatePageUtils as any).nuclenHideElement = vi.fn();
    (generatePageUtils as any).nuclenUpdateProgressBarStep = vi.fn();
    (generatePageUtils as any).nuclenToggleSummaryFields = vi.fn();
    
    // Create proper HTML elements with classList
    const createMockButton = () => {
      const btn = document.createElement('button');
      btn.addEventListener = vi.fn();
      return btn;
    };
    
    mockElements = {
      goBackBtn: createMockButton() as any,
      restartBtn: createMockButton() as any,
      step1: document.createElement('div'),
      step2: document.createElement('div'),
      postsCountEl: { innerText: 'test' } as any,
      creditsInfoEl: { textContent: 'test' } as any,
      stepBar1: document.createElement('div'),
      stepBar2: document.createElement('div'),
      updatesSection: document.createElement('div'),
      updatesContent: document.createElement('div'),
      getPostsBtn: document.createElement('button'),
      generateForm: document.createElement('form'),
      submitBtn: document.createElement('button'),
    };
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  describe('initGoBack', () => {
    it('should add click event listener to goBackBtn', () => {
      initGoBack(mockElements);
      
      expect(mockElements.goBackBtn?.addEventListener).toHaveBeenCalledWith('click', expect.any(Function));
    });

    it('should handle goBack click correctly', () => {
      const hideElementSpy = vi.spyOn(generatePageUtils, 'nuclenHideElement');
      const showElementSpy = vi.spyOn(generatePageUtils, 'nuclenShowElement');
      const updateProgressBarSpy = vi.spyOn(generatePageUtils, 'nuclenUpdateProgressBarStep');

      initGoBack(mockElements);
      
      // Get the callback and execute it
      const callback = (mockElements.goBackBtn?.addEventListener as any).mock.calls[0][1];
      callback();

      expect(hideElementSpy).toHaveBeenCalledWith(mockElements.step2);
      expect(showElementSpy).toHaveBeenCalledWith(mockElements.step1);
      expect(mockElements.postsCountEl!.innerText).toBe('');
      expect(mockElements.creditsInfoEl!.textContent).toBe('');
      expect(updateProgressBarSpy).toHaveBeenCalledWith(mockElements.stepBar1, 'current');
      expect(updateProgressBarSpy).toHaveBeenCalledWith(mockElements.stepBar2, 'todo');
    });

    it('should handle missing elements gracefully', () => {
      const partialElements: GeneratePageElements = {
        ...mockElements,
        postsCountEl: null,
        creditsInfoEl: null,
        goBackBtn: { addEventListener: vi.fn() } as any
      };

      initGoBack(partialElements);
      
      const callback = (partialElements.goBackBtn?.addEventListener as any).mock.calls[0][1];
      
      // Should not throw
      expect(() => callback()).not.toThrow();
    });

    it('should not throw if goBackBtn is null', () => {
      const elementsWithoutBtn: GeneratePageElements = {
        ...mockElements,
        goBackBtn: null
      };

      expect(() => initGoBack(elementsWithoutBtn)).not.toThrow();
    });
  });

  describe('initRestart', () => {
    it('should add click event listener to restartBtn', () => {
      initRestart(mockElements);
      
      expect(mockElements.restartBtn?.addEventListener).toHaveBeenCalledWith('click', expect.any(Function));
    });

    it('should handle restart click correctly', () => {
      const hideElementSpy = vi.spyOn(generatePageUtils, 'nuclenHideElement');
      const showElementSpy = vi.spyOn(generatePageUtils, 'nuclenShowElement');
      const updateProgressBarSpy = vi.spyOn(generatePageUtils, 'nuclenUpdateProgressBarStep');

      initRestart(mockElements);
      
      // Get the callback and execute it
      const callback = (mockElements.restartBtn?.addEventListener as any).mock.calls[0][1];
      callback();

      expect(hideElementSpy).toHaveBeenCalledWith(mockElements.updatesSection);
      expect(hideElementSpy).toHaveBeenCalledWith(mockElements.restartBtn);
      expect(hideElementSpy).toHaveBeenCalledWith(mockElements.step2);
      expect(showElementSpy).toHaveBeenCalledWith(mockElements.step1);
      expect(mockElements.postsCountEl!.innerText).toBe('');
      expect(mockElements.creditsInfoEl!.textContent).toBe('');
      expect(updateProgressBarSpy).toHaveBeenCalledWith(mockElements.stepBar1, 'current');
      expect(updateProgressBarSpy).toHaveBeenCalledWith(mockElements.stepBar2, 'todo');
    });

    it('should handle missing elements gracefully', () => {
      const partialElements: GeneratePageElements = {
        ...mockElements,
        postsCountEl: null,
        creditsInfoEl: null,
        restartBtn: { addEventListener: vi.fn() } as any,
        // Ensure all required elements are present as valid HTMLElements
        updatesSection: document.createElement('div'),
        step2: document.createElement('div'),
        step1: document.createElement('div'),
        stepBar1: document.createElement('div'),
        stepBar2: document.createElement('div')
      };

      initRestart(partialElements);
      
      const callback = (partialElements.restartBtn?.addEventListener as any).mock.calls[0][1];
      
      // Should not throw
      expect(() => callback()).not.toThrow();
    });

    it('should not throw if restartBtn is null', () => {
      const elementsWithoutBtn: GeneratePageElements = {
        ...mockElements,
        restartBtn: null
      };

      expect(() => initRestart(elementsWithoutBtn)).not.toThrow();
    });
  });

  describe('initSummaryToggle', () => {
    let generateTypeEl: HTMLSelectElement;
    let summaryFormatEl: HTMLSelectElement;
    let getElementByIdSpy: ReturnType<typeof vi.spyOn>;

    beforeEach(() => {
      generateTypeEl = document.createElement('select');
      generateTypeEl.id = 'nuclen_generate_workflow';
      
      summaryFormatEl = document.createElement('select');
      summaryFormatEl.id = 'nuclen_summary_format';

      getElementByIdSpy = vi.spyOn(document, 'getElementById') as any;
      getElementByIdSpy.mockImplementation(((id: string) => {
        if (id === 'nuclen_generate_workflow') return generateTypeEl;
        if (id === 'nuclen_summary_format') return summaryFormatEl;
        return null;
      }) as any);
    });

    it('should call nuclenToggleSummaryFields on init', () => {
      const toggleSpy = vi.spyOn(generatePageUtils, 'nuclenToggleSummaryFields');
      
      initSummaryToggle();
      
      expect(toggleSpy).toHaveBeenCalledTimes(1);
    });

    it('should add change event listeners to elements', () => {
      const addEventListenerSpyGenerate = vi.spyOn(generateTypeEl, 'addEventListener');
      const addEventListenerSpySummary = vi.spyOn(summaryFormatEl, 'addEventListener');
      
      initSummaryToggle();
      
      expect(addEventListenerSpyGenerate).toHaveBeenCalledWith('change', generatePageUtils.nuclenToggleSummaryFields);
      expect(addEventListenerSpySummary).toHaveBeenCalledWith('change', generatePageUtils.nuclenToggleSummaryFields);
    });

    it('should handle missing generateTypeEl', () => {
      getElementByIdSpy.mockImplementation(((id: string) => {
        if (id === 'nuclen_summary_format') return summaryFormatEl;
        return null;
      }) as any);

      const toggleSpy = vi.spyOn(generatePageUtils, 'nuclenToggleSummaryFields');
      
      initSummaryToggle();
      
      expect(toggleSpy).toHaveBeenCalledTimes(1);
      // Should not throw
    });

    it('should handle missing summaryFormatEl', () => {
      getElementByIdSpy.mockImplementation(((id: string) => {
        if (id === 'nuclen_generate_workflow') return generateTypeEl;
        return null;
      }) as any);

      const toggleSpy = vi.spyOn(generatePageUtils, 'nuclenToggleSummaryFields');
      
      initSummaryToggle();
      
      expect(toggleSpy).toHaveBeenCalledTimes(1);
      // Should not throw
    });

    it('should handle both elements missing', () => {
      getElementByIdSpy.mockReturnValue(null);

      const toggleSpy = vi.spyOn(generatePageUtils, 'nuclenToggleSummaryFields');
      
      expect(() => initSummaryToggle()).not.toThrow();
      expect(toggleSpy).toHaveBeenCalledTimes(1);
    });

    it('should trigger toggle on generateType change', () => {
      const toggleSpy = vi.spyOn(generatePageUtils, 'nuclenToggleSummaryFields');
      
      initSummaryToggle();
      toggleSpy.mockClear(); // Clear initial call
      
      // Trigger change event
      const changeEvent = new Event('change');
      generateTypeEl.dispatchEvent(changeEvent);
      
      expect(toggleSpy).toHaveBeenCalledTimes(1);
    });

    it('should trigger toggle on summaryFormat change', () => {
      const toggleSpy = vi.spyOn(generatePageUtils, 'nuclenToggleSummaryFields');
      
      initSummaryToggle();
      toggleSpy.mockClear(); // Clear initial call
      
      // Trigger change event
      const changeEvent = new Event('change');
      summaryFormatEl.dispatchEvent(changeEvent);
      
      expect(toggleSpy).toHaveBeenCalledTimes(1);
    });
  });
});