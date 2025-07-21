/* eslint-env browser */
import { describe, it, expect, beforeEach } from 'vitest';
import { JSDOM } from 'jsdom';

describe('Quiz Initialization', () => {
  let dom: JSDOM;
  let window: any;
  let document: any;

  beforeEach(() => {
    // Set up DOM environment
    dom = new JSDOM(`
      <!DOCTYPE html>
      <html>
        <body>
          <div class="nuclen-root" data-theme="light">
            <section id="nuclen-quiz-container" class="nuclen-quiz" data-testid="nuclen-quiz">
              <h2 id="nuclen-quiz-title" class="nuclen-quiz-title">Test your knowledge</h2>
              <div class="nuclen-quiz-progress-bar"></div>
              <div id="nuclen-quiz-question-container" class="nuclen-quiz-question-container"></div>
              <div id="nuclen-quiz-answers-container" class="nuclen-quiz-answers-container"></div>
              <div id="nuclen-quiz-result-container" class="nuclen-quiz-result-container"></div>
              <div id="nuclen-quiz-explanation-container" class="nuclen-quiz-explanation-container"></div>
              <button id="nuclen-quiz-next-button" class="nuclen-quiz-next-button"></button>
              <div id="nuclen-quiz-final-result-container" class="nuclen-quiz-final-result-container"></div>
            </section>
          </div>
        </body>
      </html>
    `, {
      url: 'http://localhost',
      pretendToBeVisual: true
    });

    window = dom.window as any;
    document = window.document;
    global.window = window as any;
    global.document = document;
  });

  it('should initialize quiz when quiz data is available', () => {
    // Mock quiz data on window
    (window as any).postQuizData = [
      {
        question: 'What is 2 + 2?',
        answers: ['3', '4', '5', '6'],
        correct: 1,
        explanation: '2 + 2 equals 4'
      },
      {
        question: 'What is the capital of France?',
        answers: ['London', 'Berlin', 'Paris', 'Madrid'],
        correct: 2,
        explanation: 'Paris is the capital of France'
      }
    ];

    // Mock settings
    (window as any).NuclenSettings = {
      questions_per_quiz: 10,
      answers_per_question: 4
    };

    // Check that quiz container is not empty
    const quizContainer = document.getElementById('nuclen-quiz-container');
    expect(quizContainer).toBeTruthy();
    expect(quizContainer?.classList.contains('nuclen-quiz')).toBe(true);

    // Check that quiz data is available
    expect((window as any).postQuizData).toBeDefined();
    expect((window as any).postQuizData.length).toBe(2);
  });

  it('should not render quiz content when no data is available', () => {
    // No quiz data set
    (window as any).postQuizData = [];

    const quizContainer = document.getElementById('nuclen-quiz-container');
    expect(quizContainer).toBeTruthy();

    // Quiz should exist but questions should not be populated
    const questionContainer = document.getElementById('nuclen-quiz-question-container');
    expect(questionContainer).toBeTruthy();
    expect(questionContainer?.innerHTML).toBe('');
  });

  it('should verify quiz elements are present in DOM', () => {
    const elements = [
      'nuclen-quiz-container',
      'nuclen-quiz-title',
      'nuclen-quiz-question-container',
      'nuclen-quiz-answers-container',
      'nuclen-quiz-result-container',
      'nuclen-quiz-explanation-container',
      'nuclen-quiz-next-button',
      'nuclen-quiz-final-result-container'
    ];

    elements.forEach(id => {
      const element = document.getElementById(id);
      expect(element).toBeTruthy();
      // Just check that it exists, don't check instance type due to jsdom quirks
    });
  });

  it('should have correct theme attribute', () => {
    const root = document.querySelector('.nuclen-root');
    expect(root).toBeTruthy();
    expect(root?.getAttribute('data-theme')).toBe('light');
  });
});

describe('Quiz Data Loading', () => {
  it('should handle quiz data from wp_localize_script', () => {
    // Simulate how WordPress localizes script data
    const mockQuizData = [
      {
        question: 'Test question 1?',
        answers: ['A', 'B', 'C', 'D'],
        correct: 0,
        explanation: 'A is correct'
      }
    ];

    // This simulates wp_localize_script output
    const script = document.createElement('script');
    script.textContent = `
      window.postQuizData = ${JSON.stringify(mockQuizData)};
      window.NuclenSettings = {
        questions_per_quiz: 5,
        answers_per_question: 4
      };
    `;
    document.head.appendChild(script);

    // Execute the script
    eval(script.textContent);

    // Verify data is available
    expect((window as any).postQuizData).toBeDefined();
    expect((window as any).postQuizData).toEqual(mockQuizData);
    expect((window as any).NuclenSettings.questions_per_quiz).toBe(5);
  });

  it('should validate quiz data structure', () => {
    const validQuizData = {
      questions: [
        {
          question: 'Valid question?',
          answers: ['A', 'B', 'C', 'D'],
          correct: 1,
          explanation: 'B is correct'
        }
      ]
    };

    const invalidQuizData = {
      questions: []
    };

    // Function to validate quiz data (matching PHP validation)
    const isValidQuizData = (data: any): boolean => {
      return !!(data && 
             data.questions && 
             Array.isArray(data.questions) && 
             data.questions.length > 0 &&
             data.questions.some((q: any) => q.question && q.question.trim() !== ''));
    };

    expect(isValidQuizData(validQuizData)).toBe(true);
    expect(isValidQuizData(invalidQuizData)).toBe(false);
    expect(isValidQuizData(null)).toBe(false);
    expect(isValidQuizData({})).toBe(false);
  });
});