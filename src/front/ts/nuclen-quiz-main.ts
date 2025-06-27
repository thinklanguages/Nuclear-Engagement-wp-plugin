// ─────────────────────────────────────────────────────────────
// File: src/front/ts/nuclen-quiz-main.ts
// FULL content – fixes: enable flag, skip-link, next-button hiding
// -----------------------------------------------------------------------------
//
// · Opt-in form now renders ONLY when Enable Opt-In is “on”.
// · Skip link appears when opt-in is before-results & not mandatory.
// · “Next” button is hidden whenever the opt-in is visible (both flows).
//
// -----------------------------------------------------------------------------

import type {
  QuizQuestion,
  OptinContext,
  NuclenSettings as NuclenSettingsType,
} from './nuclen-quiz-types';
import { escapeHtml } from './nuclen-quiz-utils';
import {
  QuizUIRefs,
  QuizState,
  renderFinal,
  renderOptinBeforeResultsFlow,
} from './nuclen-quiz-results';
import * as logger from './logger';
import { renderQuestion } from './nuclen-quiz-question';

  /* Globals injected by wp_localize_script */
  declare const postQuizData: QuizQuestion[];
  declare const NuclenSettings: NuclenSettingsType;

  declare const NuclenOptinPosition: string;
  declare const NuclenOptinMandatory: boolean;
  declare const NuclenOptinPromptText: string;
  declare const NuclenOptinButtonText: string;

  declare const NuclenOptinAjax: { url: string; nonce: string };

  declare function gtag(...args: unknown[]): void;

  /* ─────────────────────────────────────────────────────────────
     Entry
  ────────────────────────────────────────────────────────────── */
  export function initQuiz(): void {
    /* DOM refs (fail-fast if markup missing) */
    const quizContainer  = document.getElementById('nuclen-quiz-container');
    const qContainer     = document.getElementById('nuclen-quiz-question-container');
    const aContainer     = document.getElementById('nuclen-quiz-answers-container');
    const progBar        = document.getElementById('nuclen-quiz-progress-bar');
    const finalContainer = document.getElementById('nuclen-quiz-final-result-container');
    const nextBtn        = document.getElementById('nuclen-quiz-next-button');
    const explContainer  = document.getElementById('nuclen-quiz-explanation-container');
    if (
      !quizContainer || !qContainer || !aContainer || !progBar ||
      !finalContainer || !nextBtn || !explContainer
    ) {
      logger.warn('[NE] Quiz markup missing — init aborted.');
      return;
    }

    /* SETTINGS & OPT-IN CONTEXT */
    const maxQuestions = NuclenSettings?.questions_per_quiz ?? 10;
    const maxAnswers   = NuclenSettings?.answers_per_question ?? 4;

    const optin: OptinContext = {
      position: (NuclenOptinPosition as 'with_results' | 'before_results') ?? 'with_results',
      mandatory: Boolean(NuclenOptinMandatory),
      promptText: NuclenOptinPromptText,
      submitLabel: NuclenOptinButtonText,
      enabled: Boolean(window.NuclenOptinEnabled),
      webhook: window.NuclenOptinWebhook ?? '',
      ajaxUrl: NuclenOptinAjax?.url ?? '',
      ajaxNonce: NuclenOptinAjax?.nonce ?? '',
    };

    /* PREPARE QUESTIONS */
    const questions: QuizQuestion[] = postQuizData
      .filter((q) => q.question.trim() && q.answers[0]?.trim())
      .slice(0, maxQuestions)
      .map((q) => ({ ...q, answers: q.answers.slice(0, maxAnswers) }));

    const ui: QuizUIRefs = {
      qContainer: qContainer!,
      aContainer: aContainer!,
      explContainer: explContainer!,
      nextBtn: nextBtn!,
      finalContainer: finalContainer!,
      progBar: progBar!,
    };

    const state: QuizState = { currIdx: 0, score: 0, userAnswers: [] };

    /* Start */
    nextBtn.addEventListener('click', showNext);
    renderQuestion(questions, state, ui, checkAnswer);


    /* ───────────────────────────────────────────────────────────
       Quiz flow helpers
    ─────────────────────────────────────────────────────────── */

    function checkAnswer(origIdx: number, shuffledIdx: number, correctIdx: number): void {
      if (origIdx === 0) state.score++;
      state.userAnswers.push(origIdx);

      const btns = aContainer!.getElementsByTagName('button');
      for (let i = 0; i < btns.length; i++) {
        btns[i].classList.remove('nuclen-quiz-possible-answer');
        if (i === correctIdx) {
          btns[i].classList.add('nuclen-quiz-answer-correct', 'nuclen-quiz-pulse');
        } else if (i === shuffledIdx) {
          btns[i].classList.add('nuclen-quiz-answer-wrong');
        } else {
          btns[i].classList.add('nuclen-quiz-answer-not-selected');
        }
        btns[i].disabled = true;
      }

      explContainer!.innerHTML = `<p>${escapeHtml(
        questions[state.currIdx].explanation,
      )}</p>`;
      explContainer!.classList.remove('nuclen-quiz-hidden');
      nextBtn!.classList.remove('nuclen-quiz-hidden');

      if (typeof gtag === 'function') {
        if (state.currIdx === 0) gtag('event', 'nuclen_quiz_start');
        gtag('event', 'nuclen_quiz_answer');
      }
    }

    function showNext(): void {
      state.currIdx++;
      if (state.currIdx < questions.length) {
        renderQuestion(questions, state, ui, checkAnswer);
        return quizContainer!.scrollIntoView();
      }

      const finalCb = () =>
        renderFinal(ui, optin, questions, state, () =>
          renderQuestion(questions, state, ui, checkAnswer),
        );

      if (optin.enabled && optin.position === 'before_results') {
        return renderOptinBeforeResultsFlow(ui, optin, finalCb);
      }
      finalCb();
    }

  }
