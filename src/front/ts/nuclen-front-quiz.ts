/*************************************************
 * 3) The Main Quiz Code (uses question, answers, explanation)
 *************************************************/

export function nuclearEngagementInitQuiz() {
  // 1) Grab user-chosen max # questions and answers
  const maxQuestions = parseInt((window as any).NuclenSettings?.questions_per_quiz, 10) || 10;
  const maxAnswers   = parseInt((window as any).NuclenSettings?.answers_per_question, 10) || 4;

  // 2) Filter out questions with empty text; filter out empty answers; then truncate.
  const truncatedPostQuizData = postQuizData
    .filter(q => q.question && q.question.trim().length > 0) // Skip if question is empty
    .map(q => {
      const filteredAnswers = q.answers
        .filter(a => a && a.trim().length > 0) // Skip empty answers
        .slice(0, maxAnswers);

      return {
        ...q,
        answers: filteredAnswers
      };
    })
    .filter(q => q.answers.length > 0) // Skip if no valid answers remain
    .slice(0, maxQuestions);

  let currentQuestionIndex = 0;
  let quizScore = 0;
  const userAnswers: number[] = []; // store the originalIndex chosen (0 = correct)

  // Basic DOM lookups
  const quizContainer = document.getElementById('nuclen-quiz-container');
  if (!quizContainer) {
    return;
  }
  const questionContainer = document.getElementById('nuclen-quiz-question-container');
  const answersContainer = document.getElementById('nuclen-quiz-answers-container');
  const progressBar = document.getElementById('nuclen-quiz-progress-bar');
  const finalResultContainer = document.getElementById('nuclen-quiz-final-result-container');
  const nextButton = document.getElementById('nuclen-quiz-next-button');
  const explanationContainer = document.getElementById('nuclen-quiz-explanation-container');

  // Clear explanation initially
  if (explanationContainer) {
    explanationContainer.innerHTML = '';
  }

  nextButton?.addEventListener('click', NuclenShowNextQuestion);

  /**
   * Step to the next question or show final results
   */
  function NuclenShowNextQuestion() {
    currentQuestionIndex++;
    if (currentQuestionIndex < truncatedPostQuizData.length) {
      NuclenLoadQuizQuestion();
    } else {
      NuclenShowFinalQuizResult();
    }
    quizContainer?.scrollIntoView();
  }

  /**
   * Update progress bar
   */
  function NuclenUpdateQuizProgressBar() {
    if (!progressBar) return;
    const progress = ((currentQuestionIndex + 1) / truncatedPostQuizData.length) * 100;
    progressBar.style.width = `${progress}%`;
  }

  /**
   * Display final results (score, question-by-question review, optional opt-in, etc.)
   */
  function NuclenShowFinalQuizResult() {
    if (questionContainer) questionContainer.innerHTML = '';
    if (answersContainer) answersContainer.innerHTML = '';
    if (explanationContainer) explanationContainer.innerHTML = '';
    nextButton?.classList.add('nuclen-quiz-hidden');

    // Score
    let finalHTML = `
      <div id="nuclen-quiz-results-title" class="nuclen-fg">Your Score</div>
      <div id="nuclen-quiz-results-score" class="nuclen-fg">${quizScore} / ${truncatedPostQuizData.length}</div>
    `;

    // Simple feedback
    let message = '';
    if (quizScore === truncatedPostQuizData.length) {
      message = 'Perfect!';
    } else if (quizScore > truncatedPostQuizData.length / 2) {
      message = 'Well done!';
    } else {
      message = 'Why not retake the test?';
    }
    finalHTML += `<div id="nuclen-quiz-score-comment">${message}</div>`;

    // Question tabs
    finalHTML += `<div id="nuclen-quiz-result-tabs-container">`;
    truncatedPostQuizData.forEach((_, index) => {
      finalHTML += `
        <button 
          class="nuclen-quiz-result-tab" 
          onclick="nuclearEngagementShowQuizQuestionDetails(${index})"
        >
          ${index + 1}
        </button>`;
    });
    finalHTML += `</div>`;
    finalHTML += `<div id="nuclen-quiz-result-details-container" class="nuclen-fg dashboard-box"></div>`;

    // Custom HTML after quiz
    if (typeof NuclenCustomQuizHtmlAfter === 'string' && NuclenCustomQuizHtmlAfter.trim() !== '') {
      finalHTML += `
        <div id="nuclen-quiz-end-message" class="nuclen-fg">
          ${NuclenCustomQuizHtmlAfter}
        </div>
      `;
    }

    // Opt-in form
    if (window.NuclenOptinEnabled && window.NuclenOptinWebhook) {
      finalHTML += `
        <div id="nuclen-optin-container">
          <label for="nuclen-optin-name" class="nuclen-fg">Name</label>
          <input type="text" id="nuclen-optin-name">

          <label for="nuclen-optin-email" class="nuclen-fg">Email</label>
          <input type="email" id="nuclen-optin-email" required>

          <button type="button" id="nuclen-optin-submit">Sign up</button>
        </div>
      `;
    }

    // Retake
    finalHTML += `
      <button id="nuclen-quiz-retake-button" onclick="nuclearEngagementRetakeQuiz()">
        Retake Test
      </button>
    `;

    if (finalResultContainer) {
      finalResultContainer.innerHTML = finalHTML;
    }

    // Show first question’s details
    window.nuclearEngagementShowQuizQuestionDetails?.(0);

    // GA event
    if (typeof gtag === 'function') {
      gtag('event', 'nuclen_quiz_end');
    }

    // Attach opt-in logic
    if (window.NuclenOptinEnabled && window.NuclenOptinWebhook) {
      const submitButton = document.getElementById('nuclen-optin-submit');
      submitButton?.addEventListener('click', async () => {
        const nameEl = document.getElementById('nuclen-optin-name') as HTMLInputElement | null;
        const emailEl = document.getElementById('nuclen-optin-email') as HTMLInputElement | null;
        const nameValue = nameEl?.value.trim() ?? '';
        const emailValue = emailEl?.value.trim() ?? '';

        if (!emailValue) {
          alert('Please enter a valid email address.');
          return;
        }

        // Fire GA event
        if (typeof gtag === 'function') {
          gtag('event', 'nuclen_quiz_optin');
        }

        // POST to your webhook
        try {
          const response = await fetch(window.NuclenOptinWebhook, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: nameValue, email: emailValue })
          });
          if (!response.ok) {
            console.error('Webhook error:', response.status, response.statusText);
            alert('Oops! Could not submit your info.');
            return;
          }
          alert(window.NuclenOptinSuccessMessage);
          if (nameEl) nameEl.value = '';
          if (emailEl) emailEl.value = '';
        } catch (err) {
          console.error('Fetch error:', err);
          alert('Unable to connect. Please try again later.');
        }
      });
    }
  }

  /**
   * Show question details in the final results screen
   */
  window.nuclearEngagementShowQuizQuestionDetails = function (index: number) {
    const q = truncatedPostQuizData[index];
    const userAnswerOriginalIndex = userAnswers[index];

    const resultDetailsContainer = document.getElementById('nuclen-quiz-result-details-container');
    if (!resultDetailsContainer) return;

    // The correct answer is answers[0]. If userAnswerOriginalIndex===0 => correct
    let html = `
      <p class="nuclen-quiz-detail-question">${q.question}</p>
      <p class="nuclen-quiz-detail-correct"><strong>Correct answer:</strong> ${q.answers[0]}</p>
    `;
    if (userAnswerOriginalIndex === 0) {
      html += `
        <p class="nuclen-quiz-detail-chosen">
          <strong>Your answer:</strong> ${q.answers[0]}
          <span class="nuclen-quiz-checkmark">✔️</span>
        </p>`;
    } else {
      const chosenAnswer = q.answers[userAnswerOriginalIndex] ?? '[No data]';
      html += `
        <p class="nuclen-quiz-detail-chosen">
          <strong>Your answer:</strong> ${chosenAnswer}
        </p>`;
    }
    html += `<p class="nuclen-quiz-detail-explanation">${q.explanation}</p>`;
    resultDetailsContainer.innerHTML = html;

    // Highlight active tab
    const tabs = document.getElementsByClassName('nuclen-quiz-result-tab');
    for (let i = 0; i < tabs.length; i++) {
      tabs[i].classList.remove('nuclen-quiz-result-active-tab');
    }
    if (tabs[index]) {
      tabs[index].classList.add('nuclen-quiz-result-active-tab');
    }
  };

  /**
   * Retake quiz from question 1
   */
  window.nuclearEngagementRetakeQuiz = function () {
    currentQuestionIndex = 0;
    quizScore = 0;
    userAnswers.length = 0;
    if (finalResultContainer) {
      finalResultContainer.innerHTML = '';
    }
    if (progressBar) {
      progressBar.style.width = `${(1 / truncatedPostQuizData.length) * 100}%`;
    }
    NuclenLoadQuizQuestion();
  };

  /**
   * Check correctness and highlight answers
   */
  function NuclenCheckQuizAnswer(originalIndexChosen: number, shuffledIndexChosen: number, correctIndexInShuffled: number) {
    const qData = truncatedPostQuizData[currentQuestionIndex];

    if (originalIndexChosen === 0) {
      quizScore++;
    }
    userAnswers.push(originalIndexChosen);

    if (!answersContainer) return;
    const buttons = answersContainer.getElementsByTagName('button');
    for (let i = 0; i < buttons.length; i++) {
      buttons[i].classList.remove('nuclen-quiz-possible-answer');
      if (i === correctIndexInShuffled) {
        buttons[i].classList.add('nuclen-quiz-answer-correct', 'nuclen-quiz-pulse');
      } else if (i === shuffledIndexChosen) {
        buttons[i].classList.add('nuclen-quiz-answer-wrong');
      } else {
        buttons[i].classList.add('nuclen-quiz-answer-not-selected');
      }
      buttons[i].disabled = true;
    }

    if (explanationContainer) {
      explanationContainer.classList.remove('nuclen-quiz-hidden');
      explanationContainer.innerHTML = `<p>${qData.explanation}</p>`;
    }
    nextButton?.classList.remove('nuclen-quiz-hidden');
    quizContainer?.scrollIntoView();

    // GA
    if (typeof gtag === 'function') {
      if (currentQuestionIndex === 0) {
        gtag('event', 'nuclen_quiz_start');
        const startMessageEl = document.getElementById('nuclen-quiz-start-message');
        if (startMessageEl) {
          startMessageEl.style.display = 'none';
        }
      }
      gtag('event', 'nuclen_quiz_answer');
    }
  }

  /**
   * Load the current question and shuffle answers
   */
  function NuclenLoadQuizQuestion() {
    const qData = truncatedPostQuizData[currentQuestionIndex];
    if (!qData) return;

    if (questionContainer) {
      questionContainer.innerHTML = `
        <div id="nuclen-quiz-question-number">${currentQuestionIndex + 1}/${truncatedPostQuizData.length}</div>
        <div class="nuclen-quiz-title">${qData.question}</div>
      `;
    }

    // Build array for randomizing
    const answersWithIndices = qData.answers.map((answer, idx) => ({
      answer,
      originalIndex: idx
    }));
    answersWithIndices.sort(() => Math.random() - 0.5);

    if (answersContainer) {
      answersContainer.innerHTML = answersWithIndices
        .map(({ answer, originalIndex }) => `
          <button
            class="nuclen-quiz-answer-button nuclen-quiz-possible-answer"
            data-original-index="${originalIndex}"
          >
            ${answer}
          </button>
        `)
        .join('');
    }

    const correctIndexInShuffled = answersWithIndices.findIndex((item) => item.originalIndex === 0);

    nextButton?.classList.add('nuclen-quiz-hidden');
    if (explanationContainer) {
      explanationContainer.innerHTML = '';
    }
    NuclenUpdateQuizProgressBar();

    // Single-use event listener
    answersContainer?.addEventListener('click', function onClick(e) {
      const target = e.target as HTMLElement;
      if (target.matches('button.nuclen-quiz-answer-button')) {
        const chosenOriginalIndex = parseInt(target.getAttribute('data-original-index') || '0', 10);
        const chosenShuffledIndex = answersWithIndices.findIndex((a) => a.originalIndex === chosenOriginalIndex);
        setTimeout(() => {
          NuclenCheckQuizAnswer(chosenOriginalIndex, chosenShuffledIndex, correctIndexInShuffled);
        }, 100);
        answersContainer.removeEventListener('click', onClick);
      }
    });
  }

  // Start with question #1
  NuclenLoadQuizQuestion();
}

// Attach to window so lazy-loading can call it
window.nuclearEngagementInitQuiz = nuclearEngagementInitQuiz;
