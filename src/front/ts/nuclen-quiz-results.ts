// ─────────────────────────────────────────────────────────────
// File: src/front/ts/nuclen-quiz-results.ts
// -----------------------------------------------------------------------------
import type { QuizQuestion, OptinContext } from './nuclen-quiz-types';
// Opt-in functions will be imported dynamically when needed
import { escapeHtml } from './nuclen-quiz-utils';

// Globals injected by wp_localize_script
declare const NuclenStrings: {
	retake_test: string;
	your_score: string;
	perfect: string;
	well_done: string;
	retake_prompt: string;
	correct: string;
	your_answer: string;
};

declare function gtag(...args: unknown[]): void;

declare global {
	interface Window {
	nuclearEngagementShowQuizQuestionDetails?: (idx: number) => void;
	nuclearEngagementRetakeQuiz?: () => void;
	}
}

export interface QuizUIRefs {
	qContainer: HTMLElement;
	aContainer: HTMLElement;
	explContainer: HTMLElement;
	nextBtn: HTMLElement;
	finalContainer: HTMLElement;
	progBar: HTMLElement;
}

export interface QuizState {
        currIdx: number;
        score: number;
        userAnswers: number[];
}

async function buildResultsHtml(
	optin: OptinContext,
	questions: QuizQuestion[],
	state: QuizState,
): Promise<string> {
	let html = '';
	if (optin.enabled && optin.position === 'with_results') {
		const { buildOptinInlineHTML } = await import('./nuclen-quiz-optin');
		html += buildOptinInlineHTML(optin);
	}

	html += `
<div id="nuclen-quiz-results-title" class="nuclen-fg">${NuclenStrings.your_score}</div>
<div id="nuclen-quiz-results-score" class="nuclen-fg" aria-live="polite" aria-atomic="true">
${state.score} / ${questions.length}
</div>`;
	const comment =
		state.score === questions.length
			? NuclenStrings.perfect
			: state.score > questions.length / 2
				? NuclenStrings.well_done
				: NuclenStrings.retake_prompt;
	html += `<div id="nuclen-quiz-score-comment" aria-live="polite">${comment}</div>`;

	html += '<div id="nuclen-quiz-result-tabs-container">';
	questions.forEach((_, i) => {
		html += `
<button class="nuclen-quiz-result-tab" onclick="nuclearEngagementShowQuizQuestionDetails(${i})">${i + 1}</button>`;
	});
	html += '</div><div id="nuclen-quiz-result-details-container" class="nuclen-fg dashboard-box"></div>';

	if (window.NuclenCustomQuizHtmlAfter?.trim()) {
		html += `
<div id="nuclen-quiz-end-message" class="nuclen-fg">
${window.NuclenCustomQuizHtmlAfter}
</div>`;
	}

	html += `
<button id="nuclen-quiz-retake-button" onclick="nuclearEngagementRetakeQuiz()">${NuclenStrings.retake_test}</button>`;

	return html;
}

function registerShowDetailsHandler(
	questions: QuizQuestion[],
	state: QuizState,
): void {
	// Clean up any existing handler before assigning new one
	if (window.nuclearEngagementShowQuizQuestionDetails) {
		delete window.nuclearEngagementShowQuizQuestionDetails;
	}
	
	window.nuclearEngagementShowQuizQuestionDetails = (idx: number): void => {
		const q = questions[idx];
		const ua = state.userAnswers[idx];
		(document.getElementById('nuclen-quiz-result-details-container') as HTMLElement).innerHTML = `
<p class="nuclen-quiz-detail-question">${escapeHtml(q.question)}</p>
<p class="nuclen-quiz-detail-correct"><strong>${NuclenStrings.correct}</strong> ${escapeHtml(q.answers[0])}</p>
${
	ua === 0
		? `<p class="nuclen-quiz-detail-chosen"><strong>${NuclenStrings.your_answer}</strong> ${escapeHtml(q.answers[0])} <span class="nuclen-quiz-checkmark">✔️</span></p>`
		: `<p class="nuclen-quiz-detail-chosen"><strong>${NuclenStrings.your_answer}</strong> ${escapeHtml(q.answers[ua] ?? '[No data]')}</p>`
}
<p class="nuclen-quiz-detail-explanation">${escapeHtml(q.explanation)}</p>`;
		Array.from(document.getElementsByClassName('nuclen-quiz-result-tab')).forEach((el) =>
			el.classList.remove('nuclen-quiz-result-active-tab'),
		);
		document
			.getElementsByClassName('nuclen-quiz-result-tab')[idx]?.classList.add(
				'nuclen-quiz-result-active-tab',
			);
	};
	window.nuclearEngagementShowQuizQuestionDetails(0);
}

function registerRetakeHandler(
	state: QuizState,
	finalContainer: HTMLElement,
	progBar: HTMLElement,
	questionCount: number,
	renderQuestion: () => void,
): void {
	// Clean up any existing handler before assigning new one
	if (window.nuclearEngagementRetakeQuiz) {
		delete window.nuclearEngagementRetakeQuiz;
	}
	
	window.nuclearEngagementRetakeQuiz = (): void => {
		state.currIdx = 0;
		state.score = 0;
		state.userAnswers.length = 0;
		finalContainer.innerHTML = '';
		finalContainer.classList.add('nuclen-quiz-hidden');
		progBar.style.width = `${(1 / questionCount) * 100}%`;
		renderQuestion();
	};
}

export async function renderOptinBeforeResultsFlow(
	ui: QuizUIRefs,
	optin: OptinContext,
	onFinal: () => void,
): Promise<void> {
	const { qContainer, aContainer, explContainer, nextBtn, finalContainer } = ui;
	qContainer.innerHTML = '';
	aContainer.innerHTML = '';
	explContainer.innerHTML = '';
	nextBtn.classList.add('nuclen-quiz-hidden');

	const { mountOptinBeforeResults } = await import('./nuclen-quiz-optin');
	mountOptinBeforeResults(finalContainer, optin, onFinal, onFinal);
}

export async function renderFinal(
	ui: QuizUIRefs,
	optin: OptinContext,
	questions: QuizQuestion[],
	state: QuizState,
	renderQuestion: () => void,
): Promise<void> {
	const { qContainer, aContainer, explContainer, nextBtn, finalContainer, progBar } = ui;
	qContainer.innerHTML = '';
	aContainer.innerHTML = '';
	explContainer.innerHTML = '';
	nextBtn.classList.add('nuclen-quiz-hidden');
	finalContainer.classList.remove('nuclen-quiz-hidden');
	finalContainer.innerHTML = await buildResultsHtml(optin, questions, state);
	finalContainer.setAttribute('aria-live', 'polite');

	if (optin.enabled && optin.position === 'with_results') {
		const { attachInlineOptinHandlers } = await import('./nuclen-quiz-optin');
		attachInlineOptinHandlers(optin);
	}

	registerShowDetailsHandler(questions, state);
	registerRetakeHandler(state, finalContainer, progBar, questions.length, renderQuestion);

	if (typeof gtag === 'function') {
		gtag('event', 'nuclen_quiz_end');
	}
}
