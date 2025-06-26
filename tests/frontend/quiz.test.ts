import { describe, it, expect, beforeEach, vi } from 'vitest';
import { initQuiz } from '../../src/front/ts/nuclen-quiz-main';
import { renderFinal } from '../../src/front/ts/nuclen-quiz-results';
import type { QuizQuestion, QuizUIRefs, QuizState, OptinContext } from '../../src/front/ts/nuclen-quiz-types';

// Basic DOM markup used by the quiz scripts
function setupDOM() {
  document.body.innerHTML = `
    <div id="nuclen-quiz-container">
      <div id="nuclen-quiz-question-container"></div>
      <div id="nuclen-quiz-answers-container"></div>
      <div id="nuclen-quiz-explanation-container" class="nuclen-quiz-hidden"></div>
      <div id="nuclen-quiz-progress-bar"></div>
      <button id="nuclen-quiz-next-button" class="nuclen-quiz-hidden"></button>
      <div id="nuclen-quiz-final-result-container" class="nuclen-quiz-hidden"></div>
    </div>`;
}

const sampleQuestions: QuizQuestion[] = [
  {
    question: 'First Q',
    answers: ['A1', 'A2'],
    explanation: 'Because',
  },
  {
    question: 'Second Q',
    answers: ['B1', 'B2'],
    explanation: 'So',
  },
];

// Shared strings for renderFinal
(globalThis as any).NuclenStrings = {
  retake_test: 'Retake',
  your_score: 'Score',
  perfect: 'Perfect!',
  well_done: 'Well done',
  retake_prompt: 'Try again',
  correct: 'Correct',
  your_answer: 'Your answer',
};

// Minimal settings
(globalThis as any).NuclenSettings = { questions_per_quiz: 2, answers_per_question: 2 };
(globalThis as any).NuclenOptinPosition = 'with_results';
(globalThis as any).NuclenOptinMandatory = false;
(globalThis as any).NuclenOptinPromptText = 'Join us';
(globalThis as any).NuclenOptinButtonText = 'Submit';
(globalThis as any).NuclenOptinAjax = { url: '', nonce: '' };
(globalThis as any).NuclenCustomQuizHtmlAfter = '';

// Vitest DOM setup before each test
beforeEach(() => {
  setupDOM();
  (globalThis as any).postQuizData = sampleQuestions;
  (globalThis as any).NuclenOptinEnabled = false;
  (globalThis as any).NuclenOptinWebhook = '';
  (globalThis as any).gtag = vi.fn();
});

// --- Tests ---

describe('initQuiz', () => {
  it('renders first question and processes answers', () => {
    initQuiz();
    const qEl = document.getElementById('nuclen-quiz-question-container')!;
    const aEl = document.getElementById('nuclen-quiz-answers-container')!;
    expect(qEl.textContent).toContain('First Q');
    const btn = aEl.querySelector('button') as HTMLButtonElement;
    expect(btn).toBeTruthy();
    // simulate answer selection
    btn.click();
    const expl = document.getElementById('nuclen-quiz-explanation-container')!;
    const next = document.getElementById('nuclen-quiz-next-button')!;
    expect(expl.classList.contains('nuclen-quiz-hidden')).toBe(false);
    expect(next.classList.contains('nuclen-quiz-hidden')).toBe(false);
    const gtag = (globalThis as any).gtag as ReturnType<typeof vi.fn>;
    expect(gtag).toHaveBeenCalledWith('event', 'nuclen_quiz_start');
    expect(gtag).toHaveBeenCalledWith('event', 'nuclen_quiz_answer');
  });
});

describe('renderFinal', () => {
  it('outputs opt-in markup and callbacks', () => {
    setupDOM();
    const ui: QuizUIRefs = {
      qContainer: document.getElementById('nuclen-quiz-question-container')!,
      aContainer: document.getElementById('nuclen-quiz-answers-container')!,
      explContainer: document.getElementById('nuclen-quiz-explanation-container')!,
      nextBtn: document.getElementById('nuclen-quiz-next-button')!,
      finalContainer: document.getElementById('nuclen-quiz-final-result-container')!,
      progBar: document.getElementById('nuclen-quiz-progress-bar')!,
    };
    const state: QuizState = { currIdx: 0, score: 2, userAnswers: [0, 1] };
    const optin: OptinContext = {
      position: 'with_results',
      mandatory: false,
      promptText: 'Join us',
      submitLabel: 'Submit',
      enabled: true,
      webhook: '',
      ajaxUrl: '',
      ajaxNonce: '',
    };
    const gtag = vi.fn();
    (globalThis as any).gtag = gtag;
    renderFinal(ui, optin, sampleQuestions, state, () => {});
    const form = document.getElementById('nuclen-optin-container');
    expect(form).toBeTruthy();
    expect(typeof window.nuclearEngagementShowQuizQuestionDetails).toBe('function');
    expect(typeof window.nuclearEngagementRetakeQuiz).toBe('function');
    expect(gtag).toHaveBeenCalledWith('event', 'nuclen_quiz_end');
  });
});
