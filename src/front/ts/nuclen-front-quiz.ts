/*************************************************
 * 3) The Main Quiz Code (uses question, answers, explanation)
 *************************************************/

export function nuclearEngagementInitQuiz() {
  /* 1.-- Settings ---------------------------------------------------------- */
  const maxQuestions   = parseInt((window as any).NuclenSettings?.questions_per_quiz, 10) || 10;
  const maxAnswers     = parseInt((window as any).NuclenSettings?.answers_per_question, 10) || 4;
  const optinPosition  = (window as any).NuclenOptinPosition  ?? 'with_results';  // 'with_results' | 'before_results'
  const optinMandatory = (window as any).NuclenOptinMandatory ?? false;

  /* 2.-- Prepare quiz data ------------------------------------------------- */
  const truncatedPostQuizData = postQuizData
    .filter(q => q.question.trim() && q.answers[0]?.trim())
    .slice(0, maxQuestions)
    .map(q => ({ ...q, answers: q.answers.slice(0, maxAnswers) }));

  let currentQuestionIndex = 0;
  let quizScore            = 0;
  const userAnswers: number[] = [];
  let optinCompleted       = false;

  /* 3.-- DOM cache --------------------------------------------------------- */
  const quizContainer        = document.getElementById('nuclen-quiz-container');
  const questionContainer    = document.getElementById('nuclen-quiz-question-container');
  const answersContainer     = document.getElementById('nuclen-quiz-answers-container');
  const progressBar          = document.getElementById('nuclen-quiz-progress-bar');
  const finalResultContainer = document.getElementById('nuclen-quiz-final-result-container');
  const nextButton           = document.getElementById('nuclen-quiz-next-button');
  const explanationContainer = document.getElementById('nuclen-quiz-explanation-container');
  if (!quizContainer) return;
  if (explanationContainer) explanationContainer.innerHTML = '';

  nextButton?.addEventListener('click', NuclenShowNextQuestion);

  /* ─────────────────────────────────────────────────────────────────────── */
  /*                               FLOW                                     */
  /* ─────────────────────────────────────────────────────────────────────── */

  /** Step forward or end */
  function NuclenShowNextQuestion() {
    currentQuestionIndex++;

    /* Load next question */
    if (currentQuestionIndex < truncatedPostQuizData.length) {
      NuclenLoadQuizQuestion();
      quizContainer?.scrollIntoView();
      return;
    }

    /* Done with questions → opt-in check */
    if (
      window.NuclenOptinEnabled &&
      window.NuclenOptinWebhook &&
      optinPosition === 'before_results' &&
      !optinCompleted
    ) {
      NuclenShowOptinBeforeResults();
    } else {
      NuclenShowFinalQuizResult();
    }
    quizContainer?.scrollIntoView();
  }

  /** Progress bar */
  function NuclenUpdateQuizProgressBar() {
    if (!progressBar) return;
    const pct = ((currentQuestionIndex + 1) / truncatedPostQuizData.length) * 100;
    progressBar.style.width = `${pct}%`;
  }

  /* ─────────────────────────────────────────────────────────────────────── */
  /*                         FINAL RESULTS  (score)                         */
  /* ─────────────────────────────────────────────────────────────────────── */

  function NuclenShowFinalQuizResult() {
    /* clear quiz view */
    questionContainer!.innerHTML    = '';
    answersContainer!.innerHTML     = '';
    explanationContainer!.innerHTML = '';
    nextButton?.classList.add('nuclen-quiz-hidden');

    let html = '';

    /* 1. Opt-in inside results (only for with_results) */
    if (
      optinPosition === 'with_results' &&
      window.NuclenOptinEnabled &&
      window.NuclenOptinWebhook
    ) {
      html += `
        <div id="nuclen-optin-container" class="nuclen-optin-with-results">
          <label for="nuclen-optin-name"  class="nuclen-fg">Name</label>
          <input  type="text"  id="nuclen-optin-name">
          <label for="nuclen-optin-email" class="nuclen-fg">Email</label>
          <input  type="email" id="nuclen-optin-email" required>
          <button type="button" id="nuclen-optin-submit">Sign up</button>
        </div>
      `;
    }

    /* 2. Score block */
    html += `
      <div id="nuclen-quiz-results-title"  class="nuclen-fg">Your Score</div>
      <div id="nuclen-quiz-results-score"  class="nuclen-fg">${quizScore} / ${truncatedPostQuizData.length}</div>
    `;

    /* Feedback */
    const msg =
      quizScore === truncatedPostQuizData.length
        ? 'Perfect!'
        : quizScore > truncatedPostQuizData.length / 2
          ? 'Well done!'
          : 'Why not retake the test?';
    html += `<div id="nuclen-quiz-score-comment">${msg}</div>`;

    /* 3. Question-by-question tabs */
    html += `<div id="nuclen-quiz-result-tabs-container">`;
    truncatedPostQuizData.forEach((_, i) => {
      html += `
        <button class="nuclen-quiz-result-tab"
                onclick="nuclearEngagementShowQuizQuestionDetails(${i})">
          ${i + 1}
        </button>`;
    });
    html += `</div>
             <div id="nuclen-quiz-result-details-container"
                  class="nuclen-fg dashboard-box"></div>`;

    /* 4. Custom HTML after quiz */
    if (NuclenCustomQuizHtmlAfter?.trim()) {
      html += `
        <div id="nuclen-quiz-end-message" class="nuclen-fg">
          ${NuclenCustomQuizHtmlAfter}
        </div>`;
    }

    /* 5. Retake */
    html += `
      <button id="nuclen-quiz-retake-button"
              onclick="nuclearEngagementRetakeQuiz()">Retake Test</button>
    `;

    finalResultContainer!.innerHTML = html;

    /* default: show first question details */
    window.nuclearEngagementShowQuizQuestionDetails?.(0);

    /* GA */
    gtag?.('event', 'nuclen_quiz_end');

    /* Opt-in submit (with_results) */
    if (
      optinPosition === 'with_results' &&
      window.NuclenOptinEnabled &&
      window.NuclenOptinWebhook
    ) {
      document
        .getElementById('nuclen-optin-submit')
        ?.addEventListener('click', async () => {
          const name  = (document.getElementById('nuclen-optin-name')  as HTMLInputElement).value.trim();
          const email = (document.getElementById('nuclen-optin-email') as HTMLInputElement).value.trim();
          if (!email) { alert('Please enter a valid email'); return; }

          gtag?.('event', 'nuclen_quiz_optin');

          try {
            const res = await fetch(window.NuclenOptinWebhook, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ name, email })
            });
            if (!res.ok) throw new Error(res.statusText);
            alert(window.NuclenOptinSuccessMessage);
            (document.getElementById('nuclen-optin-name')  as HTMLInputElement).value = '';
            (document.getElementById('nuclen-optin-email') as HTMLInputElement).value = '';
          } catch {
            alert('Unable to submit. Please try later.');
          }
        });
    }
  }

  /* ─────────────────────────────────────────────────────────────────────── */
  /*                      OPT-IN BEFORE RESULTS FLOW                         */
  /* ─────────────────────────────────────────────────────────────────────── */

  function NuclenShowOptinBeforeResults() {
    questionContainer!.innerHTML    = '';
    answersContainer!.innerHTML     = '';
    explanationContainer!.innerHTML = '';
    nextButton?.classList.add('nuclen-quiz-hidden');

    finalResultContainer!.innerHTML = `
      <div id="nuclen-optin-container">
        <p class="nuclen-fg"><strong>${
          optinMandatory
            ? 'Please enter your details to view your score:'
            : 'Optional: join our list to receive more quizzes.'
        }</strong></p>

        <label for="nuclen-optin-name"  class="nuclen-fg">Name</label>
        <input  type="text"  id="nuclen-optin-name">
        <label for="nuclen-optin-email" class="nuclen-fg">Email *</label>
        <input  type="email" id="nuclen-optin-email" required>

        <div style="margin-top:1em;display:flex;gap:10px;">
          <button type="button" id="nuclen-optin-submit">${
            optinMandatory ? 'Submit & view results' : 'Submit'
          }</button>
          ${
            optinMandatory
              ? ''
              : '<a href="#" id="nuclen-optin-skip" style="align-self:center;font-size:.85em;">Skip & view results</a>'
          }
        </div>
      </div>
    `;

    /* submit */
    document.getElementById('nuclen-optin-submit')?.addEventListener('click', async () => {
      const name  = (document.getElementById('nuclen-optin-name')  as HTMLInputElement).value.trim();
      const email = (document.getElementById('nuclen-optin-email') as HTMLInputElement).value.trim();
      if (!email) { alert('Please enter a valid email'); return; }

      try {
        await fetch(window.NuclenOptinWebhook, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ name, email })
        });
        alert(window.NuclenOptinSuccessMessage);
        optinCompleted = true;
        NuclenShowFinalQuizResult();
      } catch {
        alert('Network error – please try again later.');
      }
    });

    /* skip (non-mandatory) */
    document.getElementById('nuclen-optin-skip')?.addEventListener('click', e => {
      e.preventDefault();
      optinCompleted = true;
      NuclenShowFinalQuizResult();
    });
  }

  /* ─────────────────────────────────────────────────────────────────────── */
  /*              Question details inside final results screen              */
  /* ─────────────────────────────────────────────────────────────────────── */

  window.nuclearEngagementShowQuizQuestionDetails = (index: number) => {
    const q  = truncatedPostQuizData[index];
    const ua = userAnswers[index]; // original index of chosen answer

    const out = `
      <p class="nuclen-quiz-detail-question">${q.question}</p>
      <p class="nuclen-quiz-detail-correct"><strong>Correct:</strong> ${q.answers[0]}</p>
      ${
        ua === 0
          ? `<p class="nuclen-quiz-detail-chosen"><strong>Your answer:</strong> ${q.answers[0]} <span class="nuclen-quiz-checkmark">✔️</span></p>`
          : `<p class="nuclen-quiz-detail-chosen"><strong>Your answer:</strong> ${q.answers[ua] ?? '[No data]'}</p>`
      }
      <p class="nuclen-quiz-detail-explanation">${q.explanation}</p>
    `;

    document.getElementById('nuclen-quiz-result-details-container')!.innerHTML = out;

    /* highlight tab */
    Array.from(document.getElementsByClassName('nuclen-quiz-result-tab')).forEach(el =>
      el.classList.remove('nuclen-quiz-result-active-tab')
    );
    document.getElementsByClassName('nuclen-quiz-result-tab')[index]
      ?.classList.add('nuclen-quiz-result-active-tab');
  };

  /* ─────────────────────────────────────────────────────────────────────── */
  /*                              Retake Quiz                               */
  /* ─────────────────────────────────────────────────────────────────────── */

  window.nuclearEngagementRetakeQuiz = () => {
    currentQuestionIndex = 0;
    quizScore            = 0;
    userAnswers.length   = 0;
    finalResultContainer!.innerHTML = '';
    progressBar!.style.width = `${(1 / truncatedPostQuizData.length) * 100}%`;
    NuclenLoadQuizQuestion();
  };

  /* ─────────────────────────────────────────────────────────────────────── */
  /*                      Answer selection & checking                       */
  /* ─────────────────────────────────────────────────────────────────────── */

  function NuclenCheckQuizAnswer(origIdx: number, shuffledIdx: number, correctIdx: number) {
    /* score & store */
    if (origIdx === 0) quizScore++;
    userAnswers.push(origIdx);

    /* colour buttons */
    const btns = answersContainer!.getElementsByTagName('button');
    for (let i = 0; i < btns.length; i++) {
      btns[i].classList.remove('nuclen-quiz-possible-answer');
      if (i === correctIdx)         btns[i].classList.add('nuclen-quiz-answer-correct', 'nuclen-quiz-pulse');
      else if (i === shuffledIdx)   btns[i].classList.add('nuclen-quiz-answer-wrong');
      else                          btns[i].classList.add('nuclen-quiz-answer-not-selected');
      btns[i].disabled = true;
    }

    /* explanation */
    explanationContainer!.classList.remove('nuclen-quiz-hidden');
    explanationContainer!.innerHTML = `<p>${truncatedPostQuizData[currentQuestionIndex].explanation}</p>`;
    nextButton!.classList.remove('nuclen-quiz-hidden');
    quizContainer?.scrollIntoView();

    /* GA */
    if (typeof gtag === 'function') {
      if (currentQuestionIndex === 0) {
        gtag('event', 'nuclen_quiz_start');
        document.getElementById('nuclen-quiz-start-message')?.remove();
      }
      gtag('event', 'nuclen_quiz_answer');
    }
  }

  /* ─────────────────────────────────────────────────────────────────────── */
  /*                         Load one question                               */
  /* ─────────────────────────────────────────────────────────────────────── */

  function NuclenLoadQuizQuestion() {
    const q = truncatedPostQuizData[currentQuestionIndex];

    questionContainer!.innerHTML = `
      <div id="nuclen-quiz-question-number">${currentQuestionIndex + 1}/${truncatedPostQuizData.length}</div>
      <div class="nuclen-quiz-title">${q.question}</div>
    `;

    /* shuffle answers */
    const answers = q.answers
      .map((ans, idx) => ({ ans, idx }))
      .filter(a => a.ans.trim())
      .sort(() => Math.random() - 0.5);

    answersContainer!.innerHTML = answers
      .map(a => `
        <button class="nuclen-quiz-answer-button nuclen-quiz-possible-answer"
                data-orig-idx="${a.idx}">
          ${a.ans}
        </button>`).join('');

    const correctIdxInShuffle = answers.findIndex(a => a.idx === 0);

    nextButton!.classList.add('nuclen-quiz-hidden');
    explanationContainer!.innerHTML = '';
    NuclenUpdateQuizProgressBar();

    /* one-shot listener */
    function onClick(e: Event) {
      const el = e.target as HTMLElement;
      if (!el.matches('button.nuclen-quiz-answer-button')) return;
      const origIdx = parseInt(el.getAttribute('data-orig-idx') || '0', 10);
      const shuffledIdx = answers.findIndex(a => a.idx === origIdx);
      setTimeout(() => NuclenCheckQuizAnswer(origIdx, shuffledIdx, correctIdxInShuffle), 100);
      answersContainer!.removeEventListener('click', onClick);
    }
    answersContainer!.addEventListener('click', onClick);
  }

  /* Kick-off */
  NuclenLoadQuizQuestion();
}

/* Expose for lazy loader */
window.nuclearEngagementInitQuiz = nuclearEngagementInitQuiz;
