import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { 
  populateQuizMetaBox, 
  populateSummaryMetaBox,
  alertApiError,
  storeGenerationResults,
  type PostResult 
} from '../../src/admin/ts/single/single-generation-utils';

describe('single-generation-utils', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  describe('populateQuizMetaBox', () => {
    it('should populate date field with finalDate when provided', () => {
      const dateInput = document.createElement('input');
      dateInput.name = 'nuclen_quiz_data[date]';
      document.body.appendChild(dateInput);

      const postResult: PostResult = { date: '2024-01-01' };
      populateQuizMetaBox(postResult, '2024-02-15');

      expect(dateInput.value).toBe('2024-02-15');
      expect(dateInput.readOnly).toBe(true);
    });

    it('should populate date field with postResult date when finalDate not provided', () => {
      const dateInput = document.createElement('input');
      dateInput.name = 'nuclen_quiz_data[date]';
      document.body.appendChild(dateInput);

      const postResult: PostResult = { date: '2024-01-01' };
      populateQuizMetaBox(postResult);

      expect(dateInput.value).toBe('2024-01-01');
      expect(dateInput.readOnly).toBe(true);
    });

    it('should handle missing date field gracefully', () => {
      const postResult: PostResult = { date: '2024-01-01' };
      
      expect(() => populateQuizMetaBox(postResult)).not.toThrow();
    });

    it('should populate quiz questions', () => {
      // Create question input
      const questionInput = document.createElement('input');
      questionInput.name = 'nuclen_quiz_data[questions][0][question]';
      document.body.appendChild(questionInput);

      // Create answer inputs
      const answer1 = document.createElement('input');
      answer1.name = 'nuclen_quiz_data[questions][0][answers][0]';
      document.body.appendChild(answer1);

      const answer2 = document.createElement('input');
      answer2.name = 'nuclen_quiz_data[questions][0][answers][1]';
      document.body.appendChild(answer2);

      // Create explanation textarea
      const explanation = document.createElement('textarea');
      explanation.name = 'nuclen_quiz_data[questions][0][explanation]';
      document.body.appendChild(explanation);

      const postResult: PostResult = {
        questions: [{
          question: 'What is TypeScript?',
          answers: ['A programming language', 'A framework'],
          explanation: 'TypeScript is a typed superset of JavaScript'
        }]
      };

      populateQuizMetaBox(postResult);

      expect(questionInput.value).toBe('What is TypeScript?');
      expect(answer1.value).toBe('A programming language');
      expect(answer2.value).toBe('A framework');
      expect(explanation.value).toBe('TypeScript is a typed superset of JavaScript');
    });

    it('should handle multiple questions', () => {
      // Create inputs for two questions
      const q1Input = document.createElement('input');
      q1Input.name = 'nuclen_quiz_data[questions][0][question]';
      document.body.appendChild(q1Input);

      const q2Input = document.createElement('input');
      q2Input.name = 'nuclen_quiz_data[questions][1][question]';
      document.body.appendChild(q2Input);

      const postResult: PostResult = {
        questions: [
          { question: 'Question 1' },
          { question: 'Question 2' }
        ]
      };

      populateQuizMetaBox(postResult);

      expect(q1Input.value).toBe('Question 1');
      expect(q2Input.value).toBe('Question 2');
    });

    it('should handle missing question fields', () => {
      const postResult: PostResult = {
        questions: [{
          question: 'Test question',
          answers: ['Answer 1'],
          explanation: 'Test explanation'
        }]
      };

      expect(() => populateQuizMetaBox(postResult)).not.toThrow();
    });

    it('should handle empty questions array', () => {
      const postResult: PostResult = { questions: [] };
      
      expect(() => populateQuizMetaBox(postResult)).not.toThrow();
    });

    it('should handle questions with missing properties', () => {
      const questionInput = document.createElement('input');
      questionInput.name = 'nuclen_quiz_data[questions][0][question]';
      document.body.appendChild(questionInput);

      const postResult: PostResult = {
        questions: [{ /* empty question object */ }]
      };

      populateQuizMetaBox(postResult);

      expect(questionInput.value).toBe('');
    });

    it('should set date field to empty string when no date provided', () => {
      const dateInput = document.createElement('input');
      dateInput.name = 'nuclen_quiz_data[date]';
      document.body.appendChild(dateInput);

      const postResult: PostResult = {};
      populateQuizMetaBox(postResult);

      expect(dateInput.value).toBe('');
    });
  });

  describe('populateSummaryMetaBox', () => {
    beforeEach(() => {
      // Clean up window.tinymce
      delete (window as any).tinymce;
    });

    it('should populate date field with finalDate when provided', () => {
      const dateInput = document.createElement('input');
      dateInput.name = 'nuclen_summary_data[date]';
      document.body.appendChild(dateInput);

      const postResult: PostResult = { date: '2024-01-01' };
      populateSummaryMetaBox(postResult, '2024-02-15');

      expect(dateInput.value).toBe('2024-02-15');
      expect(dateInput.readOnly).toBe(true);
    });

    it('should populate date field with postResult date when finalDate not provided', () => {
      const dateInput = document.createElement('input');
      dateInput.name = 'nuclen_summary_data[date]';
      document.body.appendChild(dateInput);

      const postResult: PostResult = { date: '2024-01-01' };
      populateSummaryMetaBox(postResult);

      expect(dateInput.value).toBe('2024-01-01');
      expect(dateInput.readOnly).toBe(true);
    });

    it('should populate summary textarea when tinymce is not available', () => {
      const summaryTextarea = document.createElement('textarea');
      summaryTextarea.name = 'nuclen_summary_data[summary]';
      document.body.appendChild(summaryTextarea);

      const postResult: PostResult = { summary: 'This is a test summary' };
      populateSummaryMetaBox(postResult);

      expect(summaryTextarea.value).toBe('This is a test summary');
    });

    it('should use tinymce editor when available', () => {
      const mockEditor = {
        setContent: vi.fn(),
        save: vi.fn()
      };

      (window as any).tinymce = {
        get: vi.fn().mockReturnValue(mockEditor)
      };

      const postResult: PostResult = { summary: 'TinyMCE summary content' };
      populateSummaryMetaBox(postResult);

      expect((window as any).tinymce.get).toHaveBeenCalledWith('nuclen_summary_data_summary');
      expect(mockEditor.setContent).toHaveBeenCalledWith('TinyMCE summary content');
      expect(mockEditor.save).toHaveBeenCalled();
    });

    it('should fall back to textarea when tinymce.get returns undefined', () => {
      const summaryTextarea = document.createElement('textarea');
      summaryTextarea.name = 'nuclen_summary_data[summary]';
      document.body.appendChild(summaryTextarea);

      (window as any).tinymce = {
        get: vi.fn().mockReturnValue(undefined)
      };

      const postResult: PostResult = { summary: 'Fallback summary' };
      populateSummaryMetaBox(postResult);

      expect(summaryTextarea.value).toBe('Fallback summary');
    });

    it('should fall back to textarea when editor has no setContent method', () => {
      const summaryTextarea = document.createElement('textarea');
      summaryTextarea.name = 'nuclen_summary_data[summary]';
      document.body.appendChild(summaryTextarea);

      (window as any).tinymce = {
        get: vi.fn().mockReturnValue({})
      };

      const postResult: PostResult = { summary: 'No setContent summary' };
      populateSummaryMetaBox(postResult);

      expect(summaryTextarea.value).toBe('No setContent summary');
    });

    it('should handle missing summary field gracefully', () => {
      const postResult: PostResult = { summary: 'Test summary' };
      
      expect(() => populateSummaryMetaBox(postResult)).not.toThrow();
    });

    it('should handle empty summary', () => {
      const summaryTextarea = document.createElement('textarea');
      summaryTextarea.name = 'nuclen_summary_data[summary]';
      document.body.appendChild(summaryTextarea);

      const postResult: PostResult = { summary: '' };
      populateSummaryMetaBox(postResult);

      expect(summaryTextarea.value).toBe('');
    });

    it('should handle undefined summary', () => {
      const summaryTextarea = document.createElement('textarea');
      summaryTextarea.name = 'nuclen_summary_data[summary]';
      document.body.appendChild(summaryTextarea);

      const postResult: PostResult = {};
      populateSummaryMetaBox(postResult);

      expect(summaryTextarea.value).toBe('');
    });

    it('should handle tinymce without get function', () => {
      const summaryTextarea = document.createElement('textarea');
      summaryTextarea.name = 'nuclen_summary_data[summary]';
      document.body.appendChild(summaryTextarea);

      (window as any).tinymce = {};

      const postResult: PostResult = { summary: 'No get function' };
      populateSummaryMetaBox(postResult);

      expect(summaryTextarea.value).toBe('No get function');
    });

    it('should handle editor without save method', () => {
      const mockEditor = {
        setContent: vi.fn()
        // no save method
      };

      (window as any).tinymce = {
        get: vi.fn().mockReturnValue(mockEditor)
      };

      const postResult: PostResult = { summary: 'No save method' };
      
      expect(() => populateSummaryMetaBox(postResult)).not.toThrow();
      expect(mockEditor.setContent).toHaveBeenCalledWith('No save method');
    });
  });

  describe('re-exports', () => {
    it('should re-export alertApiError and storeGenerationResults', () => {
      // Test that the re-exports exist
      expect(alertApiError).toBeDefined();
      expect(storeGenerationResults).toBeDefined();
    });
  });
});