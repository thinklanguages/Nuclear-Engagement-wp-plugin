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
  } from './nuclen-quiz-types';
  import { shuffle } from './nuclen-quiz-utils';
  import {
    buildOptinInlineHTML,
    mountOptinBeforeResults,
    attachInlineOptinHandlers,
  } from './nuclen-quiz-optin';
  
  /* Globals injected by wp_add_inline_script */
  declare function gtag(...args: any[]): void;
  
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
      console.warn('[NE] Quiz markup missing — init aborted.');
      return;
    }
  
    /* SETTINGS & OPT-IN CONTEXT */
    const maxQuestions =
      (window as any).NuclenSettings?.questions_per_quiz ?? 10;
    const maxAnswers =
      (window as any).NuclenSettings?.answers_per_question ?? 4;
  
    const optin: OptinContext = {
      position:
        ((window as any).NuclenOptinPosition as 'with_results' | 'before_results') ??
        'with_results',
      mandatory: Boolean((window as any).NuclenOptinMandatory),
      promptText: (window as any).NuclenOptinPromptText,
      submitLabel: (window as any).NuclenOptinButtonText,
      enabled: Boolean((window as any).NuclenOptinEnabled),
      webhook: (window as any).NuclenOptinWebhook,
      ajaxUrl: (window as any).NuclenOptinAjax?.url ?? '',
      ajaxNonce: (window as any).NuclenOptinAjax?.nonce ?? '',
    };
  
    /* PREPARE QUESTIONS */
    const questions: QuizQuestion[] = (window as any).postQuizData
      .filter((q) => q.question.trim() && q.answers[0]?.trim())
      .slice(0, maxQuestions)
      .map((q) => ({ ...q, answers: q.answers.slice(0, maxAnswers) }));
  
    let currIdx = 0;
    let score   = 0;
    const userAnswers: number[] = [];
  
    /* Start */
    nextBtn.addEventListener('click', showNext);
    renderQuestion();
  
    /* ───────────────────────────────────────────────────────────
       Quiz flow helpers
    ─────────────────────────────────────────────────────────── */
  
    function renderQuestion(): void {
      const q = questions[currIdx];
  
      qContainer!.innerHTML = `
        <div id="nuclen-quiz-question-number">
          ${currIdx + 1}/${questions.length}
        </div>
        <div class="nuclen-quiz-title">${q.question}</div>`;
  
      const shuffled = shuffle(
        q.answers.map((ans, idx) => ({ ans, idx })).filter((a) => a.ans.trim()),
      );
  
      aContainer!.innerHTML = shuffled
        .map(
          (a) => `
            <button
              class="nuclen-quiz-answer-button nuclen-quiz-possible-answer"
              data-orig-idx="${a.idx}"
            >${a.ans}</button>`,
        )
        .join('');
  
      const correctIdx = shuffled.findIndex((a) => a.idx === 0);
  
      /* reset */
      explContainer!.innerHTML = '';
      explContainer!.classList.add('nuclen-quiz-hidden');
      nextBtn!.classList.add('nuclen-quiz-hidden');
      updateProgress();
  
      /* one-shot answer handler */
      const handler = (e: Event) => {
        const el = e.target as HTMLElement;
        if (!el.matches('button.nuclen-quiz-answer-button')) return;
  
        const origIdx = parseInt(el.getAttribute('data-orig-idx') || '0', 10);
        checkAnswer(origIdx, shuffled.findIndex((a) => a.idx === origIdx), correctIdx);
        aContainer!.removeEventListener('click', handler);
      };
      aContainer!.addEventListener('click', handler);
    }
  
    function updateProgress(): void {
      progBar!.style.width = `${((currIdx + 1) / questions.length) * 100}%`;
    }
  
    function checkAnswer(origIdx: number, shuffledIdx: number, correctIdx: number): void {
      if (origIdx === 0) score++;
      userAnswers.push(origIdx);
  
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
  
      explContainer!.innerHTML = `<p>${questions[currIdx].explanation}</p>`;
      explContainer!.classList.remove('nuclen-quiz-hidden');
      nextBtn!.classList.remove('nuclen-quiz-hidden');
  
      if (typeof gtag === 'function') {
        if (currIdx === 0) gtag('event', 'nuclen_quiz_start');
        gtag('event', 'nuclen_quiz_answer');
      }
    }
  
    function showNext(): void {
      currIdx++;
      if (currIdx < questions.length) {
        renderQuestion();
        return quizContainer!.scrollIntoView();
      }
  
      /* End of questions */
      if (optin.enabled && optin.position === 'before_results') {
        return renderOptinBeforeResultsFlow();
      }
      renderFinal();
    }
  
    /* -------------------- before-results opt-in ------------------- */
    function renderOptinBeforeResultsFlow(): void {
      qContainer!.innerHTML = '';
      aContainer!.innerHTML = '';
      explContainer!.innerHTML = '';
      nextBtn!.classList.add('nuclen-quiz-hidden');             // ← hide “Next”
  
      mountOptinBeforeResults(
        finalContainer!,
        optin,
        () => renderFinal(), // onComplete
        () => renderFinal(), // onSkip
      );
    }
  
    /* -------------------- final screen ---------------------------- */
    function renderFinal(): void {
      qContainer!.innerHTML = '';
      aContainer!.innerHTML = '';
      explContainer!.innerHTML = '';
      nextBtn!.classList.add('nuclen-quiz-hidden');             // ← hide “Next”
      finalContainer!.classList.remove('nuclen-quiz-hidden');
  
      /* 1. inline opt-in if enabled + with_results */
      let html = '';
      if (optin.enabled && optin.position === 'with_results') {
        html += buildOptinInlineHTML(optin);
      }
  
      /* 2. score block */
      html += `
        <div id="nuclen-quiz-results-title" class="nuclen-fg">Your Score</div>
        <div id="nuclen-quiz-results-score" class="nuclen-fg">
          ${score} / ${questions.length}
        </div>`;
      const comment =
        score === questions.length
          ? 'Perfect!'
          : score > questions.length / 2
          ? 'Well done!'
          : 'Why not retake the test?';
      html += `<div id="nuclen-quiz-score-comment">${comment}</div>`;
  
      /* 3. tabs + detail container */
      html += '<div id="nuclen-quiz-result-tabs-container">';
      questions.forEach((_, i) => {
        html += `
          <button class="nuclen-quiz-result-tab"
                  onclick="nuclearEngagementShowQuizQuestionDetails(${i})">${i + 1}</button>`;
      });
      html += '</div><div id="nuclen-quiz-result-details-container" class="nuclen-fg dashboard-box"></div>';
  
      /* 4. custom after-HTML */
      if ((window as any).NuclenCustomQuizHtmlAfter?.trim()) {
        html += `
          <div id="nuclen-quiz-end-message" class="nuclen-fg">
            ${(window as any).NuclenCustomQuizHtmlAfter}
          </div>`;
      }
  
      /* 5. retake button */
      html += `
        <button id="nuclen-quiz-retake-button"
                onclick="nuclearEngagementRetakeQuiz()">Retake Test</button>`;
  
      finalContainer!.innerHTML = html;
  
      /* attach inline opt-in handler */
      if (optin.enabled && optin.position === 'with_results') {
        attachInlineOptinHandlers(optin);
      }
  
      /* default: show first question details */
      window.nuclearEngagementShowQuizQuestionDetails = (idx: number): void => {
        const q = questions[idx];
        const ua = userAnswers[idx];
        (document.getElementById('nuclen-quiz-result-details-container') as HTMLElement).innerHTML = `
          <p class="nuclen-quiz-detail-question">${q.question}</p>
          <p class="nuclen-quiz-detail-correct"><strong>Correct:</strong> ${q.answers[0]}</p>
          ${
            ua === 0
              ? `<p class="nuclen-quiz-detail-chosen"><strong>Your answer:</strong> ${q.answers[0]} <span class="nuclen-quiz-checkmark">✔️</span></p>`
              : `<p class="nuclen-quiz-detail-chosen"><strong>Your answer:</strong> ${q.answers[ua] ?? '[No data]'}</p>`
          }
          <p class="nuclen-quiz-detail-explanation">${q.explanation}</p>`;
        Array.from(document.getElementsByClassName('nuclen-quiz-result-tab')).forEach((el) =>
          el.classList.remove('nuclen-quiz-result-active-tab'),
        );
        document
          .getElementsByClassName('nuclen-quiz-result-tab')
          [idx]?.classList.add('nuclen-quiz-result-active-tab');
      };
      window.nuclearEngagementShowQuizQuestionDetails(0);
  
      /* retake */
      window.nuclearEngagementRetakeQuiz = (): void => {
        currIdx = 0;
        score = 0;
        userAnswers.length = 0;
        finalContainer!.innerHTML = '';
        finalContainer!.classList.add('nuclen-quiz-hidden');
        progBar!.style.width = `${(1 / questions.length) * 100}%`;
        renderQuestion();
      };
  
      if (typeof gtag === 'function') {
        gtag('event', 'nuclen_quiz_end');
      }
    }
  }
  